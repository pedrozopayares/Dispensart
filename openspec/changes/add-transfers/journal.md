# Journal — add-transfers (S4)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv
- Fase: /proposal adelantada. Apply espera el archivo de S3.
- Tier objetivo según ROADMAP: A.

## 2026-10-07 — spec-engineer: proposal, delta y tareas borrador

- Producido: `proposal.md`, `specs/transfers/spec.md` (capacidad nueva, 14 requisitos, 83 escenarios),
  `tasks.md` (30 tareas, `[MUT]` M1–M17). `openspec validate add-transfers --strict` → válido.
- Ancla de transporte: barrido `/usr/bin/grep` sobre `specs/` → 71 hits, 0 sin `[ancla: …]`.
- Pines obligatorios presentes: cada clase de transición prohibida (matriz de 28 combinaciones + escenarios
  por clase; M1–M5 una mutación por acción), solicitante ≠ aprobador en API (M6) y en base (M7).
- Decisiones de alcance (Assumptions de la propuesta, 11): lote explícito por línea sin FEFO; crear no
  verifica stock; borrador no editable; despacho con `transfers.create` (sin capacidad nueva, el mapa de S1
  no cambia); solicitante = creador; anula creador o `transfers.approve` en `BORRADOR`/`SOLICITADO`/`APROBADO`;
  sin `Idempotency-Key` (la máquina de estados devuelve 409 al reintento); recepción admite lote vencido en
  tránsito; `RECIBIDO_PARCIAL` terminal; resolución mínima `returned_to_origin` (ajuste en origen; lote
  vencido → 422) o `written_off` (sin movimiento).
- Sin `MODIFIED`: S1–S3 no son specs vivas aún. La extensión de la bitácora de S3 (3 acciones nuevas) y la
  referencia del traslado en el kardex (vía `reason`) se escriben como requisitos `ADDED` de `transfers`.
- Preguntas abiertas para el architect: forma de la referencia kardex → traslado (`reason` basta para la spec;
  una FK es decisión de diseño); generalización del worker de carrera de S2 a rutas arbitrarias (si S3 no lo
  hizo); orden de bloqueo traslado → discrepancia → existencias frente a la dispensación.
- Bloqueos: ninguno.

## 2026-10-07 — architect: design.md y refinamiento de tasks

Producido: `design.md` (D1–D15, contrato de 9 endpoints, 4 migraciones reversibles, 3 riesgos). `tasks.md`: tarea 0.1
de preflight nueva, 1.5 nueva (extensión de `audit_events`), tareas citan D<n>, orden 0 → (1 ∥ 2) → (3 ∥ 4) → 5 → 6,
sin bloque `web`. Specs y escenarios sin cambios. `openspec validate add-transfers --strict`: válido.

Respuestas a las preguntas abiertas:
1. Referencia kardex → traslado (D11): `reason` `Traslado #<id>` y enlace del lado del traslado, como S3 D2:
   `dispatch_movement_id`, `receipt_movement_id`, `adjustment_movement_id` nulos, únicos, FK al kardex. S2 intacto.
2. Worker de carrera (D13): payload genérico `{user_id, method, uri, headers, body}` (S3 ya lo usa); S4 añade arranque
   escalonado (`launch` + `awaitWaiting(n)`), obligatorio para despacho contra dispensación.
3. Orden de bloqueo (D4): una sola cabecera (traslado; al resolver, solo la discrepancia) → `stocks` por la clave
   global de S2 en una sola llamada a `apply()`. Dispensación: candado → ítems → `stocks`. Sin ciclos.

Otras decisiones: segregación comparando con `created_by` (vale en `BORRADOR`) y lanzada desde la acción, no la
Policy; `transfers_requester_is_creator` en base; sin `CHECK` estado ↔ actor; en tránsito derivado de las líneas, sin
bodega virtual; devolución por `AdjustStock` de S2 (regla de vencido en un solo lugar); `down()` de la bitácora con
`NOT VALID` (filas `transfer.*` imborrables romperían el `rollback` de las carreras).
Rechazadas: códigos del skill (`transfer_state_conflict`), bodega virtual de tránsito, columna `in_transit_quantity`,
`UPDATE` condicional optimista, `apply()` por línea, `transfer_id` en `kardex_movements`, tabla `transfer_events`.

