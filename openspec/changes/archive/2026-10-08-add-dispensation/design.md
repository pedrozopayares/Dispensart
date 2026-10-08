# Design — add-dispensation (S3, tier A)

## Context

Motivación y alcance en `proposal.md`; requisitos en `specs/{patients,prescriptions,dispensation,audit-trail}`.
Costuras heredadas, que S3 reutiliza sin redefinir: S0 D6 (log JSON, `Context` con `correlation_id`, línea de cierre
en `AssignCorrelationId::terminate()`), S1 D2/D3/D4/D5/D6/D9/D11 (precedencia CSRF→sesión, limitador + hash ficticio,
mapa de capacidades, forma de rechazo, `BusinessCalendar`, Scramble, siembra `firstOrCreate`), S2 D2/D5/D7/D8/D10
(`StockLedger::apply()` única escritura de existencias, `Stock::scopeInLockOrder()`, trigger `ENABLE ALWAYS`,
`DatabaseMigrations` en carreras, `RaceRunner` con procesos reales, `InsufficientStock` 409).

## Goals / Non-Goals

**Goals**
- Una sola regla FEFO y de prescripción (`DispensationPlanner`) que sirve a vista previa y a dispensación: las
  mutaciones M1/M2 se ven en ambas rutas.
- Orden de bloqueo global y fijo: clave de idempotencia → ítems de la prescripción → existencias (clave de S2).
- Precedencia de rechazos explícita y probada (tabla en D4), con la repetición idempotente antes de toda regla de
  negocio y después de la validación.
- Defensas en base: saldo por ítem, autorizador ≠ dispensador, coherencia línea↔prescripción↔producto↔lote,
  bitácoras de solo inserción, detalle de bitácora limitado a ids.
- Ningún dato de paciente ni credencial en logs, rechazos, bitácoras ni registros de idempotencia.

**Non-Goals**
- Lectura de bitácoras por API (deuda, ver journal). Alta/edición de pacientes. Caducidad o purga de claves de
  idempotencia (proposal § Assumptions 6). Índices de texto (`pg_trgm`). Inmutabilidad por trigger de
  `dispensations`/`dispensation_lines` (sin escenario; solo la acción escribe y nunca actualiza).
- Cambios a tablas o contratos de S2 (`kardex_movements`, `StockChange`, `apply()`).

## Decisions

### D1. Contextos y piezas
Contexto ↔ capacidad: `Patients` ↔ `patients`, `Prescriptions` ↔ `prescriptions`, `Dispensation` ↔ `dispensation`,
`Audit` ↔ `audit-trail`; `Idempotency` es infraestructura genérica sin dominio.
- Modelos: `Patient`, `Prescription`, `PrescriptionItem`, `Dispensation`, `DispensationLine`, `IdempotencyKey`,
  `PatientAccessLog`, `AuditEvent`. Enums: `DocumentType`, `PrescriptionStatus`, `PatientAccessAction`, `AuditAction`.
- Acciones: `Patients\SearchPatients`, `Patients\ShowPatient`, `Prescriptions\CreatePrescription`,
  `Dispensation\PreviewDispensation`, `Dispensation\DispenseMedication`.
- Servicios: `Dispensation\FefoAllocator` (puro), `Dispensation\DispensationPlanner` (estado + pendiente + FEFO,
  puro sobre filas ya cargadas), `Dispensation\StockCandidates` (consulta con o sin bloqueo),
  `Dispensation\ControlledDrugAuthorizer`, `Idempotency\IdempotencyStore`, `Audit\AuditTrail`,
  `Audit\PatientAccessRecorder`, `Patients\PatientMasker` (puro).
- `Prescription::statusOn(CarbonImmutable $today): PrescriptionStatus`, gemelo de `Lot::isExpiredOn()` de S1.
- Sin dependencias nuevas.
- Rechazada: lógica en `Observer`/eventos de modelo o en controladores (guardas del repo). El skill
  `laravel-backend` sugiere observer o middleware para la bitácora de acceso: el repo gana (D8).

