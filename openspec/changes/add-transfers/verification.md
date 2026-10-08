# Verification — add-transfers (S4, tier A)

Fuente: sección backend-implementer de `journal.md`. Árbol medido: `dev` en `5ddb0ae`. Prefijo `TRF` (capacidad
`transfers`). Rutas de prueba relativas a `software/api/tests/Feature/Transfers/` salvo indicación; de código, a
`software/api/`.

## 0. Reparto de líneas

Líneas añadidas por S4 + D-auv-3. Comando: `git diff --numstat d89d2dd^ 5ddb0ae -- <alcance> | awk -F'\t' '$1!="-"{a+=$1} END{print a}'`.

| Categoría | Alcance | Líneas |
|---|---|---|
| Producto — API | `software/api/{app,database,routes,bootstrap,lang}` | 2139 |
| Producto — SPA | `software/web` | 0 |
| Producto — infraestructura | `software/compose.yaml`, `software/docker`, `.github` | 0 |
| Humo — stack (6.1, devops) | `software/docker/smoke/transfer-smoke.sh` (`wc -l`) | 212 |
| Prueba — API | `software/api/tests` (13 de ellas en `Catalog`, D-auv-3) | 1993 |
| Generado | `software/api/openapi.json` | 2058 |
| Registro | `openspec/changes/add-transfers/**/*.md` antes de este archivo (`wc -l`) | 932 |

## 1. Matriz escenario → prueba → archivo:línea

| Capacidad | Escenarios en la spec | Escenarios con prueba |
|---|---|---|
| transfers | 84 | 84 |

