# Design — add-transfers (S4, tier A)

## Context

Motivación y alcance en `proposal.md`; requisitos en `specs/transfers`. Costuras heredadas sin redefinir: S1 D2/D4/D5/D6/D9
(precedencia CSRF→sesión→enlace→403→422, mapa de capacidades, forma de rechazo, `BusinessCalendar`, Scramble), S2
D2/D3/D7/D8/D9/D10 (`StockLedger::apply()` única escritura de existencias con clave global
`(lots.expires_on, lots.id, warehouse_id)`, FK `lots(id, product_id)`, `DatabaseMigrations` en carreras, `RaceRunner`,
`AdjustStock`, `InsufficientStock`/`LotExpired`), S3 D2/D10 (enlace línea → movimiento del kardex, `audit_events`
de solo inserción con detalle solo de ids, payload genérico del worker de carrera).

## Goals / Non-Goals

**Goals**
- Una tabla de transiciones como fuente única que sirve al servicio y a la prueba de 35 combinaciones.
- Toda acción de traslado serializada por el bloqueo de su fila de cabecera; existencias solo por `StockLedger`, en
  una sola llamada por acción: atomicidad y orden de bloqueo compartido con la dispensación.
- Integridad en la base: estados, origen ≠ destino, solicitante = creador, aprobador ≠ solicitante, rangos de
  cantidades, una discrepancia por línea.
- Cada `[MUT]` M1–M17 con una mutación definida que hace fallar su escenario siempre (no "a veces").

**Non-Goals**
- Bodega virtual de tránsito, reservas al crear, edición de borradores, `Idempotency-Key` (proposal § Assumptions).
- Endpoint de cantidades en tránsito (sin escenario; la consulta derivada queda descrita en D5 para S5/S7).
- Cambios a `kardex_movements`, `StockChange`, `apply()` o al mapa de capacidades de S1.
- Restricciones de coherencia estado ↔ actores en `transfers` (sin escenario y enmascararían M7, ver D3).

## Decisions

### D1. Contexto `Transfers` ↔ capacidad `transfers`; piezas
- Modelos: `Transfer`, `TransferLine`, `TransferDiscrepancy`. Enums: `TransferStatus` (casos en inglés, valores
  literales de RN-07: `Draft = 'BORRADOR'`, `Requested = 'SOLICITADO'`, `Approved = 'APROBADO'`,
  `InTransit = 'EN_TRANSITO'`, `Received = 'RECIBIDO'`, `PartiallyReceived = 'RECIBIDO_PARCIAL'`,
  `Voided = 'ANULADO'`), `TransferAction` (`request`, `approve`, `dispatch`, `receive`, `void`),
  `DiscrepancyStatus` (`pending`, `resolved`), `DiscrepancyResolution` (`returned_to_origin`, `written_off`).
- Dominio puro: `Transfers\TransferTransitions` (D2), `Transfers\ReceiptCalculator` (D6).
- Acciones (una por caso de uso, cada una con su `DB::transaction`): `CreateTransfer`, `RequestTransfer`,
  `ApproveTransfer`, `DispatchTransfer`, `ReceiveTransfer`, `VoidTransfer`, `ResolveDiscrepancy`.
- Excepciones nuevas: `InvalidTransferTransition` (409), `SegregationOfDutiesViolation` (403),
  `DiscrepancyAlreadyResolved` (409). Reutiliza `InsufficientStock`, `LotExpired`.
- Controladores delgados: `TransferController` (index, store, show), `TransferActionController` (request, approve,
  dispatch, receive, void), `TransferDiscrepancyController` (resolve). Sin dependencias nuevas.
- Rechazada: los códigos del skill `laravel-backend` (`transfer_state_conflict`, `RequesterCannotApprove`). La spec
  fija `invalid_transfer_transition` y `segregation_of_duties`; el repo gana.

### D2. Tabla de transiciones como fuente única
`TransferTransitions::ALLOWED` = `acción → [estado origen → estados destino]`:

