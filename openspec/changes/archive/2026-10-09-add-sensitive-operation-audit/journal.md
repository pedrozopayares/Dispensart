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

## 2026-10-09 — backend-implementer: apply

- Costuras confirmadas (0.1): `AuditTrail::record(int $actorId, AuditAction, int $subjectId, array $details = [])`
  escribe en la transacción en curso; `AuditAction` con 7 casos; `CHECK` `audit_events_action_check`,
  `audit_events_subject_type_check`, `audit_events_action_subject_check` (reemplazados por nombre en
  `2026_10_10_000004`); disparador `audit_events_append_only` (sentencia, `ENABLE ALWAYS`); `AdjustStock::handle()`
  lo llaman el controlador del ajuste, `ResolveDiscrepancy` y cinco ayudas de prueba; `CreateUser::handle()` solo
  `UserController::store`.
- Commits: `4a433a7` (migración + enum + integridad), `0ed7517` (alta), `7044892` (ajuste + carreras).
- Corridas: base 1 de 3 y cierre 2 de 3; cifras en `verification.md` § 5. `openapi.json` sin diferencias; sin
  regenerar tipos de la SPA.
- `[MUT]`: M1–M11 y controles M2, M6, M7, M11 con el comando de `tasks.md`; resultados en `verification.md` § 3.
- En vivo (4.1): migración aplicada por el entrypoint de `api`, `/ready` 200, un ajuste y un alta por Nginx dejaron
  una fila cada uno, solo ids (`verification.md` § 6). Datos sintéticos en la base de desarrollo: usuario
  `verificacion.s10@dispensart.test` y un ajuste +1.
- Decisión (desvío de D7): las pruebas de alta y ajuste usan `DatabaseMigrations`. Con `RefreshDatabase`, una fila
  escrita fuera del punto de guardado (M3, M5, M6) aborta la transacción de la prueba y el estado no se puede leer;
  el control M6 sería imposible. El disparador de prueba cae con la tabla; la función se borra en `afterEach`.
- Decisión: precondición de árbol limpio de los `[MUT]` acotada a los archivos de cada parche; S9 tenía archivos
  sin confirmar en `software/api` durante el bloque 1.
- Deuda observada (sin id): el alta con correo duplicado en carrera responde 500 (previo, fuera de alcance en
  design). Ninguna nueva.
- Bloqueos: ninguno. Un 504 transitorio de Docker Hub en el primer `up --build`; reintento correcto.

## 2026-10-09 — GATE 2: APPROVED (final-auditor, completo tier A)

- M1–M11 y los 4 controles reproducidos con la comprobación de árbol limpio sobre todo `software/api`.
- Corrida de confirmación en `dd897e0`: Pint y Larastan limpios, Pest 1039 pasan (corrida 3 de 3).
- Base de desarrollo: fila 86 `stock.adjusted` (movimiento 83) y fila 87 `user.created` (usuario 7), solo ids.
- Deuda: ninguna nueva. Observación previa fuera de alcance: carrera de email duplicado en alta de usuario responde 500.
