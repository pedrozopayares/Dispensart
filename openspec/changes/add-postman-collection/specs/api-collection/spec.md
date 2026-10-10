# Spec Delta — api-collection

## Purpose

Entrega al evaluador una colección de Postman ejecutable contra el stack, que resuelve la sesión Sanctum SPA,
recorre los flujos de la prueba con cuerpos de ejemplo y aserciones, y cubre cada operación del contrato
OpenAPI (parte A, § 7).

## ADDED Requirements

### Requirement: Colección y entorno versionados sin secretos
`software/docs/postman/` SHALL contener una colección Postman v2.1 y un entorno local con `baseUrl`
`http://localhost:8090`, los correos de los 5 usuarios semilla y una variable de contraseña con el valor por
defecto solo de desarrollo `dispensart-dev-only`. Cada variable SHALL poder sobrescribirse al ejecutar. SHALL
NOT contener otra credencial, token, cookie ni dato real.

#### Scenario: Colección y entorno ejecutables
- **WHEN** con el stack recién levantado se ejecuta `newman run` sobre la colección con el entorno local
- **THEN** newman carga ambos archivos sin error de formato y ejecuta todas las carpetas contra `http://localhost:8090`

#### Scenario: Contraseña sobrescrita al ejecutar
- **WHEN** el stack siembra los usuarios con otra contraseña y se ejecuta la colección con `--env-var` que fija esa contraseña
- **THEN** los logins responden HTTP 200 sin editar ningún archivo de `software/docs/postman/` [ancla: identity-access › Credenciales válidas — live spec]

#### Scenario: Contraseña equivocada
- **WHEN** se ejecuta la colección con una contraseña que no corresponde a los usuarios sembrados
- **THEN** el primer login responde HTTP 422 con `code` `invalid_credentials`, sus aserciones fallan y la corrida termina con código distinto de 0 [ancla: identity-access › Contraseña incorrecta — live spec]

#### Scenario: Sin credenciales reales
- **WHEN** se buscan en `software/docs/postman/` contraseñas, tokens, cookies de sesión y valores de `APP_KEY`
- **THEN** el único valor de contraseña es `dispensart-dev-only`, no aparece ningún token ni cookie guardados y todo correo es del dominio `dispensart.test`

### Requirement: Sesión SPA resuelta por script de colección
Un script previo a cada petición, a nivel de colección, SHALL obtener la cookie `XSRF-TOKEN` con
`GET /sanctum/csrf-cookie` cuando falte, enviar su valor decodificado en `X-XSRF-TOKEN` en cada escritura y
enviar `Origin` y `Referer` de `baseUrl`. Una petición SHALL poder excluirse de cada cabecera para documentar el
rechazo correspondiente.

#### Scenario: Primera escritura sin cookie previa
- **WHEN** la primera petición de la corrida es `POST /api/auth/login` con credenciales válidas y el tarro de cookies está vacío
- **THEN** el script obtiene la cookie con HTTP 204 antes del login y el login responde HTTP 200 con el rol del usuario [ancla: identity-access › Emisión de la cookie CSRF; identity-access › Credenciales válidas — live spec]

#### Scenario: Token con caracteres codificados
- **WHEN** la cookie `XSRF-TOKEN` contiene caracteres codificados en URL, como `%3D`
- **THEN** la cabecera `X-XSRF-TOKEN` lleva el valor decodificado y la escritura responde con su estado de éxito, no HTTP 419 [ancla: identity-access › Token CSRF para la SPA — live spec; `software/docker/smoke/auth-smoke.sh:26`]

#### Scenario: Escritura excluida del token
- **WHEN** un `medico` con sesión envía `POST /api/warehouses` con la petición excluida de `X-XSRF-TOKEN`
- **THEN** la API responde HTTP 419 con `code` `csrf_token_mismatch` y no crea la bodega [ancla: identity-access › Escritura con token CSRF caducado — live spec; `software/docker/smoke/auth-smoke.sh:96`]