| Acción | Desde | Hacia |
|---|---|---|
| `request` | `BORRADOR` | `SOLICITADO` |
| `approve` | `SOLICITADO` | `APROBADO` |
| `dispatch` | `APROBADO` | `EN_TRANSITO` |
| `receive` | `EN_TRANSITO` | `RECIBIDO` \| `RECIBIDO_PARCIAL` (decide D6) |
| `void` | `BORRADOR`, `SOLICITADO`, `APROBADO` | `ANULADO` |

`assertAllowed(TransferAction, TransferStatus): void` lanza `InvalidTransferTransition`; `target(action, from,
?ReceiptOutcome)` devuelve el destino. Toda acción llama `assertAllowed` sobre el estado leído **bajo bloqueo**
(D4). La prueba de dominio 2.1 recorre `TransferStatus::cases() × TransferAction::cases()` contra la tabla; M1–M5
mutan la fila de una acción.
- Rechazada: métodos `canX()` por estado en el enum (la regla se dispersa en 5 métodos; la matriz no tendría una
  sola fuente). Rechazada: paquete de máquina de estados (dependencia para 7 filas).
- Revisar si: aparece un estado con más de un destino además de `receive`.

### D3. Integridad en la base y aserción por nombre de restricción
Restricciones con nombre (tabla en Data impact). Las pruebas de sentencia directa afirman SQLSTATE `23514`/`23505`
**y el nombre de la restricción** en el mensaje de `QueryException`: si una restricción futura rechazara la misma
sentencia, la prueba seguiría apuntando a la suya y la mutación por nombre (M7) seguiría fallando.
- Segregación en base: `transfers_requester_is_creator CHECK (requested_by IS NULL OR requested_by = created_by)`
  y `transfers_approver_differs CHECK (approved_by IS NULL OR (approved_by <> created_by AND approved_by IS
  DISTINCT FROM requested_by))`. Con la primera, comparar contra `created_by` equivale a comparar contra el
  solicitante y vale también en `BORRADOR` (solicitante nulo).
- Sin `CHECK` de coherencia estado ↔ actor (p. ej. "aprobador solo si `APROBADO`+"): el escenario "Aprobador igual
  al solicitante en la base" escribe un aprobador en un `SOLICITADO`; esa restricción rechazaría la misma sentencia y
  M7 dejaría de fallar si la prueba no afirmara el nombre. Sin escenario propio: YAGNI.
- Rechazada: `ENUM` de PostgreSQL para el estado (mismo motivo que S1/S2: `varchar` + `CHECK` con nombre).
- Rechazada: trigger que valide transiciones en la base. Duplica D2 en PL/pgSQL; la spec no lo pide.

### D4. Bloqueo y orden global
- Cada acción sobre un traslado (`request`, `approve`, `dispatch`, `receive`, `void`) abre la transacción y relee
  `Transfer::whereKey($id)->lockForUpdate()->firstOrFail()`; la instancia del enlace de ruta solo sirve a la Policy
  (`created_by` es inmutable). Dos acciones simultáneas sobre el mismo traslado se serializan; la segunda lee el
  estado nuevo y recibe 409.
- `ResolveDiscrepancy` bloquea **solo la discrepancia** (`whereKey($d)->where('transfer_id', $t)->lockForUpdate()`),
  no el traslado: la resolución no cambia el estado (`RECIBIDO_PARCIAL` es terminal) y las discrepancias solo nacen
  en la transacción que fija ese estado. Si además bloqueara el traslado, M15 sería mutante equivalente (el bloqueo
  del traslado ya serializaría y la segunda leería `resolved`).
- Existencias: una sola llamada a `StockLedger::apply($changes)` por acción con todos los cambios; el libro ordena por
  la clave global. Ningún `FOR UPDATE` propio sobre `stocks` en el contexto `Transfers` (barrido 6.3).
- Orden global resultante: cabecera (`transfers` o `transfer_discrepancies`, una sola fila) → `stocks` por clave
  global. Dispensación: candado de asesoramiento → `prescription_items` → `stocks`. Ajuste: `stocks`. Ninguna ruta
  toma una cabecera después de `stocks` y ninguna toma dos cabeceras: sin ciclos.
- Rechazada: `UPDATE transfers SET status = :to WHERE id = ? AND status = :from` (optimista). Correcto, pero mezcla
  dos estilos con S2/S3 y el 409 dependería del conteo de filas tras escribir existencias.
