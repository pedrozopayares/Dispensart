# Verification — add-assistant-model-selector (S15)

Tier A. Tablas solamente. Filas de la SPA (AS, M10–M13) las agrega el frontend-implementer; cierre (7.x) el
Orchestrator. Rutas de archivo relativas a `software/api/` salvo indicación.

## 0. Reparto de líneas (parte API y tipos)

| Tipo | Alcance | Comando (raíz del repo) | Resultado |
|---|---|---|---|
| Producto | `app`, `routes`, `config`, `lang` de la API y `src/lib/api-types.ts` | `git diff --numstat 5504d20..<commit de tipos> -- software/api/app software/api/routes software/api/config software/api/lang software/web/src/lib/api-types.ts` | +440 −30 |
| Prueba | `tests`, `phpunit.xml` | mismo rango `-- software/api/tests software/api/phpunit.xml` | +547 −3 |
| Generado | `openapi.json`, `api-schema.ts` | mismo rango `-- software/api/openapi.json software/web/src/lib/api-schema.ts` | +158 −4 |
| Registro | `verification.md`, `journal.md`, `tasks.md`, `mutants/` | al cierre (Orchestrator) | — |

## 1. Línea base (tarea 0.1)

| Comprobación | Comando | Resultado |
|---|---|---|
| Stack | `docker compose -f software/compose.yaml ps` | api, db, web `healthy` |
| Pest base | `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest` | 1039 passed (4281 assertions) |
| Vitest base | `cd software/web && npx vitest run` | 32 archivos, 338 passed |
| Deriva OpenAPI | `… api-tools composer openapi` + `git diff --exit-code -- software/api/openapi.json` en el anfitrión | sin diferencias (exit 0). `composer openapi:check` dentro del contenedor sale 129: el contenedor no tiene `.git`; control: el mismo `git diff` en el anfitrión sí detecta el cambio de 3.2 antes del commit (91 líneas) |
| Deriva de tipos | `npm --prefix software/web run api:types:check` | exit 0 |
| Caché del catálogo fuera de pruebas | `… api-tools php artisan tinker --execute="echo config('assistant.models.cache_store');"` | `file` |

## 2. Escenarios API → prueba

`T-models` = `tests/Feature/Assistant/AssistantModelsEndpointTest.php`; `T-choice` =
`tests/Feature/Assistant/AssistantModelChoiceTest.php`; `T-log` = `tests/Feature/Assistant/AssistantQueryLogTest.php`;
`T-ollama` = `tests/Feature/Assistant/OllamaLlmProviderTest.php`.