#### Scenario: Login excluido de Origin y Referer
- **WHEN** se envía `POST /api/auth/login` con credenciales válidas, excluida de `Origin`, `Referer` y `X-XSRF-TOKEN`, como un cliente ajeno a la SPA
- **THEN** la API responde HTTP 403 con `code` `forbidden` y la siguiente `GET /api/auth/me` responde HTTP 401 [ancla: identity-access › Origen ajeno a la SPA — live spec; `software/docker/smoke/auth-smoke.sh:69`]

### Requirement: Sesión de cada rol
Una carpeta SHALL recorrer, para cada uno de los 5 roles semilla, login, usuario actual y cierre de sesión,
con aserciones de estado, `role` y `email`. Como newman usa un solo tarro de cookies por corrida, cada carpeta
que necesite otro rol SHALL abrir su sesión con login explícito y cerrarla al terminar.

#### Scenario: Recorrido de sesión por rol
- **WHEN** para `auxiliar`, `regente`, `medico`, `auditor` y `admin` se ejecutan login, `GET /api/auth/me` y `POST /api/auth/logout`
- **THEN** login responde HTTP 200 con el `role` esperado, `me` responde HTTP 200 con su `email` y logout responde HTTP 204 en los 5 casos [ancla: identity-access › Credenciales válidas, › Usuario autenticado, › Cierre de sesión exitoso — live spec]

#### Scenario: Usuario actual tras cerrar sesión
- **WHEN** tras el logout de cada rol se envía `GET /api/auth/me`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: identity-access › Sin sesión (Usuario actual) — live spec; `software/docker/smoke/auth-smoke.sh:103`]

### Requirement: Dispensación FEFO con repetición idempotente
Una carpeta SHALL documentar, con datos semilla y una prescripción nueva por corrida creada por el `medico`, la
vista previa FEFO, la dispensación con `Idempotency-Key` única por corrida y su repetición con la misma clave,
comprobando en el kardex un movimiento nuevo tras la primera y ninguno tras la repetición (RN-01, RN-02, RN-06,
RN-09).

#### Scenario: Vista previa en orden FEFO sin lote vencido
- **WHEN** el `auxiliar` pide la vista previa de 2 unidades de `MED-004` en `BH` sobre la prescripción de la corrida
- **THEN** la API responde HTTP 200 con `fulfillable` true, asignaciones en `expires_on` ascendente, ninguna del lote vencido `L-LOS-2401` y `expired_excluded_quantity` mayor que 0 [ancla: dispensation › Vista previa en orden FEFO; › Lote vencido excluido y contado — live spec]

#### Scenario: Dispensación con un movimiento nuevo en el kardex
- **WHEN** el `auxiliar` confirma esa dispensación con una `Idempotency-Key` única de la corrida
- **THEN** la API responde HTTP 201 y el total del kardex de esa bodega y lote crece en 1, con un movimiento `salida_dispensacion` de cantidad negativa [ancla: dispensation › Un movimiento por lote — live spec; `software/docker/smoke/dispensation-smoke.sh:140`]

#### Scenario: Repetición idempotente
- **WHEN** el `auxiliar` repite la dispensación con la misma clave y el mismo cuerpo
- **THEN** la API responde HTTP 201 con el mismo cuerpo de la primera respuesta y `Idempotent-Replayed: true`, y el total del kardex de esa bodega y lote no cambia [ancla: dispensation › Reintento devuelve la respuesta original — live spec]

#### Scenario: Dispensación sin clave
- **WHEN** el `auxiliar` envía la dispensación sin `Idempotency-Key`
- **THEN** la API responde HTTP 422 con `code` `invalid_idempotency_key` y el total del kardex no cambia [ancla: dispensation › Sin clave o clave mal formada — live spec]

#### Scenario: Corrida repetida sobre la misma base
- **WHEN** la colección se ejecuta dos veces seguidas sobre la misma base, después de los humos de dominio
- **THEN** la segunda corrida termina con código 0, porque usa otra prescripción y otras claves, sin depender de identificadores fijos

