# Journal — add-sensitive-operation-audit (S10)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-09 — spec-engineer: proposal, delta y tareas borrador

- Producido: `proposal.md`, `specs/audit-trail/spec.md` (capacidad viva, 3 requisitos `ADDED`, 19
  escenarios), `tasks.md` (15 tareas, `[MUT]` M1–M7). `openspec validate add-sensitive-operation-audit
  --strict` → válido.
- Ancla de transporte: barrido `/usr/bin/grep` del patrón de `CYCLE-TIERS.md` sobre `specs/` → 3 hits, 0 sin
  `[ancla: …]`. Además, todo escenario con estado HTTP en su `WHEN` lleva ancla a la spec viva que lo afirma.
- Alcance = fila S10: alta de usuario y ajuste de stock escriben `audit_events` en la transacción de la
  operación; migración que reemplaza por nombre los `CHECK` con `down()` reversible. Sin endpoint, sin `web`.
- Hallazgos del código que fijan el alcance: no existe ruta de lectura de bitácoras (README § fuera de
  alcance la lista); no existen acciones de cambio de rol ni desactivación (solo `GET`/`POST /api/users`);
  el disparador `audit_events_append_only` (sentencia, `ENABLE ALWAYS`) cubre las filas nuevas sin cambio;
  el `CHECK` de detalle solo numérico ya impide correo, nombre, contraseña o `reason` en el detalle.
- Decisión de comportamiento: solo el ajuste por `POST /api/stock-adjustments` escribe `stock.adjusted`; el
  `ajuste` de la resolución `returned_to_origin` sigue cubierto por `transfer.discrepancy_resolved` (la
  resolución llama al mismo servicio de ajuste, así que la ubicación de la escritura importa; M7 la fija).
- RN: RN-09 no se debilita (dispensación intacta; escenario de frontera lo comprueba). RN-03/RN-06 intactos:
  la costura única de stock no cambia. RN-10 reforzado.
- Supuestos: 5 (en `proposal.md`).
- Preguntas abiertas para el architect: dónde abrir la transacción del alta (hoy no hay) y del ajuste (envolver
  la costura, que pasa a punto de guardado) sin que la resolución herede la fila; literales de `subject_type`
  (`user`, `kardex_movement` u otros); contenido del detalle (`warehouse_id`, `lot_id` u objeto solo); forma
  del fallo forzado (disparador de prueba, patrón de S3).
- Bloqueos: ninguno.

## 2026-10-09 — architect: design.md y tareas refinadas

- Producido: `design.md` (D1–D8, contrato, impacto en datos, 3 riesgos); `tasks.md` refinado (16 tareas,
  `[MUT]` M1–M8 con arnés `mutants/M<n>.patch`, controles positivos M6 y M7, todos los comandos `api-tools` con
  `-e AI_PROVIDER=mock`). `openspec validate add-sensitive-operation-audit --strict` → válido.
- Grafo verificado: `AdjustStock::handle()` lo llaman el controlador del ajuste, `ResolveDiscrepancy` y cinco
  ayudas de prueba; `CreateUser::handle()` solo `UserController::store`.
- Decisiones: D1 fila `user.created` en `CreateUser` con transacción propia (actor por parámetro). D2 acción nueva
  `AdjustStockManually` envuelve `AdjustStock` + `AuditTrail` en una transacción; `AdjustStock`, `ResolveDiscrepancy`
  y `StockLedger` sin cambio (sin fila duplicada en la resolución; M7 lo fija). D3 reutiliza `AuditTrail`, sin
  dependencia nueva. D4 `user`/`kardex_movement`; detalle `{}` y `{warehouse_id, lot_id}`; sin `reason`, cantidad,
  rol ni secretos. D5 migración `2026_10_12_000001`; `down()` con `NOT VALID`, no falla con filas nuevas
  (obligado por el `migrate:rollback` de `DatabaseMigrations` en las carreras). D6 sin bloqueos nuevos. D7 fallo
  forzado con disparador de prueba `WHEN (NEW.action = …)` dentro de `RefreshDatabase`. D8 carreras ampliadas, no
  nuevas.
- Rechazadas: escribir en `AdjustStock` o en `StockLedger`, argumento bandera, eventos de modelo, `afterCommit`,
  paquete de actividad, `down()` que falla o borra, tipo `ENUM`/catálogo con FK, doble de `AuditTrail`.
- Riesgos señalados: llamador futuro de `AdjustStock` sin auditoría; restricción `NOT VALID` tras retroceso;
  disparador de prueba filtrado a otras pruebas. Mitigación en `design.md` § Risks.
- Fuera de alcance anotado (no deuda nueva): carrera por correo duplicado en el alta responde 500 (previo).

## 2026-10-09 — architect: corrección tras spec-validator NOT VALID

- Corrección de conteo: la sección del spec-engineer dice 15 tareas y M1–M7; vigentes tras el refinamiento: 19 tareas,
  `[MUT]` M1–M11 más controles M2, M6, M7, M11.
- `tasks.md`: comandos `[MUT]` autocontenidos (`git apply` → `php -l` + Pest filtrado al título exacto del escenario
  en `api-tools` → `git apply -R` → Pest de nuevo → `git status --porcelain` vacío); tareas de confirmación en `dev` y
  escritura de parches antes de cada bloque `[MUT]` (1.4, 2.3, 3.5); `--profile tools` en todo comando; escenarios
  nombrados en 2.2, 3.4, 4.2; D4 citado en 1.3, 2.1, 2.2, 3.1, 3.4, 4.3.
- Pines nuevos: M9 (acción sin `user.created`), M10 (tipo sin `kardex_movement`), M11 (`down()` sin `NOT VALID`) con
  prueba `SensitiveOperationMigrationTest` (`DatabaseMigrations`, retroceso y reaplicación con filas nuevas). La tarea
  de retroceso por `tinker` queda reemplazada por esa prueba.
- Carreras: los dos casos de `StockAdjustmentRaceTest` llevan el título del escenario como prefijo, para filtrar M6 por
  título exacto.
- Spec delta: «Filas nuevas inmutables» incluye `TRUNCATE`, como la spec viva.

## 2026-10-09 — Orchestrator: GATE 1

- shard = auv
- GATE 1: preaprobado (ROADMAP 2026-10-09, «Haz los dos. Autopiloto»), condiciones 1-4 OK, tier A.
  1. Alcance = fila S10: creación de usuarios y ajuste manual de stock con fila en `audit_events`, migración de los CHECK.
     Sin endpoint de lectura nuevo; cambio de rol y desactivación fuera (no existen como acciones).
  2. spec-validator VALID en la segunda pasada; ancla de transporte con 3 hits, todos anclados.
  3. Tier A = columna Tier.
  4. RN-09 intacta; el trigger de solo inserción no se toca; ningún ADR se debilita.
- Apply espera a que S9 cierre: comparten la base de pruebas `dispensart_test`.
