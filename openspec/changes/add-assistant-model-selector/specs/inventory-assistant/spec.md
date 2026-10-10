## ADDED Requirements

### Requirement: Lista de modelos disponibles
`GET /api/assistant/models` SHALL responder a todo usuario con sesión HTTP 200 con `data`: la lista de modelos
elegibles, cada uno con `id`, `provider` (`mock` u `ollama`) y `name`, con `mock` (`id` `mock`) siempre presente y
primero, sea cual sea `AI_PROVIDER`. SHALL NOT revelar la dirección de Ollama, tamaños, digests ni capacidades, y
SHALL NOT escribir en la base (parte C, RN-10).

#### Scenario: Modelos con Ollama disponible
- **WHEN** un `auxiliar_farmacia` envía `GET /api/assistant/models` y el servidor Ollama, simulado en el borde HTTP, lista `gemma4:e2b-mlx` con la capacidad `tools`
- **THEN** la API responde HTTP 200 con `data` igual a `[{"id": "mock", "provider": "mock", "name": "mock"}, {"id": "ollama:gemma4:e2b-mlx", "provider": "ollama", "name": "gemma4:e2b-mlx"}]`, en ese orden [ancla: ruta `GET /api/assistant/models`, archivo:línea al aplicar]

#### Scenario: Respuesta sin datos del servidor
- **WHEN** se lee el cuerpo de la respuesta del escenario anterior
- **THEN** no contiene el valor de `OLLAMA_BASE_URL`, ni las claves `digest`, `size`, `modified_at`, `details` ni `capabilities`

#### Scenario: Lista sin sesión
- **WHEN** un cliente sin sesión envía `GET /api/assistant/models`
- **THEN** la API responde HTTP 401 con `code` `unauthenticated` y el servidor Ollama no recibe ninguna petición [ancla: middleware `auth:sanctum`, archivo:línea al aplicar]

#### Scenario: Lista con proveedor por defecto desconocido
- **WHEN** `AI_PROVIDER` es `openai`, Ollama no responde y un `regente_farmacia` envía `GET /api/assistant/models`
- **THEN** la API responde HTTP 200 con `data` igual a `[{"id": "mock", "provider": "mock", "name": "mock"}]` [ancla: ruta `GET /api/assistant/models`, archivo:línea al aplicar]

#### Scenario: Lista sin efectos en la base
- **WHEN** un `auditor` pide la lista de modelos con Ollama disponible y con Ollama caído
- **THEN** el número de filas de existencias, movimientos de kardex, traslados, usuarios y bitácoras es el mismo antes y después

### Requirement: Disponibilidad de modelos de Ollama
Un modelo de Ollama SHALL figurar en la lista solo si, consultado `OLLAMA_BASE_URL` desde el servidor, aparece en
`/api/tags` y su ficha de `/api/show` declara la capacidad `tools`, todo dentro de 2 segundos. Ollama inalcanzable,
con error, fuera de plazo o con respuesta mal formada SHALL dejar solo `mock`, con HTTP 200 y sin mensaje de error.
Un modelo cuya ficha falla SHALL omitirse sin afectar a los demás (parte C).

#### Scenario: Modelo sin herramientas omitido
- **WHEN** Ollama lista `gemma4:e2b-mlx`, cuya ficha declara `completion` y `tools`, y `nomic-embed-text`, cuya ficha declara solo `embedding`
- **THEN** la lista tiene `mock` y `ollama:gemma4:e2b-mlx`, y no tiene `ollama:nomic-embed-text`

#### Scenario: Ollama caído
- **WHEN** el servidor Ollama rechaza la conexión y un `auxiliar_farmacia` pide la lista
- **THEN** la API responde HTTP 200 con `data` igual a `[{"id": "mock", "provider": "mock", "name": "mock"}]` y sin `code` ni `message` de error [ancla: servicio de disponibilidad de modelos, archivo:línea al aplicar]

#### Scenario: Ollama lento
- **WHEN** el servidor Ollama no termina de responder `/api/tags` dentro de 2 segundos
- **THEN** la API responde HTTP 200 solo con `mock`, sin esperar la respuesta tardía [ancla: servicio de disponibilidad de modelos, archivo:línea al aplicar]

#### Scenario: Ollama con error o respuesta mal formada
- **WHEN** `/api/tags` responde HTTP 500, o un JSON sin la lista `models`, o un texto que no es JSON
- **THEN** en cada caso la API responde HTTP 200 solo con `mock` [ancla: servicio de disponibilidad de modelos, archivo:línea al aplicar]

#### Scenario: Ficha de un modelo que falla
- **WHEN** Ollama lista `gemma4:e2b-mlx` y `qwen2.5:3b`, ambos con `tools`, y `/api/show` de `qwen2.5:3b` responde HTTP 404
- **THEN** la API responde HTTP 200 con `mock` y `ollama:gemma4:e2b-mlx`, sin `ollama:qwen2.5:3b` [ancla: servicio de disponibilidad de modelos, archivo:línea al aplicar]