- Rechazada: `SERIALIZABLE` + reintentos (S2 D2: 500 al agotar reintentos).
- Revisar si: una acción necesitara bloquear dos traslados (p. ej. traslado de retorno).

### D5. En tránsito: sin fila de existencia, derivado de las líneas
Al despachar, el stock sale de origen (`salida_traslado`); hasta la recepción vive solo como
`transfer_lines.quantity` de traslados `EN_TRANSITO`. Consulta derivada para S5/S7:
`SUM(tl.quantity) FROM transfer_lines tl JOIN transfers t … WHERE t.status = 'EN_TRANSITO' GROUP BY lot_id`.
- Rechazada: bodega virtual "En tránsito" con existencias. Duplica movimientos (salida + entrada al tránsito), aparece
  en listados de stock y alertas de S5, y el kardex no tiene tipo para ella.
- Rechazada: columna `in_transit_quantity` en `stocks`. Segunda escritura de existencias fuera de la costura de S2.
- Revisar si: el negocio pide ver lo en tránsito por bodega en la pantalla de stock.

### D6. Recepción: contrato en el FormRequest, cálculo puro
`ReceiveTransferRequest` carga las líneas del traslado (inmutables tras crear) y exige: `lines` arreglo con
exactamente las líneas del traslado (`count` igual, `lines.*.line_id` `distinct` e `in:` ids del traslado),
`lines.*.received_quantity` `required|integer|min:0` y `≤ quantity` de su línea (hook `after()`; error en
`lines.N.received_quantity`). `ReceiptCalculator::compute(lines, received) → ReceiptOutcome{status, shortages}`:
todo igual → `RECIBIDO`; si no `RECIBIDO_PARCIAL` con faltante `quantity − received` por línea con faltante > 0.
- La sobre-recepción solo se rechaza en el FormRequest. El calculador la trata como defecto (`LogicException` → 500)
  y la base la respalda (`transfer_lines_received_range`). Así M13 falla (500 ≠ 422) y no queda enmascarado.
- Orden en la transacción: bloqueo → `assertAllowed` → `apply()` con un `entrada_traslado` por línea con recibido > 0
  (el libro crea la existencia destino con `insertOrIgnore`) → `received_quantity` y `receipt_movement_id` por línea
  → discrepancias `pending` → estado, `received_by`, `received_at`. Lote vencido en tránsito: sin regla (Assumption 7).

### D7. Despacho
Bloqueo → `assertAllowed` → vencimiento de **todas** las líneas con `Lot::isExpiredOn(BusinessCalendar::today())`
(cualquiera → `LotExpired`, antes de tocar existencias) → un `apply()` con un `salida_traslado` de `-quantity` por
línea, usuario actual, `reason` `Traslado #<id>` → `dispatch_movement_id` por línea (orden de retorno de `apply()`,
respaldo por `lot_id`, único por traslado) → estado, `dispatched_by`, `dispatched_at`. `InsufficientStock` del libro
sale como 409 sin efecto (el libro valida todos los saldos antes de escribir, S2 D2 paso 3).
- Rechazada: un `apply()` por línea dentro de la transacción. Atómico, pero bloquea en orden de líneas, no en la clave
  global: interbloqueo posible con una dispensación.

### D8. Segregación de funciones (RN-08)
`ApproveTransfer`: Policy `approve` (`transfers.approve`) → transacción → bloqueo → si `actor.id === created_by` →
`SegregationOfDutiesViolation` (403 `segregation_of_duties`) → `assertAllowed` → `approved_by`, `approved_at`, fila
`transfer.approved` → 200. Segregación antes que estado (spec "Segregación antes que estado").
- Rechazada: segregación en la Policy. Una negación de Policy sale como 403 `forbidden`, no `segregation_of_duties`.
- Rechazada: comparar contra `requested_by`. Es nulo en `BORRADOR`; el escenario del regente creador que aprueba su
  borrador daría 409. `transfers_requester_is_creator` hace equivalentes ambas comparaciones donde hay solicitante.

### D9. Precedencia de rechazos

