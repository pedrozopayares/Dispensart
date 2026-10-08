# Verification — add-dispensation (S3, tier A)

Fuente: sección backend-implementer de `journal.md`. Árbol medido: `dev` en `237126f`. Prefijos: `PAT` patients,
`PRE` prescriptions, `DSP` dispensation, `AUD` audit-trail, `EXT` filas de diseño sin escenario. Rutas de prueba
relativas a `software/api/tests/Feature/`; de código, a `software/api/`.

## 0. Reparto de líneas

Líneas añadidas por S3 + D-auv-2. Comando: `git diff --numstat 0dfb7fc^ 237126f -- <alcance> | awk -F'\t' '$1!="-"{a+=$1} END{print a}'`.

| Categoría | Alcance | Líneas |
|---|---|---|
| Producto — API | `software/api/{app,config,database,routes,bootstrap,lang}` | 3108 |
| Producto — SPA | `software/web` | 0 |
| Producto — infraestructura | `software/compose.yaml`, `software/docker`, `.github` | 0 |
| Prueba — API | `software/api/tests`, `phpunit.xml` | 2445 |
| Generado | `software/api/openapi.json` | 1051 |
| Registro | `openspec/changes/add-dispensation/**/*.md` antes de este archivo (`wc -l`) | 1137 |

## 1. Matriz escenario → prueba → archivo:línea

| Capacidad | Escenarios en la spec | Escenarios con prueba |
|---|---|---|
| patients | 29 | 29 |
| prescriptions | 19 | 19 |
| dispensation | 46 | 46 |
| audit-trail | 16 | 16 |