#### Scenario: Ollama sin modelos descargados
- **WHEN** `/api/tags` responde `{"models": []}`
- **THEN** la lista tiene solo `mock` y no se llama a `/api/show`

### Requirement: Modelo elegido por pregunta
`POST /api/assistant/ask` SHALL aceptar `model` opcional. Con `model`, SHALL atender el modelo de ese `id` solo si
figura en la lista de modelos disponibles en ese momento; si no, SHALL responder 422 `validation_failed` con
`errors.model` sin llamar al proveedor. Sin `model`, SHALL atender el modelo por defecto de `AI_PROVIDER`. La
respuesta SHALL incluir `data.model` con el `id` que atendió. La dirección de Ollama SHALL salir solo del entorno
(parte C).

#### Scenario: Pregunta con un modelo de Ollama disponible
- **WHEN** `AI_PROVIDER` es `mock`, Ollama simulado lista `gemma4:e2b-mlx` con `tools`, y un `auxiliar_farmacia` envía una pregunta de existencias con `model` `ollama:gemma4:e2b-mlx`, y Ollama pide `get_stock` y luego responde con texto
- **THEN** la petición a `/api/chat` de `OLLAMA_BASE_URL` lleva `model` `gemma4:e2b-mlx` y las 4 herramientas, y la API responde HTTP 200 con `data.model` `ollama:gemma4:e2b-mlx` y la misma forma de `data` que con `mock` [ancla: ruta `POST /api/assistant/ask` + proveedor Ollama, archivo:línea al aplicar]

#### Scenario: Modelo simulado sin red
- **WHEN** `AI_PROVIDER` es `ollama` y un `regente_farmacia` envía una pregunta de existencias con `model` `mock`
- **THEN** la API responde HTTP 200 con `outcome` `answered` y `data.model` `mock`, y no sale ninguna petición de red, tampoco a `/api/tags` [ancla: enlace del proveedor por petición, archivo:línea al aplicar]

#### Scenario: Sin modelo usa el valor por defecto
- **WHEN** un `auxiliar_farmacia` envía una pregunta sin `model`, una vez con `AI_PROVIDER` `mock` y otra con `AI_PROVIDER` `ollama` y `OLLAMA_MODEL` `qwen2.5:3b` con Ollama simulado
- **THEN** la primera responde HTTP 200 con `data.model` `mock` sin red, y la segunda envía a `/api/chat` `model` `qwen2.5:3b` y responde HTTP 200 con `data.model` `ollama:qwen2.5:3b` [ancla: enlace del proveedor por petición, archivo:línea al aplicar]

#### Scenario: Modelo fuera de la lista
- **WHEN** un `auxiliar_farmacia` envía una pregunta válida con `model` `ollama:modelo-inexistente`, `openai:gpt-4o` o `http://atacante.example/api`
- **THEN** en cada caso la API responde HTTP 422 con `code` `validation_failed` y `errors.model` "El modelo elegido no está disponible.", `/api/chat` no recibe ninguna llamada y ninguna petición sale a otro destino que `OLLAMA_BASE_URL` [ancla: FormRequest de la pregunta, archivo:línea al aplicar]

#### Scenario: Modelo descargado sin herramientas
- **WHEN** Ollama lista `nomic-embed-text` con solo `embedding` y un `auditor` pregunta con `model` `ollama:nomic-embed-text`
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.model`, y `/api/chat` no recibe ninguna llamada [ancla: FormRequest de la pregunta, archivo:línea al aplicar]

#### Scenario: Modelo elegido con Ollama caído
- **WHEN** un `auxiliar_farmacia` pregunta con `model` `ollama:gemma4:e2b-mlx` y el servidor Ollama rechaza la conexión
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.model` "El modelo elegido no está disponible.", no HTTP 503 [ancla: FormRequest de la pregunta, archivo:línea al aplicar]

#### Scenario: Modelo con tipo inválido
- **WHEN** un `regente_farmacia` envía una pregunta válida con `model` numérico `7` o con una lista `["mock"]`
- **THEN** la API responde HTTP 422 con `code` `validation_failed` y `errors.model`, y el proveedor no recibe ninguna llamada [ancla: FormRequest de la pregunta, archivo:línea al aplicar]

#### Scenario: Ollama falla al responder con el modelo elegido
- **WHEN** la lista incluye `ollama:gemma4:e2b-mlx`, un `auxiliar_farmacia` pregunta con ese `model` y `/api/chat` responde HTTP 500
- **THEN** la API responde HTTP 503 con `code` `assistant_unavailable` y el mensaje fijo, como fija «Proveedor configurable por entorno» «Ollama caído o lento» [ancla: escenario vivo «Ollama caído o lento»]

