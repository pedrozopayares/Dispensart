# Spec Delta — patients

## Purpose

Permite a los roles clínicos y de farmacia encontrar a un paciente y ver sus prescripciones, protegiendo sus
datos personales de salud: acceso por rol, enmascarado en el servidor para el auditor y ningún dato personal
en logs ni rechazos (RN-10, Ley 1581).

## ADDED Requirements

### Requirement: Búsqueda de pacientes
`GET /api/patients?q=` SHALL devolver a los roles con `patients.view` hasta 20 pacientes, ordenados por nombre,
con `id`, `document_type`, `document_number`, `full_name`, `birth_date`, `phone` y `masked`; `q` obligatorio, de
3 a 50 caracteres. Quien ve datos en claro busca por prefijo de documento o fragmento de nombre sin distinguir
mayúsculas; `auditor` SHALL coincidir solo con el número de documento completo exacto (RN-10).

#### Scenario: Búsqueda por prefijo de documento
- **WHEN** un `auxiliar_farmacia` envía `GET /api/patients?q=999901` y un paciente semilla tiene documento `9999010001`
- **THEN** la API responde HTTP 200 con ese paciente en `data`, su documento completo y `masked` `false` [ancla: ruta `GET /api/patients`, archivo:línea al aplicar]

#### Scenario: Búsqueda por nombre sin distinguir mayúsculas
- **WHEN** un `medico` envía `GET /api/patients?q=SINTÉTICA` y existe un paciente cuyo nombre contiene `Sintética`
- **THEN** la API responde HTTP 200 con ese paciente en `data` [ancla: ruta `GET /api/patients`, archivo:línea al aplicar]

#### Scenario: Auditor con prefijo de documento sin resultados
- **WHEN** un `auditor` envía `GET /api/patients?q=999901` y un paciente tiene documento `9999010001`
- **THEN** la API responde HTTP 200 con `data` vacío y no se escribe fila de acceso [ancla: búsqueda de pacientes (`SearchPatients`) + ruta `GET /api/patients`, archivo:línea al aplicar]

#### Scenario: Auditor con fragmento de nombre sin resultados
- **WHEN** un `auditor` envía `GET /api/patients?q=Sint` y existe un paciente cuyo nombre contiene `Sintética`
- **THEN** la API responde HTTP 200 con `data` vacío y no se escribe fila de acceso [ancla: búsqueda de pacientes (`SearchPatients`) + ruta `GET /api/patients`, archivo:línea al aplicar]

#### Scenario: Sin coincidencias
- **WHEN** un `regente_farmacia` envía `GET /api/patients?q=zzzz` y ningún paciente coincide
- **THEN** la API responde HTTP 200 con `data` vacío [ancla: ruta `GET /api/patients`, archivo:línea al aplicar]

#### Scenario: Búsqueda sin término o demasiado corta
- **WHEN** un `auxiliar_farmacia` envía `GET /api/patients` sin `q`, o con `q` de 2 caracteres, o de 51
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.q`, sin listar pacientes y sin repetir el valor enviado [ancla: FormRequest de búsqueda de pacientes, archivo:línea al aplicar]

#### Scenario: Admin sin acceso a pacientes
- **WHEN** un `admin` envía `GET /api/patients?q=999901`
- **THEN** la API responde HTTP 403 con `code` `forbidden` [ancla: Policy de pacientes, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/patients?q=999901`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Ficha del paciente con prescripciones
`GET /api/patients/{id}` SHALL devolver a los roles con `patients.view` los campos del paciente y sus
prescripciones de la más reciente a la más antigua, cada una con `id`, `status`, `valid_until`, médico que la
emitió e ítems con producto, `is_controlled`, cantidad prescrita, dispensada y pendiente. `{id}` SHALL aceptar solo
de 1 a 18 dígitos; otro valor SHALL responder como paciente inexistente (RN-04, RN-10).

#### Scenario: Ficha con saldos
- **WHEN** un `auxiliar_farmacia` envía `GET /api/patients/{id}` de un paciente con una prescripción de 10 unidades de la que se dispensaron 4
- **THEN** la API responde HTTP 200 con la prescripción en `status` `vigente` y el ítem con prescrita 10, dispensada 4 y pendiente 6 [ancla: ruta `GET /api/patients/{id}`, archivo:línea al aplicar]

