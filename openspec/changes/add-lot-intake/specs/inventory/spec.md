## ADDED Requirements

### Requirement: Ingreso de lote
`POST /api/stock-entries` SHALL permitir solo a roles con `inventory.adjust` registrar la entrada de mercancía
a una bodega para un lote identificado por producto + `lot_code`: lo crea si no existe, lo reutiliza si su
vencimiento coincide y rechaza un vencimiento distinto o un lote vencido. Cada ingreso SHALL escribir un
movimiento `entrada` con usuario y motivo por la costura única de stock (RN-01, RN-03, RN-06).

#### Scenario: Ingreso de lote nuevo
- **WHEN** un `regente_farmacia` envía un ingreso de `quantity` 50 con un `lot_code` que no existe para el producto y `expires_on` dentro de un año
- **THEN** la API responde HTTP 201 con el movimiento `entrada` (`quantity` 50, `balance_after` 50, `reason`, su usuario), se crea el lote con ese vencimiento y la existencia queda en 50 [ancla: ruta `POST /api/stock-entries`, archivo:línea al aplicar]

#### Scenario: Ingreso sobre lote existente con el mismo vencimiento
- **WHEN** un `regente_farmacia` envía `quantity` 5 para un lote existente con su mismo `expires_on` y existencia de 10 en la bodega
- **THEN** la API responde HTTP 201, no se crea lote nuevo y la existencia queda en 15 con un movimiento `entrada` de `balance_after` 15 [ancla: ruta `POST /api/stock-entries`, archivo:línea al aplicar]

#### Scenario: Vencimiento distinto del lote existente
- **WHEN** un `regente_farmacia` envía un ingreso con el `lot_code` de un lote existente del producto y otro `expires_on`
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.expires_on`, sin crear lote ni cambiar existencias [ancla: acción de ingreso, archivo:línea al aplicar]

#### Scenario: Lote vencido rechazado
- **WHEN** un `regente_farmacia` envía un ingreso con `expires_on` igual a hoy en `America/Bogota`
- **THEN** la API responde HTTP 422 con `code` `lot_expired`, sin crear lote, ni existencia, ni movimiento [ancla: render de LotExpired, archivo:línea al aplicar]

#### Scenario: Datos inválidos o incompletos
- **WHEN** un `regente_farmacia` envía sin `reason` (obligatorio, máximo 500), o `lot_code` de solo espacios o de más de 50 caracteres, o `quantity` 0, o `quantity` -1, o `quantity` 1000001, o `expires_on` que no es fecha, o un `product_id` o `warehouse_id` inexistente
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en el campo afectado, sin crear lote ni movimiento [ancla: FormRequest de ingreso, archivo:línea al aplicar]

#### Scenario: Otro rol intenta registrar un ingreso
- **WHEN** un usuario de cada rol `auxiliar_farmacia`, `medico`, `auditor` y `admin` envía un ingreso válido
- **THEN** la API responde HTTP 403 con `code` `forbidden` en los 4 casos, sin crear lote ni movimiento [ancla: Policy de existencias, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía un ingreso válido
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` y no crea lote ni movimiento [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

#### Scenario: Ingresos simultáneos del mismo lote nuevo
- **WHEN** dos peticiones concurrentes de `regente_farmacia`, en conexiones distintas, ingresan `quantity` 3 y `quantity` 2 del mismo `lot_code` nuevo, con el mismo vencimiento y la misma bodega
- **THEN** ambas responden HTTP 201 (nunca 500), queda un solo lote, una existencia de 5 y dos movimientos `entrada` [ancla: acción de ingreso + servicio de libro de stock, archivo:línea al aplicar]
