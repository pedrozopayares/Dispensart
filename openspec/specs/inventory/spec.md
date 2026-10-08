# inventory Specification

## Purpose
Mantiene las existencias de FARTMAR IPS identificadas por bodega + producto + lote (RN-01), las expone para
consulta por rol y permite al regente ajustarlas con motivo sin dejar nunca stock negativo (RN-03), con la
integridad defendida en la base de datos.

## Requirements

### Requirement: Integridad de existencias en la base de datos
Cada existencia SHALL identificarse por bodega + producto + lote, única, con cantidad entera. La base SHALL
rechazar, aunque la escritura no pase por la API: una cantidad negativa, una segunda fila para la misma
bodega + producto + lote, una fila cuyo producto no sea el del lote, y borrar una bodega o un lote con existencias.

#### Scenario: Existencia válida
- **WHEN** se inserta directamente una existencia con bodega, lote, el producto de ese lote y cantidad 0
- **THEN** la fila queda guardada

#### Scenario: Cantidad negativa rechazada por la base
- **WHEN** se actualiza directamente en la base la cantidad de una existencia a -1
- **THEN** la base rechaza la sentencia por violación de restricción y la cantidad anterior permanece

#### Scenario: Existencia duplicada
- **WHEN** se inserta directamente una segunda existencia con la misma bodega, producto y lote que una existente
- **THEN** la base rechaza la inserción por violación de unicidad

#### Scenario: Producto que no corresponde al lote
- **WHEN** se inserta directamente una existencia cuyo `product_id` difiere del producto del lote indicado
- **THEN** la base rechaza la inserción

#### Scenario: Borrar bodega o lote con existencias
- **WHEN** se borra directamente una bodega o un lote que tiene al menos una existencia
- **THEN** la base rechaza el borrado y la existencia permanece

### Requirement: Consulta de existencias
`GET /api/stock` SHALL devolver a los roles con `inventory.view` las existencias con cantidad mayor que 0, cada
una con `id`, `quantity`, `warehouse` (`id`, `code`, `name`), `product` (`id`, `code`, `name`, `is_controlled`)
y `lot` (`id`, `lot_code`, `expires_on`, `is_expired`), ordenadas por bodega, producto, `expires_on` y `id` del
lote. Los filtros opcionales `warehouse_id`, `product_id` y `lot_id` SHALL combinarse con Y.

#### Scenario: Roles con lectura de inventario
- **WHEN** un usuario de cada rol `auxiliar_farmacia`, `regente_farmacia` y `auditor` envía `GET /api/stock` con las existencias semilla
- **THEN** la API responde HTTP 200 con las mismas existencias en el orden indicado en los 3 casos [ancla: ruta `GET /api/stock`, archivo:línea al aplicar]

#### Scenario: Filtros combinados
- **WHEN** un `auditor` envía `GET /api/stock?warehouse_id={central}&product_id={acetaminofen}`
- **THEN** la API responde HTTP 200 solo con existencias de esa bodega y ese producto [ancla: ruta `GET /api/stock`, archivo:línea al aplicar]

#### Scenario: Lote vencido con existencia visible
- **WHEN** un `regente_farmacia` consulta existencias y un lote vencido tiene cantidad 5 en una bodega
- **THEN** la API responde HTTP 200 y esa existencia aparece con `lot.is_expired` `true` [ancla: recurso de existencia, archivo:línea al aplicar]

#### Scenario: Existencia agotada omitida
- **WHEN** una existencia queda en cantidad 0 tras un ajuste y un `auxiliar_farmacia` consulta existencias
- **THEN** la API responde HTTP 200 sin esa existencia [ancla: ruta `GET /api/stock`, archivo:línea al aplicar]

#### Scenario: Filtro sin resultados
- **WHEN** un `auditor` envía `GET /api/stock?lot_id=999999`
- **THEN** la API responde HTTP 200 con una lista vacía [ancla: ruta `GET /api/stock`, archivo:línea al aplicar]

#### Scenario: Filtro mal formado
- **WHEN** un `auditor` envía `GET /api/stock?warehouse_id=abc`
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.warehouse_id` [ancla: FormRequest de consulta de existencias, archivo:línea al aplicar]

#### Scenario: Roles sin lectura de inventario
- **WHEN** un `medico` o un `admin` envía `GET /api/stock`
- **THEN** la API responde HTTP 403 con `code` `forbidden` en ambos casos [ancla: Policy de existencias, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/stock`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Ajuste de inventario
`POST /api/stock-adjustments` SHALL permitir solo a roles con `inventory.adjust` sumar o restar unidades a la
existencia de `warehouse_id` + `lot_id` (producto derivado del lote), con `quantity` entera distinta de 0 y
absoluta ≤ 1 000 000, y `reason` obligatorio, no vacío tras recortar espacios, máximo 500 caracteres. Usuario,
fecha y saldo los fija el servidor.