### D2. Enlace línea ↔ movimiento del kardex (pregunta 1)
`dispensation_lines.kardex_movement_id bigint NOT NULL`, `UNIQUE`, FK → `kardex_movements(id)` `RESTRICT`. Orden de
escritura en la transacción: `dispensations` → `StockLedger::apply($changes)` → líneas con el id del movimiento
devuelto. `apply()` devuelve `list<KardexMovement>` en el orden de los cambios (S2 D2 paso 4; tarea 0.1 lo confirma);
respaldo si no fuera así: emparejar por `lot_id`, único por dispensación (`UNIQUE (dispensation_id, lot_id)`,
y un producto no se repite en una prescripción).
- S2 queda intacto: ni `ALTER` de `kardex_movements` ni campo nuevo en `StockChange`. El índice único sirve la
  navegación kardex → dispensación de S6.
- Rechazada: columna `kardex_movements.dispensation_line_id`. Cambia la costura `StockChange`/`apply()` de S2 y
  añade columnas nulas por tipo en la tabla que más crece; S4 añadiría otra.
- Rechazada: FK compuesta `(kardex_movement_id, lot_id)` → `kardex_movements(id, lot_id)`. Exige un segundo índice
  único en la tabla de solo inserción para un desajuste que solo la acción puede producir; "Un movimiento por lote"
  lo prueba por HTTP. Revisar si: aparece un segundo escritor de líneas.

### D3. Asignación FEFO bajo bloqueo
`StockCandidates::for(warehouseId, productIds, lock)`: `Stock::inLockOrder()` (une `lots`, ordena por
`lots.expires_on, lots.id, stocks.warehouse_id`), `warehouse_id = ?`, `product_id IN (…)`, `quantity > 0`; con
`lock` agrega `FOR UPDATE OF stocks` (`->lock('for update of stocks')`, nunca `lockForUpdate()`, que bloquearía
también `lots`). **Una sola sentencia para todos los productos**: las filas se bloquean en el orden global de S2
sin importar el orden de los ítems en la petición (M13). Vista previa: la misma consulta sin `lock`.
- La regla de vencido vive solo en `FefoAllocator` (vía `Lot::isExpiredOn(today)` de S1: `expires_on <= hoy`
  excluido); la consulta no filtra por fecha. Así la vista previa obtiene `expired_excluded_quantity` de la misma
  lista y M1/M2 se observan también por HTTP.
- `FefoAllocator::allocate(candidates, requested, today) → Allocation{lines, available, shortage,
  expiredExcluded}`: recorre en el orden recibido (FEFO, desempate por id de lote), toma `min(pendiente, quantity)`.
- Bajo bloqueo, una fila actualizada por otra transacción se reevalúa (`quantity > 0`) al obtener el bloqueo: si
  quedó en 0 se salta y FEFO continúa con el siguiente lote (no se devuelve un 409 espurio).
- Trade-off: se bloquean todos los lotes con existencia del producto en la bodega, no solo los necesarios. Son
  pocos por producto y bodega; revisar si un producto supera ~50 lotes activos por bodega.
- Rechazada: un `SELECT … FOR UPDATE` por ítem en el orden de la petición (orden cruzado → interbloqueo; es M13).
  Rechazada: filtro de vencido en SQL además del asignador (dos sitios para una regla; la mutación de uno queda
  enmascarada por el otro). Rechazada: `SKIP LOCKED` (convierte espera en faltante falso).

### D4. Precedencia de rechazos en `POST /api/dispensations` (pregunta 3)