| Requisito IA | Escenario | Prueba | Archivo:línea | cláusula → ruta archivo:línea |
|---|---|---|---|---|
| Lista de modelos disponibles | Modelos con Ollama disponible | `Modelos con Ollama disponible: mock primero…` | T-models:29 | 200 → `routes/api.php:104`, `app/Http/Controllers/Assistant/AssistantModelController.php:17` |
| Lista de modelos disponibles | Respuesta sin datos del servidor | `Respuesta sin datos del servidor: …` | T-models:44 | — (sin estado HTTP en THEN); forma → `app/Http/Resources/AssistantModelResource.php:24` |
| Lista de modelos disponibles | Lista sin sesión | `Lista sin sesión: 401 unauthenticated…` | T-models:56 | 401 → `routes/api.php:38` (grupo `auth:sanctum`) con la ruta en `routes/api.php:104` |
| Lista de modelos disponibles | Lista con proveedor por defecto desconocido | `Lista con proveedor por defecto desconocido: …` | T-models:66 | 200 → `routes/api.php:104`; caída → `app/Services/Assistant/Llm/ModelCatalog.php:105` |
| Lista de modelos disponibles | Lista sin efectos en la base | `Lista sin efectos en la base: …` | T-models:75 | — |
| Disponibilidad de modelos de Ollama | Modelo sin herramientas omitido | `Modelo sin herramientas omitido: …` | T-models:104 | — ; filtro → `ModelCatalog.php:134` |
| Disponibilidad de modelos de Ollama | Ollama caído | `Ollama caído: 200 solo con mock…` | T-models:119 | 200 → `ModelCatalog.php:105` |
| Disponibilidad de modelos de Ollama | Ollama lento | `Ollama lento: solo mock, sin consultar fichas…` | T-models:130 | 200 → `ModelCatalog.php:116` (corte por plazo) |
| Disponibilidad de modelos de Ollama | Ollama con error o respuesta mal formada | `Ollama con error o respuesta mal formada: …` (dataset HTTP 500, JSON sin `models`, `models` no lista, texto no JSON) | T-models:150 | 200 → `ModelCatalog.php:105` y `ModelCatalog.php:109` (`validNames`) |
| Disponibilidad de modelos de Ollama | Ficha de un modelo que falla | `Ficha de un modelo que falla: …` | T-models:168 | 200 → `ModelCatalog.php:134` |
| Disponibilidad de modelos de Ollama | Ollama sin modelos descargados | `Ollama sin modelos descargados: …` | T-models:181 | — |
| (design D1, alfabeto seguro) | — | `nombres fuera del alfabeto seguro no entran a la lista…` | T-models:190 | — |
| Modelo elegido por pregunta | Pregunta con un modelo de Ollama disponible | `Pregunta con un modelo de Ollama disponible: …` | T-choice:51 | 200 → `routes/api.php:99`; proveedor → `app/Providers/AssistantServiceProvider.php:51` |
| Modelo elegido por pregunta | Pregunta con un modelo de Ollama disponible (variante de caché, design D3) | `Pregunta con un modelo de Ollama disponible tras pedir la lista: …` | T-choice:71 | caché → `ModelCatalog.php:76` |
| Modelo elegido por pregunta | Modelo simulado sin red | `Modelo simulado sin red: …` | T-choice:84 | 200 → `ModelCatalog.php:39`, `app/Services/Assistant/Llm/LlmProviderResolver.php:29` |
| Modelo elegido por pregunta | Sin modelo usa el valor por defecto | `Sin modelo usa el valor por defecto: …` | T-choice:96 | 200 → `LlmProviderResolver.php:26`, `AssistantServiceProvider.php:27` |
| Modelo elegido por pregunta | Modelo fuera de la lista | `Modelo fuera de la lista: …` (dataset `ollama:modelo-inexistente`, `openai:gpt-4o`, `http://atacante.example/api`) | T-choice:113 | 422 → `app/Http/Requests/Assistant/AskAssistantRequest.php:32`, `app/Rules/AvailableModel.php:19`, `app/Exceptions/ApiExceptionRenderer.php:25` |
| Modelo elegido por pregunta | Modelo descargado sin herramientas | `Modelo descargado sin herramientas: …` | T-choice:129 | 422 → `AvailableModel.php:19`, `ModelCatalog.php:134` |
| Modelo elegido por pregunta | Modelo elegido con Ollama caído | `Modelo elegido con Ollama caído: 422…` | T-choice:139 | 422 → `AvailableModel.php:19`, `ModelCatalog.php:105` |
| Modelo elegido por pregunta | Modelo con tipo inválido | `Modelo con tipo inválido: …` (dataset `7`, `["mock"]`, `null`) | T-choice:148 | 422 → `AskAssistantRequest.php:32` (`bail`, `string`) |
| Modelo elegido por pregunta | Ollama falla al responder con el modelo elegido | `Ollama falla al responder con el modelo elegido: 503…` | T-choice:165 | 503 → `app/Services/Assistant/Llm/OllamaLlmProvider.php:40`, `ApiExceptionRenderer.php:50` |
| Modelo elegido por pregunta | Pregunta sobre un paciente con un modelo de Ollama | `Pregunta sobre un paciente con un modelo de Ollama: …` | T-choice:180 | 200 → `app/Actions/Assistant/AskAssistant.php:46`, `AskAssistant.php:51` |
| Modelo elegido por pregunta | El servidor no recuerda la elección | `El servidor no recuerda la elección: …` | T-choice:198 | — |
| (control del barrido de destinos) | — | `Http::fake registra la URL de cada petición enviada…` | T-choice:211 | — |
| Proveedor configurable por entorno | Sin variable usa el modo simulado | `Sin variable usa el modo simulado: …` (sin cambio) | T-ollama:98 | 200 → `AssistantServiceProvider.php:27` |
| Proveedor configurable por entorno | Ollama con llamada a herramienta | `Ollama con llamada a herramienta: …` (sin cambio) | T-ollama:36 | 200 → `OllamaLlmProvider.php:40` |
| Proveedor configurable por entorno | Ollama caído o lento | `Ollama caído o lento: 503…` (sin cambio) | T-ollama:73 | 503 → `OllamaLlmProvider.php:40`, `ApiExceptionRenderer.php:50` |
| Proveedor configurable por entorno | Proveedor desconocido | `Proveedor desconocido: …` (sin cambio) | T-ollama:110 | 503 → `AssistantServiceProvider.php:27` (rama `default`) |
| Registro de consultas sin contenido | Línea de la consulta | `Línea de la consulta: …, modelo, …` (actualizada) | T-log:27 | — |
| Registro de consultas sin contenido | Línea con un modelo de Ollama | `Línea con un modelo de Ollama: …` | T-log:52 | — |
| Registro de consultas sin contenido | Pregunta sensible fuera del log | `Pregunta sensible fuera del log; …` (sin cambio, con control positivo) | T-log:79 | — |
| Modo simulado determinista (viva) | Misma pregunta, misma respuesta | `Misma pregunta, misma respuesta` | `tests/Feature/Assistant/AssistantEndpointTest.php:342` | — |
| assistant-evaluation (viva) | Todas aciertan con el modo simulado | `Todas aciertan con el modo simulado` | `tests/Feature/Assistant/AssistantEvalCommandTest.php:66` | — |