#### Scenario: Ajuste negativo exitoso
- **WHEN** un `regente_farmacia` envía `quantity` -3 y `reason` `Rotura en estantería` sobre una existencia de 10
- **THEN** la API responde HTTP 201 con el movimiento `ajuste` (`quantity` -3, `balance_after` 7, `reason`, su `user_id`) y la existencia queda en 7 [ancla: ruta `POST /api/stock-adjustments`, archivo:línea al aplicar]

#### Scenario: Ajuste positivo sobre existencia inexistente
- **WHEN** un `regente_farmacia` envía `quantity` 4 para una bodega y un lote no vencido sin existencia previa
- **THEN** la API responde HTTP 201, se crea la existencia con cantidad 4 y un movimiento `ajuste` con `balance_after` 4 [ancla: ruta `POST /api/stock-adjustments`, archivo:línea al aplicar]

#### Scenario: Campos del servidor ignorados
- **WHEN** un `regente_farmacia` envía un ajuste válido con `user_id`, `balance_after`, `created_at` y `type` `entrada` en el cuerpo
- **THEN** la API responde HTTP 201 y el movimiento tiene `type` `ajuste`, su propio usuario, el saldo calculado y la fecha del servidor [ancla: ruta `POST /api/stock-adjustments`, archivo:línea al aplicar]

#### Scenario: Datos inválidos o incompletos
- **WHEN** un `regente_farmacia` envía sin `reason`, o `reason` de solo espacios, o `quantity` 0, o `quantity` 2.5, o `quantity` 1000001, o un `lot_id` o `warehouse_id` inexistente
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en el campo afectado, sin cambiar existencias ni escribir movimiento [ancla: FormRequest de ajuste, archivo:línea al aplicar]