| Id | Escenario | Prueba | Archivo:línea |
|---|---|---|---|
| TRF-01 | Traslado válido | guarda un traslado BORRADOR entre dos bodegas distintas con una línea de cantidad 3 | TransferIntegrityTest.php:53 |
| TRF-02 | Estado fuera del conjunto | rechaza un estado fuera de los 7 de RN-07 y conserva el anterior | TransferIntegrityTest.php:61 |
| TRF-03 | Origen igual a destino en la base | rechaza origen igual a destino | TransferIntegrityTest.php:71 |
| TRF-04 | Aprobador igual al solicitante en la base | rechaza como aprobador al mismo usuario que solicitó y el aprobador sigue nulo · control: admite como aprobador a otro usuario | TransferIntegrityTest.php:79, :90 |
| TRF-05 | Cantidades fuera de rango en la base | rechaza cantidades fuera de rango en líneas y discrepancias (dataset 4) | TransferIntegrityTest.php:105 |
| TRF-06 | Lote repetido en un traslado | rechaza el mismo lote dos veces en un traslado | TransferIntegrityTest.php:119 |
| TRF-07 | Borrador creado | crea un BORRADOR con el creador y el producto de cada lote, sin tocar existencias ni kardex · (servicio) crea un BORRADOR con el producto del lote | TransferCreateEndpointTest.php:29; TransferActionsTest.php:34 |
| TRF-08 | Campos del servidor ignorados | ignora estado, actores y producto enviados por el cliente | TransferCreateEndpointTest.php:52 |
| TRF-09 | Observaciones con texto arbitrario | guarda observaciones con texto arbitrario como dato, sin otro efecto | TransferCreateEndpointTest.php:67 |
| TRF-10 | Sin observaciones | devuelve notes null sin observaciones | TransferCreateEndpointTest.php:78 |
| TRF-11 | Origen igual a destino | rechaza origen igual a destino en destination_warehouse_id | TransferCreateEndpointTest.php:84 |
| TRF-12 | Datos inválidos o incompletos | rechaza datos inválidos o incompletos por campo sin crear traslado (dataset 12) | TransferCreateEndpointTest.php:92 |
| TRF-13 | Lote vencido rechazado al crear | rechaza un lote que vence hoy en Bogotá con lot_expired · (dominio, reloj fijado) da por vencido el lote que vence hoy · (servicio) rechaza crear con un lote vencido | TransferCreateEndpointTest.php:113; ReceiptCalculatorTest.php:39; TransferActionsTest.php:51 |
| TRF-14 | Lote que vence mañana admitido | admite un lote que vence mañana en Bogotá · (dominio) admite el que vence mañana | TransferCreateEndpointTest.php:123; ReceiptCalculatorTest.php:39 |
| TRF-15 | Crear no verifica existencias | crea aunque la línea pida más de lo que hay en origen y la existencia sigue igual | TransferCreateEndpointTest.php:131 |
| TRF-16 | Reintento de creación | crea dos borradores distintos al repetir el envío, sin cambio de existencias | TransferCreateEndpointTest.php:140 |
| TRF-17 | Roles sin creación de traslados | rechaza con 403 a los roles sin transfers.create (dataset 3) · Policy por rol (dataset 5) | TransferCreateEndpointTest.php:151; TransferPolicyTest.php:33 |
| TRF-18 | Recorrido completo | recorre BORRADOR → … → RECIBIDO con HTTP 200 en cada paso | TransferStateMachineEndpointTest.php:81 |
| TRF-19 | Matriz de transiciones prohibidas | rechaza cada transición prohibida con 409 sin cambiar estado, existencias, kardex ni bitácora (dataset 28) · declara exactamente 28 · (dominio) 35 combinaciones | TransferStateMachineEndpointTest.php:62, :58; TransferTransitionsTest.php:35, :47 |
| TRF-20 | Despachar sin aprobación | fila `dispatch desde SOLICITADO` del dataset 28, con existencia suficiente en origen | TransferStateMachineEndpointTest.php:62 |
| TRF-21 | Aprobar un borrador no solicitado | fila `approve desde BORRADOR` (regente no creador) | TransferStateMachineEndpointTest.php:62 |
| TRF-22 | Recibir sin despacho | fila `receive desde APROBADO` (cuerpo completo) | TransferStateMachineEndpointTest.php:62 |
| TRF-23 | Anular en tránsito | fila `void desde EN_TRANSITO` (existencias iguales) | TransferStateMachineEndpointTest.php:62 |
| TRF-24 | Estados terminales | rechaza con 409 las 15 acciones sobre estados terminales: R1 creador y solicitante, R2 aprueba (dataset 3) | TransferStateMachineEndpointTest.php:101 |
| TRF-25 | Permiso antes que estado | evalúa el permiso antes que el estado: el auditor despacha un RECIBIDO y recibe 403 | TransferStateMachineEndpointTest.php:119 |
| TRF-26 | Creador solicita | pasa el borrador a SOLICITADO con el creador como solicitante y la fecha del servidor | TransferApprovalEndpointTest.php:21 |
| TRF-27 | Solicitud de un borrador ajeno | rechaza con 403 la solicitud de un borrador ajeno (otro auxiliar, regente no creador) | TransferApprovalEndpointTest.php:32 |
| TRF-28 | Rol sin creación solicita | rechaza con 403 la solicitud del auditor | TransferApprovalEndpointTest.php:41 |
| TRF-29 | Regente aprueba la solicitud de un auxiliar | aprueba la solicitud de un auxiliar con el regente como aprobador, sin stock y con una fila de bitácora | TransferApprovalEndpointTest.php:49 |
| TRF-30 | Regente aprueba la solicitud de otro regente | aprueba la solicitud de otro regente | TransferApprovalEndpointTest.php:68 |
| TRF-31 | Solicitante regente intenta aprobar | rechaza con 403 segregation_of_duties al regente que creó y solicitó, sin aprobador ni fila | TransferApprovalEndpointTest.php:74 |
| TRF-32 | Segregación antes que estado | evalúa la segregación antes que el estado: … BORRADOR → 403, no 409 · (servicio) dataset 2 | TransferApprovalEndpointTest.php:87; TransferActionsTest.php:75 |
| TRF-33 | Auxiliar intenta aprobar | rechaza con 403 forbidden al auxiliar que no es el solicitante y sigue SOLICITADO | TransferApprovalEndpointTest.php:93 |
| TRF-34 | Despacho exitoso | despacha: resta en origen con un salida_traslado por línea y pasa a EN_TRANSITO | TransferDispatchEndpointTest.php:24 |
| TRF-35 | Existencia insuficiente en una línea | rechaza con 409 insufficient_stock si una línea no alcanza, sin tocar ninguna existencia | TransferDispatchEndpointTest.php:46 |
| TRF-36 | Lote sin existencia en origen | rechaza con 409 insufficient_stock un lote sin existencia en origen | TransferDispatchEndpointTest.php:60 |
| TRF-37 | Lote vencido al despachar | rechaza con 422 lot_expired un lote vencido antes del despacho, aun con existencia suficiente | TransferDispatchEndpointTest.php:68 |
| TRF-38 | Lote vencido y existencia insuficiente a la vez | evalúa el vencimiento primero … 422 lot_expired | TransferDispatchEndpointTest.php:81 |
| TRF-39 | Reintento de despacho | rechaza un despacho repetido con 409 invalid_transfer_transition sin nuevas salidas | TransferDispatchEndpointTest.php:89 |
| TRF-40 | Roles sin despacho | rechaza con 403 a los roles sin despacho y sigue APROBADO (dataset 3) | TransferDispatchEndpointTest.php:114 |
| TRF-41 | Despacho contra dispensación por la última unidad | serializa despacho contra dispensación …, primero alternado, 10 de 10 | TransferRaceTest.php:63 |
| TRF-42 | Despachos simultáneos del mismo traslado | serializa dos despachos simultáneos del mismo traslado, 10 de 10 | TransferRaceTest.php:102 |
| TRF-43 | Recepción completa | recibe completo: RECIBIDO, sin discrepancias … · (dominio) calcula RECIBIDO | TransferReceiveEndpointTest.php:24; ReceiptCalculatorTest.php:12 |
| TRF-44 | Destino sin existencia previa | crea la existencia en destino cuando no había, con balance_after igual a lo recibido | TransferReceiveEndpointTest.php:43 |
| TRF-45 | Recepción parcial | recibe parcial: RECIBIDO_PARCIAL con una sola discrepancia … · (dominio) · (servicio) | TransferReceiveEndpointTest.php:52; ReceiptCalculatorTest.php:18; TransferActionsTest.php:106 |
| TRF-46 | Nada recibido | no recibe nada: RECIBIDO_PARCIAL sin entradas y con discrepancias de 3 y 2 · (dominio) | TransferReceiveEndpointTest.php:80; ReceiptCalculatorTest.php:24 |
| TRF-47 | Sobre-recepción | rechaza la sobre-recepción con 422 en el received_quantity de la línea · (dominio) defecto, nunca 422 | TransferReceiveEndpointTest.php:89; ReceiptCalculatorTest.php:30 |
| TRF-48 | Recepción incompleta o mal formada | rechaza una recepción incompleta o mal formada (dataset 6) | TransferReceiveEndpointTest.php:100 |
| TRF-49 | Lote vencido en tránsito | recibe completo un lote que venció en tránsito y lo muestra vencido | TransferReceiveEndpointTest.php:121 |
| TRF-50 | Reintento de recepción | rechaza repetir la recepción de un RECIBIDO_PARCIAL con 409 | TransferReceiveEndpointTest.php:132 |
| TRF-51 | Recepciones simultáneas | serializa dos recepciones parciales simultáneas, 10 de 10 | TransferRaceTest.php:128 |
| TRF-52 | Roles sin recepción | rechaza con 403 a los roles sin recepción (dataset 3) | TransferReceiveEndpointTest.php:143 |
| TRF-53 | Devolución al origen | devuelve al origen: discrepancia resuelta, ajuste de +1 … · (servicio) | TransferDiscrepancyEndpointTest.php:49; TransferActionsTest.php:122 |
| TRF-54 | Pérdida declarada | declara pérdida sin mover ninguna existencia ni movimiento | TransferDiscrepancyEndpointTest.php:72 |
| TRF-55 | Devolución sobre lote vencido | rechaza la devolución sobre un lote vencido con 422 lot_expired | TransferDiscrepancyEndpointTest.php:85 |
| TRF-56 | Resolución inválida o incompleta | rechaza una resolución inválida o incompleta (dataset 5) | TransferDiscrepancyEndpointTest.php:99 |
| TRF-57 | Discrepancia ya resuelta | rechaza resolver de nuevo una discrepancia resuelta con 409 | TransferDiscrepancyEndpointTest.php:116 |
| TRF-58 | Discrepancia de otro traslado | responde 404 a una discrepancia de otro traslado sin cambiar ninguna | TransferDiscrepancyEndpointTest.php:128 |
| TRF-59 | Auxiliar intenta resolver | rechaza con 403 al auxiliar y la discrepancia sigue pendiente | TransferDiscrepancyEndpointTest.php:139 |
| TRF-60 | Resoluciones simultáneas | serializa dos resoluciones simultáneas de la misma discrepancia, 10 de 10 | TransferRaceTest.php:157 |
| TRF-61 | Creador anula su borrador | deja al creador anular su BORRADOR con anulador, motivo y fecha, sin movimiento | TransferVoidEndpointTest.php:21 |
| TRF-62 | Regente anula un traslado aprobado ajeno | deja al regente anular un APROBADO ajeno sin tocar existencias ni kardex | TransferVoidEndpointTest.php:39 |
| TRF-63 | Creador anula su traslado solicitado | deja al creador anular su SOLICITADO | TransferVoidEndpointTest.php:50 |
| TRF-64 | Auxiliar anula un traslado ajeno | rechaza con 403 al auxiliar que no es el creador | TransferVoidEndpointTest.php:56 |
| TRF-65 | Anulación sin motivo | rechaza sin motivo o con motivo de solo espacios en errors.reason (dataset 3) | TransferVoidEndpointTest.php:64 |
| TRF-66 | Roles sin anulación | rechaza con 403 a los roles sin anulación (dataset 3) | TransferVoidEndpointTest.php:75 |
| TRF-67 | Roles con lectura de traslados | lista del más reciente al más antiguo con meta para cada rol con lectura (dataset 3) | TransferQueryEndpointTest.php:19 |
| TRF-68 | Filtro por estado | filtra por estado y bodegas combinados con Y | TransferQueryEndpointTest.php:33 |
| TRF-69 | Detalle con discrepancias | devuelve el detalle con líneas, discrepancias y el actor y la fecha de cada transición | TransferQueryEndpointTest.php:46 |
| TRF-70 | Sin resultados | devuelve data vacío sin resultados (dataset 2) | TransferQueryEndpointTest.php:75 |
| TRF-71 | Filtros mal formados | rechaza filtros mal formados en el parámetro afectado (dataset 3) | TransferQueryEndpointTest.php:81 |
| TRF-72 | Traslado inexistente | responde 404 a un traslado inexistente · ids fuera de rango (dataset 4) | TransferQueryEndpointTest.php:92, :96 |
| TRF-73 | Roles sin lectura de traslados | rechaza con 403 la lista y el detalle a medico y admin (dataset 2 × 2 rutas) | TransferQueryEndpointTest.php:109 |
| TRF-74 | Sin sesión | responde 401 sin sesión en las 7 escrituras sin cambiar traslados ni movimientos | TransferStateMachineEndpointTest.php:125 |
| TRF-75 | Sin token CSRF desde la SPA | rechaza con 419 un despacho desde la SPA sin X-XSRF-TOKEN, sin movimiento, y lo acepta con él | TransferDispatchEndpointTest.php:123 |
| TRF-76 | Acción sobre traslado inexistente | responde 404 a una acción sobre un traslado inexistente | TransferDispatchEndpointTest.php:136 |
| TRF-77 | Trazabilidad en el kardex | deja en el kardex del lote un salida_traslado en origen y un entrada_traslado en destino (vía GET /api/kardex) | TransferReceiveEndpointTest.php:151 |
| TRF-78 | Balance de un traslado parcial | cuadra el balance de un parcial: salida -5, entrada 3 y faltante 2 | TransferReceiveEndpointTest.php:66 |
| TRF-79 | Despacho rechazado sin movimiento | deja salidas y existencias iguales tras un 409 insufficient_stock y luego un 422 lot_expired | TransferDispatchEndpointTest.php:99 |
| TRF-80 | Pasos sin movimiento de stock | no mueve stock al crear, solicitar, aprobar, anular ni al resolver con written_off | TransferDiscrepancyEndpointTest.php:149 |
| TRF-81 | Aprobación registrada | aprueba la solicitud de un auxiliar … con una fila de bitácora (actor, id, correlation_id) | TransferApprovalEndpointTest.php:49 |
| TRF-82 | Anulación y resolución registradas | deja al creador anular su BORRADOR … (fila transfer.voided) · devuelve al origen … (fila transfer.discrepancy_resolved con discrepancy_id) | TransferVoidEndpointTest.php:21; TransferDiscrepancyEndpointTest.php:49 |
| TRF-83 | Rechazo sin fila | rechaza con 403 segregation_of_duties … sin fila · no escribe fila de bitácora al rechazar una anulación con 409 | TransferApprovalEndpointTest.php:74; TransferVoidEndpointTest.php:83 |
| TRF-84 | Sin texto libre en la fila | no guarda en la fila transfer.voided las observaciones ni el motivo, solo ids | TransferVoidEndpointTest.php:91 |
| EXT-01 | Bitácora ampliada (D12): 3 acciones sobre `transfer`; otra pareja rechazada | admite en la bitácora las 3 acciones · rechaza una acción de traslado sobre otro tipo | TransferIntegrityTest.php:166, :179 |
| EXT-02 | `migrate:rollback --step=4` con fila `transfer.approved` presente (tarea 1.5) | revierte las 4 migraciones de traslados con una fila transfer.approved y vuelve a migrar · rollback total de `DatabaseMigrations` al salir | TransferRaceTest.php:189 |
| EXT-03 | Factory en los 7 estados (tarea 1.3) | siembra un traslado en cada estado con solicitante = creador y aprobador regente distinto | TransferFactoryTest.php:13 |
| EXT-04 | Render de rechazos nuevos (tarea 4.1) | mapea cada rechazo de traslados a su estado y código estable (dataset 3) | TransferErrorTest.php:10 |
| EXT-05 | Integridad extra (D3, Data impact) | rechaza un solicitante distinto del creador · producto ajeno al lote · discrepancia duplicada o de otro traslado · resolución incoherente | TransferIntegrityTest.php:98, :128, :137, :151 |
| D-auv-3 | Id desbordado en catálogo → 404 | responde 404 sin 500 a un id que no cabe en bigint (productos, bodegas) | Catalog/ProductEndpointTest.php:158; Catalog/WarehouseEndpointTest.php:156 |

