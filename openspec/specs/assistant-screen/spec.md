# assistant-screen Specification

## Purpose
Permite a cualquier usuario con sesión preguntar en español al asistente de inventario desde la SPA y ver,
por cada pregunta, el resultado que decidió el servidor, la respuesta y las consultas hechas, sin guardar
las preguntas fuera de la memoria de la pantalla (parte C, RN-10).

## Requirements

### Requirement: Pantalla Asistente para los roles de operación
La SPA SHALL ofrecer la pantalla "Asistente" en `/assistant`, dentro del shell, para toda sesión salvo el rol
`admin` (§ 3), que ve el aviso de permiso de operator-workspace; los permisos los aplica el servidor en cada
herramienta.
SHALL seguir la disposición común de operator-workspace: título, caja de pregunta arriba, resultados
debajo, componentes del kit (ADR-0004) y textos del módulo central. Abrirla SHALL NOT enviar ninguna
pregunta (RN-10, parte C).

#### Scenario: Médico abre el asistente desde el menú
- **WHEN** un `medico` con sesión pulsa "Asistente" en el menú
- **THEN** la SPA abre `/assistant` con el título "Asistente de inventario", la caja "Tu pregunta" con el foco, el botón "Preguntar" y el enlace "Asistente" marcado como página actual

#### Scenario: Apertura sin preguntas enviadas
- **WHEN** un `auxiliar_farmacia` abre `/assistant`
- **THEN** se ve "Aún no has hecho preguntas. Prueba con uno de los ejemplos." y no se envía ninguna pregunta al asistente; la única petición al asistente es la lista de modelos

#### Scenario: Acceso directo sin sesión
- **WHEN** un visitante sin sesión abre `/assistant` escribiendo la dirección
- **THEN** la SPA lo lleva a `/login` sin mostrar la pantalla ni enviar ninguna pregunta

#### Scenario: Admin escribe la dirección del asistente
- **WHEN** un `admin` con sesión abre `/assistant` escribiendo la dirección
- **THEN** ve "No tienes permiso para ver esta pantalla." y el enlace "Volver al inicio", sin la caja "Tu pregunta", y no se envía ninguna petición al asistente

### Requirement: Envío de una pregunta
La pantalla SHALL enviar la pregunta escrita como `question`, junto con `model` = `id` del modelo elegido, a
`POST /api/assistant/ask` al pulsar "Preguntar" o Enter; Shift+Enter SHALL insertar un salto de línea. Antes de
enviar SHALL exigir de 3 a 500 caracteres sin contar espacios de los extremos, con contador "{n}/500". Mientras la
petición está en curso el botón SHALL quedar deshabilitado con "Consultando…" e ignorar nuevas pulsaciones o
Enter (parte C).

#### Scenario: Pregunta enviada
- **WHEN** un `auxiliar_farmacia`, con "Simulado (sin red)" elegido, escribe "¿Cuánto stock hay de acetaminofén en la farmacia central?" y pulsa "Preguntar"
- **THEN** se envía exactamente una petición `POST /api/assistant/ask` con cuerpo `{"question": "¿Cuánto stock hay de acetaminofén en la farmacia central?", "model": "mock"}`, y mientras llega la respuesta el botón muestra "Consultando…" deshabilitado y un estado anunciado "Consultando al asistente…"

#### Scenario: Pregunta vacía
- **WHEN** el usuario pulsa "Preguntar" con la caja vacía o solo con espacios
- **THEN** la caja muestra "Este campo es obligatorio." y no se envía ninguna petición

#### Scenario: Pregunta demasiado corta
- **WHEN** el usuario escribe "ab" y pulsa Enter
- **THEN** la caja muestra "Escribe al menos 3 caracteres." y no se envía ninguna petición

#### Scenario: Tope de 500 caracteres
- **WHEN** el usuario pega un texto de 600 caracteres en la caja
- **THEN** la caja conserva solo 500 caracteres, el contador muestra "500/500" y al pulsar "Preguntar" la pregunta enviada tiene 500 caracteres [ancla: no es un estado HTTP; tope `max:500` de `software/api/app/Http/Requests/Assistant/AskAssistantRequest.php:21`]

#### Scenario: Doble clic produce una sola pregunta
- **WHEN** el usuario pulsa "Preguntar" dos veces seguidas con una pregunta válida
- **THEN** se envía una sola petición al asistente