## 3. Pins `[MUT]` del backend

Procedimiento del encabezado de `tasks.md`; árbol con commit antes de cada pin (`git status --porcelain` vacío salvo
`mutants/`), `php -l` sin errores sobre el archivo mutado, `git apply -R` y árbol vacío otra vez. Comando:
`docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest <archivo> --filter '<filtro>'`.

| n | mutación | Applied → FAILS m/k: prueba | Restored → PASSES k/k |
|---|---|---|---|
| M1 | `ModelCatalog`: sin `declaresTools` (todo modelo de `/api/tags` entra) | FAILS 1/1: T-models `Modelo sin herramientas omitido` | PASSES 1/1 |
| M2 | `ModelCatalog`: `catch (ConnectionException $e) { throw $e; }` en `/api/tags` | FAILS 2/2: T-models `Ollama caído` (el filtro también toma `Lista sin efectos en la base: …con Ollama caído`) | PASSES 2/2 |
| M3 | `ModelCatalog`: sin el corte `remaining <= 0` antes de `/api/show` | FAILS 1/1: T-models `Ollama lento` | PASSES 1/1 |
| M14 | `routes/api.php`: `GET /assistant/models` fuera del grupo `auth:sanctum` | FAILS 1/1: T-models `Lista sin sesión`; control con M14 aplicado: `Modelos con Ollama disponible` PASSES 1/1 | PASSES 1/1 |
| M4 | `AvailableModel`: solo exige texto (pasa siempre) | FAILS 3/3: T-choice `Modelo fuera de la lista` (los tres valores del dataset) | PASSES 3/3 |
| M5 | fábrica de Ollama con `assistant.ollama.model` en vez del nombre elegido | FAILS 1/1: T-choice `Pregunta con un modelo de Ollama disponible: ` (con el filtro sin `: ` FAILS 1/2: la variante de caché no fija el modelo de `/api/chat`) | PASSES 1/1 |
| M6 | `ModelCatalog::contains('mock')` pasa por `available()` | FAILS 1/1: T-choice `Modelo simulado sin red` | PASSES 1/1 |
| M7 | `AssistantQueryLogger`: agrega `question` al contexto | FAILS 1/1: T-log `Línea con un modelo de Ollama` | PASSES 1/1 |
| M8 | `LlmProviderResolver::for(null)` devuelve siempre el proveedor `mock` de la fábrica | FAILS 1/1: T-choice `Sin modelo usa el valor por defecto` | PASSES 1/1 |
| M9 | `ModelCatalog`: sin `remember` (descubre siempre) | FAILS 1/1: T-choice `Pregunta con un modelo de Ollama disponible tras pedir la lista` | PASSES 1/1 |