| # | Paso | Rechazo | Dónde |
|---|---|---|---|
| 1 | CSRF (origen SPA), sesión | 419 / 401 | S1 D2 |
| 2 | `dispensations.create` | 403 `forbidden` | middleware `can:create,Dispensation` |
| 3 | formato de `Idempotency-Key` | 422 `invalid_idempotency_key` | `RequireIdempotencyKey`, en la lista de prioridad justo después de `Authorize` |
| 4 | reglas del FormRequest | 422 `validation_failed` | `StoreDispensationRequest` |
| 5 | repetición rápida (lectura sin bloqueo) | — repite 2xx guardado / 422 `idempotency_key_reused` | `IdempotencyStore::find` |
| 6 | autorizador, si algún ítem es controlado | 422 `authorization_required` → 422 `authorizer_must_differ` → 429 `too_many_attempts` → 422 `invalid_authorizer` | `ControlledDrugAuthorizer` |
| 7 | transacción: `pg_advisory_xact_lock(usuario, clave)` y relectura | repite / 422 `idempotency_key_reused` | `IdempotencyStore` |
| 8 | ítems de la prescripción `FOR UPDATE ORDER BY id`; estado | 422 `prescription_exhausted` / `prescription_expired` | `DispensationPlanner` |
| 9 | cantidad ≤ pendiente por ítem | 422 `exceeds_prescription` | `DispensationPlanner` |
| 10 | `is_controlled` releído; controlado sin autorizador verificado | 422 `authorization_required` | `DispenseMedication` |
| 11 | existencias bloqueadas (D3), FEFO de todos los ítems | 409 `insufficient_stock` + `shortages` de todos los ítems | `DispensationPlanner` |
| 12 | escrituras | 201 | `DispenseMedication` |

- **Repetición después de validar (5 tras 4).** La huella se calcula sobre `validated()` canónico, así que campos
  ajenos (`dispensed_by`) no la alteran. La validación es determinista para el mismo cuerpo: ningún id
  referenciado se borra (FK `RESTRICT`, sin endpoints de borrado). Rechazada: repetir antes de validar (huella del
  cuerpo crudo: orden de claves, espacios o campos extra darían `idempotency_key_reused` falso).
- **Repetición antes del autorizador (5 antes de 6).** Un reintento no vuelve a verificar credenciales ni gasta
  intentos del limitador, y "Reintento después de agotar la prescripción" devuelve el 201 original.
- **Autorizador fuera de la transacción y antes de las reglas de prescripción (6 antes de 8).** bcrypt (decenas a
  cientos de ms) no corre con filas bloqueadas; la fila `controlled_drug.authorization_failed` y el golpe del
  limitador se confirman sin segunda conexión. Coste aceptado: una prescripción `vencida` con autorizador inválido
  responde `invalid_authorizer`, no `prescription_expired` (ambos 422, nada cambia). Rechazada: verificar dentro de
  la transacción tras el paso 9 (bcrypt bajo bloqueo; la fila de fallo se revertiría con el `ROLLBACK`).
- **Relectura bajo candado (7).** Dos reintentos simultáneos pasan el paso 5 vacíos; el candado de asesoramiento
  transaccional serializa por `(usuario, clave)` y el segundo, al entrar, lee el registro confirmado → repite.
  Sin candado, el segundo esperaría las filas del primero y luego respondería `exceeds_prescription` o duplicaría.
- **Estado antes que pendiente (8 antes de 9)**, `agotada` sobre `vencida` (spec). Consecuencia para las pruebas:
  "Carrera por el pendiente" necesita un segundo ítem con pendiente > 0 en la prescripción; si no, el perdedor ve
  `agotada` y responde `prescription_exhausted`.
- **Paso 10**: `is_controlled` es editable (S1 `PATCH /api/products`); se relee dentro de la transacción para cerrar
  la ventana entre el paso 6 y el bloqueo. La fila de producto no se bloquea (cambio concurrente en el mismo
  milisegundo: aceptado).
- `can:` y `RequireIdempotencyKey` son middleware de ruta; `$middleware->appendToPriorityList(Authorize::class,
  RequireIdempotencyKey::class)` fija que 403 precede al 422 de la clave.
- Vista previa: pasos 1, 2, 4, 8, 9 y FEFO sin bloqueo; faltante como dato (`fulfillable: false`), no 409.
- Pacientes: 401 → 403 (Policy en el FormRequest, **antes** de buscar el modelo) → 422 → 404. La ruta
  `GET /api/patients/{patient}` no usa enlace implícito de modelo (`whereNumber`, `int $patient`, `findOrFail` en
  la acción): con enlace, S1 D2 daría 404 antes que 403 y un `admin` podría sondear qué ids existen.