#### Scenario: Enter repetido
- **WHEN** el usuario pulsa Enter dos veces seguidas con una pregunta válida
- **THEN** se envía una sola petición al asistente

#### Scenario: Salto de línea sin envío
- **WHEN** el usuario pulsa Shift+Enter dentro de la caja
- **THEN** la caja gana un salto de línea y no se envía ninguna petición

### Requirement: Resultado según el outcome del servidor
Cada respuesta SHALL mostrarse con una etiqueta en español distinta por `outcome`: `answered` "Respondida",
`no_results` "Sin resultados", `out_of_scope` "Fuera de alcance", `not_permitted` "Sin permiso", `unknown`
"Sin respuesta", con estilo propio para `answered` y otro para el resto. Debajo SHALL mostrarse `answer`
como texto plano, respetando sus saltos de línea, sin interpretarlo como HTML. La SPA SHALL NOT deducir el
resultado del texto (RN-10, parte C).

#### Scenario: Pregunta respondida
- **WHEN** la API responde con `outcome` `answered` y un `answer` de dos líneas que contiene "30 unidades disponibles"
- **THEN** la pregunta aparece arriba del historial con la etiqueta "Respondida", las dos líneas en líneas separadas, la caja queda vacía y conserva el foco

#### Scenario: Sin resultados
- **WHEN** la API responde con `outcome` `no_results` y `answer` "No encontré resultados para esa consulta."
- **THEN** se ve la etiqueta "Sin resultados" con ese texto y no se ve la etiqueta "Respondida"

#### Scenario: Pregunta fuera de alcance
- **WHEN** un `auxiliar_farmacia` pregunta "¿Va a llover mañana en Santa Marta?" y la API responde con `outcome` `out_of_scope`, `tool_calls` vacío y el mensaje fijo de fuera de alcance
- **THEN** se ve la etiqueta "Fuera de alcance" con el mensaje del servidor, sin la etiqueta "Respondida"

#### Scenario: Pregunta sobre un paciente
- **WHEN** un `regente_farmacia` pregunta "¿Qué medicamentos le dispensaron a la paciente Ana Sintética Pérez?" y la API responde con `outcome` `out_of_scope` y `tool_calls` vacío
- **THEN** se ve la etiqueta "Fuera de alcance" con el mensaje del servidor y ningún dato adicional del paciente

#### Scenario: Rol sin permiso para la consulta
- **WHEN** un `medico` pregunta "¿Qué lotes vencen en los próximos 30 días?" y la API responde con `outcome` `not_permitted`, `answer` "Tu rol no tiene permiso para consultar esa información." y la llamada `find_expiring_lots` con `status` `denied`
- **THEN** se ve la etiqueta "Sin permiso" con ese texto y ningún código de lote ni cantidad

#### Scenario: Sin respuesta
- **WHEN** la API responde con `outcome` `unknown` y `answer` "No sé responder esa pregunta con la información disponible."
- **THEN** se ve la etiqueta "Sin respuesta" con ese texto

#### Scenario: Respuesta con marcado no se interpreta
- **WHEN** la API responde con `outcome` `answered` y un `answer` que contiene `<img src=x onerror=alert(1)>`
- **THEN** la pantalla muestra ese texto literal y el documento no gana ningún elemento `img`

### Requirement: Consultas hechas por pregunta
Bajo cada respuesta SHALL listarse cada elemento de `tool_calls` con nombre de herramienta, argumentos y
estado en español: herramientas "Existencias", "Lotes por vencer", "Stock bajo mínimo", "Estado de
traslados"; estados "Consultada", "Sin permiso", "Rechazada", "Argumentos inválidos", "Falló"; argumentos
"Producto", "Bodega", "Días", "Traslado", "Estado". Una herramienta fuera del catálogo SHALL mostrarse como
"Herramienta fuera del catálogo", nunca con el nombre pedido (parte C).

#### Scenario: Consulta de existencias
- **WHEN** la respuesta trae una llamada `get_stock` con `status` `ok` y argumentos `product` "acetaminofén" y `warehouse` "Farmacia Central"
- **THEN** bajo la respuesta se ve "Existencias", "Consultada", "Producto: acetaminofén" y "Bodega: Farmacia Central"

#### Scenario: Estado de traslado en español
- **WHEN** la respuesta trae una llamada `get_transfer_status` con `status` `ok` y argumento `status` `EN_TRANSITO`
- **THEN** se ve "Estado de traslados" y "Estado: En tránsito", nunca `EN_TRANSITO`