Parches: `mutants/M1.patch` … `M9.patch`, `mutants/M14.patch`.

## 4. Corridas y evaluación

| Corrida | Comando | Resultado |
|---|---|---|
| Asistente + arquitectura (tarea 2.3) | `… api-tools vendor/bin/pest tests/Feature/Assistant tests/Arch` | 246 passed (1088 assertions) |
| Reglas `arch()` sin cambio | `git diff --exit-code -- software/api/tests/Arch/AssistantArchTest.php` | exit 0 |
| `assistant:eval` simulado (tarea 3.3) | `… -e AI_PROVIDER=mock api-tools php artisan assistant:eval` | `Aciertos: 24/24`, exit 0; entradas del conjunto (`resources/assistant/evaluation-set.json`, `python3 -I`): 24 |
| Deriva OpenAPI tras commit (3.2) | `… api-tools composer openapi` + `git diff --exit-code -- software/api/openapi.json` | exit 0 |
| Ruta nueva en el contrato | `/usr/bin/grep -c '"/assistant/models"' software/api/openapi.json` | 1; control `/usr/bin/grep -c '"/assistant/ask"' software/api/openapi.json`: 1 |
| Tipos (4.1) | `npm --prefix software/web run api:types:check`; `npm --prefix software/web run typecheck` | exit 0; sin errores |
| Cierre backend (3.5) | `… api-tools vendor/bin/pint --test` | PASS, 413 archivos |
| Cierre backend (3.5) | `… api-tools vendor/bin/phpstan analyse --memory-limit=1G` | No errors |
| Cierre backend (3.5) | `… -e AI_PROVIDER=mock api-tools vendor/bin/pest` | 1071 passed (4469 assertions) |

## 5. Barridos del backend

| Barrido | Comando (desde `software/api`) | Resultado | Control positivo |
|---|---|---|---|
| `Log::` en catálogo, elección, resolver y regla | `/usr/bin/grep -c 'Log::' app/Services/Assistant/Llm/ModelCatalog.php app/Services/Assistant/Llm/ModelChoice.php app/Services/Assistant/Llm/LlmProviderResolver.php app/Rules/AvailableModel.php` | 0 | `AssistantQueryLogger.php`: 1 |
| `new` de proveedores concretos fuera del service provider | `/usr/bin/grep -rlE 'new (OllamaLlmProvider\|MockLlmProvider)' app \| /usr/bin/grep -vc AssistantServiceProvider` | 0 | `AssistantServiceProvider.php`: 2 |
| Lectura de `model` fuera del FormRequest | `/usr/bin/grep -rnE "input\('model'\)\|->model\b\|request\(\)" app \| /usr/bin/grep -v Requests/ \| /usr/bin/grep model` | 3, todas legítimas: salida del recurso, el controlador llama a `$request->model()`, carga de `/api/chat` | `validated('model')` en `AskAssistantRequest.php`: 1 |
| Depuración en el diff | `git diff 5504d20..HEAD -- software/api software/web \| /usr/bin/grep -E '^\+' \| /usr/bin/grep -cE '\b(dd\|dump\|var_dump\|ray\|print_r)\(\|console\.log'` | 0 | línea sintética `+ dd($x);` por la misma tubería: 1 |
| `Log::` o `throw` en el catálogo (URL o cuerpos hacia fuera) | `/usr/bin/grep -cE 'Log::\|throw ' app/Services/Assistant/Llm/ModelCatalog.php` | 0 | `base_url` leído en el mismo archivo: 1 |
| Escritura a la base en el catálogo | `/usr/bin/grep -cE 'DB::\|->save\(\|::create\(' app/Services/Assistant/Llm/ModelCatalog.php` | 0 | archivos de `app/Actions` con `DB::`: 11 |