### Requirement: Control especial con coautorización
Una carpeta SHALL documentar la dispensación de `MED-006` (control especial) sobre la prescripción de la
corrida, en una bodega con existencia vigente: vista previa que exige autorización, rechazo sin autorizador y
éxito con el `regente` semilla como coautorizador (RN-05).

#### Scenario: Vista previa exige autorización
- **WHEN** el `auxiliar` pide la vista previa de 1 unidad de `MED-006`
- **THEN** la API responde HTTP 200 con `requires_authorization` true [ancla: dispensation › Ítem de control especial señalado — live spec]

#### Scenario: Control especial sin autorizador
- **WHEN** el `auxiliar` dispensa `MED-006` sin `authorizer_email` ni `authorizer_password`
- **THEN** la API responde HTTP 422 con `code` `authorization_required` [ancla: dispensation › Falta la autorización — live spec]

#### Scenario: Control especial coautorizado por el regente
- **WHEN** el `auxiliar` dispensa `MED-006` con el correo del `regente` y la contraseña del entorno
- **THEN** la API responde HTTP 201 con `authorized_by` distinto de `dispensed_by` y sin `authorizer_password` en la respuesta [ancla: dispensation › Auxiliar dispensa con autorización del regente — live spec]

### Requirement: Traslado completo con discrepancia
Una carpeta SHALL recorrer un traslado de un lote vigente con existencia en dos bodegas: creación, solicitud,
aprobación, despacho, recepción parcial, transición prohibida, resolución de la discrepancia y consulta;
además la autoaprobación del regente y su anulación (RN-07, RN-08).

#### Scenario: Recorrido hasta la recepción parcial
- **WHEN** el `auxiliar` crea y solicita un traslado de 2 unidades, el `regente` lo aprueba, el `auxiliar` lo despacha y recibe 1
- **THEN** las respuestas son HTTP 201 `BORRADOR`, HTTP 200 `SOLICITADO`, HTTP 200 `APROBADO`, HTTP 200 `EN_TRANSITO` y HTTP 200 `RECIBIDO_PARCIAL` con una discrepancia `pending` de faltante 1 [ancla: transfers › Recorrido completo; › Recepción parcial — live spec; `software/docker/smoke/transfer-smoke.sh:177`]

#### Scenario: Autoaprobación del regente
- **WHEN** el `regente` crea, solicita y aprueba su propio traslado
- **THEN** la aprobación responde HTTP 403 con `code` `segregation_of_duties`, el traslado sigue `SOLICITADO` y su anulación con motivo responde HTTP 200 `ANULADO` [ancla: transfers › Solicitante regente intenta aprobar; › Creador anula su traslado solicitado — live spec]

#### Scenario: Despachar un traslado ya recibido
- **WHEN** el `auxiliar` despacha de nuevo el traslado `RECIBIDO_PARCIAL`
- **THEN** la API responde HTTP 409 con `code` `invalid_transfer_transition` [ancla: transfers › Matriz de transiciones prohibidas — live spec]

#### Scenario: Resolución de la discrepancia
- **WHEN** el `regente` resuelve la discrepancia con `returned_to_origin` y un motivo, y luego la resuelve de nuevo
- **THEN** la primera responde HTTP 200 `resolved` y la segunda HTTP 409 con `code` `discrepancy_already_resolved` [ancla: transfers › Devolución al origen; › Discrepancia ya resuelta — live spec]

#### Scenario: Consulta del traslado
- **WHEN** el `auxiliar` consulta el detalle y el listado filtrado por `RECIBIDO_PARCIAL`
- **THEN** ambas responden HTTP 200, el detalle trae la discrepancia `resolved` y el listado incluye el traslado [ancla: transfers › Detalle con discrepancias; › Filtro por estado — live spec]

