# identity-access Specification

## Purpose
Identifica a cada persona que usa Dispensart, le asigna exactamente un rol de FARTMAR IPS y decide en el
servidor, por rol, qué puede hacer; mantiene la sesión de la SPA por cookie HttpOnly con protección CSRF y
responde los rechazos con una forma JSON estable (RN-10, parte A).

## Requirements

### Requirement: Un rol por usuario
Todo usuario SHALL tener exactamente un rol del conjunto `auxiliar_farmacia`, `regente_farmacia`, `medico`,
`auditor`, `admin`. La base de datos SHALL rechazar un rol nulo o fuera del conjunto, aunque la escritura no
pase por la API.

#### Scenario: Usuario con rol válido
- **WHEN** se inserta un usuario con rol `regente_farmacia`
- **THEN** la fila queda guardada con ese rol

#### Scenario: Rol fuera del conjunto rechazado por la base
- **WHEN** se inserta directamente en la base un usuario con rol `superusuario`
- **THEN** la base rechaza la inserción por violación de restricción y no queda fila

#### Scenario: Rol nulo rechazado por la base
- **WHEN** se inserta directamente en la base un usuario sin rol
- **THEN** la base rechaza la inserción y no queda fila

### Requirement: Mapa de capacidades por rol
El sistema SHALL resolver la autorización con un mapa rol → capacidades derivado de § 3, con denegación por
defecto: una capacidad ausente del mapa de un rol SHALL negarse. Capacidades: `catalog.view`,
`catalog.manage`, `users.manage`, `inventory.view`, `inventory.adjust`, `dispensations.create`,
`controlled_drugs.authorize`, `transfers.view`, `transfers.create`, `transfers.receive`,
`transfers.approve`, `prescriptions.create`, `patients.view`.

#### Scenario: Capacidades de auxiliar_farmacia
- **WHEN** se resuelven las capacidades del rol `auxiliar_farmacia`
- **THEN** son exactamente `catalog.view`, `inventory.view`, `dispensations.create`, `transfers.view`, `transfers.create`, `transfers.receive`, `patients.view`

#### Scenario: Capacidades de regente_farmacia
- **WHEN** se resuelven las capacidades del rol `regente_farmacia`
- **THEN** son exactamente las de `auxiliar_farmacia` más `transfers.approve`, `controlled_drugs.authorize` e `inventory.adjust`

#### Scenario: Capacidades de medico
- **WHEN** se resuelven las capacidades del rol `medico`
- **THEN** son exactamente `catalog.view`, `prescriptions.create`, `patients.view`

#### Scenario: Capacidades de auditor, solo lectura
- **WHEN** se resuelven las capacidades del rol `auditor`
- **THEN** son exactamente `catalog.view`, `inventory.view`, `transfers.view`, `patients.view`, y ninguna capacidad de escritura

#### Scenario: Capacidades de admin, sin datos clínicos ni inventario
- **WHEN** se resuelven las capacidades del rol `admin`
- **THEN** son exactamente `catalog.view`, `catalog.manage`, `users.manage`, y no incluyen `patients.view`, `inventory.view` ni capacidad clínica alguna

#### Scenario: Capacidad desconocida denegada a todo rol
- **WHEN** se consulta una capacidad que no figura en el mapa, por ejemplo `reports.export`, para cada uno de los 5 roles
- **THEN** la respuesta es denegar en los 5 casos

### Requirement: Rol decidido en el servidor
Toda ruta bajo `/api`, salvo `POST /api/auth/login`, SHALL exigir sesión autenticada. El rol que autoriza
cada petición SHALL leerse del usuario guardado en la base en esa petición; ningún campo, cabecera ni cookie
enviado por el cliente SHALL alterarlo.

#### Scenario: Petición sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/warehouses` con `Accept: application/json`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum` + render de AuthenticationException, archivo:línea al aplicar]