Pines `[MUT]` que tal como estaban en el borrador no podían fallar o fallaban a veces (corregidos en diseño y tareas):
- M15: equivalente si la resolución bloquea el traslado (el borrador decía "traslado → discrepancia"). D4: bloquea
  solo la discrepancia.
- M9: intermitente con arranque simultáneo (si el despacho llega primero, el mutante pasa) y equivalente si solo
  añade una lectura sin bloqueo antes de `apply()`. D13/D14: arranque escalonado alternado; mutante = escritura sin
  la costura.
- M12: equivalente si queda la transacción externa. D14 lo define.
- M7: enmascarable por cualquier otra restricción que rechace la misma sentencia. D3: la prueba afirma el nombre.

Bloqueante de redacción para el spec-engineer (riesgo 3; cambia el resultado de una prueba, no es solo redacción):
«Matriz de transiciones prohibidas» exige un actor "distinto del solicitante", imposible para `solicitar` en los
estados con solicitante (solicitante = creador y solo el creador pasa la Policy): leído al pie de la letra da 403,
no 409. Corrección propuesta de una línea: "…distinto del solicitante, salvo para solicitar, que la envía el
creador". «Estados terminales» con "un `regente_farmacia`" se cumple solo con dos regentes (R1 creador solicita, R2
aprueba): admitido por D15 sin cambio, o explicitarlo en la misma corrección.

Riesgos señalados: los 3 de `design.md`; menores: motivo recortado en el kardex de la devolución, 404 antes que 403
en traslados, sin consulta de en tránsito hasta S5/S7.

## 2026-10-07 — spec-engineer: corrección por hallazgo del architect (D15, riesgo 3)

- «Matriz de transiciones prohibidas»: actor «distinto del solicitante, salvo para solicitar, que la envía el
  creador». «Estados terminales»: R1 crea, solicita y envía solicitar/despachar/recibir/anular; R2 envía aprobar.
- Sin cambio de comportamiento ni de `tasks.md`. Validación estricta válida; ancla: 71 hits, 0 sin anclar.

## 2026-10-07 — spec-engineer: corrección por hallazgos del spec-validator

- `transfers` «Movimientos de traslado en el kardex»: escenario negativo «Despacho rechazado sin movimiento»
  (409 `insufficient_stock` y 422 `lot_expired`, sin `salida_traslado`), citado en tarea 5.4.