| Id | Escenario | Prueba | Archivo:línea |
|---|---|---|---|
| PAT-01 | Búsqueda por prefijo de documento | encuentra por prefijo de documento con el documento completo y masked falso | Patients/PatientEndpointTest.php:30 |
| PAT-02 | Búsqueda por nombre sin distinguir mayúsculas | encuentra por nombre sin distinguir mayúsculas (`q=SINTÉTICA`) | Patients/PatientEndpointTest.php:41 |
| PAT-03 | Sin coincidencias | devuelve data vacío sin coincidencias y no registra filas de acceso | Patients/PatientEndpointTest.php:48 |
| PAT-04 | Búsqueda sin término o demasiado corta | rechaza un término ausente, corto o largo sin listar pacientes ni repetir el valor (dataset 3) | Patients/PatientEndpointTest.php:56 |
| PAT-05 | Admin sin acceso a pacientes | responde por rol: datos en claro, enmascarados o 403 (dataset 5) | Patients/PatientEndpointTest.php:117 |
| PAT-06 | Sin sesión (búsqueda) | responde 401 sin sesión | Patients/PatientEndpointTest.php:135 |
| PAT-07 | Ficha con saldos | devuelve la ficha con prescripciones de la más reciente a la más antigua y sus saldos | Patients/PatientEndpointTest.php:149 |
| PAT-08 | Paciente sin prescripciones | devuelve prescriptions vacío para un paciente sin prescripciones | Patients/PatientEndpointTest.php:168 |
| PAT-09 | Paciente inexistente | responde 404 a un paciente inexistente sin fila de acceso (regente, medico) | Patients/PatientEndpointTest.php:175 |
| PAT-10 | Admin sin acceso a la ficha | responde 403 al admin sin datos del paciente ni fila de acceso, también para un id inexistente | Patients/PatientEndpointTest.php:186 |
| PAT-11 | Sin sesión (ficha) | responde 401 sin sesión | Patients/PatientEndpointTest.php:195 |
| PAT-12 | Auditor ve la ficha enmascarada | enmascara la ficha para el auditor con sus prescripciones completas · (unitaria) enmascara documento, nombre, teléfono y fecha | Patients/PatientEndpointTest.php:199; Patients/PatientMaskerTest.php:9 |
| PAT-13 | Ningún dato en claro en la respuesta del auditor | no deja ningún dato en claro en los cuerpos de búsqueda y ficha del auditor | Patients/PatientEndpointTest.php:212 |
| PAT-14 | Auditor busca por documento completo | devuelve al auditor el paciente buscado por documento completo enmascarado | Patients/PatientEndpointTest.php:108 |
| PAT-15 | Otros roles ven datos completos | muestra al regente los datos en claro · por rol (dataset 5) | Patients/PatientEndpointTest.php:229, :117 |
| PAT-16 | Excepción de base de datos con documento en los valores enlazados | no deja el documento de los valores enlazados en el cuerpo ni en el log de una excepción de base de datos | Patients/PatientLogTest.php:50 |
| PAT-17 | Lecturas normales sin datos personales en el log | no escribe datos personales en el log en una búsqueda y una ficha normales | Patients/PatientLogTest.php:71 |
| PAT-18 | Excepción con datos del paciente en el mensaje | no deja en el cuerpo ni en el log una excepción con nombre y documento en su mensaje | Patients/PatientLogTest.php:83 |
| PAT-19 | Control positivo del barrido | línea plantada detectada por el mismo barrido (dentro de las dos pruebas de excepción) | Patients/PatientLogTest.php:50, :83 |
| PAT-20 | Ficha registrada por patrón | registra la ficha por el patrón de la ruta, sin el id de la URL | Patients/PatientLogTest.php:103 |
| PAT-21 | Paciente inexistente sin identificador en el log | responde 404 a un paciente inexistente sin su id ni la clase del modelo en el log | Patients/PatientLogTest.php:110 |
| PAT-22 | Ruta inexistente con identificador | registra unmatched para una ruta inexistente con identificador | Patients/PatientLogTest.php:119 |
| PAT-23 | Ruta sin parámetros sin cambio | registra /ready sin cambio · (S0) línea de cierre de /ready | Patients/PatientLogTest.php:128; RequestLogTest.php:22 |
| PAT-24 | Documento duplicado rechazado por la base | rechaza en la base un documento duplicado del mismo tipo y no deja fila | Patients/PatientIntegrityTest.php:20 |
| PAT-25 | Mismo número con otro tipo de documento | acepta el mismo número con otro tipo de documento | Patients/PatientIntegrityTest.php:31 |
| PAT-26 | Paciente sin nombre | rechaza en la base un paciente sin nombre, sin tipo, sin número o con nombre en blanco (dataset 5) | Patients/PatientIntegrityTest.php:39 |
| PAT-27 | Tres pacientes sembrados | siembra exactamente 3 pacientes con documento del rango 99990 | Patients/PatientSeedTest.php:18 |
| PAT-28 | Siembra repetida | repite la siembra sin duplicar pacientes ni prescripciones y sin reiniciar saldos | Patients/PatientSeedTest.php:25 |
| PAT-29 | Sin datos reales | no contiene en el código de siembra documentos fuera del rango 99990 ni nombres sin la marca de sintético | Patients/PatientSeedTest.php:52 |
| PRE-01 | Prescripción creada | crea la prescripción vigente a nombre del médico con ítems sin dispensar y su fila en la bitácora | Prescriptions/PrescriptionEndpointTest.php:42 |
| PRE-02 | Médico enviado por el cliente ignorado | ignora el médico enviado por el cliente | Prescriptions/PrescriptionEndpointTest.php:65 |
| PRE-03 | Datos inválidos o incompletos | rechaza datos inválidos o incompletos por campo sin crear prescripción (dataset 8) | Prescriptions/PrescriptionEndpointTest.php:74 |
| PRE-04 | Vigencia en el pasado | rechaza una vigencia de ayer en Bogotá | Prescriptions/PrescriptionEndpointTest.php:92 |
| PRE-05 | Otro rol intenta prescribir | rechaza con 403 a los demás roles sin crear prescripción (dataset 4) · Policies por rol (dataset 5) | Prescriptions/PrescriptionEndpointTest.php:105; Dispensation/S3PolicyTest.php:11 |
| PRE-06 | Sin sesión | responde 401 sin sesión sin crear prescripción | Prescriptions/PrescriptionEndpointTest.php:118 |
| PRE-07 | Sin token CSRF | rechaza con 419 una prescripción desde la SPA sin X-XSRF-TOKEN y la acepta con él | Prescriptions/PrescriptionEndpointTest.php:124 |
| PRE-08 | Vence hoy sigue vigente | mantiene vigente una prescripción con pendiente que vence hoy | Prescriptions/PrescriptionStatusTest.php:24 |
| PRE-09 | Venció ayer | devuelve vencida una prescripción con pendiente que venció ayer | Prescriptions/PrescriptionStatusTest.php:28 |
| PRE-10 | Todo dispensado | devuelve agotada una prescripción con todo dispensado · es agotada solo si todos los ítems tienen pendiente 0 | Prescriptions/PrescriptionStatusTest.php:32, :40 |
| PRE-11 | Agotada y vencida | prefiere agotada sobre vencida | Prescriptions/PrescriptionStatusTest.php:36 |
| PRE-12 | Cambio de día sin escritura | pasa a vencida al día siguiente sin escritura alguna | Prescriptions/PrescriptionStatusTest.php:46 |
| PRE-13 | Frontera de medianoche en Bogotá | sigue vigente a las 23:30 de Bogotá del día valid_until | Prescriptions/PrescriptionStatusTest.php:55 |
| PRE-14 | Estado no vigente rechaza la dispensación | rechaza una prescripción vencida ayer sin cambiar saldos · agota la prescripción y rechaza una unidad más | Dispensation/DispensationEndpointTest.php:160, :136 |
| PRE-15 | Parciales acumuladas | dispensa un parcial, lo acumula, agota la prescripción… (4 + 6 = 10) | Dispensation/DispensationEndpointTest.php:136 |
| PRE-16 | Dispensada mayor que prescrita rechazada por la base | rechaza en la base una dispensada mayor que la prescrita y el ítem conserva su valor | Prescriptions/PrescriptionIntegrityTest.php:12 |
| PRE-17 | Dispensada negativa o prescrita cero rechazada por la base | rechaza en la base una dispensada negativa o una prescrita cero (dataset 2) | Prescriptions/PrescriptionIntegrityTest.php:23 |
| PRE-18 | Prescripciones sembradas | da a cada paciente semilla una prescripción vigente del médico semilla y solo una con el controlado | Patients/PatientSeedTest.php:37 |
| PRE-19 | Siembra repetida | repite la siembra sin duplicar pacientes ni prescripciones y sin reiniciar saldos | Patients/PatientSeedTest.php:25 |
| DSP-01 | Vista previa en orden FEFO | devuelve las asignaciones FEFO en orden sin cambiar existencias, kardex ni saldos | Dispensation/DispensationPreviewEndpointTest.php:30 |
| DSP-02 | Faltante visible sin error | muestra el faltante sin error · (unitaria) informa el faltante sin error | Dispensation/DispensationPreviewEndpointTest.php:48; Dispensation/FefoAllocatorTest.php:73 |
| DSP-03 | Lote vencido excluido y contado | excluye y cuenta el lote vencido | Dispensation/DispensationPreviewEndpointTest.php:58 |
| DSP-04 | Ítem de control especial señalado | señala el ítem de control especial en el ítem y en la respuesta | Dispensation/DispensationPreviewEndpointTest.php:67 |
| DSP-05 | Prescripción no dispensable | rechaza la vista previa de una prescripción vencida o agotada (dataset 2) | Dispensation/DispensationPreviewEndpointTest.php:80 |
| DSP-06 | Datos inválidos de la vista previa | rechaza datos inválidos por campo (dataset 5) | Dispensation/DispensationPreviewEndpointTest.php:91 |
| DSP-07 | Rol sin permiso de dispensar (vista previa) | rechaza con 403 a los roles sin permiso de dispensar (dataset 3) | Dispensation/DispensationPreviewEndpointTest.php:106 |
| DSP-08 | Sin sesión (vista previa) | responde 401 sin sesión | Dispensation/DispensationPreviewEndpointTest.php:116 |
| DSP-09 | Consumo en orden FEFO entre varios lotes | consume L1 y luego L2 y deja L3 intacto · (unitaria) consume en orden FEFO aunque lleguen desordenados | Dispensation/DispensationEndpointTest.php:44; Dispensation/FefoAllocatorTest.php:39 |
| DSP-10 | Empate de vencimiento | desempata por id de lote a igual vencimiento (HTTP · unitaria) | Dispensation/DispensationEndpointTest.php:57; Dispensation/FefoAllocatorTest.php:51 |
| DSP-11 | Lote vencido nunca seleccionado | nunca selecciona un lote vencido ni le escribe movimiento (HTTP · unitaria) | Dispensation/DispensationEndpointTest.php:68; Dispensation/FefoAllocatorTest.php:57 |
| DSP-12 | Lote que vence hoy excluido | excluye el lote que vence hoy en Bogotá y responde 409 sin cambios (HTTP · unitaria) | Dispensation/DispensationEndpointTest.php:81; Dispensation/FefoAllocatorTest.php:65 |
| DSP-13 | Otra bodega no se toca | no toca otra bodega aunque tenga el lote que vence primero | Dispensation/DispensationEndpointTest.php:95 |
| DSP-14 | Stock insuficiente, todo o nada | rechaza todo con 409 y los faltantes si un ítem no alcanza | Dispensation/DispensationEndpointTest.php:108 |
| DSP-15 | Dispensación parcial exitosa | dispensa un parcial, lo acumula… (forma 201, pendiente 6) | Dispensation/DispensationEndpointTest.php:136 |
| DSP-16 | Parcial que completa la prescripción | ídem (6 restantes → agotada) | Dispensation/DispensationEndpointTest.php:136 |
| DSP-17 | Prescripción agotada | ídem (1 más con clave nueva → 422 prescription_exhausted, sin cambios) | Dispensation/DispensationEndpointTest.php:136 |
| DSP-18 | Prescripción vencida | rechaza una prescripción vencida ayer sin cambiar saldos | Dispensation/DispensationEndpointTest.php:160 |
| DSP-19 | Más de lo pendiente | rechaza más de lo pendiente sin cambios | Dispensation/DispensationEndpointTest.php:171 |
| DSP-20 | Carrera por el pendiente de la prescripción | serializa la carrera por el pendiente de un ítem: un 201, un 422 exceeds_prescription, 10 de 10 | Dispensation/DispensationRaceTest.php:115 |
| DSP-21 | Solicitud mal formada | rechaza una solicitud mal formada por campo sin cambios (dataset 8) | Dispensation/DispensationEndpointTest.php:182 |
| DSP-22 | Rol sin permiso de dispensar | rechaza con 403 a medico, auditor y admin sin cambios, antes de exigir la clave (dataset 3) · Policies (dataset 5) | Dispensation/DispensationEndpointTest.php:245; Dispensation/S3PolicyTest.php:11 |
| DSP-23 | Sin sesión | responde 401 sin sesión sin cambios | Dispensation/DispensationEndpointTest.php:261 |
| DSP-24 | Sin token CSRF | rechaza con 419 una dispensación desde la SPA sin X-XSRF-TOKEN y la acepta con él | Dispensation/DispensationEndpointTest.php:271 |
| DSP-25 | Dispensador enviado por el cliente ignorado | ignora el dispensador enviado por el cliente | Dispensation/DispensationEndpointTest.php:285 |
| DSP-26 | Carrera por la última unidad | serializa la carrera por la última unidad: un 201, un 409 y nunca negativo, 10 de 10 | Dispensation/DispensationRaceTest.php:61 |
| DSP-27 | Carrera con lote siguiente disponible | lleva al perdedor al lote siguiente: dos 201, L1 y L2 en 0, 10 de 10 | Dispensation/DispensationRaceTest.php:87 |
| DSP-28 | Orden cruzado sin bloqueo mutuo | no se bloquea con productos pedidos en orden cruzado: dos 201…, 10 de 10 | Dispensation/DispensationRaceTest.php:142 |
| DSP-29 | Fallo a mitad de la transacción | revierte todo y responde 500 si falla la escritura de la segunda línea | Dispensation/DispensationFailureTest.php:14 |
| DSP-30 | Un movimiento por lote | escribe un salida_dispensacion por lote con su saldo, el dispensador y el enlace a su línea | Dispensation/DispensationEndpointTest.php:205 |
| DSP-31 | Rechazo sin movimientos | `dispensationState()` (incluye kardex) igual antes y después en permisos, validación, prescripción, autorización y stock | Dispensation/DispensationEndpointTest.php:245, :182, :136, :160, :171, :108; Dispensation/ControlledDrugAuthorizationTest.php:59, :73, :83 |
| DSP-32 | Reintento devuelve la respuesta original | repite la respuesta original byte a byte con Idempotent-Replayed y sin efectos nuevos | Dispensation/DispensationIdempotencyTest.php:23 |
| DSP-33 | Reintento después de agotar la prescripción | repite la respuesta original aunque la prescripción ya esté agotada | Dispensation/DispensationIdempotencyTest.php:37 |
| DSP-34 | Misma clave con otro cuerpo | rechaza la misma clave con otra cantidad sin cambios | Dispensation/DispensationIdempotencyTest.php:64 |
| DSP-35 | Sin clave o clave mal formada | rechaza una clave ausente, corta o con espacios sin cambios (dataset 4) · exige la clave antes de validar el cuerpo | Dispensation/DispensationIdempotencyTest.php:75; Dispensation/DispensationEndpointTest.php:293 |
| DSP-36 | Reintentos simultáneos con la misma clave | responde lo mismo a dos reintentos simultáneos con la misma clave y dispensa una vez, 10 de 10 | Dispensation/DispensationRaceTest.php:170 |
| DSP-37 | Un rechazo no consume la clave | no consume la clave con un rechazo: tras reponer la existencia el mismo reintento crea la dispensación | Dispensation/DispensationIdempotencyTest.php:92 |
| DSP-38 | Misma clave de otro usuario | acepta la misma clave de otro usuario como una dispensación nueva y propia | Dispensation/DispensationIdempotencyTest.php:107 |
| DSP-39 | Auxiliar dispensa con autorización del regente | dispensa con la autorización del regente, guarda authorized_by y registra ambas filas | Dispensation/ControlledDrugAuthorizationTest.php:37 |
| DSP-40 | Regente dispensa con otro regente | permite a un regente dispensar con la autorización de otro regente | Dispensation/ControlledDrugAuthorizationTest.php:50 |
| DSP-41 | Falta la autorización | exige correo y contraseña del autorizador sin cambios (dataset 3) | Dispensation/ControlledDrugAuthorizationTest.php:59 |
| DSP-42 | Autorizador igual al dispensador | rechaza que el regente se autorice a sí mismo sin cambios | Dispensation/ControlledDrugAuthorizationTest.php:73 |
| DSP-43 | Autorizador inválido indistinguible | responde igual a un correo inexistente, una contraseña errada y un usuario sin la capacidad (auxiliar y medico) | Dispensation/ControlledDrugAuthorizationTest.php:83 |
| DSP-44 | Demasiados intentos fallidos de autorizador | responde 429 con Retry-After al sexto intento tras 5 fallos, aun con la contraseña correcta | Dispensation/ControlledDrugAuthorizationTest.php:111 |
| DSP-45 | Producto no controlado con datos de autorizador | ignora credenciales erradas cuando ningún producto es controlado | Dispensation/ControlledDrugAuthorizationTest.php:124 |
| DSP-46 | Contraseña del autorizador fuera de logs y rechazos | no deja la contraseña del autorizador en respuestas, log, bitácoras ni registros de idempotencia | Dispensation/ControlledDrugAuthorizationTest.php:136 |
| AUD-01 | Ficha registrada | registra exactamente una fila view con usuario, paciente, ruta, correlation_id y fecha (auxiliar) | Patients/PatientEndpointTest.php:237 |
| AUD-02 | Búsqueda registra cada paciente devuelto | registra una fila search por cada paciente devuelto y ninguna por los demás | Patients/PatientEndpointTest.php:84 |
| AUD-03 | Búsqueda sin resultados | devuelve data vacío sin coincidencias y no registra filas de acceso | Patients/PatientEndpointTest.php:48 |
| AUD-04 | Lecturas del auditor también registradas | registra exactamente una fila view… (auditor) | Patients/PatientEndpointTest.php:237 |
| AUD-05 | Acceso denegado o inexistente sin fila de acceso | 403 al admin sin fila · 404 sin fila (regente, medico) | Patients/PatientEndpointTest.php:186, :175 |
| AUD-06 | Fallo al registrar el acceso | responde 500 sin datos del paciente si la fila de acceso no puede escribirse | Patients/PatientEndpointTest.php:254 |
| AUD-07 | Sin datos personales en la fila | no guarda en la fila de acceso el término, el nombre ni el documento | Patients/PatientEndpointTest.php:98 |
| AUD-08 | Dispensación ordinaria registrada | registra exactamente una fila dispensation.created del dispensador y ninguna de autorización | Dispensation/DispensationEndpointTest.php:222 |
| AUD-09 | Dispensación de control especial registrada con su autorizador | dispensa con la autorización del regente, guarda authorized_by y registra ambas filas | Dispensation/ControlledDrugAuthorizationTest.php:37 |
| AUD-10 | Intento fallido de autorización registrado | responde igual a un correo inexistente…, y registra cada fallo (sin correo ni contraseña) | Dispensation/ControlledDrugAuthorizationTest.php:83 |
| AUD-11 | Prescripción registrada | crea la prescripción vigente… y su fila en la bitácora | Prescriptions/PrescriptionEndpointTest.php:42 |
| AUD-12 | Reintento idempotente sin filas nuevas | repite la respuesta original byte a byte… (`operations` en la foto) | Dispensation/DispensationIdempotencyTest.php:23 |
| AUD-13 | Rechazo sin fila de operación | no escribe filas de operación en un 409 ni en un 422 aun con autorización válida | Dispensation/ControlledDrugAuthorizationTest.php:165 |
| AUD-14 | Inserción admitida | admite la inserción directa en ambas bitácoras con fecha puesta por la base | Audit/AuditTrailIntegrityTest.php:39 |
| AUD-15 | Modificación rechazada por la base | rechaza en la base un UPDATE sobre cada bitácora y las filas conservan sus valores (dataset 2) | Audit/AuditTrailIntegrityTest.php:45 |
| AUD-16 | Borrado rechazado por la base | rechaza en la base un DELETE o un TRUNCATE sobre cada bitácora (dataset 4) | Audit/AuditTrailIntegrityTest.php:56 |
| EXT-01 | (1.3) FKs compuestas, autorizador ≠ dispensador, idempotencia en base | DispensationIntegrityTest: dispensación coherente, FK paciente, CHECK autorizador, líneas (coherente, dataset 4, movimiento único), idempotencia (unicidad por usuario, dataset 4) | Dispensation/DispensationIntegrityTest.php:40, :46, :54, :89, :95, :108, :137, :146 |
| EXT-02 | (1.4) detalle solo con ids, acción ↔ tipo | rechaza en la base un detalle con texto, una acción desconocida o una acción sobre otro tipo de objeto | Audit/AuditTrailIntegrityTest.php:67 |
| EXT-03 | (1.6) fábricas | Factory de paciente · de prescripción en 3 estados · segundo regente y controlado | Dispensation/DispensationFactoryTest.php:16, :23, :37 |
| EXT-04 | (4.1) render de rechazos | mapea cada rechazo… (dataset 9) · shortages · 429 Retry-After · huella canónica | Dispensation/DispensationErrorTest.php:18, :36, :44, :52 |
| EXT-05 | (D5) huella independiente del orden y de campos ajenos (HTTP) | repite sin importar orden de claves, campos ajenos ni credenciales del autorizador | Dispensation/DispensationIdempotencyTest.php:51 |
| EXT-06 | (D8) búsqueda acotada y comodines escapados | limita a 20 resultados ordenados por nombre y escapa los comodines | Patients/PatientEndpointTest.php:72 |
| EXT-07 | D-auv-2: fecha del kardex al escribir | lista el kardex de una existencia en el orden real de sus saldos aunque las transacciones se solapen | Kardex/KardexTimestampOrderTest.php:35 |