#### Scenario: Llamada negada
- **WHEN** la respuesta trae una llamada `get_transfer_status` con `status` `denied` y argumentos `{}`
- **THEN** se ve "Estado de traslados" con "Sin permiso" y ninguna línea de argumentos

#### Scenario: Herramienta fuera del catálogo
- **WHEN** la respuesta trae una llamada `approve_transfer` con `status` `rejected` y argumentos `{}`
- **THEN** se ve "Herramienta fuera del catálogo" con "Rechazada" y el texto `approve_transfer` no aparece en la pantalla

#### Scenario: Sin consultas
- **WHEN** la respuesta trae `tool_calls` vacío
- **THEN** bajo la respuesta se ve "Sin consultas a herramientas."

### Requirement: Historial de la pantalla solo en memoria
La pantalla SHALL conservar las últimas 10 preguntas con su resultado, la más reciente arriba, solo en la
memoria de la pantalla: SHALL perderse al salir de la pantalla, al recargar y al cerrar sesión, y SHALL NOT
escribirse en `localStorage`, `sessionStorage`, la URL ni la consola, porque el usuario puede escribir datos
de un paciente aunque el asistente no los use (RN-10, Ley 1581). El `id` del modelo elegido SHALL ser lo único
guardado en `localStorage`.

#### Scenario: Dos preguntas seguidas
- **WHEN** el usuario hace una pregunta, recibe respuesta y hace otra
- **THEN** ambas se ven con su resultado y la segunda queda arriba de la primera

#### Scenario: Undécima pregunta
- **WHEN** el usuario recibe la respuesta de su undécima pregunta
- **THEN** el historial muestra 10 entradas y ya no muestra la primera pregunta

#### Scenario: Salir y volver vacía el historial
- **WHEN** el usuario con preguntas respondidas abre "Inventario" y vuelve a "Asistente"
- **THEN** ve "Aún no has hecho preguntas. Prueba con uno de los ejemplos." y ninguna pregunta anterior

#### Scenario: Pregunta con un documento fuera del navegador persistente
- **WHEN** el usuario pregunta "¿Cuánto acetaminofén retiró 9999010001?" y recibe la respuesta
- **THEN** ni `localStorage`, ni `sessionStorage`, ni la URL, ni ninguna llamada a la consola contienen `9999010001`

#### Scenario: Almacenamiento solo con el modelo
- **WHEN** el usuario elige "Ollama · gemma4:e2b-mlx", pregunta "¿Cuánto acetaminofén retiró 9999010001?" y recibe la respuesta
- **THEN** `localStorage` tiene una sola clave escrita por la pantalla, con valor `ollama:gemma4:e2b-mlx`, `sessionStorage` sigue vacío y ninguno contiene `9999010001` ni `acetaminofén`

### Requirement: Errores de la pregunta
Ante un rechazo o un fallo, la pantalla SHALL mostrar en una región de alerta el mensaje del catálogo central
por `code`, conservar la pregunta escrita, rehabilitar "Preguntar" y no añadir entrada al historial.
`errors.question` SHALL mostrarse junto a la caja. `unauthenticated` y `csrf_token_mismatch` SHALL seguir el
manejo de app-shell (RN-10, parte C).

#### Scenario: Validación del servidor
- **WHEN** la API rechaza la pregunta con `code` `validation_failed` y `errors.question` "La pregunta debe tener al menos 3 caracteres."
- **THEN** ese mensaje aparece junto a la caja, la caja conserva lo escrito y "Preguntar" vuelve a habilitarse

#### Scenario: Demasiadas preguntas
- **WHEN** la API rechaza la pregunta con `code` `too_many_requests`
- **THEN** la pantalla muestra "Hiciste muchas preguntas seguidas. Espera un minuto e intenta de nuevo." y conserva la pregunta

#### Scenario: Asistente no disponible
- **WHEN** la API responde con `code` `assistant_unavailable`
- **THEN** la pantalla muestra "El asistente no está disponible en este momento. Intenta más tarde.", conserva la pregunta y el historial no gana entrada

#### Scenario: Fallo de red
- **WHEN** la petición falla por red o con `code` `server_error`
- **THEN** la pantalla muestra "No pudimos conectar con el servidor. Intenta de nuevo." sin el mensaje técnico del navegador