#### Scenario: 401 en JSON aunque falte Accept
- **WHEN** un cliente sin sesión envía `GET /api/auth/me` sin cabecera `Accept`
- **THEN** la API responde HTTP 401 en JSON con `code` `unauthenticated`, sin redirección ni HTTP 500 [ancla: render JSON forzado para `api/*`, archivo:línea al aplicar]

#### Scenario: Rol falsificado por el cliente ignorado
- **WHEN** un `auxiliar_farmacia` autenticado envía `POST /api/products` con `"role":"admin"` en el cuerpo y una cabecera `X-Role: admin`
- **THEN** la API responde HTTP 403 con `code` `forbidden` y no crea el producto [ancla: Policy de productos, archivo:línea al aplicar]

#### Scenario: Cambio de rol en la base vigente en la petición siguiente
- **WHEN** un `admin` con sesión abierta pasa a rol `auditor` por escritura directa en la base y luego envía `POST /api/warehouses` con datos válidos
- **THEN** la API responde HTTP 403 con `code` `forbidden` sin cerrar la sesión [ancla: Policy de bodegas, archivo:línea al aplicar]

### Requirement: Forma JSON de los rechazos
Todo rechazo de una ruta `/api` SHALL responder JSON con `code` (inglés, estable) y `message` (español, del
módulo de textos del backend); los de validación SHALL añadir `errors` por campo. Códigos: 401
`unauthenticated`, 403 `forbidden`, 404 `not_found`, 419 `csrf_token_mismatch`, 422 `validation_failed` o
`invalid_credentials`, 429 `too_many_attempts`. Ningún rechazo SHALL incluir traza, SQL ni datos personales.

#### Scenario: Rechazo por permisos
- **WHEN** un `medico` envía `POST /api/warehouses` con datos válidos
- **THEN** la API responde HTTP 403 con cuerpo `{"code":"forbidden","message":"No tienes permiso para realizar esta acción."}` [ancla: render de AuthorizationException, archivo:línea al aplicar]

#### Scenario: Recurso inexistente
- **WHEN** un `admin` envía `PATCH /api/products/999999` y no existe ese producto
- **THEN** la API responde HTTP 404 con `code` `not_found` y un `message` en español, sin el nombre de la clase del modelo [ancla: render de ModelNotFoundException, archivo:línea al aplicar]

#### Scenario: Validación con errores por campo
- **WHEN** un `admin` envía `POST /api/products` sin `code`
- **THEN** la API responde HTTP 422 con `code` `validation_failed`, `message` en español y `errors.code` con al menos un mensaje en español [ancla: render de ValidationException, archivo:línea al aplicar]

### Requirement: Token CSRF para la SPA
`GET /sanctum/csrf-cookie` SHALL responder sin cuerpo y emitir la cookie `XSRF-TOKEN` legible por la SPA.
Toda petición con sesión que cambie estado (POST, PUT, PATCH, DELETE) desde el dominio de la SPA SHALL
exigir la cabecera `X-XSRF-TOKEN` válida; si falta o no coincide, SHALL rechazarse sin efecto.

#### Scenario: Emisión de la cookie CSRF
- **WHEN** la SPA envía `GET /sanctum/csrf-cookie`
- **THEN** la API responde HTTP 204 y emite la cookie `XSRF-TOKEN` sin atributo HttpOnly [ancla: ruta de Sanctum `sanctum/csrf-cookie`, archivo:línea al aplicar]

#### Scenario: Login sin token CSRF
- **WHEN** la SPA envía `POST /api/auth/login` con credenciales válidas, desde su origen, sin cabecera `X-XSRF-TOKEN`
- **THEN** la API responde HTTP 419 con `code` `csrf_token_mismatch` y no abre sesión [ancla: middleware CSRF + render de TokenMismatchException, archivo:línea al aplicar]

#### Scenario: Escritura con token CSRF caducado
- **WHEN** un `admin` con sesión envía `POST /api/warehouses` con un `X-XSRF-TOKEN` que no corresponde a su sesión
- **THEN** la API responde HTTP 419 con `code` `csrf_token_mismatch`, no crea la bodega y la sesión sigue abierta [ancla: middleware CSRF, archivo:línea al aplicar]