## 2. Ancla de transporte: cláusula → ruta archivo:línea

Barrido de la spec: 72 hits, 72 con `[ancla: …]` (journal del spec-validator, GATE 1). Anclas resueltas:

| Ancla de la spec | Código archivo:línea |
|---|---|
| ruta `POST /api/transfers` | routes/api.php:75; app/Http/Controllers/Transfers/TransferController.php (store) |
| ruta `GET /api/transfers` / `GET /api/transfers/{id}` | routes/api.php:74, :77 |
| rutas de acciones de traslado (request, approve, dispatch, receive, void) | routes/api.php:79, :81, :83, :85, :86 |
| ruta de resolución (enlace anidado `scopeBindings`) | routes/api.php:88–89 |
| middleware `auth:sanctum` (401) | routes/api.php:35; app/Exceptions/ApiExceptionRenderer.php:23 |
| middleware CSRF (419) | app/Http/Middleware/ValidateCsrfToken.php:13; app/Exceptions/ApiExceptionRenderer.php:60 |
| render de ModelNotFoundException (404) | routes/api.php:76 (ids 1–18 dígitos), enlace implícito; app/Exceptions/ApiExceptionRenderer.php:58 |
| Policy de traslados (403 `forbidden`) | app/Policies/TransferPolicy.php:16–57; routes/api.php:78, :80, :82, :84 (`can`); FormRequests: StoreTransferRequest.php:21, ListTransfersRequest.php:20, ReceiveTransferRequest.php:20, VoidTransferRequest.php:16, ResolveDiscrepancyRequest.php:18; app/Exceptions/ApiExceptionRenderer.php:57 |
| FormRequest de creación de traslado (422) | app/Http/Requests/Transfers/StoreTransferRequest.php:31 |
| FormRequest de consulta de traslados (422) | app/Http/Requests/Transfers/ListTransfersRequest.php:28 |
| FormRequest de recepción de traslado (422) | app/Http/Requests/Transfers/ReceiveTransferRequest.php:30, :47 (`after`, recibido ≤ cantidad) |
| FormRequest de anulación de traslado (422) | app/Http/Requests/Transfers/VoidTransferRequest.php:27 |
| FormRequest de resolución de discrepancia (422) | app/Http/Requests/Transfers/ResolveDiscrepancyRequest.php:29 |
| render de InvalidTransferTransition (409) | app/Services/Transfers/TransferTransitions.php:40; app/Exceptions/ApiExceptionRenderer.php:44 |
| render de SegregationOfDutiesViolation (403) | app/Actions/Transfers/ApproveTransfer.php:38; app/Exceptions/ApiExceptionRenderer.php:45 |
| render de DiscrepancyAlreadyResolved (409) | app/Actions/Transfers/ResolveDiscrepancy.php:45; app/Exceptions/ApiExceptionRenderer.php:46 |
| render de LotExpired (422) | app/Actions/Transfers/CreateTransfer.php:36; app/Actions/Transfers/DispatchTransfer.php:48; app/Actions/Inventory/AdjustStock.php (devolución); app/Exceptions/ApiExceptionRenderer.php:32 |
| render de InsufficientStock (409) | app/Services/Inventory/StockLedger.php (checkBalances); app/Exceptions/ApiExceptionRenderer.php:29 |
| servicio de traslados (bloqueo + carrera) | app/Services/Transfers/TransferLocker.php:16; app/Actions/Transfers/DispatchTransfer.php:60; app/Actions/Transfers/ReceiveTransfer.php:59; app/Actions/Transfers/ResolveDiscrepancy.php:41 |
| recurso de movimiento de S2 (kardex) | app/Http/Resources/KardexMovementResource.php (sin cambios) |