| # | Paso | Rechazo |
|---|---|---|
| 1 | CSRF (origen SPA), sesión | 419 / 401 |
| 2 | enlace de modelo; discrepancia con `scopeBindings()` | 404 `not_found` |
| 3 | Policy | 403 `forbidden` |
| 4 | FormRequest (`store`, `receive`, `void`, `resolve`, `index`) | 422 `validation_failed` |
| 5 | creación: lote vencido en alguna línea | 422 `lot_expired` |
| 6 | transacción: bloqueo de cabecera (D4) | — |
| 7 | aprobación: actor = creador | 403 `segregation_of_duties` |
| 8 | `assertAllowed` / discrepancia ya `resolved` | 409 `invalid_transfer_transition` / `discrepancy_already_resolved` |
| 9 | despacho o `returned_to_origin`: lote vencido | 422 `lot_expired` |
| 10 | `apply()` | 409 `insufficient_stock` |
| 11 | escrituras + fila de bitácora | 200 / 201 |

Policy `TransferPolicy` sobre el mapa de S1 sin cambiarlo: `viewAny`/`view` → `transfers.view`; `create`,
`dispatch` → `transfers.create`; `request` → `transfers.create` y creador; `approve`, `resolveDiscrepancy` →
`transfers.approve`; `receive` → `transfers.receive`; `void` → `transfers.approve`, o `transfers.create` y creador.
404 antes que 403 (S1 D2): los ids de traslado no son sensibles (a diferencia de pacientes en S3 D4).

### D10. Resolución de discrepancias
Bloqueo de la discrepancia (D4) → `pending` o `DiscrepancyAlreadyResolved` → `returned_to_origin`: `AdjustStock` de
S2 con bodega origen, lote de la línea, `+shortage`, usuario y `reason`
`Traslado #<id>, discrepancia #<d>: <motivo>` recortado a 500 con `mb_substr` (regla de lote vencido de S2 en un solo
lugar → 422 `lot_expired`); guarda `adjustment_movement_id` · `written_off`: sin movimiento → `resolved`, resolución,
motivo completo, `resolved_by`, `resolved_at` → fila `transfer.discrepancy_resolved` → 200.
- Rechazada: regla de vencido repetida en `ResolveDiscrepancy` llamando al libro directamente (dos sitios para una
  regla). Si `AdjustStock` de S2 no expone llamada tipada, se usa su firma real (0.1), sin cambiar su comportamiento.
- Trade-off: un motivo cercano a 500 caracteres queda recortado en el kardex; completo en la discrepancia.

### D11. Enlace kardex ↔ traslado (pregunta 1 del spec-engineer)
`reason` identifica el traslado (lo que pide la spec) y, como S3 D2, el enlace estructurado vive del lado del
traslado: `transfer_lines.dispatch_movement_id`, `transfer_lines.receipt_movement_id`,
`transfer_discrepancies.adjustment_movement_id`, nulos, `UNIQUE`, FK → `kardex_movements(id)` `RESTRICT`. S2 intacto.
- Rechazada: columna `transfer_id` en `kardex_movements` (cambia `StockChange`/`apply()`; S3 ya lo rechazó).
- Rechazada: solo `reason` (el balance por traslado no se podría auditar por SQL sin parsear texto).

### D12. Bitácora: extensión de los `CHECK` de S3
Migración que reemplaza, por nombre, los `CHECK` de `audit_events` de acción, `subject_type` y emparejamiento para
admitir `transfer.approved`, `transfer.voided`, `transfer.discrepancy_resolved` con `subject_type = 'transfer'`.
Detalle: `{}` en aprobar/anular, `{"discrepancy_id": n}` al resolver (lo admite `audit_events_details_ids_only`).
`AuditAction` gana los 3 casos; `AuditTrail::record()` se llama dentro de la transacción de la acción.
- `down()` repone los `CHECK` estrechos con `NOT VALID`: `audit_events` es de solo inserción y una prueba de
  carrera de resolución deja filas `transfer.*` que no se pueden borrar; sin `NOT VALID` el `rollback` de D7 de S2
  fallaría en cada corrida. Rechazada: tabla `transfer_events` propia (la spec exige la bitácora de S3).