#### Scenario: Lectura sin token CSRF
- **WHEN** un usuario con sesión envía `GET /api/auth/me` sin `X-XSRF-TOKEN`
- **THEN** la API responde HTTP 200, porque las lecturas no exigen token [ancla: ruta `GET /api/auth/me`, archivo:línea al aplicar]

### Requirement: Inicio de sesión
`POST /api/auth/login` con `email` y `password` SHALL abrir una sesión por cookie HttpOnly, regenerar el
identificador de sesión y devolver el usuario. SHALL NOT devolver token alguno. El correo SHALL compararse sin
distinguir mayúsculas. La respuesta de credenciales inválidas SHALL ser idéntica exista o no el correo.

#### Scenario: Credenciales válidas
- **WHEN** la SPA, con token CSRF válido, envía `POST /api/auth/login` con el correo y la contraseña de un usuario existente
- **THEN** la API responde HTTP 200 con `id`, `name`, `email`, `role` y `abilities`, emite una cookie de sesión con `HttpOnly`, distinta de la que traía la petición, y el cuerpo no contiene `token`, `password` ni `remember_token` [ancla: ruta `POST /api/auth/login`, archivo:línea al aplicar]

#### Scenario: Correo con mayúsculas
- **WHEN** un usuario registrado como `admin@dispensart.test` inicia sesión con `ADMIN@Dispensart.test` y su contraseña
- **THEN** la API responde HTTP 200 con ese usuario [ancla: ruta `POST /api/auth/login`, archivo:línea al aplicar]

#### Scenario: Contraseña incorrecta
- **WHEN** se envía un correo existente con una contraseña incorrecta
- **THEN** la API responde HTTP 422 con `code` `invalid_credentials` y `message` "Correo o contraseña incorrectos.", y no emite cookie de sesión autenticada [ancla: acción de login, archivo:línea al aplicar]

#### Scenario: Correo inexistente indistinguible
- **WHEN** se envía un correo que no existe con cualquier contraseña
- **THEN** la API responde HTTP 422 con exactamente el mismo cuerpo que en el caso de contraseña incorrecta [ancla: acción de login, archivo:línea al aplicar]

#### Scenario: Datos incompletos o mal formados
- **WHEN** se envía solo `email`, o un `email` sin formato de correo, o un cuerpo vacío
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en cada campo ausente o inválido, sin intentar autenticar [ancla: FormRequest de login, archivo:línea al aplicar]

#### Scenario: Demasiados intentos fallidos
- **WHEN** se registran 5 intentos fallidos para el mismo correo desde la misma IP en 60 segundos y llega un sexto intento, aun con la contraseña correcta
- **THEN** la API responde HTTP 429 con `code` `too_many_attempts` y cabecera `Retry-After`, y no abre sesión [ancla: limitador de login, archivo:línea al aplicar]

#### Scenario: Éxito reinicia el contador
- **WHEN** tras 4 intentos fallidos el usuario inicia sesión con éxito, cierra sesión y falla otra vez
- **THEN** ese intento responde HTTP 422 `invalid_credentials`, no 429 [ancla: limitador de login, archivo:línea al aplicar]

#### Scenario: Origen ajeno a la SPA
- **WHEN** se envía `POST /api/auth/login` con credenciales válidas desde un origen que no está entre los dominios con estado configurados
- **THEN** la respuesta no emite cookie de sesión autenticada y una petición posterior a `GET /api/auth/me` con las cookies recibidas no devuelve el usuario

### Requirement: Cierre de sesión
`POST /api/auth/logout` SHALL invalidar la sesión en el servidor y regenerar el token CSRF; la cookie de
sesión anterior SHALL dejar de autenticar.

#### Scenario: Cierre de sesión exitoso
- **WHEN** un usuario con sesión y token CSRF válido envía `POST /api/auth/logout`
- **THEN** la API responde HTTP 204 y una petición posterior a `GET /api/auth/me` con la cookie anterior responde HTTP 401 [ancla: ruta `POST /api/auth/logout`, archivo:línea al aplicar]