## 3. [MUT] (corridas delta filtradas; parche exacto → prueba → `git checkout` → prueba; `git status` limpio al final)

Ejecutadas con un script de mutación fuera del repo sobre `dev` en `102c1a4`/`5ddb0ae`.

| n | Mutación | Aplicada → FALLA m/k: prueba | Restaurada → PASA k/k |
|---|---|---|---|
| M1 | `TransferTransitions::ALLOWED`: `request` desde los 7 estados | 6/28: filas `request desde …` de la matriz HTTP | 28/28 |
| M2 | ídem `approve` | 6/28: filas `approve desde …` | 28/28 |
| M3 | ídem `dispatch` | 6/28: filas `dispatch desde …` | 28/28 |
| M4 | ídem `receive` (destinos RECIBIDO \| RECIBIDO_PARCIAL) | 6/28: filas `receive desde …` | 28/28 |
| M5 | ídem `void` | 4/28: filas `void desde …` | 28/28 |
| M6 | `ApproveTransfer`: sin comprobación de segregación (`if (false)`) | 1/1: rechaza con 403 segregation_of_duties al regente que creó (500 por `transfers_approver_differs`) | 1/1 |
| M7 | migración: sin `transfers_approver_differs` | 1/1: rechaza como aprobador al mismo usuario que solicitó | 1/1 |
| M8 | `TransferLocker` sin `lockForUpdate()` | 2/2: despachos simultáneos 10/10 iteraciones fuera · recepciones simultáneas 10/10 fuera | 2/2 |
| M9 | `DispatchTransfer` sin libro: lectura sin bloqueo + `UPDATE quantity = quantity + Δ` + `INSERT` directo (D14) | 1/1: despacho contra dispensación, 5/10 iteraciones fuera (cuenta igual a las iteraciones con la dispensación primero del arranque alternado de D13; el índice no se registró) | 1/1 |
| M10 | `DispatchTransfer`: sin regla de vencimiento | 1/1: rechaza con 422 lot_expired un lote vencido antes del despacho, aun con existencia suficiente | 1/1 |
| M11 | `CreateTransfer`: sin regla de vencimiento | 1/1: rechaza un lote que vence hoy en Bogotá con lot_expired | 1/1 |
| M12 | `DispatchTransfer`: sin transacción externa y `apply()` por línea en orden de id (D14) | 1/1: rechaza con 409 insufficient_stock si una línea no alcanza (A queda en 7) | 1/1 |
| M13 | `ReceiveTransferRequest::after`: sin la regla recibido ≤ cantidad | 1/1: rechaza la sobre-recepción con 422 (llega a 500 por `LogicException`) | 1/1 |
| M14 | `ReceiveTransfer`: sin crear discrepancias | 1/1: recibe parcial: RECIBIDO_PARCIAL con una sola discrepancia | 1/1 |
| M15 | `ResolveDiscrepancy` sin `lockForUpdate()` de la discrepancia | 1/1: resoluciones simultáneas, 10/10 iteraciones fuera | 1/1 |
| M16 | `TransferPolicy::approve` devuelve `true` | 1/1: rechaza con 403 forbidden al auxiliar que no es el solicitante | 1/1 |
| M17 | `TransferPolicy::request` sin comprobar el creador | 2/2: rechaza con 403 la solicitud de un borrador ajeno (otro auxiliar, regente) | 2/2 |
| CAP | rutas de traslado con `whereNumber` en vez de `[0-9]{1,18}` (D-auv-3) | 3/4: ids de 19 dígitos en detalle, despacho y discrepancia (500); la fila no numérica PASA (control) | 4/4 |
| D-auv-3 | catálogo antes del arreglo (`whereNumber`) | 2/2: responde 404 sin 500 a un id que no cabe en bigint (productos, bodegas) → 500 | 2/2 |