## 6. Humo del stack real (tarea 7.2)

Stack reconstruido: `docker compose -f software/compose.yaml up -d --build --wait api web` → db, api, web `Healthy`.
Local con `AI_PROVIDER=ollama` (`software/.env`) y Ollama del anfitrión encendido.

| Escenario | Comprobación | Archivo:línea | Resultado |
|---|---|---|---|
| IA «Lista de modelos disponibles»: «Lista sin sesión» | `GET /api/assistant/models` anónimo → 401 | `software/docker/smoke/assistant-smoke.sh:100` | PASA, HTTP 401 |
| IA «Lista de modelos disponibles» (`mock` primero) | regente → 200, `.data[0].id == "mock"`, `.data[0].provider == "mock"` | `software/docker/smoke/assistant-smoke.sh:109` | PASA; lista `["mock","ollama:gemma4:e2b-mlx"]` |
| IA «Modelos con Ollama disponible» | `SMOKE_EXPECT_OLLAMA_MODEL` → `index("ollama:" + $m) != null` | `software/docker/smoke/assistant-smoke.sh:112` | PASA con `gemma4:e2b-mlx` |
| IA «Ollama caído» (lista solo `mock`) | `SMOKE_EXPECT_MOCK_ONLY=1` → `map(.id) == ["mock"]` | `software/docker/smoke/assistant-smoke.sh:119` | lo corre 7.4; control negativo abajo |
| IA «Modelo elegido por pregunta»: «Modelo simulado sin red» | `ask` con `"model":"mock"` → 200, `.data.model == "mock"` | `software/docker/smoke/assistant-smoke.sh:126` | PASA, HTTP 200 |
| IA «Modelo fuera de la lista» | `ask` con `"model":"ollama:no-existe"` → 422, `errors.model` no vacío | `software/docker/smoke/assistant-smoke.sh:128` | PASA, HTTP 422 |

| Corrida | Comando | Salida | Comprobaciones / fallas |
|---|---|---|---|
| Sintaxis | `bash -n software/docker/smoke/assistant-smoke.sh` | exit 0 | — |
| Humo del asistente (base sin traslados: crea BORRADOR) | `bash software/docker/smoke/assistant-smoke.sh` | exit 0 | 19 / 0 |
| Con Ollama esperado | `SMOKE_EXPECT_OLLAMA_MODEL=gemma4:e2b-mlx bash software/docker/smoke/assistant-smoke.sh` | exit 0 | 17 / 0 |
| Control negativo (las comprobaciones opcionales muerden) | `SMOKE_EXPECT_MOCK_ONLY=1 SMOKE_EXPECT_OLLAMA_MODEL=no-existe bash …/assistant-smoke.sh` | exit 1 | 18 / 2 (las dos esperadas) |
| Humo completo | `bash software/docker/smoke.sh` | exit 0, `Humo VERDE` | dominios auth 38, stock 15, alerts 18, dispensation 23, transfer 28, assistant 16; fallas 0 |

| Barrido | Comando | Resultado | Control positivo |
|---|---|---|---|
| Secretos en el diff del humo | `git diff -- software/docker/smoke/assistant-smoke.sh \| /usr/bin/grep -E '^\+' \| /usr/bin/grep -ciE '(api[_-]?key\|secret\|token\|password)\s*[:=]'` | 0 | línea sintética `+API_KEY=abc` por el mismo filtro: 1 |

## 6. Reparto de líneas (parte SPA y documentación)

| Tipo | Alcance | Comando (raíz del repo) | Resultado |
|---|---|---|---|
| Producto | `software/web/src` sin `*.test.*` | `git diff --numstat 849ff92..785c250 -- software/web/src \| /usr/bin/grep -v '\.test\.'` (suma) | +227 −18 |
| Prueba | `software/web/src/**/*.test.*` | mismo rango \| `/usr/bin/grep '\.test\.'` (suma) | +375 −16 |
| Documentación | `README.md`, `software/docs/asistente.md` | `git diff --numstat 849ff92..785c250 -- README.md software/docs/asistente.md` | +26 −3 |