#### Scenario: Código desconocido
- **WHEN** la API rechaza la pregunta con `code` `quota_exceeded`
- **THEN** la pantalla muestra "Ocurrió un error inesperado. Intenta de nuevo." y no muestra `quota_exceeded`

#### Scenario: Sesión expirada al preguntar
- **WHEN** la pregunta recibe `code` `unauthenticated`
- **THEN** la SPA navega a `/login` con "Tu sesión expiró. Inicia sesión de nuevo." como fija app-shell «Sesión expirada y token CSRF vencido»

#### Scenario: Error anterior se limpia
- **WHEN** tras un error el usuario pregunta de nuevo y recibe respuesta
- **THEN** el mensaje de error desaparece y la respuesta queda arriba del historial

### Requirement: Preguntas de ejemplo y aviso de privacidad
La pantalla SHALL ofrecer cuatro preguntas de ejemplo, una por herramienta, como botones alcanzables con
teclado y deshabilitados durante una consulta; elegir una SHALL copiarla en la caja con el foco y SHALL NOT
enviarla. Bajo la caja SHALL verse el
aviso fijo "No escribas nombres ni documentos de pacientes: el asistente solo responde sobre inventario y
traslados." (RN-10, parte C).

#### Scenario: Ejemplo rellena la caja
- **WHEN** el usuario pulsa el ejemplo "¿Qué productos están por debajo del stock mínimo?"
- **THEN** la caja contiene ese texto con el foco y no se envía ninguna petición

#### Scenario: Ejemplos disponibles
- **WHEN** el usuario abre `/assistant`
- **THEN** ve los ejemplos "¿Cuánto stock hay de acetaminofén en la farmacia central?", "¿Qué lotes de acetaminofén vencen en los próximos 60 días en la farmacia central?", "¿Qué productos están por debajo del stock mínimo?" y "¿Cuántos traslados hay en tránsito?", y el aviso de privacidad

#### Scenario: Ejemplo durante una consulta
- **WHEN** hay una pregunta en curso y el usuario intenta pulsar un ejemplo
- **THEN** los ejemplos están deshabilitados, la caja no cambia, no se envía otra petición y la respuesta en curso se muestra al llegar

### Requirement: Selector de modelo
Dentro del formulario, sobre la caja de pregunta, la pantalla SHALL mostrar el selector "Modelo" (selector nativo
del kit, ADR-0004) con solo los modelos de `GET /api/assistant/models`: `mock` como "Simulado (sin red)" y cada
modelo de Ollama como "Ollama · {name}". SHALL deshabilitarse durante una consulta. Si la lista falla, SHALL
ofrecer solo `mock` con un aviso, sin bloquear la pregunta. `errors.model` SHALL mostrarse junto al selector
(parte C).

#### Scenario: Selector con Ollama disponible
- **WHEN** un `auxiliar_farmacia` abre `/assistant` y la lista trae `mock` y `ollama:gemma4:e2b-mlx`
- **THEN** el selector "Modelo" ofrece exactamente "Simulado (sin red)" y "Ollama · gemma4:e2b-mlx", en ese orden, con "Simulado (sin red)" elegido

#### Scenario: Ollama no disponible
- **WHEN** la lista trae solo `mock`
- **THEN** el selector ofrece solo "Simulado (sin red)", no hay opción de Ollama ni deshabilitada ni oculta que pueda elegirse, y no se ve ningún aviso de error

#### Scenario: Apertura consulta solo la lista
- **WHEN** un `regente_farmacia` abre `/assistant`
- **THEN** se envía exactamente una petición `GET /api/assistant/models` y ninguna `POST /api/assistant/ask`

#### Scenario: Carga de la lista
- **WHEN** la lista de modelos aún no llega
- **THEN** el selector está deshabilitado con "Simulado (sin red)", se anuncia "Cargando modelos…", la caja admite escritura y "Preguntar" queda deshabilitado hasta que la lista llega o falla

#### Scenario: Fallo de la lista
- **WHEN** `GET /api/assistant/models` falla por red o con `code` `server_error`
- **THEN** el selector ofrece solo "Simulado (sin red)", se ve "No se pudo consultar los modelos disponibles. Se usa Simulado (sin red).", y al preguntar el cuerpo lleva `model` `mock`

