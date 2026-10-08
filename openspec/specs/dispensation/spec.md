# dispensation Specification

## Purpose
Entrega medicamentos a un paciente contra una prescripción vigente, consumiendo lotes no vencidos de una
bodega en orden FEFO, de forma atómica, segura ante concurrencia e idempotente, con la coautorización de un
segundo regente para los productos de control especial (RN-01..RN-05, RN-09).

## Requirements

### Requirement: Vista previa de asignación FEFO
`POST /api/dispensations/preview` con `prescription_id`, `warehouse_id` e `items` (`prescription_item_id`,
`quantity`) SHALL devolver por ítem los lotes que la dispensación tomaría hoy por FEFO, lo disponible, el
faltante, la cantidad vencida excluida y si exige autorización, sin cambiar existencias, kardex ni saldos y
sin bloquear filas (RN-01, RN-02, RN-05).

#### Scenario: Vista previa en orden FEFO
- **WHEN** un `auxiliar_farmacia` pide la vista previa de 5 unidades de un producto que en la bodega tiene el lote L1 (vence en 10 días, 3 unidades) y L2 (vence en 40 días, 10 unidades)
- **THEN** la API responde HTTP 200 con las asignaciones L1:3 y L2:2 en ese orden, `shortage` 0 y `fulfillable` `true`, y existencias, kardex y saldos de la prescripción no cambian [ancla: ruta `POST /api/dispensations/preview`, archivo:línea al aplicar]

#### Scenario: Faltante visible sin error
- **WHEN** se pide la vista previa de 20 unidades y la bodega solo tiene 13 no vencidas de ese producto
- **THEN** la API responde HTTP 200 con las asignaciones posibles, `available` 13, `shortage` 7 y `fulfillable` `false` [ancla: ruta `POST /api/dispensations/preview`, archivo:línea al aplicar]

#### Scenario: Lote vencido excluido y contado
- **WHEN** se pide la vista previa y la bodega tiene además un lote vencido con 5 unidades de ese producto
- **THEN** la API responde HTTP 200 sin ese lote en las asignaciones y con `expired_excluded_quantity` 5 en el ítem [ancla: ruta `POST /api/dispensations/preview`, archivo:línea al aplicar]

#### Scenario: Ítem de control especial señalado
- **WHEN** se pide la vista previa de una prescripción con un ítem cuyo producto tiene `is_controlled` `true`
- **THEN** la API responde HTTP 200 con `requires_authorization` `true` en ese ítem y en la respuesta [ancla: ruta `POST /api/dispensations/preview`, archivo:línea al aplicar]

#### Scenario: Prescripción no dispensable
- **WHEN** se pide la vista previa de una prescripción `vencida`, o de una `agotada`
- **THEN** la API responde HTTP 422 con `code` `prescription_expired`, o `prescription_exhausted`, respectivamente [ancla: servicio de dispensación + render de rechazos de dispensación, archivo:línea al aplicar]

#### Scenario: Datos inválidos de la vista previa
- **WHEN** se envía sin `warehouse_id`, o con `quantity` 0, o con un `prescription_item_id` de otra prescripción, o con un `prescription_id` o `warehouse_id` inexistente
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en el campo afectado [ancla: FormRequest de dispensación, archivo:línea al aplicar]