### D13. Pruebas de carrera (pregunta 2 del spec-engineer)
`RaceRunner` de S2/S3 con payload genérico `{user_id, method, uri, headers, body}` (S3 ya lo usa para
dispensaciones; 0.1 lo confirma o lo generaliza sin cambiar pruebas previas). S4 añade **arranque escalonado**:
`launch(payload)` + `awaitWaiting(n)` sobre `pg_stat_activity` (`application_name = race-worker`,
`wait_event_type = 'Lock'`), además del arranque simultáneo. Barrera de tabla de S2 D8 en los cuatro casos;
`DatabaseMigrations`, filas dedicadas por iteración, N = 10.

| Caso | Arranque | Fixture | Mutante → falla |
|---|---|---|---|
| Despacho vs dispensación | escalonado, primero alterna por iteración (impares dispensación, pares despacho) | L con 1 u.; traslado `APROBADO` de 1 u.; prescripción con pendiente | M9: con la dispensación primero, el despacho sin libro lee 1 sin bloqueo y su `UPDATE` relativo da -1 → `CHECK` → 500 (o, con escritura absoluta, dos salidas). Con arranque simultáneo el despacho podría llegar primero y el mutante pasaría: el escalonado es obligatorio |
| Despachos simultáneos | simultáneo | existencia ≥ 2 × cantidad | M8: ambos leen `APROBADO`; dos `salida_traslado` por línea |
| Recepciones simultáneas | simultáneo | recibido > 0 en alguna línea y faltante en otra | M8: segunda entrada; la segunda discrepancia viola `transfer_discrepancies_line_unique` → 500 |
| Resoluciones simultáneas | simultáneo | lote no vencido; existencia origen presente | M15: ambos leen `pending`; dos `ajuste` |

### D14. Definición exacta de mutaciones con riesgo de equivalencia
- **M9**: `DispatchTransfer` no llama al libro; lee `stocks` sin bloqueo, valida y ejecuta
  `UPDATE stocks SET quantity = quantity - :q` + `INSERT` del movimiento. "Leer antes sin bloqueo y luego llamar
  `apply()`" es equivalente (el libro revalida bajo bloqueo): no cuenta.
- **M12**: quitar la transacción externa de `DispatchTransfer` **y** llamar `apply()` una vez por línea en orden de
  `id`; fixture con la línea suficiente (A) creada primero. Sin quitar la transacción externa es equivalente.
- **M15**: quitar `lockForUpdate()` de la discrepancia; real solo porque D4 no bloquea el traslado al resolver.
- **M7**: quitar `transfers_approver_differs` por nombre; la prueba afirma el nombre (D3).
- **M13**: quitar la regla `≤ quantity` del FormRequest; real porque el calculador no mapea a 422 (D6).
- **M10**: fixture con existencia suficiente del lote vencido (sin ella el mutante daría 409, que igual falla por
  código, pero el fallo debe venir de la regla).
- **M6/M17**: el mutante da 500 por `transfers_approver_differs` / `transfers_requester_is_creator`, no 200; la
  prueba afirma 403 y código: falla igual.

### D15. Actor de la matriz de transiciones (prueba 5.8 y "Estados terminales")
Actor por acción, siempre con el permiso y pasando la Policy, para que el único rechazo posible sea el 409:
`request` → el creador; `approve` → un `regente_farmacia` distinto del creador; `dispatch`, `receive` → un
`auxiliar_farmacia`; `void` → un `regente_farmacia`. En "Estados terminales" el traslado lo crea un regente R1, que
envía `request`; `approve` lo envía R2. Ver Riesgo 3 sobre la redacción de la spec.

## API contract

Rechazos con la forma de S1 D5. CSRF en escrituras desde el origen de la SPA. Fechas de transición ISO 8601 UTC.
`Transfer` detalle = `{id,status,notes,origin_warehouse{id,code,name},destination_warehouse{…},created_by{id,name},
created_at,requested_by|null,requested_at|null,approved_by,approved_at,dispatched_by,dispatched_at,received_by,
received_at,voided_by,voided_at,void_reason,lines:[{id,product{id,code,name},lot{id,lot_code,expires_on,is_expired},
quantity,received_quantity|null}],discrepancies:[{id,line_id,lot_id,shortage,status,resolution|null,
resolution_reason|null,resolved_by|null,resolved_at|null}]}`.

