# kardex Specification

## Purpose
Registra cada cambio de existencias de FARTMAR IPS como un movimiento inmutable con saldo resultante, usuario,
fecha y motivo, de modo que la historia de cada bodega + producto + lote pueda reconstruirse y auditarse
(RN-06, parte A).

## Requirements

### Requirement: Kardex de solo inserción
Los movimientos del kardex SHALL ser inmutables. La base SHALL rechazar toda sentencia `UPDATE`, `DELETE` o
`TRUNCATE` sobre ellos, aunque no pase por la API y venga del mismo usuario de base que usa la aplicación. Las
correcciones SHALL hacerse con un nuevo movimiento `ajuste` con motivo.

#### Scenario: Inserción aceptada
- **WHEN** se inserta directamente un movimiento válido
- **THEN** la fila queda guardada

#### Scenario: Edición rechazada por la base
- **WHEN** se ejecuta directamente `UPDATE` sobre la cantidad o el motivo de un movimiento existente
- **THEN** la base rechaza la sentencia con un error que nombra la inmutabilidad del kardex y la fila no cambia [ancla: trigger de solo inserción del kardex, archivo:línea al aplicar]

#### Scenario: Borrado rechazado por la base
- **WHEN** se ejecuta directamente `DELETE` de un movimiento existente
- **THEN** la base rechaza la sentencia y la fila permanece [ancla: trigger de solo inserción del kardex, archivo:línea al aplicar]

#### Scenario: Vaciado rechazado por la base
- **WHEN** se ejecuta directamente `TRUNCATE` sobre la tabla de movimientos
- **THEN** la base rechaza la sentencia y las filas permanecen [ancla: trigger de solo inserción del kardex, archivo:línea al aplicar]

#### Scenario: Sin ruta de edición ni borrado
- **WHEN** un `regente_farmacia` envía `PATCH` o `DELETE` a `/api/kardex/{id}` de un movimiento existente
- **THEN** la API responde HTTP 404 con `code` `not_found` y el movimiento no cambia, porque la ruta no existe [ancla: tabla de rutas sin `kardex/{id}` + render de NotFoundHttpException, archivo:línea al aplicar]

### Requirement: Integridad del movimiento en la base de datos
Cada movimiento SHALL tener `type` del conjunto `entrada`, `salida_dispensacion`, `salida_traslado`,
`entrada_traslado`, `ajuste`; bodega, producto y lote existentes; `quantity` entera distinta de 0 con el signo
del tipo (entradas > 0, salidas < 0, ajuste cualquiera); `balance_after` ≥ 0; fecha fijada por la base. Un
`ajuste` SHALL exigir usuario y motivo no vacío. La base SHALL rechazar lo demás.

#### Scenario: Tipos futuros ya admitidos
- **WHEN** se inserta directamente un movimiento `salida_traslado` con `quantity` -2 y otro `entrada_traslado` con `quantity` 2
- **THEN** ambas filas quedan guardadas

#### Scenario: Tipo fuera del conjunto
- **WHEN** se inserta directamente un movimiento con `type` `devolucion`
- **THEN** la base rechaza la inserción por violación de restricción

#### Scenario: Signo contrario al tipo
- **WHEN** se inserta directamente un `entrada` con `quantity` -1, o un `salida_dispensacion` con `quantity` 1, o cualquier movimiento con `quantity` 0
- **THEN** la base rechaza cada inserción por violación de restricción

#### Scenario: Saldo resultante negativo
- **WHEN** se inserta directamente un movimiento con `balance_after` -1
- **THEN** la base rechaza la inserción por violación de restricción

#### Scenario: Ajuste sin usuario o sin motivo
- **WHEN** se inserta directamente un `ajuste` con usuario nulo, o con motivo nulo o de solo espacios
- **THEN** la base rechaza la inserción por violación de restricción

### Requirement: Un movimiento por cada cambio de existencia
Todo cambio de cantidad de una existencia SHALL escribir exactamente un movimiento en la misma transacción, con
`quantity` igual a la variación y `balance_after` igual a la cantidad resultante. Para cada existencia, su
cantidad SHALL igualar el `balance_after` de su último movimiento y la suma de sus `quantity` (RN-06).

