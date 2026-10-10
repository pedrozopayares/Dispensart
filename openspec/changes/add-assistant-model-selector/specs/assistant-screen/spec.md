## ADDED Requirements

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

## MODIFIED Requirements

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