Declarados: 17 (M1–M17); entregados: 17 + CAP + D-auv-3.

## 4. Barridos (`/usr/bin/grep`, desde `software/api`)

| # | Comando | Resultado | Control positivo |
|---|---|---|---|
| 1 | `/usr/bin/grep -rniE "lockForUpdate\|for update" app/Actions/Transfers app/Services/Transfers` | 2 hits: `TransferLocker.php:16` (fila de `transfers`) y `ResolveDiscrepancy.php:41` (fila de la discrepancia); 0 sobre `stocks` | mismo patrón en `app/Services/Inventory/StockLedger.php` → 1 |
| 2 | `/usr/bin/grep -rnE "stocks\|Stock::\|->decrement\(\|->increment\(" app/Actions/Transfers app/Services/Transfers \| /usr/bin/grep -c .` | 0 | mismo patrón (`stocks\|Stock::`) en `StockLedger.php` → 3 |
| 3 | `/usr/bin/grep -rn "ledger->apply\|adjust->handle" app/Actions/Transfers` | 3 hits: una llamada por acción (despacho, recepción, devolución) | los 3 hits son el control |
| 4 | `/usr/bin/grep -rn "Log::" app/Actions/Transfers app/Services/Transfers app/Http/Controllers/Transfers app/Http/Requests/Transfers \| /usr/bin/grep -c .` | 0 | `app/Http/Middleware/AssignCorrelationId.php` → 1 |
| 5 | `/usr/bin/grep -rnE "\b(dd\|dump\|ray\|var_dump\|print_r)\(" app tests \| /usr/bin/grep -c .` | 0 | `printf 'dd($x);'` → 1 |
| 6 | `/usr/bin/grep -rn "audit->record" app/Actions/Transfers` | 3 hits: aprobar y anular sin detalle; resolver con `discrepancy_id` | los 3 hits son el control |
| 7 | `/usr/bin/grep -rn "Facades\\\\DB" app/Http/Controllers/Transfers \| /usr/bin/grep -c .` | 0 | `app/Actions/Transfers/DispatchTransfer.php` → 1 |