### Requirement: Alertas, existencias, kardex y ajuste
Una carpeta SHALL documentar como `regente` las alertas, la consulta de existencias y kardex, y un ajuste de
-1 sobre una existencia semilla leída antes del ajuste, con aserciones estables ante las escrituras previas de
los humos y de la propia colección (RN-01, RN-06, RN-11).

#### Scenario: Alertas con el lote vencido sembrado
- **WHEN** el `regente` envía `GET /api/alerts`
- **THEN** la API responde HTTP 200 con `expiring_lots` y `low_stock` como listas, `L-ACE-2401` con `is_expired` true y cada fila de `low_stock` con disponible menor que el mínimo [ancla: inventory-alerts › Lote ya vencido con existencia; › Producto bajo su mínimo — live spec]

#### Scenario: Ajuste con movimiento en el kardex
- **WHEN** el `regente` ajusta -1 una existencia semilla de cantidad leída `q` y consulta existencias y kardex de esa bodega y lote
- **THEN** el ajuste responde HTTP 201 con `balance_after` `q - 1`, la existencia es `q - 1` y el kardex muestra el `ajuste` con su motivo [ancla: inventory › Ajuste negativo exitoso; kardex › Movimiento de ajuste con usuario y motivo — live spec]

#### Scenario: Ajuste mayor que la existencia
- **WHEN** el `regente` ajusta una cantidad negativa mayor que la existencia
- **THEN** la API responde HTTP 409 con `code` `insufficient_stock` y la existencia no cambia [ancla: inventory › Ajuste mayor que la existencia — live spec; `software/docker/smoke/stock-smoke.sh:117`]

### Requirement: Asistente con modelo elegido
Una carpeta SHALL documentar como `regente` la lista de modelos del asistente y una pregunta con `model`
`mock`, que no depende de Ollama, más el rechazo de un modelo fuera de la lista.

#### Scenario: Lista de modelos con mock primero
- **WHEN** el `regente` envía `GET /api/assistant/models`
- **THEN** la API responde HTTP 200 con `data[0].id` `mock`, haya o no Ollama en el anfitrión [ancla: inventory-assistant › Lista de modelos disponibles — live spec; `software/docker/smoke/assistant-smoke.sh:109`]

#### Scenario: Pregunta con el modelo mock
- **WHEN** el `regente` pregunta por los traslados en tránsito con `model` `mock`
- **THEN** la API responde HTTP 200 con `data.model` `mock` [ancla: inventory-assistant › Modelo simulado sin red — live spec]

#### Scenario: Modelo fuera de la lista
- **WHEN** el `regente` pregunta con `model` `ollama:no-existe`
- **THEN** la API responde HTTP 422 con `errors.model` no vacío [ancla: inventory-assistant › Modelo fuera de la lista — live spec]

### Requirement: Catálogo y administración
Una carpeta SHALL documentar como `admin` el listado y alta de usuarios, el alta y edición de bodegas y
productos y el listado de lotes, con códigos y correos únicos por corrida, para que la colección cubra cada
operación del contrato sin chocar con corridas previas.

#### Scenario: Altas y ediciones del admin
- **WHEN** el `admin` crea un usuario, una bodega y un producto con sufijo único de la corrida, edita la bodega y el producto creados y lista usuarios y lotes
- **THEN** las altas responden HTTP 201, las ediciones HTTP 200 con el valor nuevo y los listados HTTP 200, con los lotes en `expires_on` ascendente [ancla: identity-access › Alta exitosa (usuarios); catalog › Alta exitosa (bodegas); › Cambio parcial; › Todos los lotes en orden de vencimiento — live spec]

#### Scenario: Código de bodega duplicado
- **WHEN** el `admin` crea de nuevo la bodega con el mismo código de la corrida
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.code` [ancla: catalog › Código o nombre duplicado — live spec]

### Requirement: Permisos denegados
Una carpeta SHALL documentar los rechazos de acceso: sin sesión, por rol sin la capacidad y por CSRF, cada uno
con su estado y `code` en el cuerpo.

#### Scenario: Sin sesión
- **WHEN** sin sesión se envía `GET /api/warehouses`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` [ancla: identity-access › Petición sin sesión — live spec]

