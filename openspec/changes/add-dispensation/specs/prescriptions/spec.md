# Spec Delta — prescriptions

## Purpose

Registra lo que un médico prescribe a un paciente y lleva, por ítem, cuánto se ha dispensado, para que toda
dispensación se haga contra una prescripción vigente y nunca supere lo prescrito (RN-04).

## ADDED Requirements

### Requirement: Creación de prescripciones por el médico
`POST /api/prescriptions` con `patient_id`, `valid_until` (fecha ≥ hoy en `America/Bogota`) e `items` (1 a
20, cada uno `product_id` existente y `quantity` entera ≥ 1, sin producto repetido) SHALL permitir solo a
roles con `prescriptions.create` crear una prescripción cuyo médico es el usuario autenticado (RN-04).

#### Scenario: Prescripción creada
- **WHEN** un `medico` envía `POST /api/prescriptions` con un paciente existente, `valid_until` dentro de 30 días y dos ítems de 10 y 5 unidades
- **THEN** la API responde HTTP 201 con `id`, `patient_id`, `status` `vigente`, `prescriber` igual al médico autenticado e ítems con dispensada 0 y pendiente igual a lo prescrito [ancla: ruta `POST /api/prescriptions`, archivo:línea al aplicar]

#### Scenario: Médico enviado por el cliente ignorado
- **WHEN** un `medico` envía la prescripción con `prescriber_id` de otro usuario en el cuerpo
- **THEN** la API responde HTTP 201 y la prescripción queda a nombre del médico autenticado [ancla: ruta `POST /api/prescriptions`, archivo:línea al aplicar]

#### Scenario: Datos inválidos o incompletos
- **WHEN** un `medico` envía `POST /api/prescriptions` sin `items`, o con `items` vacío, o con 21 ítems, o con `quantity` 0, o con un producto repetido, o con un `product_id` o `patient_id` inexistente, o sin `valid_until`
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en el campo afectado, y no crea prescripción [ancla: FormRequest de prescripción, archivo:línea al aplicar]

#### Scenario: Vigencia en el pasado
- **WHEN** un `medico` envía `valid_until` igual a ayer en `America/Bogota`
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.valid_until`, y no crea prescripción [ancla: FormRequest de prescripción, archivo:línea al aplicar]

#### Scenario: Otro rol intenta prescribir
- **WHEN** un usuario de cada rol `auxiliar_farmacia`, `regente_farmacia`, `auditor` y `admin` envía `POST /api/prescriptions` con datos válidos
- **THEN** la API responde HTTP 403 con `code` `forbidden` en los 4 casos y el número de prescripciones no cambia [ancla: Policy de prescripciones, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `POST /api/prescriptions` con datos válidos
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` y no crea prescripción [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

#### Scenario: Sin token CSRF
- **WHEN** un `medico` con sesión envía `POST /api/prescriptions` desde el origen de la SPA sin `X-XSRF-TOKEN`
- **THEN** la API responde HTTP 419 con `code` `csrf_token_mismatch` y no crea prescripción [ancla: middleware CSRF, archivo:línea al aplicar]

### Requirement: Estado de la prescripción
El `status` SHALL calcularse en cada lectura, nunca guardarse: `agotada` si todo ítem tiene pendiente 0;
si no, `vencida` si `valid_until` es anterior a hoy en `America/Bogota`; si no, `vigente`. `valid_until` es
inclusivo y `agotada` prevalece sobre `vencida` (RN-04).

#### Scenario: Vence hoy sigue vigente
- **WHEN** se consulta una prescripción con pendiente > 0 y `valid_until` igual a hoy en `America/Bogota`
- **THEN** su `status` es `vigente`

#### Scenario: Venció ayer
- **WHEN** se consulta una prescripción con pendiente > 0 y `valid_until` igual a ayer en `America/Bogota`
- **THEN** su `status` es `vencida`

#### Scenario: Todo dispensado
- **WHEN** se consulta una prescripción cuyos ítems tienen todos pendiente 0
- **THEN** su `status` es `agotada`

#### Scenario: Agotada y vencida
- **WHEN** se consulta una prescripción con todos sus ítems en pendiente 0 y `valid_until` igual a ayer
- **THEN** su `status` es `agotada`

#### Scenario: Cambio de día sin escritura
- **WHEN** una prescripción `vigente` llega al día siguiente a su `valid_until` sin que nadie la modifique
- **THEN** la siguiente lectura la devuelve `vencida`, sin necesidad de proceso programado

#### Scenario: Frontera de medianoche en Bogotá
- **WHEN** son las 23:30 del día `valid_until` en `America/Bogota` (04:30 UTC del día siguiente)
- **THEN** la prescripción con pendiente > 0 sigue `vigente`

#### Scenario: Estado no vigente rechaza la dispensación
- **WHEN** un `auxiliar_farmacia` envía `POST /api/dispensations` con clave nueva contra una prescripción `vencida`, o contra una `agotada`
- **THEN** la API responde HTTP 422 con `code` `prescription_expired`, o `prescription_exhausted`, respectivamente, y el saldo de sus ítems no cambia [ancla: servicio de dispensación + render de rechazos de dispensación, archivo:línea al aplicar]

### Requirement: Saldo acumulado por ítem
Cada ítem SHALL llevar su cantidad dispensada acumulada, entre 0 y la prescrita. La base SHALL rechazar una
dispensada negativa o mayor que la prescrita, y una prescrita menor que 1, aunque la escritura no pase por
la API (RN-04).

#### Scenario: Parciales acumuladas
- **WHEN** sobre un ítem de 10 unidades se registran dispensaciones de 4 y luego de 6
- **THEN** el ítem queda con dispensada 10 y pendiente 0

#### Scenario: Dispensada mayor que prescrita rechazada por la base
- **WHEN** se actualiza directamente en la base la dispensada de un ítem de 10 a 11
- **THEN** la base rechaza la escritura por violación de restricción y el ítem conserva su valor

#### Scenario: Dispensada negativa o prescrita cero rechazada por la base
- **WHEN** se escribe directamente una dispensada de -1, o un ítem con prescrita 0
- **THEN** la base rechaza cada escritura por violación de restricción

### Requirement: Prescripciones semilla
La siembra SHALL dar a cada uno de los 3 pacientes semilla una prescripción `vigente` emitida por el médico
semilla, con `valid_until` relativo a la fecha de siembra; exactamente una SHALL incluir el producto de
control especial. La siembra SHALL ser idempotente (§ 6).

#### Scenario: Prescripciones sembradas
- **WHEN** se siembra una base vacía y se consulta cada paciente semilla
- **THEN** cada uno tiene exactamente una prescripción `vigente` del médico semilla, y exactamente una de las tres incluye el producto con `is_controlled` `true`

#### Scenario: Siembra repetida
- **WHEN** la siembra corre dos veces seguidas
- **THEN** cada paciente semilla sigue con exactamente una prescripción semilla y los saldos dispensados no se reinician