#### Scenario: Pregunta sobre un paciente con un modelo de Ollama
- **WHEN** un `regente_farmacia` pregunta "¿Qué medicamentos le dispensaron a la paciente Ana Sintética Pérez?" con `model` `ollama:gemma4:e2b-mlx` disponible
- **THEN** la API responde HTTP 200 con `outcome` `out_of_scope` y `data.model` `ollama:gemma4:e2b-mlx`, `/api/chat` no recibe ninguna llamada y ninguna petición a Ollama contiene texto de la pregunta [ancla: filtro previo del asistente, archivo:línea al aplicar]

#### Scenario: El servidor no recuerda la elección
- **WHEN** con `AI_PROVIDER` `mock` un `auxiliar_farmacia` pregunta con `model` `ollama:gemma4:e2b-mlx` y luego pregunta sin `model`
- **THEN** la segunda respuesta tiene `data.model` `mock` y no sale ninguna petición a `/api/chat` por ella

## MODIFIED Requirements

### Requirement: Proveedor configurable por entorno
El proveedor por defecto, que atiende las preguntas sin `model` y `assistant:eval`, SHALL elegirse con
`AI_PROVIDER`: `mock` (por defecto, también vacía) u `ollama` (`OLLAMA_BASE_URL`, `OLLAMA_MODEL`,
`OLLAMA_TIMEOUT`). Cambiar de proveedor SHALL NOT cambiar la forma de la respuesta ni otra ruta.
Proveedor inalcanzable, con error, fuera de tiempo o valor desconocido SHALL responder 503
`assistant_unavailable` sin efectos (parte C).

#### Scenario: Sin variable usa el modo simulado
- **WHEN** `AI_PROVIDER` no está definida y un `auxiliar_farmacia` envía una pregunta de existencias sin `model`
- **THEN** la API responde HTTP 200 con `outcome` `answered` y no sale ninguna petición de red [ancla: enlace del proveedor por configuración, archivo:línea al aplicar]

#### Scenario: Ollama con llamada a herramienta
- **WHEN** `AI_PROVIDER` es `ollama`, la pregunta llega sin `model` y el servidor Ollama, simulado en el borde HTTP, pide `get_stock` y luego responde con texto
- **THEN** la petición enviada a `OLLAMA_BASE_URL` usa `OLLAMA_MODEL` y declara las 4 herramientas, y la API responde HTTP 200 con la misma forma de `data` que con `mock` [ancla: proveedor Ollama, archivo:línea al aplicar]

#### Scenario: Ollama caído o lento
- **WHEN** `AI_PROVIDER` es `ollama` y el servidor rechaza la conexión, responde HTTP 500 o supera `OLLAMA_TIMEOUT`
- **THEN** la API responde HTTP 503 con `code` `assistant_unavailable` y `message` "El asistente no está disponible en este momento. Intenta más tarde.", sin URL, traza ni texto del proveedor [ancla: proveedor Ollama + render de AssistantUnavailable, archivo:línea al aplicar]

#### Scenario: Proveedor desconocido
- **WHEN** `AI_PROVIDER` es `openai` y un `regente_farmacia` envía una pregunta sin `model`
- **THEN** la API responde HTTP 503 con `code` `assistant_unavailable`, y `GET /api/stock` del mismo usuario sigue respondiendo HTTP 200 [ancla: enlace del proveedor por configuración, archivo:línea al aplicar]

### Requirement: Registro de consultas sin contenido
Cada consulta SHALL escribir una línea de log con `outcome`, nombre y `status` de cada llamada, rondas, proveedor,
modelo, duración y `correlation_id`. SHALL NOT registrar la pregunta, la respuesta, los argumentos de texto ni los
resultados de herramientas (RN-10).

#### Scenario: Línea de la consulta
- **WHEN** un `auxiliar_farmacia` envía una pregunta de existencias con `X-Correlation-Id: traza-asistente`
- **THEN** existe exactamente una línea JSON del asistente con `outcome` `answered`, `get_stock` con `ok`, el proveedor `mock`, el modelo `mock` y `correlation_id` `traza-asistente`

#### Scenario: Línea con un modelo de Ollama
- **WHEN** un `auxiliar_farmacia` pregunta "¿Cuánto stock hay de acetaminofén en la farmacia central?" con `model` `ollama:gemma4:e2b-mlx` y Ollama simulado responde
- **THEN** la línea del asistente tiene el proveedor `ollama` y el modelo `ollama:gemma4:e2b-mlx`, y no contiene `acetaminofén` ni `farmacia central`

#### Scenario: Pregunta sensible fuera del log
- **WHEN** un `regente_farmacia` pregunta "¿Qué le dispensaron a Ana Sintética Pérez, documento 9999010001?"
- **THEN** ninguna línea de log contiene `Ana Sintética Pérez` ni `9999010001`, y el mismo barrido sí detecta el documento cuando la prueba lo escribe a propósito en una línea de log