## 5. Corridas

| # | Comando | Árbol | Resultado |
|---|---|---|---|
| preflight 0.1 | `php artisan test --filter=Race` tras extender `RaceRunner` | sin commit (luego `13a4802`) | 12 pasan (S2 2, S3 5, S4 5) |
| deltas | `php artisan test tests/Feature/Transfers` (+ `tests/Arch`) | sin commit / `2921d6a` | 230 → 243 pasan |
| [MUT] | 19 corridas filtradas mutada + 19 restauradas | `102c1a4`, `5ddb0ae` | § 3 |
| cierre | `pint --test && phpstan analyse --memory-limit=1G && php artisan test` | `5ddb0ae` | Pint pasa; Larastan 0 errores; Pest **737 pasan / 2907 aserciones** |
| delta posterior | `php artisan test tests/Feature/Transfers/TransferReceiveEndpointTest.php` + Pint + Larastan (regla `in` de `line_id` solo con traslado enlazado) | `bc90997` | 18 pasan / 90 aserciones; Pint pasa; Larastan 0 errores |
| OpenAPI | `composer openapi`; `npm run openapi:lint` | `bc90997` | 9 rutas de traslados con 401/403/404/409/419/422; Redocly válido (antes: 1 error `no-enum-type-mismatch` en `ReceiveTransferRequest`, corregido) |