### D5. Idempotencia (RN-09)
Tabla `idempotency_keys`, alcance **por usuario** (`UNIQUE (user_id, key)`): global haría que la clave de un
usuario rechace la de otro (`idempotency_key_reused` ajeno) y abriría la búsqueda entre usuarios.
- Huella: `sha256` de JSON canónico (claves ordenadas recursivamente, listas en su orden) de `method`, patrón de
  ruta y `validated()` **sin** `authorizer_email` ni `authorizer_password`. Incluir la ruta permite que S4 reutilice
  la tabla sin colisión semántica.
- Se guarda la respuesta exitosa: `response_status` y `response_body` como **`text`**, no `jsonb` (jsonb reordena
  claves; la spec pide el mismo cuerpo). La primera respuesta se emite desde ese mismo texto: original y
  repeticiones son idénticas byte a byte. El cuerpo solo lleva ids, lotes y cantidades (sin datos de paciente).
- Inserción del registro: último paso de la transacción de la dispensación. Un rechazo o un fallo revierte todo:
  la clave no se consume.
- Candado: `pg_advisory_xact_lock(hashtextextended('idempotency:' || user_id || ':' || key, 0))`. Se toma primero
  en la transacción; una colisión de hash solo serializa, nunca interbloquea (es el primer candado tomado).
- Repetición: `Idempotent-Replayed: true`, estado y cuerpo guardados; sin bitácora ni movimientos nuevos.
- Rechazada: caché de Laravel (no transaccional con la dispensación). Rechazada: fila "pendiente" insertada al
  inicio y actualizada al final (columnas nulas, estados intermedios; el candado da lo mismo con fila completa).
  Rechazada: clave en `dispensations` y re-render del recurso (el cuerpo podría cambiar con el tiempo).