#### Scenario: Cierre sin sesión
- **WHEN** un cliente sin sesión envía `POST /api/auth/logout`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

#### Scenario: Cierre repetido
- **WHEN** el mismo cliente repite `POST /api/auth/logout` tras un cierre exitoso
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` y no falla con HTTP 500 [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Usuario actual
`GET /api/auth/me` SHALL devolver el usuario de la sesión con `id`, `name`, `email`, `role` y `abilities`
(la lista del mapa para su rol). Las capacidades son informativas para la SPA; el servidor SHALL seguir
autorizando cada petición por sí mismo.

#### Scenario: Usuario autenticado
- **WHEN** un `regente_farmacia` con sesión envía `GET /api/auth/me`
- **THEN** la API responde HTTP 200 con su `id`, `name`, `email`, `role` `regente_farmacia` y `abilities` igual a las capacidades de su rol, sin `password` ni `remember_token` [ancla: ruta `GET /api/auth/me`, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/auth/me`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Listado de usuarios
`GET /api/users` SHALL devolver a un `admin` todos los usuarios ordenados por nombre con `id`, `name`,
`email` y `role`, sin contraseña ni token. Cualquier otro rol SHALL recibir rechazo por permisos.

#### Scenario: Admin lista usuarios
- **WHEN** un `admin` envía `GET /api/users` con los 5 usuarios semilla en la base
- **THEN** la API responde HTTP 200 con 5 usuarios ordenados por `name`, cada uno sin `password` ni `remember_token` [ancla: ruta `GET /api/users`, archivo:línea al aplicar]

#### Scenario: Otro rol intenta listar usuarios
- **WHEN** un usuario de cada rol `auxiliar_farmacia`, `regente_farmacia`, `medico` y `auditor` envía `GET /api/users`
- **THEN** la API responde HTTP 403 con `code` `forbidden` en los 4 casos [ancla: Policy de usuarios, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/users`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Alta de usuarios
`POST /api/users` SHALL permitir solo a `admin` crear un usuario con `name`, `email`, `password` (mínimo 8
caracteres) y `role` del conjunto. El correo SHALL guardarse en minúsculas y ser único sin distinguir
mayúsculas; la contraseña SHALL guardarse con hash, nunca en claro.

#### Scenario: Alta exitosa
- **WHEN** un `admin` envía `POST /api/users` con nombre, correo `nueva.aux@dispensart.test`, contraseña de 12 caracteres y rol `auxiliar_farmacia`
- **THEN** la API responde HTTP 201 con `id`, `name`, `email`, `role`, sin contraseña; la columna de contraseña guardada difiere del texto enviado, y el nuevo usuario puede iniciar sesión con ella [ancla: ruta `POST /api/users`, archivo:línea al aplicar]

#### Scenario: Correo duplicado sin distinguir mayúsculas
- **WHEN** un `admin` envía `POST /api/users` con correo `Medico@Dispensart.test` y ya existe `medico@dispensart.test`
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.email`, y no crea usuario [ancla: FormRequest de alta de usuario, archivo:línea al aplicar]

#### Scenario: Rol inválido o datos incompletos
- **WHEN** un `admin` envía `POST /api/users` con rol `superusuario`, o sin `name`, o con contraseña de 7 caracteres
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en el campo afectado, y no crea usuario [ancla: FormRequest de alta de usuario, archivo:línea al aplicar]

#### Scenario: Otro rol intenta crear usuarios
- **WHEN** un usuario de cada rol `auxiliar_farmacia`, `regente_farmacia`, `medico` y `auditor` envía `POST /api/users` con datos válidos y rol `admin`
- **THEN** la API responde HTTP 403 con `code` `forbidden` en los 4 casos y el número de usuarios no cambia [ancla: Policy de usuarios, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `POST /api/users` con datos válidos
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` y no crea usuario [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]
