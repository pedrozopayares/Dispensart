# inventory-alerts Specification

## Purpose
Define el stock mínimo por bodega + producto y expone, para los roles que leen inventario, las alertas de RN-11:
lotes con existencia que vencen en 90 días o menos y productos por debajo de su mínimo en cada bodega.

## Requirements

### Requirement: Integridad del stock mínimo en la base de datos
Cada stock mínimo SHALL pertenecer a una bodega y un producto existentes, ser único por bodega + producto y ser
un entero mayor que 0 (RN-11). La ausencia de fila SHALL significar que el producto no tiene mínimo en esa
bodega. La base SHALL rechazar, aunque la escritura no pase por la API: un mínimo ≤ 0, un segundo mínimo para el
mismo par, un par inexistente y borrar una bodega o un producto con mínimo.

#### Scenario: Mínimo válido
- **WHEN** se inserta directamente un mínimo de 10 para una bodega y un producto existentes
- **THEN** la fila queda guardada

#### Scenario: Mínimo cero o negativo rechazado por la base
- **WHEN** se inserta directamente un mínimo de 0, o de -5, para una bodega y un producto existentes
- **THEN** la base rechaza la inserción por violación de restricción en ambos casos

#### Scenario: Mínimo duplicado
- **WHEN** se inserta directamente un segundo mínimo para la misma bodega y el mismo producto
- **THEN** la base rechaza la inserción por violación de unicidad

#### Scenario: Bodega o producto inexistente
- **WHEN** se inserta directamente un mínimo con un `warehouse_id` o un `product_id` inexistente
- **THEN** la base rechaza la inserción

#### Scenario: Borrar bodega o producto con mínimo
- **WHEN** se borra directamente una bodega o un producto que tiene un mínimo definido
- **THEN** la base rechaza el borrado y el mínimo permanece

### Requirement: Mínimos semilla
La siembra SHALL definir mínimos para pares bodega + producto de modo que, sobre las existencias semilla, haya al
menos un par bajo su mínimo, al menos un par con mínimo y existencias suficientes, y al menos un par con
existencias y sin mínimo (RN-11). Repetir la siembra SHALL NOT crear filas ni cambiar valores ya presentes.

#### Scenario: Siembra inicial de mínimos
- **WHEN** se siembra una base vacía y se calculan las alertas de stock bajo
- **THEN** hay al menos un par bodega + producto alertado, al menos un par con mínimo no alertado y al menos un par con existencias sin mínimo

#### Scenario: Siembra repetida
- **WHEN** se siembra dos veces seguidas la misma base
- **THEN** el conteo de mínimos y cada valor son iguales tras la segunda corrida

#### Scenario: Mínimo cambiado a mano sobrevive a la resiembra
- **WHEN** se cambia directamente en la base el valor de un mínimo semilla y luego se vuelve a sembrar
- **THEN** el mínimo conserva el valor cambiado

### Requirement: Consulta de alertas
`GET /api/alerts` SHALL devolver a los roles con `inventory.view` un objeto `data` con dos listas,
`expiring_lots` y `low_stock`, calculadas al momento de la consulta (RN-11). El filtro opcional `warehouse_id`
SHALL restringir ambas listas a esa bodega; sin filtro SHALL cubrir todas las bodegas. Sin alertas, ambas listas
SHALL venir vacías, nunca ausentes.