#### Scenario: Rol sin permiso de dispensar
- **WHEN** un usuario de cada rol `medico`, `auditor` y `admin` pide la vista previa con datos válidos
- **THEN** la API responde HTTP 403 con `code` `forbidden` en los 3 casos [ancla: Policy de dispensaciones, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión pide la vista previa
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

### Requirement: Asignación FEFO en la dispensación
`POST /api/dispensations` SHALL consumir, por ítem, de los lotes del producto con existencia > 0 en la bodega
indicada y no vencidos (regla de `catalog`), por `expires_on` ascendente y, a igual fecha, por lote de menor
`id`, tomando de varios lotes si hace falta. Si algún ítem no alcanza, SHALL rechazarse completa sin efecto
(RN-01, RN-02).

#### Scenario: Consumo en orden FEFO entre varios lotes
- **WHEN** un `auxiliar_farmacia` dispensa 5 unidades y la bodega tiene L1 (vence en 10 días, 3 u.), L2 (vence en 40 días, 10 u.) y L3 (vence en 90 días, 10 u.)
- **THEN** la API responde HTTP 201 con líneas L1:3 y L2:2, y las existencias quedan L1 0, L2 8 y L3 10 [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Empate de vencimiento
- **WHEN** se dispensan 4 unidades y dos lotes con el mismo `expires_on` tienen 3 unidades cada uno
- **THEN** la API responde HTTP 201 tomando 3 del lote de menor `id` y 1 del otro [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Lote vencido nunca seleccionado
- **WHEN** se dispensan 2 unidades y la bodega tiene L0 vencido ayer con 50 unidades y L1 que vence en 10 días con 2 unidades
- **THEN** la API responde HTTP 201 con una sola línea L1:2, y L0 sigue con 50 unidades y sin movimiento nuevo [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Lote que vence hoy excluido
- **WHEN** se dispensa 1 unidad y el único lote con existencia en la bodega vence hoy en `America/Bogota`
- **THEN** la API responde HTTP 409 con `code` `insufficient_stock`, sin cambiar existencias ni kardex [ancla: servicio de dispensación + render de InsufficientStock, archivo:línea al aplicar]

#### Scenario: Otra bodega no se toca
- **WHEN** se dispensa desde Farmacia Urgencias y el lote que vence primero solo tiene existencia en Farmacia Central
- **THEN** la API responde HTTP 201 con líneas solo de lotes de Farmacia Urgencias, y la existencia de Farmacia Central no cambia [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Stock insuficiente, todo o nada
- **WHEN** se dispensan dos ítems, el primero con existencia suficiente y el segundo pidiendo 8 con solo 5 disponibles no vencidas
- **THEN** la API responde HTTP 409 con `code` `insufficient_stock` y `shortages` con el ítem, pedido 8 y disponible 5; no se crea dispensación y no cambian existencias, kardex ni saldos de ningún ítem [ancla: servicio de dispensación + render de InsufficientStock, archivo:línea al aplicar]

### Requirement: Dispensación contra prescripción vigente
Toda dispensación SHALL referirse a una prescripción `vigente` y quedar asociada a su paciente. La cantidad
de cada ítem SHALL ser ≤ su pendiente; las parciales SHALL acumularse en el saldo del ítem. Una solicitud
mal formada SHALL rechazarse sin efecto (RN-04).

#### Scenario: Dispensación parcial exitosa
- **WHEN** un `auxiliar_farmacia` dispensa 4 de un ítem de 10 de una prescripción `vigente`
- **THEN** la API responde HTTP 201 con `id`, `prescription_id`, `patient_id`, `warehouse_id`, `dispensed_by` igual al usuario, `authorized_by` `null` y sus líneas, y el ítem queda con pendiente 6 [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Parcial que completa la prescripción
- **WHEN** después se dispensan las 6 pendientes de ese ítem, único de la prescripción
- **THEN** la API responde HTTP 201 y la prescripción pasa a `agotada` [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Prescripción agotada
- **WHEN** se intenta dispensar 1 unidad más de esa prescripción con una clave nueva
- **THEN** la API responde HTTP 422 con `code` `prescription_exhausted` y nada cambia [ancla: servicio de dispensación + render de rechazos de dispensación, archivo:línea al aplicar]

#### Scenario: Prescripción vencida
- **WHEN** se dispensa de una prescripción con pendiente > 0 cuyo `valid_until` fue ayer
- **THEN** la API responde HTTP 422 con `code` `prescription_expired` y nada cambia [ancla: servicio de dispensación + render de rechazos de dispensación, archivo:línea al aplicar]

#### Scenario: Más de lo pendiente
- **WHEN** se piden 7 unidades de un ítem con pendiente 6
- **THEN** la API responde HTTP 422 con `code` `exceeds_prescription` y nada cambia [ancla: servicio de dispensación + render de rechazos de dispensación, archivo:línea al aplicar]

#### Scenario: Carrera por el pendiente de la prescripción
- **WHEN** dos peticiones simultáneas, en conexiones distintas y con claves distintas, piden cada una las 5 unidades pendientes del mismo ítem con existencia de sobra
- **THEN** una responde HTTP 201 y la otra HTTP 422 `exceeds_prescription` (nunca 500), y el ítem queda con dispensada igual a la prescrita [ancla: servicio de dispensación + render de rechazos de dispensación, archivo:línea al aplicar]

#### Scenario: Solicitud mal formada
- **WHEN** se envía sin `items`, o con `items` vacío, con `quantity` 0 o no entera, con un `prescription_item_id` repetido o de otra prescripción, o con `prescription_id` o `warehouse_id` inexistentes
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors` en el campo afectado, y nada cambia [ancla: FormRequest de dispensación, archivo:línea al aplicar]

### Requirement: Acceso a la dispensación
`POST /api/dispensations` SHALL exigir sesión, token CSRF desde la SPA y `dispensations.create`
(`auxiliar_farmacia`, `regente_farmacia`); el dispensador SHALL ser siempre el usuario autenticado (RN-04).

#### Scenario: Rol sin permiso de dispensar
- **WHEN** un usuario de cada rol `medico`, `auditor` y `admin` envía `POST /api/dispensations` con datos válidos y clave nueva
- **THEN** la API responde HTTP 403 con `code` `forbidden` en los 3 casos y no cambian existencias ni kardex [ancla: Policy de dispensaciones, archivo:línea al aplicar]

#### Scenario: Sin sesión
- **WHEN** un cliente sin sesión envía `POST /api/dispensations`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` y nada cambia [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

#### Scenario: Sin token CSRF
- **WHEN** un `auxiliar_farmacia` con sesión envía `POST /api/dispensations` desde el origen de la SPA sin `X-XSRF-TOKEN`
- **THEN** la API responde HTTP 419 con `code` `csrf_token_mismatch` y nada cambia [ancla: middleware CSRF, archivo:línea al aplicar]

#### Scenario: Dispensador enviado por el cliente ignorado
- **WHEN** un `auxiliar_farmacia` envía la dispensación con `dispensed_by` de otro usuario en el cuerpo
- **THEN** la API responde HTTP 201 con `dispensed_by` igual al usuario autenticado [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

### Requirement: Dispensación atómica y segura ante concurrencia
Cada dispensación SHALL ejecutarse en una sola transacción que bloquea los ítems de prescripción y las
existencias que toca en un orden determinista, de modo que peticiones simultáneas nunca dejen stock negativo,
nunca dispensen de más y nunca respondan 500 por bloqueo mutuo; un fallo a mitad SHALL revertirlo todo (RN-03).

#### Scenario: Carrera por la última unidad
- **WHEN** dos peticiones simultáneas, en conexiones reales distintas, de prescripciones distintas y con claves distintas, piden cada una 1 unidad de un producto cuya única existencia en la bodega es 1
- **THEN** exactamente una responde HTTP 201 y la otra HTTP 409 `insufficient_stock` (nunca 500), la existencia queda en 0 y hay exactamente un movimiento `salida_dispensacion` nuevo [ancla: servicio de dispensación + render de InsufficientStock, archivo:línea al aplicar]

#### Scenario: Carrera con lote siguiente disponible
- **WHEN** dos peticiones simultáneas, en conexiones reales distintas, de prescripciones distintas y con claves distintas, piden cada una 1 unidad de un producto que en la bodega tiene L1 (vence antes, 1 u.) y L2 (vence después, 1 u.)
- **THEN** ambas responden HTTP 201 (nunca 409 ni 500), una con línea L1:1 y la otra con línea L2:1, L1 y L2 quedan en 0 y hay exactamente dos movimientos `salida_dispensacion` nuevos [ancla: consulta FEFO con bloqueo del servicio de dispensación + ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Orden cruzado sin bloqueo mutuo
- **WHEN** dos peticiones simultáneas dispensan los productos A y B, una listando A antes que B y la otra B antes que A, con existencia suficiente para ambas
- **THEN** ambas responden HTTP 201, ninguna responde 500 y cada existencia baja exactamente la suma pedida [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Fallo a mitad de la transacción
- **WHEN** la escritura de la segunda línea de una dispensación de dos lotes falla por un error forzado en la prueba
- **THEN** la API responde HTTP 500 con `code` `server_error`, y no quedan dispensación, líneas, movimientos, cambios de existencia, cambios de saldo ni clave de idempotencia guardada [ancla: manejador de excepciones de la API, archivo:línea al aplicar]

### Requirement: Movimiento de kardex por lote consumido
Cada lote consumido SHALL escribir, en la misma transacción, exactamente un movimiento `salida_dispensacion`
con la cantidad negativa tomada, el saldo resultante y el dispensador; cada línea de la dispensación SHALL
quedar enlazada a su movimiento. Una dispensación rechazada SHALL NOT escribir movimientos (RN-06).

#### Scenario: Un movimiento por lote
- **WHEN** una dispensación exitosa toma 3 unidades de L1 (existencia 3) y 2 de L2 (existencia 10)
- **THEN** hay exactamente 2 movimientos `salida_dispensacion` nuevos: L1 con cantidad -3 y saldo 0, y L2 con -2 y saldo 8, ambos con el usuario dispensador y enlazados a las líneas de esa dispensación

#### Scenario: Rechazo sin movimientos
- **WHEN** una dispensación termina en rechazo por permisos, validación, prescripción, autorización o stock insuficiente
- **THEN** el número de movimientos del kardex no cambia

### Requirement: Idempotencia de la dispensación
`POST /api/dispensations` SHALL exigir `Idempotency-Key` (16 a 128 caracteres `[A-Za-z0-9_-]`), con alcance
por usuario. Repetir clave y cuerpo SHALL devolver el estado y cuerpo originales con
`Idempotent-Replayed: true`, sin efectos nuevos; otro cuerpo SHALL rechazarse. Solo los éxitos consumen la
clave; las credenciales del autorizador no cuentan para comparar cuerpos (RN-09).

#### Scenario: Reintento devuelve la respuesta original
- **WHEN** un `auxiliar_farmacia` repite una dispensación exitosa con la misma `Idempotency-Key` y el mismo cuerpo
- **THEN** la API responde HTTP 201 con el mismo cuerpo de la primera respuesta y la cabecera `Idempotent-Replayed: true`, y no cambian existencias, kardex, saldos ni el número de dispensaciones [ancla: ruta `POST /api/dispensations` + almacén de idempotencia, archivo:línea al aplicar]

#### Scenario: Reintento después de agotar la prescripción
- **WHEN** la primera petición dispensó todo el pendiente y el cliente la reintenta con la misma clave y cuerpo
- **THEN** la API responde HTTP 201 con la respuesta original, no `prescription_exhausted`, y sin efectos nuevos [ancla: ruta `POST /api/dispensations` + almacén de idempotencia, archivo:línea al aplicar]

#### Scenario: Misma clave con otro cuerpo
- **WHEN** el mismo usuario reutiliza la clave de una dispensación exitosa con otra cantidad
- **THEN** la API responde HTTP 422 con `code` `idempotency_key_reused` y nada cambia [ancla: almacén de idempotencia + render de rechazos de dispensación, archivo:línea al aplicar]

#### Scenario: Sin clave o clave mal formada
- **WHEN** se envía `POST /api/dispensations` sin `Idempotency-Key`, o con una de 10 caracteres, o con espacios
- **THEN** la API responde HTTP 422 con `code` `invalid_idempotency_key` y nada cambia [ancla: middleware de idempotencia, archivo:línea al aplicar]

#### Scenario: Reintentos simultáneos con la misma clave
- **WHEN** dos peticiones idénticas con la misma clave llegan a la vez en conexiones distintas
- **THEN** ambas responden HTTP 201 con el mismo `id`, existe una sola dispensación y un solo juego de movimientos [ancla: almacén de idempotencia, archivo:línea al aplicar]

#### Scenario: Un rechazo no consume la clave
- **WHEN** una petición responde HTTP 409 `insufficient_stock`, luego un ajuste repone la existencia y el cliente reintenta con la misma clave y cuerpo
- **THEN** la API responde HTTP 201 sin `Idempotent-Replayed` y crea la dispensación una sola vez [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Misma clave de otro usuario
- **WHEN** un segundo usuario con `dispensations.create` envía su propia dispensación válida con una clave que ya usó otro usuario
- **THEN** la API responde HTTP 201 con una dispensación nueva propia, sin `Idempotent-Replayed` y sin datos de la del primer usuario [ancla: almacén de idempotencia, archivo:línea al aplicar]

### Requirement: Coautorización de control especial
Si algún ítem es de un producto con `is_controlled` `true`, la petición SHALL traer `authorizer_email` y
`authorizer_password` de un usuario con `controlled_drugs.authorize` distinto del dispensador; se verifican
antes de tocar existencias y `authorized_by` queda guardado. La contraseña SHALL NOT guardarse, registrarse
ni devolverse (RN-05).

#### Scenario: Auxiliar dispensa con autorización del regente
- **WHEN** un `auxiliar_farmacia` dispensa un ítem de control especial con el correo y la contraseña correctos del `regente_farmacia`
- **THEN** la API responde HTTP 201 con `authorized_by` igual al regente, y la respuesta no contiene `authorizer_password` [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Regente dispensa con otro regente
- **WHEN** un `regente_farmacia` dispensa un ítem de control especial autorizado por otro `regente_farmacia`
- **THEN** la API responde HTTP 201 con `authorized_by` igual al segundo regente [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Falta la autorización
- **WHEN** se dispensa un ítem de control especial sin `authorizer_email` o sin `authorizer_password`
- **THEN** la API responde HTTP 422 con `code` `authorization_required` y nada cambia [ancla: servicio de autorización de control especial + render de rechazos de dispensación, archivo:línea al aplicar]

#### Scenario: Autorizador igual al dispensador
- **WHEN** un `regente_farmacia` dispensa un ítem de control especial con su propio correo y contraseña como autorizador
- **THEN** la API responde HTTP 422 con `code` `authorizer_must_differ` y nada cambia [ancla: servicio de autorización de control especial + render de rechazos de dispensación, archivo:línea al aplicar]

#### Scenario: Autorizador inválido indistinguible
- **WHEN** el autorizador es un correo inexistente, o un regente con contraseña incorrecta, o un `auxiliar_farmacia` o `medico` con su contraseña correcta
- **THEN** la API responde HTTP 422 con `code` `invalid_authorizer` y exactamente el mismo cuerpo en los tres casos, y nada cambia [ancla: servicio de autorización de control especial + render de rechazos de dispensación, archivo:línea al aplicar]

#### Scenario: Demasiados intentos fallidos de autorizador
- **WHEN** el mismo dispensador acumula 5 intentos fallidos con el mismo correo de autorizador en 60 segundos y envía un sexto, aun con la contraseña correcta
- **THEN** la API responde HTTP 429 con `code` `too_many_attempts` y cabecera `Retry-After`, y nada cambia [ancla: limitador de autorizador, archivo:línea al aplicar]

#### Scenario: Producto no controlado con datos de autorizador
- **WHEN** se dispensa solo producto no controlado y el cuerpo trae `authorizer_email` y `authorizer_password` incorrectos
- **THEN** la API responde HTTP 201 con `authorized_by` `null`, sin verificar las credenciales [ancla: ruta `POST /api/dispensations`, archivo:línea al aplicar]

#### Scenario: Contraseña del autorizador fuera de logs y rechazos
- **WHEN** se envía una dispensación de control especial con `authorizer_password` `Clave-Sintetica-123`, una vez con éxito y otra con datos inválidos
- **THEN** ningún cuerpo de respuesta, línea de log, fila de bitácora ni registro de idempotencia contiene `Clave-Sintetica-123`