### D6. Coautorización RN-05 sin guardar la contraseña
`ControlledDrugAuthorizer::verify(User $dispenser, ?string $email, ?string $password, int $prescriptionId,
int $warehouseId): User`, solo si algún ítem es controlado (si no, se ignoran los campos: "Producto no controlado con
datos de autorizador").
1. Falta correo o contraseña → `AuthorizationRequired`.
2. Correo normalizado (trim + minúsculas, como S1 D3) igual al del dispensador → `AuthorizerMustDiffer` (sin
   verificar contraseña ni tocar el limitador).
3. Limitador `'controlled-authorizer|'.$dispenserId.'|'.sha1($email)`, 5 en 60 s →
   `TooManyAuthorizerAttempts` (429 + `Retry-After`).
4. Usuario por correo; si no existe, `Hash::check` contra el hash ficticio de S1 D3 (mismo tiempo). Falla si la
   contraseña no coincide **o** `! $user->role->allows('controlled_drugs.authorize')`. Fallo → `hit(60)`, fila
   `controlled_drug.authorization_failed` (actor dispensador, sujeto la prescripción, `details {warehouse_id}`),
   `InvalidAuthorizer` con un único cuerpo. Éxito → `clear`.
- La comparación con hash ficticio se reutiliza de S1; si vive dentro de `LoginAction`, se extrae a
  `Identity\CredentialVerifier` sin cambiar comportamiento (pruebas de S1 lo cubren).
- La contraseña no entra en huella, `details`, log (S0 nunca registra cuerpos), mensajes de validación (sin
  `:input`) ni `$dontFlash` (se añade `authorizer_password`). En base, `dispensations_authorizer_differs`.
- Rechazada: flujo pendiente→autorizado o pre-autorización con token (proposal § Assumptions 1).

### D7. Enmascarado del auditor en el recurso
`PatientPolicy::viewIdentifiable(User)`: **lista de permitidos** (`medico`, `auxiliar_farmacia`,
`regente_farmacia`); todo otro rol con `patients.view` ve enmascarado. `PatientResource` construye su arreglo desde
`PatientMasker::mask()` cuando la Policy niega: el modelo crudo nunca se serializa para el auditor. Reglas exactas de
la spec (documento salvo 3 últimos, inicial + `***` por palabra con `mb_substr`, teléfono salvo 2 últimos, fecha
`null`); prescripciones e ítems completos.
- Rechazada: capacidad nueva `patients.view_unmasked` en el mapa de S1 (cambia escenarios "son exactamente…" de
  `identity-access`). Rechazada: `role !== auditor` (un rol futuro vería en claro por defecto). Rechazada:
  enmascarar en la SPA (prohibido por RN-10).

### D8. Bitácora de acceso: en la acción, antes de responder
`SearchPatients`/`ShowPatient` consultan y luego llaman `PatientAccessRecorder::record(user, patientIds, action,
routePattern)` (una sentencia `INSERT` multi-fila; `correlation_id` de `Context`). El controlador solo envuelve el
resultado en el recurso. Si el `INSERT` falla, la excepción sube y el manejador responde 500 `server_error`: falla
cerrado porque los datos aún no se serializaron. Búsqueda vacía → ninguna fila.
- Rechazada: middleware terminable o evento `retrieved` (escribe tras enviar = falla abierto; `retrieved` dispara
  en lecturas internas). Rechazada: transacción envolvente (no aporta: si la escritura falla no hay respuesta).
- Búsqueda: `document_number LIKE :q || '%'` o `full_name ILIKE '%' || :q || '%'` con `%`, `_` y `\` escapados;
  orden `full_name, id`; `LIMIT 20`. `ILIKE` sobre `É/é` depende de `LC_CTYPE` UTF-8 de la imagen `postgres:16`
  (la misma en compose y CI); el escenario "SINTÉTICA" lo fija.

### D9. Logs: patrón de ruta y excepciones sin mensaje (modifica S0 D6)
- `AssignCorrelationId::terminate()`: `path` = `'/'.ltrim($request->route()?->uri(), '/')`; sin ruta, o ruta
  `isFallback`, → `unmatched`. Rutas sin parámetros (`/ready`) no cambian.
- `App\Logging\RedactExceptionProcessor` (procesador Monolog instalado por `JsonLineTap`, por tanto en todo log
  del canal): si `context.exception` es `Throwable`, sustituye el mensaje del registro por la clase y el contexto
  por `{class, code, file, line, constraint?}`; `code` = SQLSTATE en `QueryException`; `constraint` = nombre
  extraído por regex `constraint "([a-z0-9_]+)"` (identificador, nunca el `DETAIL` con valores). Sin traza, sin
  mensaje, sin `previous`. Cubre bindings de SQL, mensajes con nombre/documento y `ModelNotFoundException`.
- Rechazada: redactar por patrones (dígitos, nombres) dentro del mensaje: los nombres no se detectan. Coste: un 500
  se depura con clase + archivo:línea + SQLSTATE + restricción + `correlation_id`.
- Cierra el riesgo 2 de `add-project-skeleton/design.md`. La spec `service-health` de S0 no cambia de escenario
  (`/ready` sin parámetros); delta de una fila al archivar S0 si el auditor lo exige (journal del spec-engineer).

### D10. Pruebas de carrera (preguntas 2 y 4)
- **Limpieza = S2 D7 sin cambios.** `DatabaseMigrations` por prueba de carrera (migrar al entrar, `rollback` al
  salir). `down()` hace `DROP TABLE`/`DROP TRIGGER`, que los triggers de solo inserción no interceptan; `TRUNCATE`
  sigue rechazado. `DatabaseTruncation` sigue prohibido. Filas dedicadas por iteración (bodega, lotes,
  prescripciones propias).
- **Workers**: `RaceRunner` de S2 con payload de `POST /api/dispensations` (usuario, cabecera de clave, cuerpo).
- **Barrera de tabla (S2 D8)** — `LOCK TABLE kardex_movements IN SHARE MODE` — para "Carrera por la última unidad",
  "Carrera por el pendiente", "Reintentos simultáneos" y el escenario M3 (ver Riesgo 1): el primero espera en el
  `INSERT` del kardex con sus filas tomadas; el segundo espera en el candado que el diseño correcto le impone
  (fila de existencia, ítem o candado de asesoramiento). Ambos visibles en `pg_stat_activity` → se libera.
- **Barrera de filas para M13 (orden cruzado)**: la conexión del padre abre transacción y toma `SELECT … FROM stocks
  WHERE id IN (a, b) FOR UPDATE`. Correcto: ambos workers esperan la primera fila del orden global; al liberar, uno
  toma ambas y el otro espera → dos 201. Mutante (bloqueo por ítem en orden de la petición): el worker A·B espera
  `a`, el B·A espera `b`; al liberar cada uno obtiene la suya (el primer esperante retiene el candado de tupla, así
  que el otro no se adelanta) y pide la del otro → interbloqueo detectado → `40P01` → 500. Falla en cada
  iteración, no "a veces". Con la barrera de tabla el mutante pasaría cuando un worker toma ambas filas antes de que
  el otro arranque.
- **N = 10 iteraciones por prueba**, una migración por prueba; mutantes deben fallar 10/10.
- **Fallos forzados sin costura en producción**: DDL dentro de la transacción de `RefreshDatabase` (trigger
  temporal `BEFORE INSERT` que lanza para el segundo `lot_id` en `dispensation_lines`; columna renombrada para la
  búsqueda que falla con el documento en los bindings; trigger temporal en `patient_access_logs`). El `ROLLBACK`
  de la prueba lo deshace.

## API contract

Rechazos con la forma de S1 D5; `insufficient_stock` añade `shortages`. CSRF en escrituras desde el origen de la SPA.

| Método y ruta | Sesión / CSRF | Petición | Respuestas | Prueba HTTP real |
|---|---|---|---|---|
| `GET /api/patients` | sí / no | `q` 3–50 | 200 `{data:[{id,document_type,document_number,full_name,birth_date,phone,masked}]}` ≤ 20; 401; 403; 422 | 5.1 |
| `GET /api/patients/{patient}` | sí / no | `patient` numérico | 200 `{data:{…paciente, prescriptions:[{id,status,valid_until,created_at,prescriber{id,name},items:[{id,product{id,code,name,is_controlled},prescribed_quantity,dispensed_quantity,pending_quantity}]}]}}`; 401; 403; 404; 500 | 5.2 |
| `POST /api/prescriptions` | sí / sí | `patient_id`, `valid_until` (≥ hoy Bogotá), `items[1..20]{product_id distinto, quantity 1..1 000 000}` | 201 `{data: prescripción con prescriber e ítems}`; 401; 403; 419; 422 | 5.4 |
| `POST /api/dispensations/preview` | sí / sí | `prescription_id`, `warehouse_id`, `items[1..20]{prescription_item_id distinto y de esa prescripción, quantity 1..1 000 000}` | 200 `{data:{prescription_id,warehouse_id,requires_authorization,fulfillable,items:[{prescription_item_id,product_id,requested,available,shortage,expired_excluded_quantity,requires_authorization,allocations:[{lot_id,lot_code,expires_on,quantity}]}]}}`; 401; 403; 419; 422 `validation_failed`/`prescription_expired`/`prescription_exhausted`/`exceeds_prescription` | 5.5 |
| `POST /api/dispensations` | sí / sí | cabecera `Idempotency-Key` `[A-Za-z0-9_-]{16,128}`; cuerpo de preview + `authorizer_email?`, `authorizer_password?` | 201 `{data:{id,prescription_id,patient_id,warehouse_id,dispensed_by,authorized_by,created_at,lines:[{id,prescription_item_id,product_id,lot_id,lot_code,expires_on,quantity,kardex_movement_id}]}}` (+ `Idempotent-Replayed: true` al repetir); 401; 403; 409 `insufficient_stock`+`shortages[{prescription_item_id,product_id,requested,available}]`; 419; 422 (D4); 429 `too_many_attempts`+`Retry-After`; 500 | 5.6, 5.7, 5.8, 5.9, 5.10 |

Excepciones nuevas (render en `bootstrap/app.php`, mensajes en `lang/es/errors.php`, sin valores enviados):
`PrescriptionExpired`, `PrescriptionExhausted`, `ExceedsPrescription`, `AuthorizationRequired`,
`AuthorizerMustDiffer`, `InvalidAuthorizer` (422), `TooManyAuthorizerAttempts` (429 `too_many_attempts`),
`InvalidIdempotencyKey`, `IdempotencyKeyReused` (422). `InsufficientStock` de S2 gana `shortages` opcional (el 409
del propio libro, que sería un defecto, sale sin ella).

## Data impact

Siete migraciones posteriores a S2, todas con `down()` y restricciones con nombre explícito (las mutaciones las
quitan por nombre). Ids `id()` como S1/S2; fechas `timestampTz`; cantidades `integer`.

| # | Migración | Contenido | `down()` |
|---|---|---|---|
| 1 | `create_patients_table` | `document_type varchar(3)` + `patients_document_type_check IN ('CC','TI','CE','PA','RC')`; `document_number varchar(20)`; `full_name varchar(150)`; `birth_date date NULL`; `phone varchar(20) NULL`; NOT NULL + `CHECK (btrim(x) <> '')` en número y nombre; `patients_document_unique (document_type, document_number)` | `DROP TABLE` |
| 2 | `create_prescriptions_table` | `patient_id` FK `RESTRICT`; `prescriber_id` FK `users` `RESTRICT`; `valid_until date NOT NULL`; `prescriptions_id_patient_unique (id, patient_id)`; índices `(patient_id, created_at)`, `(prescriber_id)` | `DROP TABLE` |
| 3 | `create_prescription_items_table` | `prescription_id`, `product_id` FK `RESTRICT`; `prescribed_quantity`, `dispensed_quantity DEFAULT 0`; `prescription_items_prescribed_positive CHECK (prescribed_quantity >= 1)`; `prescription_items_dispensed_range CHECK (dispensed_quantity BETWEEN 0 AND prescribed_quantity)`; `prescription_items_prescription_product_unique`; `prescription_items_id_prescription_product_unique (id, prescription_id, product_id)`; índice `(product_id)` | `DROP TABLE` |
| 4 | `create_dispensations_table` | `prescription_id`, `patient_id`, `warehouse_id`, `dispensed_by`, `authorized_by NULL`; `created_at timestamptz DEFAULT now()` (sin `updated_at`); FK `dispensations_prescription_patient_foreign (prescription_id, patient_id) → prescriptions(id, patient_id)`; `dispensations_authorizer_differs CHECK (authorized_by IS NULL OR authorized_by <> dispensed_by)`; `dispensations_id_prescription_unique (id, prescription_id)`; índices de FK | `DROP TABLE` |
| 5 | `create_dispensation_lines_table` | `dispensation_id`, `prescription_id`, `prescription_item_id`, `product_id`, `lot_id`, `quantity`, `kardex_movement_id`; `dispensation_lines_quantity_positive CHECK (quantity > 0)`; FK `(dispensation_id, prescription_id) → dispensations(id, prescription_id)`; FK `(prescription_item_id, prescription_id, product_id) → prescription_items(id, prescription_id, product_id)`; FK `(lot_id, product_id) → lots(id, product_id)` (único de S2); FK `kardex_movement_id → kardex_movements(id)`; `dispensation_lines_kardex_movement_unique`; `dispensation_lines_dispensation_lot_unique`; índices `(prescription_item_id)`, `(lot_id)` | `DROP TABLE` |
| 6 | `create_idempotency_keys_table` | `user_id` FK `RESTRICT`; `key varchar(128)` + `CHECK (key ~ '^[A-Za-z0-9_-]{16,128}$')`; `request_hash varchar(64)` + `CHECK (~ '^[0-9a-f]{64}$')`; `response_status smallint CHECK (BETWEEN 200 AND 299)`; `response_body text NOT NULL`; `created_at DEFAULT now()`; `idempotency_keys_user_key_unique (user_id, key)` | `DROP TABLE` |
| 7 | `create_audit_tables` | `patient_access_logs` (`user_id`, `patient_id` FK `RESTRICT`; `action` CHECK `view`/`search`; `route varchar(255)`; `correlation_id varchar(128)`; `created_at DEFAULT now()`; índices `(patient_id, created_at)`, `(user_id, created_at)`). `audit_events` (`actor_id` FK `RESTRICT`; `action` CHECK 4 literales; `subject_type` CHECK `prescription`/`dispensation`; `subject_id bigint`; `audit_events_action_subject_check` empareja acción y tipo; `details jsonb NOT NULL DEFAULT '{}'` + `audit_events_details_ids_only CHECK (jsonb_typeof(details) = 'object' AND NOT jsonb_path_exists(details, 'strict $.* ? (@.type() != "number")'))`; `correlation_id`; `created_at`; índices `(subject_type, subject_id)`, `(actor_id, created_at)`). Función `reject_append_only_mutation()` y triggers `patient_access_logs_append_only`, `audit_events_append_only` `BEFORE UPDATE OR DELETE OR TRUNCATE FOR EACH STATEMENT`, `ENABLE ALWAYS` (S2 D5) | `DROP TRIGGER` ×2, `DROP FUNCTION`, `DROP TABLE` ×2 |

- Saldo guardado (`dispensed_quantity`) y actualizado con `SET dispensed_quantity = dispensed_quantity + :q`
  (relativo): el `CHECK` respalda RN-04 aun si el bloqueo faltara (M10 da 500, no sobre-dispensa silenciosa).
  Rechazado: saldo calculado como `SUM` de líneas (no admite `CHECK`).
- `audit_events_details_ids_only` hace imposible guardar un correo o nombre en el detalle; el CHECK es `IMMUTABLE`
  (`jsonb_path_exists` lo es; las variantes `_tz` no).
- Sin índice para la búsqueda por nombre/prefijo (3 pacientes semilla). Revisar si: > 10⁴ pacientes →
  `varchar_pattern_ops` en documento y `pg_trgm` GIN en `lower(full_name)`.
- Orden de bloqueo global: candado de asesoramiento `(usuario, clave)` → `prescription_items` de una prescripción
  por `id` → `stocks` por `(lots.expires_on, lots.id, warehouse_id)`. S2 (ajustes) y S4 (traslados) solo toman
  `stocks` con la misma clave: sin ciclos.

## Risks / Trade-offs

1. [Mutante equivalente: M3 tal como está en el borrador ("quitar el bloqueo de existencias") **no** hace fallar
   "Carrera por la última unidad": `StockLedger::apply()` vuelve a bloquear y revalida el saldo, así que el
   perdedor igual responde 409] → el bloqueo propio de S3 garantiza FEFO fresco, no saldo ≥ 0. Requiere un
   escenario que lo observe (propuesto en journal: "Carrera con lote siguiente disponible", ambas 201, L1 0 y L2 0;
   el mutante da 409). **Bloquea GATE 1 hasta que el spec-engineer decida** (sin el escenario, M3 no tiene objetivo).
2. [Datos de paciente o credenciales filtrados por logs, mensajes de excepción, cuerpos guardados o bitácoras] →
   D9 a nivel Monolog (no por llamada), D5 cuerpo solo con ids, D6 contraseña fuera de huella/detalle/flash,
   `audit_events_details_ids_only`, barridos 4.2/4.4/5.14 con control positivo, M12/M14. Coste: depurar un 500 sin
   mensaje.
3. [Pruebas de carrera lentas o con falso verde: barrera mal elegida, fixture que cambia el código de rechazo] →
   barrera de filas solo para M13 y de tabla para el resto (D10), segundo ítem pendiente en "Carrera por el
   pendiente" (D4), N = 10 y mutantes 10/10; una migración por prueba (cuatro ciclos `migrate`/`rollback`).

Menores: bloqueo de todos los lotes candidatos (D3); limitador por dispensador + correo, no global (varios
dispensadores reparten intentos; cada fallo queda en bitácora); `ILIKE` dependiente de la configuración regional de
la imagen de base.

## Migration Plan

Requisitos previos: S1 y S2 archivados (FK a `lots(id, product_id)` y `kardex_movements`). Arranque: `migrate
--force` → `db:seed --force` con `PatientSeeder` y `PrescriptionSeeder` tras `UserSeeder` y `ProductSeeder`
(idempotentes: `firstOrCreate` por tipo + número; una prescripción del médico semilla por paciente si no existe,
sin tocar saldos). Reversión: revertir commits + `migrate:rollback --step=7`; la prueba de carrera ejecuta todos los
`down()` en cada corrida.

## Open Questions

- Nombre exacto del scope (`inLockOrder`) y orden de retorno de `apply()`: se confirman en 0.1; si difieren, D2/D3
  usan el respaldo descrito sin cambiar specs ni tareas.