#### Scenario: Roles con lectura de inventario
- **WHEN** un usuario de cada rol `auxiliar_farmacia`, `regente_farmacia` y `auditor` envía `GET /api/alerts` con los datos semilla
- **THEN** la API responde HTTP 200 con el mismo `data.expiring_lots` y el mismo `data.low_stock` en los 3 casos [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Filtro por bodega
- **WHEN** un `auditor` envía `GET /api/alerts?warehouse_id={urgencias}` y hay alertas en Farmacia Urgencias y en Farmacia Central
- **THEN** la API responde HTTP 200 y cada elemento de ambas listas tiene `warehouse.id` igual a Farmacia Urgencias [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Sin alertas
- **WHEN** un `regente_farmacia` consulta alertas y ningún lote con existencia vence en 90 días o menos ni hay pares bajo su mínimo
- **THEN** la API responde HTTP 200 con `data.expiring_lots` y `data.low_stock` como listas vacías [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Bodega inexistente
- **WHEN** un `auditor` envía `GET /api/alerts?warehouse_id=999999`
- **THEN** la API responde HTTP 200 con ambas listas vacías [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Filtro mal formado
- **WHEN** un `auditor` envía `GET /api/alerts?warehouse_id=abc`, o `warehouse_id=0`
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.warehouse_id` en ambos casos [ancla: FormRequest de consulta de alertas, archivo:línea al aplicar]

#### Scenario: Roles sin lectura de inventario
- **WHEN** un `medico` o un `admin` envía `GET /api/alerts`
- **THEN** la API responde HTTP 403 con `code` `forbidden` en ambos casos [ancla: Policy de alertas, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/alerts`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Alerta de vencimiento
`expiring_lots` SHALL listar cada existencia con cantidad > 0 cuyo lote vence en 90 días o menos desde hoy en
`America/Bogota`, incluidos los lotes ya vencidos (regla de `catalog`), con `warehouse`, `product`, `lot`
(`id`, `lot_code`, `expires_on`, `is_expired`), `quantity` y `days_to_expiry` (`expires_on` menos hoy), por
`expires_on`, `id` del lote y bodega (RN-11, RN-01).

#### Scenario: Lote que vence en 90 días incluido
- **WHEN** un `regente_farmacia` consulta alertas y un lote con `expires_on` igual a hoy + 90 días tiene cantidad 3 en una bodega
- **THEN** la API responde HTTP 200 y `expiring_lots` contiene esa existencia con `quantity` 3, `days_to_expiry` 90 y `lot.is_expired` `false` [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Lote que vence en 91 días excluido
- **WHEN** un `regente_farmacia` consulta alertas y un lote con `expires_on` igual a hoy + 91 días tiene cantidad 3 en una bodega
- **THEN** la API responde HTTP 200 y `expiring_lots` no contiene esa existencia [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Lote ya vencido con existencia
- **WHEN** un `auditor` consulta alertas y un lote que vence hoy y otro que venció ayer tienen cantidad > 0
- **THEN** la API responde HTTP 200 y `expiring_lots` contiene ambos con `lot.is_expired` `true` y `days_to_expiry` 0 y -1 [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Lote próximo a vencer sin existencia
- **WHEN** un `auxiliar_farmacia` consulta alertas y la existencia de un lote que vence en 10 días quedó en cantidad 0
- **THEN** la API responde HTTP 200 y `expiring_lots` no contiene esa existencia [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Mismo lote en dos bodegas
- **WHEN** un `auditor` consulta alertas sin filtro y un lote que vence en 30 días tiene existencia en Farmacia Central y en Farmacia Urgencias
- **THEN** la API responde HTTP 200 y `expiring_lots` contiene dos elementos de ese lote, uno por bodega, cada uno con su cantidad [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Orden por vencimiento
- **WHEN** un `regente_farmacia` consulta alertas con existencias de lotes que vencen en 60, 5 y 30 días
- **THEN** la API responde HTTP 200 y `expiring_lots` los lista en orden 5, 30, 60 días [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Frontera del día en hora de Bogotá
- **WHEN** son las 23:30 en `America/Bogota` (04:30 UTC del día siguiente) y un lote con existencia vence en hoy + 91 días según Bogotá
- **THEN** la API responde HTTP 200 y `expiring_lots` no contiene esa existencia [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

### Requirement: Alerta de stock bajo mínimo
`low_stock` SHALL listar cada par bodega + producto con mínimo cuya existencia disponible (suma de lotes no
vencidos del producto en esa bodega) es menor que el mínimo, con `warehouse`, `product`, `minimum_quantity` y
`available_quantity`, por nombre de bodega, nombre e `id` de producto. Un par sin mínimo SHALL NOT alertarse.
Las unidades `EN_TRANSITO` SHALL NOT contar en origen ni destino hasta recibirse (RN-11, RN-01, RN-07).

#### Scenario: Producto bajo su mínimo
- **WHEN** un `regente_farmacia` consulta alertas y un producto con mínimo 10 en una bodega tiene 4 unidades no vencidas allí
- **THEN** la API responde HTTP 200 y `low_stock` contiene ese par con `minimum_quantity` 10 y `available_quantity` 4 [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Existencia igual al mínimo
- **WHEN** un `regente_farmacia` consulta alertas y un producto con mínimo 10 en una bodega tiene exactamente 10 unidades no vencidas allí
- **THEN** la API responde HTTP 200 y `low_stock` no contiene ese par [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Suma de varios lotes cubre el mínimo
- **WHEN** un `auditor` consulta alertas y un producto con mínimo 10 en una bodega tiene 6 y 5 unidades en dos lotes no vencidos allí
- **THEN** la API responde HTTP 200 y `low_stock` no contiene ese par [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Sin existencias con mínimo definido
- **WHEN** un `auxiliar_farmacia` consulta alertas y un producto con mínimo 5 en una bodega no tiene existencias allí, ni filas ni cantidad
- **THEN** la API responde HTTP 200 y `low_stock` contiene ese par con `available_quantity` 0 [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Existencia vencida no cuenta
- **WHEN** un `regente_farmacia` consulta alertas y un producto con mínimo 10 en una bodega tiene 4 unidades no vencidas y 20 de un lote que vence hoy
- **THEN** la API responde HTTP 200 y `low_stock` contiene ese par con `available_quantity` 4 [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Mínimo propio de cada bodega
- **WHEN** un `auditor` consulta alertas y un producto tiene mínimo 10 en Farmacia Central y en Farmacia Urgencias, con 3 unidades en Central y 50 en Urgencias
- **THEN** la API responde HTTP 200 y `low_stock` contiene solo el par de Farmacia Central con `available_quantity` 3 [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Producto sin mínimo
- **WHEN** un `regente_farmacia` consulta alertas y un producto sin mínimo en una bodega tiene 0 unidades allí
- **THEN** la API responde HTTP 200 y `low_stock` no contiene ese par [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar]

#### Scenario: Unidades en tránsito no cuentan hasta la recepción
- **WHEN** un producto tiene mínimo 10 en Farmacia Central, con 12 unidades no vencidas, y en Farmacia Urgencias, con 6; se despacha un traslado de 5 unidades de Central a Urgencias, que queda `EN_TRANSITO`; un `regente_farmacia` consulta alertas; luego se recibe completo el traslado y vuelve a consultar
- **THEN** ambas consultas responden HTTP 200; en la primera `low_stock` contiene Farmacia Central con `available_quantity` 7 y Farmacia Urgencias con `available_quantity` 6; en la segunda contiene Farmacia Central con `available_quantity` 7 y no contiene Farmacia Urgencias, que suma 11 [ancla: ruta `GET /api/alerts`, archivo:línea al aplicar; despacho y recepción ya afirmados en transfers «Despacho exitoso» y «Recepción completa»]