## 2. Ancla de transporte: cláusula → ruta archivo:línea

Barrido: `/usr/bin/grep -rnE '<patrón de CYCLE-TIERS.md>' specs/ | /usr/bin/grep -c .` desde la carpeta del cambio →
70 hits, todos con `[ancla: …]` en la spec (journal del spec-engineer). Anclas resueltas:

| Ancla de la spec | Código archivo:línea |
|---|---|
| middleware `auth:sanctum` (401) | routes/api.php:32; app/Exceptions/ApiExceptionRenderer.php:23 |
| middleware CSRF (419) | app/Http/Middleware/ValidateCsrfToken.php:13; app/Exceptions/ApiExceptionRenderer.php:57 |
| ruta `GET /api/patients` | routes/api.php:56 |
| ruta `GET /api/patients/{id}` | routes/api.php:57 |
| ruta `POST /api/prescriptions` | routes/api.php:58 |
| ruta `POST /api/dispensations/preview` | routes/api.php:59 |
| ruta `POST /api/dispensations` | routes/api.php:63 |
| Policy de pacientes (403) | app/Http/Requests/Patients/SearchPatientsRequest.php:16; app/Http/Requests/Patients/ShowPatientRequest.php:16; app/Policies/PatientPolicy.php:28 |
| Policy de prescripciones (403) | app/Http/Requests/Prescriptions/StorePrescriptionRequest.php:22 |
| Policy de dispensaciones (403) | routes/api.php:60, :64 (`can:create`); app/Exceptions/ApiExceptionRenderer.php:54 |
| render de ModelNotFoundException (404) | app/Actions/Patients/ShowPatient.php:20 (`findOrFail`); app/Exceptions/ApiExceptionRenderer.php:55 |
| FormRequest de búsqueda de pacientes (422) | app/Http/Requests/Patients/SearchPatientsRequest.php:25 |
| FormRequest de prescripción (422) | app/Http/Requests/Prescriptions/StorePrescriptionRequest.php:30, :34 |
| FormRequest de dispensación (422) | app/Http/Requests/Dispensation/PreviewDispensationRequest.php:28, :37; app/Http/Requests/Dispensation/StoreDispensationRequest.php:19 |
| middleware de idempotencia (422 `invalid_idempotency_key`) | app/Http/Middleware/RequireIdempotencyKey.php:25; bootstrap/app.php:57; app/Exceptions/ApiExceptionRenderer.php:42 |
| almacén de idempotencia (repetición, 422 `idempotency_key_reused`) | app/Services/Idempotency/IdempotencyStore.php:36, :44, :53; app/Actions/Dispensation/DispenseMedication.php:73; app/Exceptions/ApiExceptionRenderer.php:43 |
| servicio de autorización de control especial (422 / 429) | app/Services/Dispensation/ControlledDrugAuthorizer.php:40, :45, :51, :57, :64; app/Exceptions/ApiExceptionRenderer.php:36–41 |
| limitador de autorizador (429) | app/Services/Dispensation/ControlledDrugAuthorizer.php:51 |
| servicio de dispensación + render de rechazos de dispensación (422) | app/Services/Dispensation/DispensationPlanner.php:59, :60, :69; app/Exceptions/ApiExceptionRenderer.php:33–35 |
| servicio de dispensación + render de InsufficientStock (409) | app/Actions/Dispensation/DispenseMedication.php:122; app/Exceptions/ApiExceptionRenderer.php:29 |
| consulta FEFO con bloqueo del servicio de dispensación | app/Services/Dispensation/StockCandidates.php:30; app/Actions/Dispensation/DispenseMedication.php:102 |
| manejador de excepciones de la API (500) | app/Exceptions/ApiExceptionRenderer.php:47 |
| redactor del log | app/Logging/JsonLineTap.php:23; app/Logging/RedactExceptionProcessor.php:40 |
| middleware de log de petición / enrutador | app/Http/Middleware/AssignCorrelationId.php:55; app/Support/RoutePattern.php |
| recurso de paciente | app/Http/Resources/PatientResource.php:28 |