## 7. Escenarios AS → prueba

`T-model` = `software/web/src/features/assistant/assistant-model.test.tsx`; `T-page` =
`software/web/src/features/assistant/assistant-page.test.tsx`. Sin THEN anclado nuevo en `assistant-screen`
(el único ancla, «Tope de 500 caracteres», no cambia).

| Requisito AS | Escenario | Prueba | Archivo:línea |
|---|---|---|---|
| Selector de modelo | Selector con Ollama disponible | `Selector con Ollama disponible: …` (por `getByLabelText('Modelo')` y rol `combobox`) | T-model:72 |
| Selector de modelo | Ollama no disponible | `Ollama no disponible: …` | T-model:82 |
| Selector de modelo | Apertura consulta solo la lista | `Apertura consulta solo la lista: …` | T-model:92 |
| Selector de modelo | Carga de la lista | `Carga de la lista: …` | T-model:103 |
| Selector de modelo | Fallo de la lista | `Fallo de la lista (%s): …` (dataset `red`, `server_error`) | T-model:122 |
| Selector de modelo | Pregunta con un modelo de Ollama | `Pregunta con un modelo de Ollama: …` | T-model:136 |
| Selector de modelo | Selector durante una consulta | `Selector durante una consulta: …` | T-model:147 |
| Selector de modelo | Modelo rechazado por el servidor | `Modelo rechazado por el servidor: …` | T-model:167 |
| Selector de modelo | Admin sin consulta de modelos | `Admin sin consulta de modelos: …` | T-model:190 |
| Elección de modelo conservada al recargar | La elección sobrevive a una recarga | `La elección sobrevive a una recarga: …` | T-model:201 |
| Elección de modelo conservada al recargar | Primera carga | `Primera carga: …` | T-model:216 |
| Elección de modelo conservada al recargar | Modelo guardado que ya no está disponible | `Modelo guardado que ya no está disponible: …` | T-model:226 |
| Elección de modelo conservada al recargar | Valor guardado manipulado | `Valor guardado manipulado (%j): …` (dataset `http://atacante.example/api`, `<img src=x onerror=alert(1)>`, texto vacío) | T-model:238 |
| Elección de modelo conservada al recargar | Almacenamiento no disponible | `Almacenamiento no disponible: …` | T-model:255 |
| Modelo que respondió | Respuesta de Ollama | `Respuesta de Ollama: …` | T-model:280 |
| Modelo que respondió | Cambiar el selector no reescribe el historial | `Cambiar el selector no reescribe el historial: …` | T-model:289 |
| Modelo que respondió | Modelo desconocido en la respuesta | `Modelo desconocido en la respuesta: …` | T-model:302 |
| Pantalla Asistente para los roles de operación | Médico abre el asistente desde el menú | sin cambio de aserción (espera "Preguntar" habilitado con `waitFor`) | T-page:66 |
| Pantalla Asistente para los roles de operación | Apertura sin preguntas enviadas | `Apertura sin preguntas enviadas: … la única petición al asistente es la lista de modelos` (actualizada) | T-page:79 |
| Pantalla Asistente para los roles de operación | Acceso directo sin sesión | sin cambio | T-page:89 |
| Pantalla Asistente para los roles de operación | Admin escribe la dirección del asistente | sin cambio | T-page:100 |
| Envío de una pregunta | Pregunta enviada | `Pregunta enviada: … {"question": …, "model": "mock"} …` (actualizada) | T-page:121 |
| Envío de una pregunta | Pregunta vacía | sin cambio | T-page:139 |
| Envío de una pregunta | Pregunta demasiado corta | sin cambio | T-page:151 |
| Envío de una pregunta | Tope de 500 caracteres | sin cambio | T-page:162 |
| Envío de una pregunta | Doble clic produce una sola pregunta | sin cambio | T-page:174 |
| Envío de una pregunta | Enter repetido | sin cambio | T-page:190 |
| Envío de una pregunta | Salto de línea sin envío | sin cambio | T-page:205 |
| Historial de la pantalla solo en memoria | Dos preguntas seguidas | sin cambio | T-page:408 |
| Historial de la pantalla solo en memoria | Undécima pregunta | sin cambio | T-page:425 |
| Historial de la pantalla solo en memoria | Salir y volver vacía el historial | sin cambio | T-page:437 |
| Historial de la pantalla solo en memoria | Pregunta con un documento fuera del navegador persistente | sin cambio | T-page:456 |
| Historial de la pantalla solo en memoria | Almacenamiento solo con el modelo | `Almacenamiento solo con el modelo: …` (nueva) | T-page:485 |
| (S8 viva) Resultado según el outcome | Pregunta sobre un paciente | texto completo de la entrada gana `Respondió: Simulado (sin red)` | T-page:267 |