- `tasks.md`: 0.1, 4.2, 5.13, 6.2, 6.3 con comando ejecutable; 5.13, 6.2, 6.3 marcadas Cimiento; 4.2 nombra
  sus 11 escenarios 403. Orden y pines del architect intactos. Validación estricta válida; ancla: 72 hits, 0 sin anclar.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-transfers` tras correcciones de prosa (redacción de dos escenarios según D15,
  escenario negativo de kardex, comandos de verificación). Ancla de transporte: 72 hits, 72 con ancla.
- Pines M7, M9, M12 y M15 redefinidos por el architect para que puedan fallar.
- Condición 1 (alcance exacto de S4): OK. Máquina de estados, despacho/recepción, discrepancias con
  resolución mínima, segregación de funciones. Sin pantallas.
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): A, coincide con ROADMAP.
- Condición 4 (ADR / RN): ninguna decisión congelada ni regla debilitada.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier A

## 2026-10-08 — Orchestrator: /apply adelantado

- `openspec validate add-transfers --strict`: válido. GATE 1 registrado.
- S3 tiene backend cerrado (Pest 488, M1–M14) y espera humo + auditoría. S4 usa `StockLedger` (S2,
  archivado) y `audit_events` de S3 ya construido. Se adelanta el backend de S4 (paralelismo autorizado).

## 2026-10-08 — backend-implementer: grupos 0–5, 6.2, 6.3 y deuda D-auv-3 (`software/api`)

Tareas `[x]`: 0.1, 1.1–1.5, 2.1–2.2, 3.1–3.6, 4.1–4.2, 5.1–5.13, 6.2, 6.3. Pendiente: **6.1** (humo sobre el stack:
script bajo `software/docker`, devops-implementer). Commits en `dev`: `d89d2dd`, `4ea9b37`, `13a4802`, `2921d6a`,
`102c1a4` (D-auv-3), `5ddb0ae`, `bc90997`. Sin dependencias nuevas. Trazabilidad (84/84), `[MUT]` M1–M17 + CAP +
D-auv-3, anclas, barridos, reparto de líneas y corridas: `verification.md` §§ 0–5.

### 0.1 — costuras confirmadas

| Pregunta | Hallazgo | Archivo:línea |
|---|---|---|
| `StockLedger::apply(list<StockChange>): list<KardexMovement>` | movimientos en el orden de los cambios; transacción propia o punto de guardado | app/Services/Inventory/StockLedger.php:30 |
| Firma de `AdjustStock` | `handle(User, array{warehouse_id, lot_id, quantity, reason}): KardexMovement`; ingreso a lote vencido → `LotExpired` | app/Actions/Inventory/AdjustStock.php:27 |
| `CHECK` de `audit_events` | `audit_events_action_check`, `audit_events_subject_type_check`, `audit_events_action_subject_check`, `audit_events_details_ids_only` | database/migrations/2026_10_09_000007_create_audit_tables.php |
| Casos de `AuditAction` (antes) | 4: prescription.created, dispensation.created, controlled_drug.authorized, controlled_drug.authorization_failed | app/Enums/AuditAction.php |
| Payload de `RaceRunner` | `{uri, user_id, body, headers?}` ya genérico (S3); sin arranque escalonado → añadido `staggered()` con `launch` + `awaitWaiting(n)`; `post()` y `postAdjustments()` intactos; `--filter=Race` 12 verdes | tests/Support/RaceRunner.php |

### Decisiones de implementación (sin cambio de spec)

- Bloqueo del traslado en un solo sitio (`Services\Transfers\TransferLocker`): M8 es una mutación de una línea.
- Segregación compara `created_by` con el actor dentro de la transacción, antes de `target()` (D8).
- `ReceiveTransfer` llama `assertAllowed` antes del cálculo: un traslado no `EN_TRANSITO` da 409 aunque el cuerpo
  sea otro; el calculador solo ve recepciones válidas.
- Las líneas despachadas se enlazan por índice de `apply()` y se confirma el lote (`LogicException` si no coincide).
- Rutas de traslado con ids de 1 a 18 dígitos (`[0-9]{1,18}`), como `{patient}`: id desbordado → 404, nunca 500.
- OpenAPI: 403 `segregation_of_duties` solo en aprobar (override por operación); 409 genérico ampliado a
  `invalid_transfer_transition` y `discrepancy_already_resolved` (cambia la descripción del 409 de ajustes y
  dispensación). La regla `in` de `line_id` solo existe con traslado enlazado: Scramble publicaba `enum: [""]` y
  Redocly fallaba (`no-enum-type-mismatch`), corregido en `bc90997`.
- `lang/es/validation.php` gana `different`, `size.array`, `max.numeric` y atributos de traslados.

### D-auv-3 (deuda del coordinador)

| Ítem | Evidencia |
|---|---|
| `PATCH /api/products/{product}` y `PATCH /api/warehouses/{warehouse}` con `[0-9]{1,18}` | routes/api.php:44, :48 (commit `102c1a4`) |
| Pruebas: id de 19 dígitos → 404 `not_found` | Catalog/ProductEndpointTest.php:158; Catalog/WarehouseEndpointTest.php:156 |
| Control positivo: antes del arreglo → 500 en ambas (2/2 FALLA); después 49/49 del catálogo | `verification.md` § 3 fila D-auv-3 |
| Mismo tope en `{transfer}` y `{discrepancy}`; con `whereNumber` 3/4 filas FALLAN (500) | `verification.md` § 3 fila CAP |

### Corridas

| # | Resultado |
|---|---|
| cierre (`5ddb0ae`): `pint --test && phpstan analyse --memory-limit=1G && php artisan test` | Pint pasa; Larastan 0 errores; Pest 737 pasan / 2907 aserciones |
| delta posterior (`bc90997`): recepción + Pint + Larastan; OpenAPI + Redocly | 18 pasan; Redocly válido |

### Deuda y pendientes (en prosa; el Orchestrator asigna ids)

- `openapi.json` cambió (9 rutas de traslados, códigos nuevos): `software/web/src/lib/api-schema.ts` debe
  regenerarse (`npm run api:types`) o el control de deriva de CI fallará. Tarea del frontend-implementer.
- 6.1 (humo de traslados sobre el stack) queda para devops-implementer.
- El mensaje de `lot_expired` («no admite ingreso de unidades», de S2) se lee raro al despachar o crear un traslado
  con un lote vencido; el código es correcto. Redacción en `lang/es/errors.php`, no cambia comportamiento.
- La consulta de cantidades en tránsito (D5) no tiene endpoint; S5/S7 la derivan de `transfer_lines` cuando la usen.

## 2026-10-08 — devops-implementer: 6.1 humo de traslados sobre el stack

- Stack: `docker compose -f software/compose.yaml up -d --build --wait api` (solo `api`, sin `down -v`): api, db, web
  sanos; `/health` 200, `/ready` 200; `api` corre como uid 1000.
- Script nuevo `software/docker/smoke/transfer-smoke.sh` (bash + curl + jq, patrón de los humos S2/S3). Respuestas:
  crear 201 `BORRADOR`, solicitar 200, aprobar (regente) 200, autoaprobación del regente 403 `segregation_of_duties`,
  anular 200, despachar 200 (origen −2, `salida_traslado` −2), recibir parcial 200 `RECIBIDO_PARCIAL` (discrepancia
  `pending` de 1, `entrada_traslado` +1), despachar de nuevo 409 `invalid_transfer_transition`, resolver 200 (`ajuste`
  +1 en origen), resolver de nuevo 409 `discrepancy_already_resolved`, detalle 200.
- Corridas 1–3: 28 comprobaciones, 0 fallas cada una. Puede fallar: contraseña errónea (1 falla), recepción completa
  (7 fallas), aprobación por el solicitante (15 fallas); todas salen con código 1. Tablas en `verification.md` § 6.
- Incidente corregido: la primera versión tomaba la existencia más grande y enviaba a cualquier otra bodega; creó la
  fila BH/L-ACE-2403, que pasó a ser la primera de `GET /api/stock` y rompió `stock-smoke.sh` (esa fila no tiene
  `entrada` semilla). Ahora el humo elige un lote vigente ya presente en dos bodegas (no crea filas). Base de
  desarrollo limpiada por la API: ajuste −3 en BH/L-ACE-2403 y anulación de los traslados 9 y 19 que los controles
  dejaron en `SOLICITADO`. Tras eso, `auth`, `stock` y `dispensation` humo: 0 fallas.

Deuda (prosa): `stock-smoke.sh` depende del orden de `GET /api/stock` (ajusta la primera fila y exige que su kardex
tenga una `entrada` semilla); cualquier flujo que cree una fila de existencia nueva que ordene primero lo rompe.
Debería elegir una fila con `entrada` semilla en lugar de la primera.

## 2026-10-08 — devops-implementer: deuda de `stock-smoke.sh` saldada (pedido del Orchestrator)

- `stock-smoke.sh` ya no toma la primera fila: resuelve FC por código en `/api/warehouses` y L-IBU-2402 por
  `lot_code` en `/api/stock?warehouse_id=`, y opera sobre `/api/stock?warehouse_id=&lot_id=`. Requiere `jq`.
- Cuatro humos, una corrida: 0 fallas. Control con lote inexistente: 2 fallas, salida 1. `verification.md` § 6.
  Sin deuda pendiente de 6.1.
