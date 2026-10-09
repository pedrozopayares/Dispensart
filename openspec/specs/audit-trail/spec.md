# audit-trail Specification

## Purpose
Deja constancia inmutable de quién consultó qué paciente y de quién ejecutó o autorizó cada operación
sensible, sin guardar datos personales ni credenciales en la propia bitácora (RN-05, RN-10, parte A).

## Requirements

### Requirement: Bitácora de acceso a pacientes
Toda respuesta exitosa que devuelva datos de pacientes SHALL escribir, en la misma petición, una fila por
paciente devuelto con usuario, `patient_id`, acción (`view` en la ficha, `search` en la búsqueda), ruta,
`correlation_id` y fecha del servidor. Si la fila no puede escribirse, SHALL fallar sin entregar datos (RN-10).

#### Scenario: Ficha registrada
- **WHEN** un `auxiliar_farmacia` envía `GET /api/patients/{id}` y recibe HTTP 200
- **THEN** existe exactamente una fila nueva con su usuario, ese `patient_id`, acción `view`, la ruta, el `correlation_id` de la respuesta y la fecha del servidor

#### Scenario: Búsqueda registra cada paciente devuelto
- **WHEN** una búsqueda devuelve 2 pacientes
- **THEN** existen exactamente 2 filas nuevas con acción `search`, una por cada `patient_id` devuelto, y ninguna para pacientes no devueltos

#### Scenario: Búsqueda sin resultados
- **WHEN** una búsqueda devuelve `data` vacío
- **THEN** no se escribe ninguna fila

#### Scenario: Lecturas del auditor también registradas
- **WHEN** un `auditor` abre la ficha enmascarada de un paciente
- **THEN** existe exactamente una fila nueva con el usuario auditor y ese `patient_id`

#### Scenario: Acceso denegado o inexistente sin fila de acceso
- **WHEN** un `admin` recibe HTTP 403 al pedir una ficha, o un `medico` recibe HTTP 404 por un paciente inexistente
- **THEN** no se escribe ninguna fila de acceso a paciente [ancla: Policy de pacientes y render de ModelNotFoundException, archivo:línea al aplicar]

#### Scenario: Fallo al registrar el acceso
- **WHEN** la escritura de la fila de acceso falla por un error forzado en la prueba durante `GET /api/patients/{id}`
- **THEN** la API responde HTTP 500 con `code` `server_error` y el cuerpo no contiene ningún dato del paciente [ancla: manejador de excepciones de la API, archivo:línea al aplicar]

#### Scenario: Sin datos personales en la fila
- **WHEN** se revisa la fila de acceso de una búsqueda por `q=9999010001`
- **THEN** la fila no contiene el término de búsqueda, el nombre ni el documento del paciente, solo su `patient_id`

### Requirement: Bitácora de operaciones sensibles
SHALL escribirse una fila con actor, acción, tipo e `id` del objeto, `correlation_id`, fecha del servidor y
solo `id`s como detalle, para: `prescription.created`, `dispensation.created`, `controlled_drug.authorized` y
`controlled_drug.authorization_failed`. Las exitosas, en la transacción de la operación; el fallo de
autorización, aunque la petición se rechace (RN-05, RN-10).