## 8. Pins `[MUT]` de la SPA

Árbol con commit `60a6e7f` antes de cada pin; `git status --porcelain -- software/web` vacío antes y después
(fuera de `software/web` solo `openspec/DEBT.md` y `software/docker/smoke/assistant-smoke.sh`, de otros agentes, y
`mutants/`). Comando: `cd software/web && npx vitest run src/features/assistant -t '<título>'`.

| n | mutación | Applied → FAILS m/k: prueba | Restored → PASSES k/k |
|---|---|---|---|
| M10 | `resolveModel`: usa el valor guardado sin comprobar la lista | FAILS 1/1: T-model `Modelo guardado que ya no está disponible` | PASSES 1/1 |
| M11 | historial etiquetado con la selección actual (`model.id`) en vez de `data.model` | FAILS 1/1: T-model `Cambiar el selector no reescribe el historial` | PASSES 1/1 |
| M12 | `localStorage` guarda además la pregunta al recibir la respuesta | FAILS 1/1: T-page `Almacenamiento solo con el modelo` | PASSES 1/1 |
| M13 | sin `storeModel` en el `onChange` del selector | FAILS 1/1: T-model `La elección sobrevive a una recarga` | PASSES 1/1 |

Parches: `mutants/M10.patch` … `M13.patch`.

## 9. Corridas de la SPA

| Corrida | Comando | Resultado |
|---|---|---|
| Delta del asistente (antes del commit) | `cd software/web && npx vitest run src/features/assistant` | 3 archivos, 65 passed |
| Cierre frontend (5.9, corrida completa) | `npm run lint && npm run typecheck && npx vitest run` desde `software/web` | lint exit 0; `tsc -b` exit 0; vitest: 33 entornos jsdom (33 archivos), 0 archivos fallidos según `node_modules/.vite/vitest/<hash>/results.json` escrito por esa corrida |
| Conteo del cierre | derivado: base 338 + 20 (`T-model`) + 1 (`api.test.ts`) + 1 (`T-page`) | 360; control: el delta del asistente da 65 = 43 previas + 22 |
| Comandos de la guía (6.1) | `ollama pull gemma4:e2b-mlx`; `ollama show gemma4:e2b-mlx \| /usr/bin/grep -c tools` | exit 0; 1 |
| URL en la guía | `/usr/bin/grep -rnE 'https?://[^ )]*' software/docs/asistente.md` | 0 hits; control: el mismo patrón sobre `software/.env.example` da 1 |

## 10. Barridos de la SPA

