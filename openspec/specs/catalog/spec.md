# catalog Specification

## Purpose
Mantiene el catálogo maestro sobre el que opera el inventario de FARTMAR IPS: bodegas, productos (marcando
los de control especial, RN-05) y lotes con su fecha de vencimiento (RN-01), con integridad defendida en la
base de datos y escritura reservada al rol `admin`.

## Requirements

### Requirement: Consulta de bodegas
`GET /api/warehouses` SHALL devolver a todo usuario autenticado, de cualquier rol, todas las bodegas
ordenadas por `name`, cada una con `id`, `code` y `name`.

#### Scenario: Cualquier rol consulta bodegas
- **WHEN** un usuario de cada uno de los 5 roles envía `GET /api/warehouses` con las 3 bodegas semilla en la base
- **THEN** la API responde HTTP 200 con las 3 bodegas ordenadas por `name` en los 5 casos [ancla: ruta `GET /api/warehouses`, archivo:línea al aplicar]

#### Scenario: Sin bodegas
- **WHEN** un usuario autenticado consulta bodegas y la tabla está vacía
- **THEN** la API responde HTTP 200 con una lista vacía [ancla: ruta `GET /api/warehouses`, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/warehouses`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Alta de bodegas
`POST /api/warehouses` SHALL permitir solo a `admin` crear una bodega con `code` (obligatorio, máximo 20
caracteres, único) y `name` (obligatorio, máximo 120 caracteres, único).

#### Scenario: Alta exitosa
- **WHEN** un `admin` envía `POST /api/warehouses` con `code` `FC2` y `name` `Farmacia Consulta Externa`
- **THEN** la API responde HTTP 201 con `id`, `code` y `name` de la bodega creada [ancla: ruta `POST /api/warehouses`, archivo:línea al aplicar]

#### Scenario: Código o nombre duplicado
- **WHEN** un `admin` envía `POST /api/warehouses` con un `code` o un `name` que ya usa otra bodega
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en el campo duplicado, y no crea bodega [ancla: FormRequest de bodega, archivo:línea al aplicar]

#### Scenario: Datos incompletos o demasiado largos
- **WHEN** un `admin` envía `POST /api/warehouses` sin `name`, o con `code` de 21 caracteres
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en el campo afectado, y no crea bodega [ancla: FormRequest de bodega, archivo:línea al aplicar]

#### Scenario: Otro rol intenta crear
- **WHEN** un usuario de cada rol `auxiliar_farmacia`, `regente_farmacia`, `medico` y `auditor` envía `POST /api/warehouses` con datos válidos
- **THEN** la API responde HTTP 403 con `code` `forbidden` en los 4 casos y el número de bodegas no cambia [ancla: Policy de bodegas, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `POST /api/warehouses` con datos válidos
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` y no crea bodega [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Modificación de bodegas
`PATCH /api/warehouses/{id}` SHALL permitir solo a `admin` cambiar `code`, `name` o ambos, con las mismas
reglas del alta. Un campo ausente SHALL quedar sin cambios; un cuerpo vacío SHALL devolver la bodega intacta.

#### Scenario: Cambio parcial
- **WHEN** un `admin` envía `PATCH /api/warehouses/{id}` solo con `name` `Farmacia Central Norte`
- **THEN** la API responde HTTP 200 con el nombre nuevo y el `code` anterior sin cambios [ancla: ruta `PATCH /api/warehouses/{id}`, archivo:línea al aplicar]

#### Scenario: Conservar su propio código
- **WHEN** un `admin` envía `PATCH /api/warehouses/{id}` con el mismo `code` que ya tiene esa bodega
- **THEN** la API responde HTTP 200, sin error de unicidad [ancla: FormRequest de bodega, archivo:línea al aplicar]

#### Scenario: Cuerpo vacío
- **WHEN** un `admin` envía `PATCH /api/warehouses/{id}` con `{}`
- **THEN** la API responde HTTP 200 con la bodega sin cambios [ancla: ruta `PATCH /api/warehouses/{id}`, archivo:línea al aplicar]

#### Scenario: Código de otra bodega
- **WHEN** un `admin` envía `PATCH /api/warehouses/{id}` con el `code` de otra bodega
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.code`, y la bodega no cambia [ancla: FormRequest de bodega, archivo:línea al aplicar]

#### Scenario: Bodega inexistente
- **WHEN** un `admin` envía `PATCH /api/warehouses/999999`
- **THEN** la API responde HTTP 404 con `code` `not_found` [ancla: render de ModelNotFoundException, archivo:línea al aplicar]

#### Scenario: Otro rol intenta modificar
- **WHEN** un `regente_farmacia` envía `PATCH /api/warehouses/{id}` de una bodega existente con datos válidos
- **THEN** la API responde HTTP 403 con `code` `forbidden` y la bodega no cambia [ancla: Policy de bodegas, archivo:línea al aplicar]

### Requirement: Consulta de productos
`GET /api/products` SHALL devolver a todo usuario autenticado todos los productos ordenados por `name`, cada
uno con `id`, `code`, `name`, `presentation` (puede ser `null`) e `is_controlled`.

#### Scenario: Cualquier rol consulta productos
- **WHEN** un usuario de cada uno de los 5 roles envía `GET /api/products` con los 6 productos semilla
- **THEN** la API responde HTTP 200 con 6 productos ordenados por `name`, exactamente uno con `is_controlled` `true`, en los 5 casos [ancla: ruta `GET /api/products`, archivo:línea al aplicar]

#### Scenario: Sin productos
- **WHEN** un usuario autenticado consulta productos y la tabla está vacía
- **THEN** la API responde HTTP 200 con una lista vacía [ancla: ruta `GET /api/products`, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/products`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Alta de productos
`POST /api/products` SHALL permitir solo a `admin` crear un producto con `code` (obligatorio, máximo 30,
único), `name` (obligatorio, máximo 150), `presentation` (opcional, máximo 150) e `is_controlled` (booleano
opcional; si se omite, `false`).

#### Scenario: Alta exitosa de control especial
- **WHEN** un `admin` envía `POST /api/products` con `code` `MED-099`, `name` `Hidromorfona 2 mg/mL`, `presentation` `Ampolla 1 mL` e `is_controlled` `true`
- **THEN** la API responde HTTP 201 con el producto creado e `is_controlled` `true` [ancla: ruta `POST /api/products`, archivo:línea al aplicar]

#### Scenario: Campos opcionales omitidos
- **WHEN** un `admin` envía `POST /api/products` solo con `code` y `name`
- **THEN** la API responde HTTP 201 con `presentation` `null` e `is_controlled` `false` [ancla: ruta `POST /api/products`, archivo:línea al aplicar]

#### Scenario: Datos inválidos
- **WHEN** un `admin` envía `POST /api/products` con `is_controlled` `"quizás"`, o sin `name`, o con un `code` que ya existe
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en el campo afectado, y no crea producto [ancla: FormRequest de producto, archivo:línea al aplicar]

#### Scenario: Otro rol intenta crear
- **WHEN** un usuario de cada rol `auxiliar_farmacia`, `regente_farmacia`, `medico` y `auditor` envía `POST /api/products` con datos válidos
- **THEN** la API responde HTTP 403 con `code` `forbidden` en los 4 casos y el número de productos no cambia [ancla: Policy de productos, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `POST /api/products` con datos válidos
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` y no crea producto [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Modificación de productos
`PATCH /api/products/{id}` SHALL permitir solo a `admin` cambiar cualquier subconjunto de `code`, `name`,
`presentation` e `is_controlled`, con las reglas del alta. Un campo ausente SHALL quedar sin cambios.

#### Scenario: Marcar como control especial
- **WHEN** un `admin` envía `PATCH /api/products/{id}` solo con `is_controlled` `true`
- **THEN** la API responde HTTP 200 con `is_controlled` `true` y `code`, `name` y `presentation` sin cambios [ancla: ruta `PATCH /api/products/{id}`, archivo:línea al aplicar]

#### Scenario: Código de otro producto
- **WHEN** un `admin` envía `PATCH /api/products/{id}` con el `code` de otro producto
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.code`, y el producto no cambia [ancla: FormRequest de producto, archivo:línea al aplicar]

#### Scenario: Producto inexistente
- **WHEN** un `admin` envía `PATCH /api/products/999999`
- **THEN** la API responde HTTP 404 con `code` `not_found` [ancla: render de ModelNotFoundException, archivo:línea al aplicar]

#### Scenario: Otro rol intenta modificar
- **WHEN** un `auditor` envía `PATCH /api/products/{id}` de un producto existente con `is_controlled` `false`
- **THEN** la API responde HTTP 403 con `code` `forbidden` y el producto no cambia [ancla: Policy de productos, archivo:línea al aplicar]

### Requirement: Consulta de lotes
`GET /api/lots` SHALL devolver a todo usuario autenticado los lotes con `id`, `product_id`, `lot_code`,
`expires_on` (`AAAA-MM-DD`) e `is_expired`, ordenados por `expires_on` ascendente y luego por `id`. El
parámetro opcional `product_id` SHALL filtrar por producto.

#### Scenario: Todos los lotes en orden de vencimiento
- **WHEN** un `auditor` envía `GET /api/lots`
- **THEN** la API responde HTTP 200 con todos los lotes ordenados por `expires_on` y luego por `id` [ancla: ruta `GET /api/lots`, archivo:línea al aplicar]

#### Scenario: Filtro por producto
- **WHEN** un `medico` envía `GET /api/lots?product_id={id}` de un producto con 3 lotes
- **THEN** la API responde HTTP 200 con exactamente esos 3 lotes [ancla: ruta `GET /api/lots`, archivo:línea al aplicar]

#### Scenario: Producto sin lotes o inexistente
- **WHEN** un usuario autenticado envía `GET /api/lots?product_id=999999`
- **THEN** la API responde HTTP 200 con una lista vacía [ancla: ruta `GET /api/lots`, archivo:línea al aplicar]

#### Scenario: Filtro mal formado
- **WHEN** un usuario autenticado envía `GET /api/lots?product_id=abc`
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.product_id` [ancla: FormRequest de consulta de lotes, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/lots`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Estado de vencimiento del lote
Un lote SHALL considerarse vencido cuando su `expires_on` es menor o igual a la fecha actual en la zona
`America/Bogota`. `is_expired` SHALL calcularse en cada respuesta, nunca guardarse (RN-01).

#### Scenario: Lote que venció ayer
- **WHEN** se consulta un lote con `expires_on` igual a ayer en `America/Bogota`
- **THEN** su `is_expired` es `true` [ancla: recurso de lote, archivo:línea al aplicar]

#### Scenario: Lote que vence hoy
- **WHEN** se consulta un lote con `expires_on` igual a hoy en `America/Bogota`
- **THEN** su `is_expired` es `true` [ancla: recurso de lote, archivo:línea al aplicar]

#### Scenario: Lote que vence mañana
- **WHEN** se consulta un lote con `expires_on` igual a mañana en `America/Bogota`
- **THEN** su `is_expired` es `false` [ancla: recurso de lote, archivo:línea al aplicar]

#### Scenario: Cambio de día sin escritura
- **WHEN** un lote no vencido llega a su fecha de vencimiento sin que nadie lo modifique
- **THEN** la siguiente consulta lo devuelve con `is_expired` `true` [ancla: recurso de lote, archivo:línea al aplicar]

### Requirement: Integridad del catálogo en la base de datos
La base SHALL rechazar, aunque la escritura no pase por la API: dos lotes con el mismo `lot_code` para el
mismo producto; un lote sin producto existente o sin `expires_on`; dos bodegas con el mismo `code` o
`name`; dos productos con el mismo `code`; y borrar un producto que tenga lotes.

#### Scenario: Lote duplicado para el mismo producto
- **WHEN** se inserta directamente un segundo lote con el mismo `product_id` y `lot_code` que uno existente
- **THEN** la base rechaza la inserción por violación de unicidad

#### Scenario: Mismo código de lote en otro producto
- **WHEN** se inserta un lote con un `lot_code` que ya existe pero para otro producto
- **THEN** la inserción se acepta

#### Scenario: Lote huérfano o sin vencimiento
- **WHEN** se inserta directamente un lote con un `product_id` inexistente, o sin `expires_on`
- **THEN** la base rechaza la inserción

#### Scenario: Código de bodega o de producto duplicado en la base
- **WHEN** se inserta directamente una bodega con un `code` existente, o un producto con un `code` existente
- **THEN** la base rechaza la inserción por violación de unicidad

#### Scenario: Borrar producto con lotes
- **WHEN** se borra directamente en la base un producto que tiene al menos un lote
- **THEN** la base rechaza el borrado y producto y lotes permanecen