## 3. [MUT] (corridas delta filtradas; parche exacto → prueba → `git checkout` → prueba; `git status` limpio al final)

| n | Mutación | Aplicada → FALLA m/k: prueba | Restaurada → PASA k/k |
|---|---|---|---|
| M1 | `FefoAllocator`: orden por vencimiento descendente | 2/2: consume L1 y luego L2 (HTTP) · consume en orden FEFO (unitaria) | 2/2 |
| M2a | `Lot::isExpiredOn`: `<` en vez de `<=` | 2/2: excluye el lote que vence hoy en Bogotá (HTTP) · excluye el lote que vence hoy (unitaria) | 2/2 |
| M2b | `FefoAllocator`: sin filtro de vencidos | 2/2: nunca selecciona un lote vencido (HTTP · unitaria) | 2/2 |
| M3 | `StockCandidates`: sin `FOR UPDATE OF stocks` | 1/2: lleva al perdedor al lote siguiente, 10/10 iteraciones fuera · última unidad PASA (mutante equivalente para ese escenario, design riesgo 1) | 2/2 |
| M4 | `IdempotencyStore::find` nunca encuentra la clave (lectura rápida y relectura) | 1/1: repite la respuesta original byte a byte | 1/1 |
| M5 | `IdempotencyStore::find` sin comparar la huella | 1/1: rechaza la misma clave con otra cantidad | 1/1 |
| M6 | `PatientResource`: datos en claro para todo rol | 2/2: enmascara la ficha para el auditor · ningún dato en claro en los cuerpos del auditor | 2/2 |
| M7 | `ShowPatient`: sin escritura de acceso | 2/2: registra exactamente una fila view (auxiliar, auditor) | 2/2 |
| M8 | `ControlledDrugAuthorizer`: sin autorizador ≠ dispensador | 1/1: rechaza que el regente se autorice a sí mismo (llega a 500 por el CHECK) | 1/1 |
| M9 | `ControlledDrugAuthorizer`: sin exigir `controlled_drugs.authorize` | 1/1: responde igual a un correo inexistente… (auxiliar y medico dan 201) | 1/1 |
| M10 | `DispenseMedication`: ítems sin `FOR UPDATE` | 1/1: carrera por el pendiente, 10/10 iteraciones fuera (500 por `prescription_items_dispensed_range`) | 1/1 |
| M11 | migración de bitácoras sin trigger de `audit_events` | 1/2: UPDATE sobre cada bitácora, fila «operaciones sensibles» (la de acceso sigue PASA) | 2/2 |
| M12 | `JsonLineTap` sin `RedactExceptionProcessor` | 1/1: no deja el documento de los valores enlazados… | 1/1 |
| M13 | `DispenseMedication`: un bloqueo por ítem en el orden de la petición | 1/1: orden cruzado, 10/10 iteraciones fuera (`40P01` → 500) | 1/1 |
| M14 | `AssignCorrelationId`: ruta literal | 1/1: registra la ficha por el patrón de la ruta | 1/1 |
| D-auv-2 | migración `clock_timestamp()` retirada (default `CURRENT_TIMESTAMP`) | 1/1: lista el kardex… en el orden real de sus saldos | 1/1 |