## 6. Humo sobre el stack (6.1, devops-implementer)

Stack: `docker compose -f software/compose.yaml up -d --build --wait api` (solo `api`, sin `down -v`; migraciones y
siembra en el arranque). Script: `software/docker/smoke/transfer-smoke.sh` contra `http://localhost:8090`. Lote
elegido por el script: vigente con existencia en dos bodegas (semilla L-ACE-2402, FC → FU); no crea filas de existencia.

| Escenario | Comprobación sobre el stack | Archivo:línea |
|---|---|---|
| TRF-18 Recorrido completo (hasta recepción parcial) | auxiliar crea 201 `BORRADOR` → solicita 200 `SOLICITADO` → regente aprueba 200 `APROBADO` (aprobador ≠ solicitante) → auxiliar despacha 200 `EN_TRANSITO` | software/docker/smoke/transfer-smoke.sh:146, :150, :154, :171 |
| TRF-31 Solicitante regente intenta aprobar | regente crea y solicita otro traslado; su aprobación → 403 `segregation_of_duties`; sigue `SOLICITADO` sin aprobador; se anula 200 | software/docker/smoke/transfer-smoke.sh:162, :164, :166 |
| TRF-45 Recepción parcial | 2 enviadas, 1 recibida → 200 `RECIBIDO_PARCIAL`, una discrepancia `pending` de faltante 1; existencia destino +1 | software/docker/smoke/transfer-smoke.sh:177, :181, :184 |
| TRF-77 Trazabilidad en el kardex | origen: exactamente un movimiento nuevo `salida_traslado` −2 y existencia −2; destino: uno `entrada_traslado` +1 | software/docker/smoke/transfer-smoke.sh:173, :174, :185 |
| TRF-19 Matriz de transiciones prohibidas (una celda) | despachar un `RECIBIDO_PARCIAL` → 409 `invalid_transfer_transition`; existencia de origen sin cambio | software/docker/smoke/transfer-smoke.sh:188, :190 |
| Resolución de discrepancia | regente `returned_to_origin` → 200 `resolved`; origen: un `ajuste` +1, neto −1; segunda resolución → 409 `discrepancy_already_resolved`; detalle con la discrepancia resuelta | software/docker/smoke/transfer-smoke.sh:194, :197, :198, :199, :204 |