#### Scenario: Paciente sin prescripciones
- **WHEN** un `medico` envía `GET /api/patients/{id}` de un paciente sin prescripciones
- **THEN** la API responde HTTP 200 con `prescriptions` vacío [ancla: ruta `GET /api/patients/{id}`, archivo:línea al aplicar]

#### Scenario: Paciente inexistente
- **WHEN** un `regente_farmacia` envía `GET /api/patients/999999` y no existe
- **THEN** la API responde HTTP 404 con `code` `not_found` [ancla: render de ModelNotFoundException, archivo:línea al aplicar]

#### Scenario: Identificador fuera de rango
- **WHEN** un `regente_farmacia` envía `GET /api/patients/1234567890123456789` (19 dígitos), o `GET /api/patients/abc`
- **THEN** la API responde HTTP 404 con `code` `not_found`, nunca 500, y no se escribe fila de acceso [ancla: restricción de parámetro de las rutas de pacientes + render de NotFoundHttpException, archivo:línea al aplicar]

#### Scenario: Admin sin acceso a la ficha
- **WHEN** un `admin` envía `GET /api/patients/{id}` de un paciente existente
- **THEN** la API responde HTTP 403 con `code` `forbidden` y ningún dato del paciente [ancla: Policy de pacientes, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/patients/{id}`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Enmascarado para el auditor
Para el rol `auditor`, búsqueda y ficha SHALL enmascarar en el servidor, con las mismas claves y `masked`
`true`: `document_number` con todo salvo los 3 últimos caracteres cambiado por `*`; `full_name` como la
inicial de cada palabra seguida de `***`; `phone` con todo salvo los 2 últimos dígitos cambiado por `*`;
`birth_date` `null`. Prescripciones e ítems SHALL verse completos (RN-10).

#### Scenario: Auditor ve la ficha enmascarada
- **WHEN** un `auditor` envía `GET /api/patients/{id}` de un paciente con documento `9999010001`, nombre `Ana Sintética Pérez` y teléfono `3000000012`
- **THEN** la API responde HTTP 200 con `document_number` `*******001`, `full_name` `A*** S*** P***`, `phone` `********12`, `birth_date` `null`, `masked` `true` y sus prescripciones completas [ancla: recurso de paciente, archivo:línea al aplicar]

#### Scenario: Ningún dato en claro en la respuesta del auditor
- **WHEN** un `auditor` envía la búsqueda y la ficha del paciente anterior
- **THEN** ninguno de los dos cuerpos de respuesta, leídos como texto, contiene `9999010001`, `Ana`, `Sintética`, `Pérez`, `3000000012` ni su fecha de nacimiento [ancla: recurso de paciente, archivo:línea al aplicar]

#### Scenario: Auditor busca por documento completo
- **WHEN** un `auditor` envía `GET /api/patients?q=9999010001`
- **THEN** la API responde HTTP 200 con el paciente enmascarado, igual que en la ficha [ancla: recurso de paciente, archivo:línea al aplicar]

#### Scenario: Otros roles ven datos completos
- **WHEN** un `regente_farmacia` envía `GET /api/patients/{id}` del mismo paciente
- **THEN** la API responde HTTP 200 con los datos en claro y `masked` `false` [ancla: recurso de paciente, archivo:línea al aplicar]

### Requirement: Datos del paciente fuera de logs y rechazos
Ninguna línea de log, cuerpo de rechazo ni mensaje de excepción registrado SHALL contener nombre, documento,
teléfono, fecha de nacimiento de un paciente ni el término de búsqueda, incluidas las excepciones de base de
datos que llevan valores enlazados. Los logs SHALL identificar pacientes solo por `id` (RN-10).

#### Scenario: Excepción de base de datos con documento en los valores enlazados
- **WHEN** la consulta de `GET /api/patients?q=9999010001` falla con una excepción de base de datos forzada en la prueba
- **THEN** la API responde HTTP 500 con `code` `server_error`, y ni el cuerpo ni ninguna línea de log contienen `9999010001`, aunque la línea de error sí trae el `correlation_id` [ancla: manejador de excepciones de la API + redactor del log, archivo:línea al aplicar]

#### Scenario: Lecturas normales sin datos personales en el log
- **WHEN** un `auxiliar_farmacia` busca y abre la ficha del paciente `Ana Sintética Pérez`
- **THEN** ninguna línea de log contiene su nombre, documento, teléfono ni fecha de nacimiento

#### Scenario: Excepción con datos del paciente en el mensaje
- **WHEN** durante la ficha de `Ana Sintética Pérez` se lanza una excepción no controlada cuyo mensaje contiene su nombre y su documento, forzada en la prueba
- **THEN** la API responde HTTP 500 con `code` `server_error`, y ni el cuerpo ni ninguna línea de log contienen `Ana Sintética Pérez` ni `9999010001` [ancla: manejador de excepciones de la API + redactor del log, archivo:línea al aplicar]

#### Scenario: Control positivo del barrido
- **WHEN** la misma prueba escribe a propósito una línea de log con el documento `9999010001`
- **THEN** el mismo barrido del log la detecta, lo que demuestra que el barrido no es un cero vacío

### Requirement: Rutas de pacientes registradas por patrón
La línea de log de cierre de cada petición SHALL registrar en `path` el patrón de la ruta resuelta (p. ej.
`/api/patients/{patient}`), nunca la ruta literal con identificadores; una petición sin ruta resuelta SHALL
registrar `path` `unmatched`. Ninguna línea SHALL contener el `id` de paciente de la URL ni el mensaje de la
excepción de modelo no encontrado (RN-10).

#### Scenario: Ficha registrada por patrón
- **WHEN** un `auxiliar_farmacia` envía `GET /api/patients/{id}` con el `id` 4242 de un paciente existente
- **THEN** la línea de cierre trae `path` `/api/patients/{patient}`, y ninguna línea de log de esa petición contiene `/api/patients/4242`

#### Scenario: Paciente inexistente sin identificador en el log
- **WHEN** un `regente_farmacia` envía `GET /api/patients/987654` y no existe
- **THEN** la API responde HTTP 404 con `code` `not_found`, la línea de cierre trae `path` `/api/patients/{patient}`, y ninguna línea de log contiene `987654` ni el nombre de la clase del modelo [ancla: render de ModelNotFoundException + middleware de log de petición, archivo:línea al aplicar]

#### Scenario: Ruta inexistente con identificador
- **WHEN** un cliente envía `GET /api/patients/987654/historial`, que no corresponde a ninguna ruta
- **THEN** la API responde HTTP 404 con `code` `not_found`, la línea de cierre trae `path` `unmatched`, y ninguna línea de log contiene `987654` [ancla: enrutador de la API + middleware de log de petición, archivo:línea al aplicar]

#### Scenario: Ruta sin parámetros sin cambio
- **WHEN** un cliente envía `GET /ready`
- **THEN** la línea de cierre trae `path` `/ready`, igual que exige `service-health`

### Requirement: Identidad única del paciente
La base SHALL rechazar dos pacientes con el mismo tipo y número de documento, y un paciente sin tipo de
documento, número o nombre completo, aunque la escritura no pase por la API (RN-10).

#### Scenario: Documento duplicado rechazado por la base
- **WHEN** se inserta directamente un paciente con tipo `CC` y número `9999010001` y ya existe otro igual
- **THEN** la base rechaza la inserción por violación de restricción y no queda fila

#### Scenario: Mismo número con otro tipo de documento
- **WHEN** se inserta directamente un paciente con tipo `TI` y número `9999010001` y existe uno `CC` con ese número
- **THEN** la fila queda guardada

#### Scenario: Paciente sin nombre
- **WHEN** se inserta directamente un paciente sin `full_name`
- **THEN** la base rechaza la inserción y no queda fila

### Requirement: Pacientes semilla sintéticos
La siembra SHALL crear 3 pacientes sintéticos, con números de documento del rango ficticio `99990…`
documentado y nombres marcados como sintéticos, de forma idempotente por tipo y número de documento (§ 6,
RN-10).

#### Scenario: Tres pacientes sembrados
- **WHEN** se siembra una base vacía
- **THEN** existen exactamente 3 pacientes, todos con documento que empieza por `99990`

#### Scenario: Siembra repetida
- **WHEN** la siembra corre dos veces seguidas
- **THEN** siguen existiendo exactamente 3 pacientes semilla, sin duplicados

#### Scenario: Sin datos reales
- **WHEN** se revisan los datos semilla de pacientes en el código fuente
- **THEN** no contienen documentos fuera del rango `99990…` ni nombres sin la marca de sintético