#### Scenario: Rol sin la capacidad
- **WHEN** el `medico` envía `GET /api/alerts`, el `auxiliar` un ajuste de stock, el `auditor` una vista previa de dispensación, el `admin` `GET /api/patients` y el `regente` `GET /api/users`
- **THEN** la API responde HTTP 403 con `code` `forbidden` en los 5 casos [ancla: inventory-alerts › Roles sin lectura de inventario; inventory › Otro rol intenta ajustar; dispensation › Rol sin permiso de dispensar; patients › Admin sin acceso a pacientes; identity-access › Otro rol intenta listar usuarios — live spec]

#### Scenario: CSRF antes que permisos
- **WHEN** el `medico` con sesión envía `POST /api/warehouses` sin `X-XSRF-TOKEN`
- **THEN** la API responde HTTP 419 con `code` `csrf_token_mismatch`, no HTTP 403 [ancla: identity-access › Escritura con token CSRF caducado — live spec; `software/docker/smoke/auth-smoke.sh:95`]

### Requirement: Salud y disponibilidad
Una carpeta SHALL documentar `GET /health` y `GET /ready` en la raíz del origen, sin sesión.

#### Scenario: Salud y disponibilidad con el stack sano
- **WHEN** con el stack sano se envían `GET /health` y `GET /ready` sin cookies
- **THEN** ambas responden HTTP 200 y `/health` trae `status` `ok` [ancla: service-health › API viva con dependencias sanas; › API lista — live spec]

#### Scenario: Base de datos detenida
- **WHEN** con el servicio `db` detenido se ejecuta la carpeta de salud
- **THEN** `GET /ready` responde HTTP 503, su aserción falla y newman termina con código distinto de 0 [ancla: service-health › Base de datos inalcanzable — live spec]

#### Scenario: Salud fuera del prefijo /api
- **WHEN** se compara la URL de las dos peticiones con la de las demás
- **THEN** solo ellas omiten el prefijo `/api`, como fija el contrato [ancla: service-health › URL en la raíz del origen — live spec]

### Requirement: Cobertura del contrato por la colección
Una guarda ejecutable SHALL comparar el conjunto de operaciones (método y ruta) de `software/api/openapi.json`
con las peticiones de la colección y terminar con código distinto de 0, nombrando la diferencia, si una
operación no tiene petición o una petición no corresponde a ninguna operación. `GET /sanctum/csrf-cookie` SHALL
admitirse por lista explícita.

#### Scenario: Colección completa
- **WHEN** cada operación del contrato tiene al menos una petición en la colección
- **THEN** la guarda termina con código 0 e informa el número de operaciones cubiertas

#### Scenario: Operación sin petición
- **WHEN** se quita de la colección la única petición de `POST /api/transfers/{transfer}/void`
- **THEN** la guarda termina con código distinto de 0 y nombra esa operación

#### Scenario: Petición fuera del contrato
- **WHEN** la colección contiene una petición a una ruta que no figura en el contrato ni en la lista explícita
- **THEN** la guarda termina con código distinto de 0 y nombra esa petición

### Requirement: Resultado de la corrida verificable
La ejecución con `newman` SHALL terminar con código 0 solo si todas las aserciones de todas las carpetas
pasan, e imprimir el resumen de peticiones, aserciones y fallas.

#### Scenario: Corrida verde
- **WHEN** se ejecuta la colección completa sobre el stack recién sembrado
- **THEN** newman termina con código 0 y el resumen muestra 0 aserciones fallidas

#### Scenario: Aserción fallida
- **WHEN** una aserción de la colección no se cumple, por ejemplo la repetición idempotente enviada con otra clave
- **THEN** newman termina con código distinto de 0 y el resumen nombra la aserción fallida