| Método y ruta | Sesión / CSRF | Petición | Respuestas | Prueba HTTP real |
|---|---|---|---|---|
| `GET /api/transfers` | sí / no | `status?` (7 literales), `origin_warehouse_id?`, `destination_warehouse_id?` (entero ≥ 1), `per_page?` 1–100 (50), `page?` | 200 `{data:[{id,status,origin_warehouse,destination_warehouse,created_by,created_at}],links,meta{current_page,per_page,total}}` por `created_at DESC, id DESC`; 401; 403; 422 | 5.2 |
| `GET /api/transfers/{transfer}` | sí / no | — | 200 `{data: Transfer}`; 401; 403; 404 | 5.2 |
| `POST /api/transfers` | sí / sí | `origin_warehouse_id`, `destination_warehouse_id` (≠), `notes?` ≤ 1000, `lines[1..50]{lot_id distinto, quantity 1..1 000 000}` | 201 `{data: Transfer}` `BORRADOR`; 401; 403; 419; 422 `validation_failed` / `lot_expired` | 5.1 |
| `POST /api/transfers/{transfer}/request` | sí / sí | — | 200 `{data: Transfer}`; 401; 403; 404; 409 `invalid_transfer_transition`; 419 | 5.3, 5.8 |
| `POST /api/transfers/{transfer}/approve` | sí / sí | — | 200; 401; 403 `forbidden` / `segregation_of_duties`; 404; 409; 419 | 5.3, 5.8 |
| `POST /api/transfers/{transfer}/dispatch` | sí / sí | — | 200; 401; 403; 404; 409 `invalid_transfer_transition` / `insufficient_stock`; 419; 422 `lot_expired` | 5.4, 5.8, 5.11 |
| `POST /api/transfers/{transfer}/receive` | sí / sí | `lines[]{line_id, received_quantity 0..quantity}`, todas las líneas, sin repetir | 200; 401; 403; 404; 409; 419; 422 | 5.5, 5.8, 5.11 |
| `POST /api/transfers/{transfer}/void` | sí / sí | `reason` ≤ 500 | 200; 401; 403; 404; 409; 419; 422 | 5.6, 5.8 |
| `POST /api/transfers/{transfer}/discrepancies/{discrepancy}/resolve` | sí / sí | `resolution` (`returned_to_origin`\|`written_off`), `reason` ≤ 500 | 200 `{data:{id,line_id,lot_id,shortage,status,resolution,resolution_reason,resolved_by{id,name},resolved_at}}`; 401; 403; 404; 409 `discrepancy_already_resolved`; 419; 422 `validation_failed` / `lot_expired` | 5.7, 5.11 |

## Data impact

Cuatro migraciones posteriores a S3, `down()` en todas, restricciones con nombre. Ids `id()`, fechas `timestampTz`,
cantidades `integer`, FKs `RESTRICT`.

| # | Migración | Contenido | `down()` |
|---|---|---|---|
| 1 | `create_transfers_table` | `origin_warehouse_id`, `destination_warehouse_id` FK `warehouses`; `status varchar(20) NOT NULL` sin default; `notes varchar(1000) NULL`; `created_by` NOT NULL, `requested_by`, `approved_by`, `dispatched_by`, `received_by`, `voided_by` NULL FK `users`; `requested_at` … `voided_at` NULL; `void_reason varchar(500) NULL`; `timestampsTz`. `transfers_status_check` (7 literales); `transfers_distinct_warehouses CHECK (origin_warehouse_id <> destination_warehouse_id)`; `transfers_requester_is_creator`; `transfers_approver_differs` (D3). Índices `(created_at, id)`, `(status)`, `(origin_warehouse_id)`, `(destination_warehouse_id)` | `DROP TABLE` |
| 2 | `create_transfer_lines_table` | `transfer_id` FK; `lot_id`, `product_id` con FK `transfer_lines_lot_product_foreign (lot_id, product_id) → lots(id, product_id)`; `quantity`; `received_quantity NULL`; `dispatch_movement_id`, `receipt_movement_id` NULL FK `kardex_movements`. `transfer_lines_quantity_positive CHECK (quantity > 0)`; `transfer_lines_received_range CHECK (received_quantity IS NULL OR received_quantity BETWEEN 0 AND quantity)`; `transfer_lines_transfer_lot_unique (transfer_id, lot_id)`; `transfer_lines_id_transfer_unique (id, transfer_id)`; únicos en ambos movimientos; índice `(lot_id, product_id)` | `DROP TABLE` |
| 3 | `create_transfer_discrepancies_table` | `transfer_id`, `transfer_line_id` con FK `(transfer_line_id, transfer_id) → transfer_lines(id, transfer_id)`; `shortage`; `status varchar(16)`; `resolution varchar(32) NULL`; `resolution_reason varchar(500) NULL`; `resolved_by` NULL FK `users`; `resolved_at NULL`; `adjustment_movement_id` NULL único FK `kardex_movements`; `timestampsTz`. `transfer_discrepancies_shortage_positive CHECK (shortage > 0)`; `_status_check`; `_resolution_check`; `_resolution_coherent CHECK ((status = 'pending' AND resolution, resolution_reason, resolved_by, resolved_at IS NULL) OR (status = 'resolved' AND todos NOT NULL AND btrim(resolution_reason) <> ''))`; `transfer_discrepancies_line_unique (transfer_line_id)`; índice `(transfer_id, status)` | `DROP TABLE` |
| 4 | `extend_audit_events_for_transfers` | reemplaza por nombre los `CHECK` de acción, `subject_type` y emparejamiento de S3 (D12) | repone los de S3 con `NOT VALID` |