| Corrida | Resultado | Código de salida |
|---|---|---|
| humo, corrida 1 | 28 comprobaciones, 0 fallas | 0 |
| humo, corridas 2 y 3 (repetibilidad) | 28 comprobaciones, 0 fallas cada una | 0 y 0 |
| control: `SEED_USER_PASSWORD=wrong-password` | login 422 → aborta; 2 comprobaciones, 1 falla | 1 |
| control: copia en scratchpad con `RECEIVED=2` (recepción completa) | 7 fallas (no `RECIBIDO_PARCIAL`, sin discrepancia, resolución 404, sin `ajuste`) | 1 |
| control: copia en scratchpad con el auxiliar (solicitante) aprobando | 15 fallas (aprobar 403, despacho 409, sin movimientos) | 1 |
| `auth-smoke.sh`, `stock-smoke.sh`, `dispensation-smoke.sh` tras el rebuild | 38, 13 y 23 comprobaciones, 0 fallas | 0, 0 y 0 |
| `/health`, `/ready` vía `localhost:8090` | 200 y 200 | — |
| `docker compose exec api id -u` | 1000 | — |

| Barrido | Resultado | Control positivo |
|---|---|---|
| `/usr/bin/grep -nE "(sk-\|ghp_\|AKIA\|password\s*=\s*['\"][^'\"$]{6,})"` sobre el script | 0 hits | archivo en scratchpad con `sk-abcdef123456` → 1 hit |

| `stock-smoke.sh` con objetivo por clave semilla (FC + L-IBU-2402 vía `warehouse_id`/`lot_id`) | Resultado | Código de salida |
|---|---|---|
| `auth`, `stock`, `dispensation`, `transfer` humo, una corrida | 38, 15, 23 y 28 comprobaciones, 0 fallas | 0, 0, 0 y 0 |
| control: copia en scratchpad con `SEED_LOT='L-NOPE-0000'` | 2 fallas (stock 422, sin existencia semilla) | 1 |
| `stock-smoke.sh` tras endurecer `jq` ante cuerpos de error (`.data[]?`) | 15 comprobaciones, 0 fallas | 0 |