#### Scenario: Otro rol intenta ajustar
- **WHEN** un usuario de cada rol `auxiliar_farmacia`, `medico`, `auditor` y `admin` envía un ajuste válido
- **THEN** la API responde HTTP 403 con `code` `forbidden` en los 4 casos, sin cambiar existencias ni escribir movimiento [ancla: Policy de ajuste, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía un ajuste válido
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` y no escribe movimiento [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

#### Scenario: Sin token CSRF desde la SPA
- **WHEN** un `regente_farmacia` con sesión de la SPA envía un ajuste válido sin `X-XSRF-TOKEN`
- **THEN** la API responde HTTP 419 con `code` `csrf_token_mismatch` y no escribe movimiento [ancla: middleware CSRF, archivo:línea al aplicar]

#### Scenario: Reintento del mismo ajuste
- **WHEN** un `regente_farmacia` envía dos veces el mismo ajuste válido de -1 sobre una existencia de 5
- **THEN** ambas responden HTTP 201, la existencia queda en 3 y hay dos movimientos `ajuste`, porque el ajuste no es idempotente y se corrige con otro ajuste [ancla: ruta `POST /api/stock-adjustments`, archivo:línea al aplicar]

### Requirement: Ajuste nunca deja stock negativo
Un ajuste negativo cuyo valor absoluto supere la cantidad disponible SHALL rechazarse con HTTP 409
`insufficient_stock` sin efecto alguno. Ajustes simultáneos sobre la misma existencia SHALL serializarse: la
cantidad nunca queda negativa y cada saldo del kardex parte del saldo anterior real (RN-03).

#### Scenario: Ajuste mayor que la existencia
- **WHEN** un `regente_farmacia` envía `quantity` -6 sobre una existencia de 5
- **THEN** la API responde HTTP 409 con `code` `insufficient_stock` y `message` en español, la existencia sigue en 5 y no hay movimiento nuevo [ancla: render de InsufficientStock, archivo:línea al aplicar]

#### Scenario: Ajuste negativo sin existencia previa
- **WHEN** un `regente_farmacia` envía `quantity` -1 para una bodega y un lote sin existencia
- **THEN** la API responde HTTP 409 con `code` `insufficient_stock` y no se crea existencia ni movimiento [ancla: render de InsufficientStock, archivo:línea al aplicar]

#### Scenario: Ajuste que deja exactamente cero
- **WHEN** un `regente_farmacia` envía `quantity` -5 sobre una existencia de 5
- **THEN** la API responde HTTP 201 y la existencia queda en 0 con un movimiento de `balance_after` 0 [ancla: ruta `POST /api/stock-adjustments`, archivo:línea al aplicar]

#### Scenario: Carrera por la última unidad
- **WHEN** dos peticiones concurrentes de `regente_farmacia`, en conexiones distintas, envían `quantity` -1 sobre una existencia de 1
- **THEN** una responde HTTP 201 y la otra HTTP 409 `insufficient_stock` (nunca 500), la existencia queda en 0 y hay exactamente un movimiento nuevo [ancla: servicio de libro de stock + render de InsufficientStock, archivo:línea al aplicar]

#### Scenario: Ajustes positivos simultáneos sobre existencia inexistente
- **WHEN** dos peticiones concurrentes envían `quantity` 3 y `quantity` 2 para la misma bodega y lote sin existencia
- **THEN** ambas responden HTTP 201, queda una sola existencia con cantidad 5 y dos movimientos cuyos saldos son la suma acumulada en orden de inserción [ancla: servicio de libro de stock, archivo:línea al aplicar]

### Requirement: Ajuste sobre lote vencido
Un ajuste positivo sobre un lote vencido (regla de vencimiento de `catalog`) SHALL rechazarse con HTTP 422
`lot_expired` sin efecto. Un ajuste negativo sobre un lote vencido SHALL permitirse para dar de baja
existencias vencidas (RN-01).

#### Scenario: Baja de existencia vencida
- **WHEN** un `regente_farmacia` envía `quantity` -5 y `reason` `Baja por vencimiento` sobre la existencia de 5 de un lote vencido
- **THEN** la API responde HTTP 201 y la existencia queda en 0 [ancla: ruta `POST /api/stock-adjustments`, archivo:línea al aplicar]

#### Scenario: Ingreso a lote vencido rechazado
- **WHEN** un `regente_farmacia` envía `quantity` 2 sobre un lote cuyo `expires_on` es hoy en `America/Bogota`
- **THEN** la API responde HTTP 422 con `code` `lot_expired` y `message` en español, sin cambiar existencias ni escribir movimiento [ancla: render de LotExpired, archivo:línea al aplicar]

#### Scenario: Ingreso a lote que vence mañana
- **WHEN** un `regente_farmacia` envía `quantity` 2 sobre un lote cuyo `expires_on` es mañana en `America/Bogota`
- **THEN** la API responde HTTP 201 [ancla: ruta `POST /api/stock-adjustments`, archivo:línea al aplicar]

### Requirement: Existencias semilla
La siembra SHALL crear existencias con cantidad > 0 en las 3 bodegas, cada una con exactamente un movimiento
`entrada` de igual cantidad, `balance_after` igual a la cantidad y usuario nulo. SHALL incluir un lote vencido
con existencia, el producto controlado con existencia no vencida y un producto con 2 lotes no vencidos en una
misma bodega. Repetir la siembra SHALL NOT crear filas ni movimientos ni cambiar cantidades.

#### Scenario: Siembra inicial
- **WHEN** se siembra una base vacía
- **THEN** cada bodega tiene al menos una existencia, existe un lote vencido con cantidad > 0, el producto controlado tiene existencia no vencida y un producto tiene 2 lotes no vencidos con existencia en la misma bodega

#### Scenario: Un movimiento de entrada por existencia sembrada
- **WHEN** se siembra una base vacía
- **THEN** cada existencia tiene exactamente un movimiento, de tipo `entrada`, con cantidad y `balance_after` iguales a su cantidad y `user_id` nulo

#### Scenario: Siembra repetida
- **WHEN** se siembra dos veces seguidas la misma base
- **THEN** los conteos de existencias y de movimientos y cada cantidad son iguales tras la segunda corrida

#### Scenario: Ajustes sobreviven a la resiembra
- **WHEN** un `regente_farmacia` ajusta -2 una existencia semilla y luego se vuelve a sembrar
- **THEN** la existencia conserva la cantidad ajustada y no aparece un segundo movimiento `entrada`

#### Scenario: Existencia previa no sembrada rechazada por la siembra
- **WHEN** antes de sembrar ya existe, creada por un ajuste positivo de 4, la existencia de una bodega + lote que la siembra incluye
- **THEN** la siembra no la toma: no escribe movimiento `entrada` para ella y su cantidad sigue en 4
