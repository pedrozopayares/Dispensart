# service-health Specification

## Purpose
Permite a orquestadores, balanceadores y operadores saber si la API está viva y lista para atender, y
seguir cada petición de punta a punta en los logs mediante un identificador de correlación, sin exponer
datos personales (RN-10).

## Requirements

### Requirement: Vivacidad de la API
La API SHALL responder `GET /health` con HTTP 200 y el cuerpo JSON `{"status":"ok"}` mientras el proceso
atienda peticiones, sin consultar la base de datos, la caché ni el almacenamiento de sesiones. La respuesta
SHALL NOT incluir versión, entorno, nombre de host ni cookie alguna.

#### Scenario: API viva con dependencias sanas
- **WHEN** un cliente envía `GET /health` con la base de datos disponible
- **THEN** la API responde HTTP 200 con el cuerpo exacto `{"status":"ok"}` [ancla: ruta `GET /health`, archivo:línea al aplicar]

#### Scenario: API viva con la base de datos caída
- **WHEN** la base de datos es inalcanzable y un cliente envía `GET /health`
- **THEN** la API responde HTTP 200 con `{"status":"ok"}`, porque la vivacidad no depende de la base [ancla: ruta `GET /health`, archivo:línea al aplicar]

#### Scenario: Vivacidad sin estado ni datos internos
- **WHEN** un cliente envía `GET /health`
- **THEN** la respuesta no trae cabecera `Set-Cookie` y su cuerpo no contiene más clave que `status`

### Requirement: Disponibilidad de la API
La API SHALL responder `GET /ready` con HTTP 200 solo cuando la base de datos responde a una consulta y no
quedan migraciones pendientes; en cualquier otro caso SHALL responder HTTP 503. El cuerpo SHALL ser JSON con
`status` (`ready` o `not_ready`) y `checks.database` y `checks.migrations`, sin mensajes de excepción,
credenciales, hosts ni cadenas de conexión.

#### Scenario: API lista
- **WHEN** la base de datos responde y todas las migraciones están aplicadas, y un cliente envía `GET /ready`
- **THEN** la API responde HTTP 200 con `{"status":"ready","checks":{"database":"ok","migrations":"ok"}}` [ancla: ruta `GET /ready`, archivo:línea al aplicar]

#### Scenario: Base de datos inalcanzable
- **WHEN** la base de datos es inalcanzable y un cliente envía `GET /ready`
- **THEN** la API responde HTTP 503 con `{"status":"not_ready","checks":{"database":"fail","migrations":"skipped"}}` [ancla: ruta `GET /ready`, archivo:línea al aplicar]

#### Scenario: Migraciones pendientes
- **WHEN** la base de datos responde pero existe al menos una migración sin aplicar, y un cliente envía `GET /ready`
- **THEN** la API responde HTTP 503 con `{"status":"not_ready","checks":{"database":"ok","migrations":"pending"}}` [ancla: ruta `GET /ready`, archivo:línea al aplicar]

#### Scenario: Fallo sin filtrar detalles internos
- **WHEN** `GET /ready` falla porque la conexión a la base de datos lanza una excepción
- **THEN** el cuerpo no contiene el mensaje de la excepción, el host, el usuario ni el nombre de la base, y la excepción queda registrada en el log con el `correlation_id` de la petición

### Requirement: Identificador de correlación por petición
Toda respuesta de la API SHALL llevar la cabecera `X-Correlation-Id`. Si la petición trae un
`X-Correlation-Id` válido (1 a 128 caracteres de `[A-Za-z0-9._-]`), la API SHALL reutilizarlo; si falta o es
inválido, SHALL generar un UUID nuevo. El valor efectivo SHALL ser el mismo en la respuesta y en todas las
líneas de log de esa petición.

#### Scenario: Cabecera válida respetada
- **WHEN** un cliente envía `GET /health` con `X-Correlation-Id: pedido-123.abc`
- **THEN** la respuesta trae `X-Correlation-Id: pedido-123.abc`

#### Scenario: Cabecera ausente
- **WHEN** un cliente envía `GET /health` sin `X-Correlation-Id`
- **THEN** la respuesta trae `X-Correlation-Id` con un UUID válido, y dos peticiones seguidas reciben UUIDs distintos

#### Scenario: Cabecera inválida reemplazada
- **WHEN** un cliente envía `X-Correlation-Id` con 129 caracteres, o con espacios, saltos de línea o caracteres fuera del conjunto permitido
- **THEN** la respuesta trae un UUID generado en `X-Correlation-Id`, y el valor recibido no aparece ni en la respuesta ni en ninguna línea de log

#### Scenario: Error no controlado conserva el identificador
- **WHEN** una petición termina en una excepción no controlada
- **THEN** la API responde HTTP 500 con la cabecera `X-Correlation-Id`, sin traza en el cuerpo, y la línea de log del error trae el mismo `correlation_id` [ancla: manejador de excepciones de la API, archivo:línea al aplicar]

