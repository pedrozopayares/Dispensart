# Journal — add-stock-and-kardex (S2)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv
- Fase: /proposal adelantada mientras S0 espera el reinicio de sesión. Apply espera el archivo de S1.
- Tier objetivo según ROADMAP: A.

## 2026-10-07 — spec-engineer: proposal, deltas, tasks borrador

Producido: `proposal.md`; deltas `specs/inventory` y `specs/kardex` (capacidades nuevas, `## Purpose` incluido,
sin MODIFIED sobre S1); `tasks.md` borrador (6 grupos, `[MUT]` declarados M1–M10). Alineado con
`add-catalog-and-identity/design.md`: capacidades `inventory.view`/`inventory.adjust` de D4 sin tocar el mapa,
forma de rechazo D5 ampliada con 409 `insufficient_stock` y 422 `lot_expired`, `BusinessCalendar` y
`Lot::isExpiredOn` de D6, seeders por clave natural de D11, envoltura `{data}`, `SpaClient` para el 419.
`openspec validate add-stock-and-kardex --strict`: válido. Ancla de transporte barrida con `/usr/bin/grep`:
todo hit lleva `[ancla: …]`; control positivo con una línea sin ancla en el scratchpad, detectada. Cobertura
escenario → tarea barrida: todo escenario citado; control positivo con un escenario inventado, detectado.

Supuestos (9, en `proposal.md` § Assumptions): literales de RN-06 como tipos; unidades enteras; `entrada`
solo por siembra, sin alta de lotes; ajuste positivo a lote vencido rechazado y negativo permitido; ajuste
positivo crea la existencia faltante; ajustes sin `Idempotency-Key`; RN-05 no aplica a ajustes; stock sin
paginar y solo cantidades > 0, kardex paginado; movimientos de siembra con usuario nulo.

Preguntas abiertas para el architect:
1. Aislamiento de las pruebas de carrera (3.3): necesitan filas confirmadas en dos conexiones, y el trigger
   rechaza `TRUNCATE`; elegir entre `DatabaseMigrations`/`migrate:fresh` por prueba u otra vía, sin abrir una
   puerta de vaciado en producción.
2. Mecanismo de la coherencia producto-lote (1.1): FK compuesta a `lots(id, product_id)` exige un índice único
   nuevo sobre `lots`, tabla de S1.
3. Si la base debe impedir también un `UPDATE` directo de la cantidad sin movimiento (trigger de restricción
   diferido); hoy la regla "un movimiento por cambio" se defiende en el servicio único y con M3.
4. Barrera y mecanismo de concurrencia de 3.3 (procesos, `pcntl_fork` o pool de `Process`; bloqueo de
   asesoramiento como barrera).

Candidatos de deuda (el Orchestrator asigna id): endpoint de entrada de mercancía con alta de lotes;
`Idempotency-Key` en ajustes reutilizando el mecanismo de S3; decidir si RN-05 alcanza a ajustes de
productos controlados.

## 2026-10-07 — architect: design.md y refinamiento de tasks

Producido: `design.md` (D1–D10, contrato de 3 endpoints, 3 migraciones reversibles, 3 riesgos); `tasks.md`
reordenado (la carrera pasa a 5.4/5.5, después del endpoint, porque recorre el kernel HTTP real). Escenarios y specs
sin cambios. M1–M10 conservan sus ids.

Respuestas a las preguntas abiertas:
1. Aislamiento (D7): el trigger conserva `TRUNCATE`. `RefreshDatabase` no se afecta (`migrate:fresh` hace `DROP`;
   cada prueba termina en `ROLLBACK`). Carrera con `DatabaseMigrations` + filas dedicadas por iteración; el
   `rollback` de salida ejerce todos los `down()`. `DatabaseTruncation` prohibido. Rechazados:
   `session_replication_role` (además anulado por `ENABLE ALWAYS`), `DISABLE TRIGGER` en limpieza, filas sin limpiar.
2. Coherencia producto-lote (D3): S2 agrega `lots_id_product_id_unique` con migración propia y `down()`; no edita S1.
   FK compuesta en `stocks`. El kardex referencia la existencia por FK compuesta (D4).
3. Trigger diferido cantidad = saldo (D6): no. Sin escenario, no dispara bajo `RefreshDatabase`; defensa en
   `StockLedger` + M3/M5. Revisar si aparece otro escritor de `stocks`.
4. Mecanismo (D8): procesos con `Process::start()` + worker por kernel HTTP; barrera `LOCK TABLE kardex_movements
   IN SHARE MODE` liberada al ver N esperas en `pg_stat_activity`. M4 y M10 fallan de forma determinista, no
   probabilística. Rechazados: `pcntl_fork`, `Concurrency::run()`, barrera de bloqueo de asesoramiento.

Costura para S3/S4 (D2): `StockLedger::apply(list<StockChange>)`, clave global de bloqueo
`(lots.expires_on, lots.id, warehouse_id)` vía `Stock::scopeInLockOrder`; S3 bloquea candidatos FEFO con ese scope y
escribe por `apply()` dentro de su transacción. La regla de lote vencido vive en cada llamador, no en el libro.

Candidatos de deuda — decisión:
- Entrada de mercancía con alta de lotes: fuera, sin fila de deuda (ningún criterio calificado la pide).
- `Idempotency-Key` en ajustes: fuera, sin deuda; revisar si el mecanismo de S3 queda genérico.
- RN-05 en ajustes de controlados: fuera; RN-05 rige la dispensación y el ajuste ya exige regente.
- **Nuevo, señalado**: rol de base de la app = superusuario y dueño; puede deshabilitar el trigger por DDL
  (riesgo 3). Candidato de deuda menor para S8: rol de ejecución sin propiedad ni superusuario.

## 2026-10-07 — spec-engineer: hallazgos del spec-validator

- tasks 5.7, 6.2, 6.3 marcadas "Cimiento" con comando de verificación; orden del architect intacto.
- inventory "Existencias semilla": escenario negativo "Existencia previa no sembrada rechazada por la siembra"
  (la siembra no toma una bodega + lote ya existente; coherente con la idempotencia por bodega + lote del
  design), citado en 3.4. `openspec validate --strict`: válido.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-stock-and-kardex` tras correcciones de prosa (citas de tareas, escenario
  negativo de siembra). Ancla de transporte: 36 hits, 36 con ancla.
- Condición 1 (alcance exacto de S2): OK. Existencias bodega+producto+lote, kardex solo inserción,
  restricciones en DB, ajustes; consulta de inventario y kardex solo API (pantallas en S6).
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): A, coincide con ROADMAP.
- Condición 4 (ADR / RN): ninguna decisión congelada ni regla debilitada.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier A
- Riesgo del architect: el usuario de base de la app es superusuario y dueño de las tablas, puede
  deshabilitar el trigger del kardex. Se decide al auditar S2 si se fila como deuda (arreglo natural:
  rol de aplicación sin privilegios en la imagen de base de datos).