- Sin índices sobre las FKs de actores: `users` no se borra (sin endpoint), tablas de decenas de filas. Revisar si
  aparece baja de usuarios.
- Bloqueo: `SELECT … FROM transfers WHERE id = ? FOR UPDATE` o `SELECT … FROM transfer_discrepancies WHERE id = ? AND
  transfer_id = ? FOR UPDATE`, luego `stocks` solo por `StockLedger` (D4).
- Literales de estado escritos en la migración, no desde `TransferStatus::cases()` (S1/S2).
- Reversión: `migrate:rollback --step=4` deja S3 intacto; las carreras la ejecutan en cada corrida (S2 D7).

## Risks / Trade-offs

1. [Mutantes equivalentes o intermitentes en carreras: M15 si la resolución bloquea el traslado, M9 si el despacho
   llega primero, M12 si queda la transacción externa, M7 enmascarado por otra restricción] → D4 (resolución solo
   bloquea la discrepancia), D13 (arranque escalonado alternado), D14 (mutaciones definidas), D3 (aserción por nombre
   de restricción); mutantes 10/10.
2. [`rollback` roto por filas `transfer.*` imborrables en `audit_events`, o interbloqueo despacho/recepción contra
   dispensación] → `NOT VALID` en `down()` (D12), probado por cada prueba de carrera; una sola llamada a `apply()` por
   acción, una sola cabecera bloqueada antes de `stocks` (D4); un `40P01` sale 500 y se registra.
3. [Redacción de "Matriz de transiciones prohibidas": "un usuario con el permiso de la acción y distinto del
   solicitante" es imposible para `request` en los estados con solicitante (solicitante = creador, y solo el creador
   pasa la Policy): leída al pie de la letra la prueba obtiene 403, no 409. "Estados terminales" exige dos regentes]
   → D15 fija el actor por acción; el spec-engineer corrige la redacción (una línea: "distinto del solicitante salvo
   para solicitar, que envía el creador") antes de GATE 1. Cambia el actor de la prueba, no el comportamiento.

Menores: motivo recortado en el kardex de la devolución (D10); 404 antes que 403 en traslados (D9); en tránsito sin
consulta propia hasta que S5/S7 la necesiten (D5).

## Migration Plan

Requisitos previos: S1–S3 archivados (`lots(id, product_id)`, `kardex_movements`, `StockLedger`, `AdjustStock`,
`audit_events`, `RaceRunner`). Arranque: `migrate --force` → `db:seed --force` (sin siembra de traslados).
Reversión: revertir commits + `migrate:rollback --step=4`.

## Open Questions

- Nombres exactos de los `CHECK` de `audit_events`, firma de `AdjustStock` y payload de `RaceRunner`: se confirman en
  0.1; si difieren, D10/D12/D13 usan la forma real sin cambiar specs.
