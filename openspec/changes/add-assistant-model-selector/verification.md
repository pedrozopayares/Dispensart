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