#### Scenario: Ajuste escribe un solo movimiento
- **WHEN** un `regente_farmacia` hace un ajuste exitoso de -3 sobre una existencia de 10
- **THEN** el número de movimientos de esa existencia aumenta exactamente en 1, con `quantity` -3 y `balance_after` 7

#### Scenario: Cadena de saldos tras varios cambios
- **WHEN** se aplican en orden los ajustes +4, -2 y -1 sobre una existencia sembrada de 6
- **THEN** los `balance_after` son 10, 8 y 7, la existencia vale 7 y la suma de sus `quantity` es 7

#### Scenario: Ajuste rechazado no deja movimiento
- **WHEN** un ajuste termina en rechazo por permisos, validación, lote vencido o stock insuficiente
- **THEN** el número de movimientos y la cantidad de la existencia no cambian

#### Scenario: Fallo al escribir el movimiento revierte la existencia
- **WHEN** la escritura del movimiento falla dentro de un ajuste después de calcular la nueva cantidad
- **THEN** la existencia conserva su cantidad anterior y no queda movimiento parcial

### Requirement: Consulta del kardex
`GET /api/kardex` SHALL devolver a los roles con `inventory.view` los movimientos del más reciente al más
antiguo (fecha, luego `id`), paginados (`per_page` 1–100, 50 por defecto), con filtros opcionales
`warehouse_id`, `product_id` y `lot_id` combinados con Y. Cada movimiento SHALL incluir tipo, cantidad, saldo,
motivo, fecha ISO 8601 con zona, bodega, producto, lote y usuario (`null` si es del sistema).

#### Scenario: Roles con lectura consultan el kardex
- **WHEN** un usuario de cada rol `auxiliar_farmacia`, `regente_farmacia` y `auditor` envía `GET /api/kardex`
- **THEN** la API responde HTTP 200 con `data` en orden descendente y `meta` con `current_page`, `per_page` y `total` en los 3 casos [ancla: ruta `GET /api/kardex`, archivo:línea al aplicar]

#### Scenario: Filtro por producto, lote y bodega
- **WHEN** un `auditor` envía `GET /api/kardex?warehouse_id={w}&product_id={p}&lot_id={l}` tras ajustar esa existencia y otra
- **THEN** la API responde HTTP 200 solo con movimientos de esa bodega, producto y lote [ancla: ruta `GET /api/kardex`, archivo:línea al aplicar]

#### Scenario: Movimiento de ajuste con usuario y motivo
- **WHEN** un `auditor` consulta el kardex después de un ajuste del regente semilla
- **THEN** la API responde HTTP 200 y el movimiento `ajuste` muestra `user.name` del regente y su `reason` [ancla: recurso de movimiento, archivo:línea al aplicar]

#### Scenario: Movimiento de siembra sin usuario
- **WHEN** un `auxiliar_farmacia` consulta el kardex de una existencia semilla sin ajustes
- **THEN** la API responde HTTP 200 con un único movimiento `entrada` y `user` `null` [ancla: recurso de movimiento, archivo:línea al aplicar]

#### Scenario: Página fuera de rango o filtro sin resultados
- **WHEN** un `auditor` envía `GET /api/kardex?page=999` o `GET /api/kardex?lot_id=999999`
- **THEN** la API responde HTTP 200 con `data` vacío [ancla: ruta `GET /api/kardex`, archivo:línea al aplicar]

#### Scenario: Parámetros mal formados
- **WHEN** un `auditor` envía `GET /api/kardex?product_id=abc` o `GET /api/kardex?per_page=101`
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en el parámetro afectado [ancla: FormRequest de consulta del kardex, archivo:línea al aplicar]

#### Scenario: Roles sin lectura de inventario
- **WHEN** un `medico` o un `admin` envía `GET /api/kardex`
- **THEN** la API responde HTTP 403 con `code` `forbidden` en ambos casos [ancla: Policy de movimientos, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/kardex`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]