Declarados: 14 (M1–M14); entregados: 15 filas (M2 partido en a/b según su tarea) + D-auv-2.

## 4. Barridos (`/usr/bin/grep`, desde `software/api`)

| # | Comando | Resultado | Control positivo |
|---|---|---|---|
| 1 | `/usr/bin/grep -rnE "Log::(debug\|info\|notice\|warning\|error\|critical\|alert\|emergency)\(" app database` | 2 hits: línea de cierre (método, patrón, estado, duración) y aviso de siembra sin datos | los 2 hits son el control: el patrón encuentra las llamadas existentes |
| 2 | `/usr/bin/grep -c lockForUpdate app/Services/Dispensation/StockCandidates.php` | 0 | mismo patrón en `app/Services/Inventory/StockLedger.php` → 1 |
| 3 | `/usr/bin/grep -c ":input" lang/es/validation.php lang/es/errors.php` | 0 y 0 | `printf 'x :input y'` por el mismo patrón → 1 |
| 4 | archivos de `app` que nombran las bitácoras → `/usr/bin/grep -nE "(->\|::)(update\|delete\|forceDelete\|truncate)\("` | 0 | mismo patrón en `tests/Feature/Audit` → 1 |
| 5 | `/usr/bin/grep -rnE "\b(dd\|dump\|ray\|var_dump\|print_r)\(" tests` | 0 | `printf 'dd($x);'` → 1 |
| 6 | `/usr/bin/grep -rn "authorizer_password\|authorizerPassword" app bootstrap` | 7 hits: regla, accesor, controlador, firma y paso al verificador, `dontFlash`; ninguno en log, huella, bitácora ni cuerpo | los 7 hits son el control |
| 7 | `/usr/bin/grep -rnE "\b(sha1\|md5)\(" app` | 0 | `printf 'sha1($e)'` → 1 |

## 5. Corridas

| # | Comando | Árbol | Resultado |
|---|---|---|---|
| cierre | `pint --test && phpstan analyse --memory-limit=1G && php artisan test` | `85fc059` | Pint pasa; Larastan 0 errores; Pest **488 pasan / 1972 aserciones** |
| delta | `php artisan test tests/Feature/Patients/PatientEndpointTest.php` (+ Pint del archivo) | `237126f` | 28 pasan / 167 aserciones (fila `medico` añadida al 404) |
| OpenAPI | `composer openapi` ×2 + `cmp`; `npm run openapi:lint` | `85fc059` | idéntico entre corridas; Redocly válido |