| Barrido | Comando (raíz del repo) | Resultado | Control positivo |
|---|---|---|---|
| Consola en líneas agregadas | `git diff 849ff92..HEAD -- software/web/src \| /usr/bin/grep -E '^\+' \| /usr/bin/grep -cE 'console\.(log\|info\|warn\|error\|debug)'` | 0 | línea sintética `+ console.log(x)` por la misma tubería: 1 |
| Almacenamiento del navegador en producto | `/usr/bin/grep -rlE 'localStorage\|sessionStorage' software/web/src \| /usr/bin/grep -v '\.test\.'` | solo `features/assistant/model-preference.ts` | archivos de prueba con el patrón: 4 |
| Colores crudos en archivos tocados | `cat` de `assistant-model-select.tsx model-preference.ts assistant-answer.tsx assistant-page.tsx` \| `/usr/bin/grep -cE '#[0-9a-fA-F]{3,6}\b\|-(red\|blue\|green\|gray\|slate\|zinc\|neutral\|white\|black)(-[0-9]{2,3})?\b\|\[(rgb\|hsl\|oklch)'` | 0 | `className="bg-blue-500"` sintético: 1; tokens `text-muted-foreground` en los mismos archivos: 4 |
| Texto de UI fuera de `strings.ts` | `/usr/bin/grep -nE "[>'\"][¿¡]?[A-ZÁÉÍÓÚ][a-záéíóúñ]+ [a-záéíóúñ]+"` sobre `assistant-model-select.tsx model-preference.ts`, sin comentarios | 0 | `'Cargando modelos ya'` sintético: 1; `'Simulado (sin red)'` en `strings.ts`: 1 |
| `catch` sin relanzar | `/usr/bin/grep -c catch software/web/src/features/assistant/model-preference.ts` | 2, ambos por spec «Almacenamiento no disponible» (sin error visible) | `assistant-model-select.tsx`: 0 |
| HTML crudo | `/usr/bin/grep -rc dangerouslySetInnerHTML software/web/src/features/assistant` (archivos con hits) | 0 | línea sintética: 1 |
| Captura | — | pendiente de 7.3 (Orchestrator, stack reconstruido en 7.1) | — |

## 11. Cierre del Orchestrator (tareas 7.1, 7.3, 7.4, 7.5)

| Paso | Comando o acción | Resultado |
|---|---|---|
| 7.1 | `docker compose -f software/compose.yaml up -d --build --wait` | `/ready` 200; `/assistant` 200 |
| 7.3 humo | `SMOKE_EXPECT_OLLAMA_MODEL=gemma4:e2b-mlx bash software/docker/smoke/assistant-smoke.sh` | exit 0; Comprobaciones 17, fallas 0 |
| 7.3 navegador | regente, `/assistant` desde el menú | Selector «Modelo» en «Simulado (sin red)» al entrar (`captures/selector-inicial-mock.jpg`); opciones `mock` y `ollama:gemma4:e2b-mlx` |
| 7.3 navegador | elegir «Ollama · gemma4:e2b-mlx» y preguntar el ejemplo de stock | Respondida, «Respondió: Ollama · gemma4:e2b-mlx» (`captures/respuesta-ollama.jpg`) |
| 7.3 navegador | recargar `/assistant` | El selector conserva «Ollama · gemma4:e2b-mlx» |
| 7.4 humo, `AI_PROVIDER=ollama` local | `OLLAMA_BASE_URL=http://host.docker.internal:1 docker compose … up -d --wait api`, 35 s, `SMOKE_EXPECT_MOCK_ONLY=1 bash …assistant-smoke.sh` | exit 1: la lista es `["mock"]` (PASA), pero las preguntas antiguas del humo sin `model` usan el proveedor por defecto `ollama` y dan 503 (fallas 4). Comportamiento especificado: sin `model` decide `AI_PROVIDER` |
| 7.4 humo, `AI_PROVIDER=mock` | `AI_PROVIDER=mock OLLAMA_BASE_URL=http://host.docker.internal:1 docker compose … up -d --wait api`, mismo humo | exit 0; «la lista es solo ["mock"]»; Comprobaciones 17, fallas 0 |
| 7.4 navegador | recargar `/assistant` con `ollama:gemma4:e2b-mlx` guardado | Selector en «Simulado (sin red)» y aviso «El modelo que elegiste ya no está disponible. Se usa Simulado (sin red).»; pregunta de stock «Respondió: Simulado (sin red)» (`captures/ollama-caido-vuelve-a-mock.jpg`) |
| Restauración | `docker compose -f software/compose.yaml up -d --wait api` | `AI_PROVIDER=ollama`, `OLLAMA_BASE_URL=http://host.docker.internal:11434` |
| 7.5 | barrido de títulos de escenario de `specs/*/spec.md` contra este archivo | Todos presentes (control: el barrido imprime «FALTA» si se borra una fila) |