### Requirement: Logs estructurados sin datos personales
La API SHALL escribir sus logs en la salida estándar de error del contenedor, una línea JSON por entrada, con
`timestamp` (ISO-8601 UTC), `level`, `message` y `correlation_id`. Cada petición atendida SHALL producir una
línea de cierre con método, ruta sin query string, código de estado y duración en milisegundos. Los logs
SHALL NOT contener query string, cuerpo, cookies ni cabeceras distintas de `X-Correlation-Id` (RN-10).

#### Scenario: Línea de cierre por petición
- **WHEN** un cliente envía `GET /ready` con `X-Correlation-Id: traza-1`
- **THEN** se escribe exactamente una línea JSON de cierre con `method` `GET`, `path` `/ready`, el código de estado devuelto, `duration_ms` numérico y `correlation_id` `traza-1`

#### Scenario: Cada línea es JSON válido
- **WHEN** se atiende cualquier petición
- **THEN** cada línea que la API escribe en su log se decodifica como un objeto JSON con las claves `timestamp`, `level`, `message` y `correlation_id`

#### Scenario: Query string, cuerpo y cabeceras excluidos
- **WHEN** un cliente envía `POST /health?documento=123456` con cuerpo `{"nombre":"Paciente Sintético"}`, una cookie y una cabecera `Authorization`
- **THEN** ninguna línea de log contiene `documento`, `123456`, `Paciente Sintético`, el valor de la cookie ni el de `Authorization`

#### Scenario: Log fuera de una petición
- **WHEN** la API escribe un log fuera de una petición HTTP, por ejemplo al migrar durante el arranque
- **THEN** la línea es JSON válido con `correlation_id` en `null`

### Requirement: Contrato OpenAPI de salud y disponibilidad
`software/api/openapi.json` SHALL documentar `GET /health` y `GET /ready` en la raíz del mismo origen, no bajo
`/api`, como operaciones públicas sin cookie ni CSRF, con sus códigos, el esquema de su cuerpo y la cabecera
`X-Correlation-Id`, sin claves internas (RN-10; parte D). Una prueba de contrato SHALL fallar si falta alguna de
las dos. El contrato SHALL seguir saliendo de la exportación del código, sin deriva.

#### Scenario: Vivacidad documentada
- **WHEN** se lee la operación `GET /health` de `openapi.json`
- **THEN** declara la respuesta 200 con un objeto cuya única clave es `status` con valor `ok`, y la cabecera `X-Correlation-Id` [ancla: SH › API viva con dependencias sanas — live spec `service-health`; ruta `software/api/routes/health.php:8`]

#### Scenario: Disponibilidad documentada con su fallo
- **WHEN** se lee la operación `GET /ready` de `openapi.json`
- **THEN** declara las respuestas 200 y 503, ambas con la cabecera `X-Correlation-Id` y un objeto con `status` (`ready` o `not_ready`) y `checks` con `database` (`ok` o `fail`) y `migrations` (`ok`, `pending`, `skipped` o `fail`) [ancla: SH › API lista y SH › Base de datos inalcanzable — live spec `service-health`; `software/api/app/Http/Controllers/Health/ReadyController.php:17`]

#### Scenario: URL en la raíz del origen
- **WHEN** se resuelve la URL de `GET /health` y de `GET /ready` combinando su ruta con el servidor que les aplica en el contrato
- **THEN** las URLs son `/health` y `/ready`, no `/api/health` ni `/api/ready`, y las demás operaciones siguen resolviendo bajo `/api`

#### Scenario: Operaciones públicas sin seguridad de sesión
- **WHEN** se leen los requisitos de seguridad y las respuestas de `GET /health` y `GET /ready`
- **THEN** ninguna exige la cookie de sesión ni `X-XSRF-TOKEN`, y ninguna declara 401 ni 419 [ancla: rutas sin grupo de middleware, `software/api/routes/health.php:7-9`]

#### Scenario: Contrato sin claves internas
- **WHEN** se recorren las propiedades de los esquemas de respuesta de `GET /health` y `GET /ready`
- **THEN** no aparece ninguna clave fuera de `status`, `checks`, `database` y `migrations`, ni ejemplo alguno con host, usuario, versión o mensaje de excepción

#### Scenario: Salud ausente del contrato
- **WHEN** se quita `GET /health` o `GET /ready` de `openapi.json`
- **THEN** la prueba de contrato falla nombrando la operación ausente

#### Scenario: Contrato derivado del código
- **WHEN** se ejecutan `composer openapi:check` en `software/api`, `npm run openapi:lint` en la raíz y `npm run api:types:check` en `software/web`
- **THEN** los tres terminan con código 0

#### Scenario: Edición manual del contrato
- **WHEN** se agregan a mano a `openapi.json` las operaciones de salud sin que la exportación del código las produzca
- **THEN** `composer openapi:check` termina con código distinto de 0
