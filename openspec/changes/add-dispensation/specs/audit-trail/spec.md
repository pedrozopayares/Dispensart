# Spec Delta — audit-trail

## Purpose

Deja constancia inmutable de quién consultó qué paciente y de quién ejecutó o autorizó cada operación
sensible, sin guardar datos personales ni credenciales en la propia bitácora (RN-05, RN-10, parte A).

## ADDED Requirements

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