#### Scenario: Dispensación ordinaria registrada
- **WHEN** una dispensación sin productos de control especial responde HTTP 201
- **THEN** existe exactamente una fila nueva `dispensation.created` con el dispensador como actor y el `id` de la dispensación, y ninguna `controlled_drug.authorized` [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Dispensación de control especial registrada con su autorizador
- **WHEN** un `auxiliar_farmacia` dispensa un ítem de control especial autorizado por el `regente_farmacia` y recibe HTTP 201
- **THEN** existen una fila `dispensation.created` con el auxiliar como actor y una `controlled_drug.authorized` con el regente como actor, ambas con el `id` de la dispensación [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Intento fallido de autorización registrado
- **WHEN** una dispensación de control especial responde HTTP 422 `invalid_authorizer`
- **THEN** existe una fila nueva `controlled_drug.authorization_failed` con el dispensador como actor, sin correo ni contraseña del autorizador, y no hay fila `dispensation.created` [ancla: servicio de autorización de control especial, archivo:línea al aplicar]

#### Scenario: Prescripción registrada
- **WHEN** un `medico` crea una prescripción y recibe HTTP 201
- **THEN** existe exactamente una fila nueva `prescription.created` con el médico como actor y el `id` de la prescripción [ancla: ruta `POST /api/prescriptions`, archivo:línea al aplicar]

#### Scenario: Reintento idempotente sin filas nuevas
- **WHEN** se repite una dispensación exitosa con la misma `Idempotency-Key` y el mismo cuerpo
- **THEN** el número de filas de la bitácora de operaciones no cambia

#### Scenario: Rechazo sin fila de operación
- **WHEN** una dispensación responde HTTP 409 `insufficient_stock`, o HTTP 422 `prescription_exhausted`
- **THEN** no se escribe fila `dispensation.created` ni `controlled_drug.authorized` [ancla: servicio de dispensación + render de rechazos de dispensación, archivo:línea al aplicar]

### Requirement: Bitácoras de solo inserción
La base SHALL rechazar toda sentencia `UPDATE`, `DELETE` o `TRUNCATE` sobre la bitácora de acceso a
pacientes y sobre la de operaciones sensibles, aunque la escritura no pase por la API (RN-10).

#### Scenario: Inserción admitida
- **WHEN** se inserta directamente una fila válida en cada bitácora
- **THEN** ambas filas quedan guardadas

#### Scenario: Modificación rechazada por la base
- **WHEN** se ejecuta directamente un `UPDATE` sobre una fila de cada bitácora
- **THEN** la base rechaza cada sentencia y las filas conservan sus valores

#### Scenario: Borrado rechazado por la base
- **WHEN** se ejecuta directamente un `DELETE` o un `TRUNCATE` sobre cada bitácora
- **THEN** la base rechaza cada sentencia y el número de filas no cambia

### Requirement: Bitácora de alta de usuarios
`POST /api/users` con HTTP 201 SHALL escribir, en la transacción del alta, exactamente una fila
`user.created` en la bitácora de operaciones con el admin como actor, el `id` del usuario creado como objeto,
`correlation_id` y fecha del servidor, y solo `id`s como detalle. Si la fila no puede escribirse, el alta
SHALL revertirse. Un alta rechazada SHALL NOT escribir fila (parte A, RN-10).

#### Scenario: Alta registrada
- **WHEN** un `admin` envía `POST /api/users` con correo `nueva.aux@dispensart.test`, contraseña de 12 caracteres y rol `auxiliar_farmacia`, y recibe HTTP 201
- **THEN** existe exactamente una fila nueva `user.created` con el admin como actor, el `id` de la respuesta como objeto, el `correlation_id` de la cabecera `X-Correlation-Id` de la respuesta y la fecha del servidor [ancla: ruta `POST /api/users`, archivo:línea al aplicar]

#### Scenario: Sin datos personales ni secretos en la fila
- **WHEN** un `admin` crea un usuario con nombre `Nueva Auxiliar Sintética`, correo `nueva.aux@dispensart.test` y contraseña `Clave-Sintetica-456`
- **THEN** la fila `user.created`, serializada completa, no contiene el nombre, el correo, la contraseña ni el hash guardado del usuario, solo `id`s

#### Scenario: Fallo al registrar revierte el alta
- **WHEN** la escritura de la fila `user.created` falla por un error forzado en la prueba durante un alta válida
- **THEN** la API responde HTTP 500 con `code` `server_error`, no existe usuario con ese correo y el número de usuarios no cambia [ancla: manejador de excepciones de la API, archivo:línea al aplicar]

#### Scenario: Alta rechazada sin fila
- **WHEN** un `admin` envía un correo duplicado o un rol `superusuario` (HTTP 422), un `auditor` envía un alta válida (HTTP 403) y un cliente sin sesión envía un alta válida (HTTP 401)
- **THEN** el número de filas de la bitácora de operaciones no cambia en ninguno de los 4 casos [ancla: identity-access › Alta de usuarios — escenarios «Correo duplicado sin distinguir mayúsculas», «Rol inválido o datos incompletos», «Otro rol intenta crear usuarios», «Sin sesión»]

### Requirement: Bitácora de ajustes de inventario
`POST /api/stock-adjustments` con HTTP 201 SHALL escribir, en la transacción de la existencia y del
movimiento, exactamente una fila `stock.adjusted` con quien ajusta como actor, el `id` del movimiento `ajuste`
creado como objeto, `correlation_id`, fecha del servidor y solo `id`s como detalle, nunca el `reason`. Si la
fila falla, existencia y kardex SHALL quedar sin cambio. Un ajuste rechazado SHALL NOT escribir fila (RN-03,
RN-06, RN-10).

#### Scenario: Ajuste registrado
- **WHEN** un `regente_farmacia` envía `quantity` -3 sobre una existencia de 10 y recibe HTTP 201
- **THEN** existe exactamente una fila nueva `stock.adjusted` con el regente como actor, el `id` del movimiento de la respuesta como objeto, el `correlation_id` de la cabecera `X-Correlation-Id` y la fecha del servidor [ancla: ruta `POST /api/stock-adjustments`, archivo:línea al aplicar]

#### Scenario: Sin texto libre en la fila
- **WHEN** un `regente_farmacia` ajusta con `reason` `Rotura frente a Ana Sintética 9999010001`
- **THEN** la fila `stock.adjusted`, serializada completa, no contiene ese motivo ni ninguna de sus palabras distintivas, solo `id`s

#### Scenario: Fallo al registrar revierte el ajuste
- **WHEN** la escritura de la fila `stock.adjusted` falla por un error forzado en la prueba durante un ajuste de -3 sobre una existencia de 10
- **THEN** la API responde HTTP 500 con `code` `server_error`, la existencia sigue en 10 y no hay movimiento `ajuste` nuevo [ancla: manejador de excepciones de la API, archivo:línea al aplicar]

#### Scenario: Ajuste rechazado sin fila
- **WHEN** un ajuste responde HTTP 409 `insufficient_stock`, HTTP 422 `lot_expired` por ingreso a un lote vencido, HTTP 422 `validation_failed`, HTTP 403 `forbidden` para un `auditor`, HTTP 401 sin sesión o HTTP 419 sin token CSRF
- **THEN** el número de filas de la bitácora de operaciones no cambia en ninguno de los 6 casos [ancla: inventory › «Ajuste mayor que la existencia», «Ingreso a lote vencido rechazado», «Datos inválidos o incompletos», «Otro rol intenta ajustar», «Sin sesión», «Sin token CSRF desde la SPA»]

#### Scenario: Carrera por la última unidad con una sola fila
- **WHEN** dos peticiones concurrentes de `regente_farmacia`, en conexiones distintas, envían `quantity` -1 sobre una existencia de 1
- **THEN** una responde HTTP 201 y la otra HTTP 409 `insufficient_stock`, y existe exactamente una fila nueva `stock.adjusted`, cuyo objeto es el único movimiento `ajuste` nuevo [ancla: inventory › «Carrera por la última unidad»]

#### Scenario: Ajustes simultáneos con una fila por movimiento
- **WHEN** dos peticiones concurrentes envían `quantity` 3 y `quantity` 2 para la misma bodega y lote sin existencia, y ambas responden HTTP 201
- **THEN** existen exactamente dos filas nuevas `stock.adjusted`, cada una con un movimiento `ajuste` nuevo distinto como objeto [ancla: inventory › «Ajustes positivos simultáneos sobre existencia inexistente»]

#### Scenario: Reintento del mismo ajuste con dos filas
- **WHEN** un `regente_farmacia` envía dos veces el mismo ajuste válido de -1 sobre una existencia de 5 y ambas responden HTTP 201
- **THEN** existen dos filas nuevas `stock.adjusted`, una por cada movimiento `ajuste`, porque el ajuste no es idempotente [ancla: inventory › «Reintento del mismo ajuste»]

#### Scenario: Resolución de discrepancia sin fila de ajuste
- **WHEN** el `regente_farmacia` resuelve una discrepancia con `returned_to_origin` y recibe HTTP 200, lo que crea un movimiento `ajuste` en origen
- **THEN** existe exactamente una fila nueva `transfer.discrepancy_resolved` y ninguna `stock.adjusted` [ancla: ruta `POST /api/transfers/{id}/discrepancies/{discrepancy}/resolve`, archivo:línea al aplicar]

#### Scenario: Dispensación y su repetición idempotente sin fila de ajuste
- **WHEN** una dispensación responde HTTP 201 y se repite con la misma `Idempotency-Key` y el mismo cuerpo
- **THEN** no existe ninguna fila `stock.adjusted` y la repetición no cambia el número de filas de la bitácora de operaciones (RN-09) [ancla: dispensation › «Reintento devuelve la respuesta original»]

### Requirement: Integridad de las acciones de la bitácora en la base
La base SHALL admitir `user.created` solo sobre un usuario y `stock.adjusted` solo sobre un movimiento de
kardex, conservar las acciones y emparejamientos vigentes, y rechazar toda otra acción, todo emparejamiento
cruzado y todo detalle no numérico, aunque la escritura no pase por la API. Las filas nuevas SHALL seguir
siendo de solo inserción (RN-10).

#### Scenario: Acciones nuevas admitidas
- **WHEN** se inserta directamente una fila `user.created` sobre un usuario y una `stock.adjusted` sobre un movimiento de kardex
- **THEN** ambas filas quedan guardadas

#### Scenario: Acciones vigentes siguen admitidas
- **WHEN** se inserta directamente una fila de cada una de las 7 acciones vigentes sobre su tipo de objeto
- **THEN** las 7 filas quedan guardadas

#### Scenario: Emparejamiento cruzado rechazado por la base
- **WHEN** se inserta directamente `user.created` sobre el tipo de objeto del movimiento de kardex, o `stock.adjusted` sobre el tipo de objeto del usuario
- **THEN** la base rechaza cada sentencia con SQLSTATE 23514 nombrando la restricción de emparejamiento acción ↔ tipo de objeto, y no se guarda fila [ancla: migración que extiende los CHECK de audit_events, archivo:línea al aplicar]

#### Scenario: Acción fuera del conjunto rechazada por la base
- **WHEN** se inserta directamente una fila con acción `user.role_changed`
- **THEN** la base rechaza la sentencia con SQLSTATE 23514 nombrando la restricción de acciones, y no se guarda fila [ancla: migración que extiende los CHECK de audit_events, archivo:línea al aplicar]

#### Scenario: Detalle con texto rechazado por la base
- **WHEN** se inserta directamente una fila `user.created` con detalle `{"email": "nueva.aux@dispensart.test"}`
- **THEN** la base rechaza la sentencia con SQLSTATE 23514 nombrando la restricción de detalle solo numérico, y no se guarda fila [ancla: migración de creación de las bitácoras, archivo:línea al aplicar]

#### Scenario: Filas nuevas inmutables
- **WHEN** se ejecuta directamente un `UPDATE`, un `DELETE` y un `TRUNCATE` con una fila `user.created` y una `stock.adjusted` presentes
- **THEN** la base rechaza cada sentencia y las filas conservan sus valores [ancla: audit-trail › «Modificación rechazada por la base», «Borrado rechazado por la base»]