#### Scenario: Pregunta con un modelo de Ollama
- **WHEN** el usuario elige "Ollama · gemma4:e2b-mlx", escribe "¿Cuántos traslados hay en tránsito?" y pulsa "Preguntar"
- **THEN** se envía una sola petición `POST /api/assistant/ask` con cuerpo `{"question": "¿Cuántos traslados hay en tránsito?", "model": "ollama:gemma4:e2b-mlx"}`

#### Scenario: Selector durante una consulta
- **WHEN** hay una pregunta en curso
- **THEN** el selector está deshabilitado y su valor no cambia hasta que llega la respuesta

#### Scenario: Modelo rechazado por el servidor
- **WHEN** la API rechaza la pregunta con `code` `validation_failed` y `errors.model` "El modelo elegido no está disponible."
- **THEN** ese mensaje aparece junto al selector, la caja conserva la pregunta, el historial no gana entrada, la lista de modelos se pide de nuevo y, si el modelo ya no figura, el selector pasa a "Simulado (sin red)"

#### Scenario: Admin sin consulta de modelos
- **WHEN** un `admin` con sesión abre `/assistant` escribiendo la dirección
- **THEN** ve el aviso de permiso y no se envía `GET /api/assistant/models`

### Requirement: Elección de modelo conservada al recargar
La pantalla SHALL guardar en `localStorage` solo el `id` del modelo, y solo cuando el usuario lo elige. Al abrir
SHALL restaurar el `id` guardado si figura en la lista recibida; si no figura, si no es un `id` de la lista o si no
hay valor, SHALL elegir `mock` sin enviar nunca el valor guardado sin validar. Sin almacenamiento disponible SHALL
funcionar con `mock`. Nada SHALL guardarse en la base (parte C, RN-10).

#### Scenario: La elección sobrevive a una recarga
- **WHEN** el usuario elige "Ollama · gemma4:e2b-mlx", recarga la página y la lista vuelve a traer ese modelo
- **THEN** el selector muestra "Ollama · gemma4:e2b-mlx" y la siguiente pregunta lleva `model` `ollama:gemma4:e2b-mlx`

#### Scenario: Primera carga
- **WHEN** el usuario abre `/assistant` sin valor guardado y la lista trae `mock` y `ollama:gemma4:e2b-mlx`
- **THEN** el selector muestra "Simulado (sin red)" y `localStorage` no gana ninguna clave hasta que el usuario elige

#### Scenario: Modelo guardado que ya no está disponible
- **WHEN** el valor guardado es `ollama:gemma4:e2b-mlx` y la lista trae solo `mock`
- **THEN** el selector muestra "Simulado (sin red)", se ve "El modelo que elegiste ya no está disponible. Se usa Simulado (sin red).", y la siguiente pregunta lleva `model` `mock`

#### Scenario: Valor guardado manipulado
- **WHEN** el valor guardado es `http://atacante.example/api`, `<img src=x onerror=alert(1)>` o un texto vacío
- **THEN** el selector muestra "Simulado (sin red)", ninguna petición lleva ese valor y el documento no gana ningún elemento `img`

#### Scenario: Almacenamiento no disponible
- **WHEN** leer o escribir `localStorage` lanza un error
- **THEN** la pantalla funciona con "Simulado (sin red)", la elección vale para la visita y no se muestra ningún error

### Requirement: Modelo que respondió
Cada entrada del historial SHALL mostrar el modelo que la atendió, tomado de `data.model` de su respuesta y no de la
selección actual: "Respondió: Simulado (sin red)" o "Respondió: Ollama · {name}". Un `data.model` que no es `mock` ni
empieza por `ollama:` SHALL mostrarse como "Respondió: Modelo desconocido", sin el texto recibido (parte C).

#### Scenario: Respuesta de Ollama
- **WHEN** la respuesta trae `data.model` `ollama:gemma4:e2b-mlx`
- **THEN** la entrada muestra "Respondió: Ollama · gemma4:e2b-mlx"

#### Scenario: Cambiar el selector no reescribe el historial
- **WHEN** una entrada fue atendida con `data.model` `mock` y después el usuario elige "Ollama · gemma4:e2b-mlx"
- **THEN** esa entrada sigue mostrando "Respondió: Simulado (sin red)"

#### Scenario: Modelo desconocido en la respuesta
- **WHEN** la respuesta trae `data.model` `openai:gpt-4o`
- **THEN** la entrada muestra "Respondió: Modelo desconocido" y el texto `openai:gpt-4o` no aparece en la pantalla
