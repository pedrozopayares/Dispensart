# Verification — add-inventory-assistant (S7, tier A)

Fuente: sección backend-implementer de `journal.md`. Árbol medido: `dev` en `b6d6d02` (corrida completa). Prefijos
`AST` (capacidad `inventory-assistant`) y `EVL` (`assistant-evaluation`). Rutas de prueba relativas a
`software/api/tests/Feature/Assistant/` salvo indicación; de código, a `software/api/`. Datos de los escenarios:
`database/seeders/AssistantEvalSeeder.php` (tabla en su docblock), sembrados por `tests/Helpers/Assistant.php`.
Pendiente de devops-implementer: 7.1, 7.2 y 9.1 (filas `EVL-10`–`EVL-12` sin prueba aún).

## 0. Reparto de líneas

Líneas añadidas por S7 desde GATE 1. Comando: `git diff --numstat e62b004^ b6d6d02 -- <alcance> | awk '{a+=$1} END{print a}'`.
`git log e62b004^..b6d6d02 -- software/api software/docs` lista solo commits de S7 (los de S6 en el mismo rango
tocan `software/web` y `openspec/changes/add-operator-screens`, fuera de estos alcances).

| Categoría | Alcance | Líneas |
|---|---|---|
| Producto — API | `software/api/{app,config,database,routes,bootstrap,lang}` | 2755 |
| Datos de evaluación | `software/api/resources/assistant/evaluation-set.json` | 205 |
| Prueba — API | `software/api/tests` | 2086 |
| Generado | `software/api/openapi.json` | 196 |
| Generado | `software/web/src/lib/api-schema.ts` | 130 |
| Documentación | `software/docs/asistente.md` (`wc -w` = 597, límite 600) | 75 |
| Registro | `openspec/changes/add-inventory-assistant/**/*.md` antes de este archivo (`wc -l`) | 912 |
| Registro — parches `[MUT]` | `openspec/changes/add-inventory-assistant/mutants/*.patch` (`wc -l`; 9 archivos) | 129 |

## 1. Matriz escenario → prueba → archivo:línea

| Capacidad | Escenarios en la spec (`/usr/bin/grep -c '^#### Scenario:'`) | Con prueba | Pendientes (devops 7.2) |
|---|---|---|---|
| inventory-assistant | 50 | 50 | 0 |
| assistant-evaluation | 12 | 9 | 3 |

| Casos de prueba del asistente (`pest --list-tests tests/Feature/Assistant tests/Arch/AssistantArchTest.php`) | 191 |
|---|---|

"ruta" = HTTP real con el `mock` real (decorado por `RecordingLlmProvider`, que solo registra). "guionado" = proveedor
de prueba que simula un modelo comprometido (design D15).

| Id | Escenario | Prueba | Archivo:línea |
|---|---|---|---|
| AST-01 | Pregunta respondida | ruta (auxiliar) · herramienta | AssistantEndpointTest.php:42; AssistantToolsTest.php:96 |
| AST-02 | Sin sesión | ruta `SpaClient`, 0 llamadas al proveedor | AssistantEndpointTest.php:57 |
| AST-03 | Sin token CSRF desde la SPA | ruta `SpaClient` + control con token | AssistantEndpointTest.php:66 |
| AST-04 | Pregunta ausente o fuera de longitud | ruta, dataset `sin question`, `vacía`, `2 caracteres`, `501 caracteres`, `numérica`; frontera 500 | AssistantEndpointTest.php:77, :91 |
| AST-05 | Exceso de preguntas | ruta (auditor), la 21 sin llamada al proveedor; otro usuario sigue 200 | AssistantErrorRenderingTest.php:31 |
| AST-06 | Consulta sin efectos en la base | ruta (regente), una pregunta por herramienta | AssistantEndpointTest.php:95 |
| AST-07 | Catálogo ofrecido al modelo | catálogo · carga enviada a Ollama (auxiliar) · mismas herramientas entre preguntas | ToolCatalogTest.php:15; OllamaLlmProviderTest.php:36; AssistantEndpointTest.php:365 |
| AST-08 | Herramienta fuera del catálogo | servicio guionado (`approve_transfer`, `run_sql`, `get_patient`) · ronda mixta · ruta · registro | AssistantOrchestratorTest.php:121, :137; AssistantEndpointTest.php:299; ToolCatalogTest.php:32 |
| AST-09 | Argumentos fuera de esquema | validador (`sql`, `user_id`, `role`, bodega numérica, …) · ruta · servicio | ToolCatalogTest.php:38; AssistantEndpointTest.php:311; AssistantOrchestratorTest.php:58 |
| AST-10 | Escritura dentro de una herramienta | ejecutor, dataset `insert`, `update`, `delete` · sin fuga del modo | ReadOnlyToolRunnerTest.php:20, :32 |
| AST-11 | Auditor consulta existencias | ruta | AssistantEndpointTest.php:112 |
| AST-12 | Médico pregunta por inventario | ruta (mensaje exacto, carga posterior sin lotes ni cantidades) · ejecutor sin consultas | AssistantEndpointTest.php:119; ReadOnlyToolRunnerTest.php:49 |
| AST-13 | Admin pregunta por traslados | ruta · ejecutor | AssistantEndpointTest.php:132; ReadOnlyToolRunnerTest.php:49 |
| AST-14 | Rol pedido por el modelo ignorado | ruta guionado (médico) · servicio sin consulta a `stocks` · validador | AssistantEndpointTest.php:139; AssistantOrchestratorTest.php:69; ToolCatalogTest.php:77 |
| AST-15 | Pregunta de ejemplo de la parte C | ruta (regente) · herramienta | AssistantEndpointTest.php:151; AssistantToolsTest.php:41 |
| AST-16 | Plazo no indicado | herramienta (`days` 90 efectivo) · ruta · regla del `mock` | AssistantToolsTest.php:52; AssistantEndpointTest.php:161; MockLlmProviderTest.php:47 |
| AST-17 | Lote vencido con existencia | ruta (auditor) · herramienta · composición | AssistantEndpointTest.php:170; AssistantToolsTest.php:60; OutcomeAndAnswerTest.php:62 |
| AST-18 | Plazo fuera de rango | ruta (regente) · validador (`5000`, `0`, `366`, `"60"`, decimal) | AssistantEndpointTest.php:176; ToolCatalogTest.php:63 |
| AST-19 | Producto inexistente | ruta (auxiliar) · herramienta | AssistantEndpointTest.php:184; AssistantToolsTest.php:70 |
| AST-20 | Total disponible sin vencidos | ruta (regente) · herramienta · composición | AssistantEndpointTest.php:192; AssistantToolsTest.php:87; OutcomeAndAnswerTest.php:48 |
| AST-21 | Producto sin existencias en la bodega | ruta (auditor) · herramienta con control | AssistantEndpointTest.php:199; AssistantToolsTest.php:104 |
| AST-22 | Producto bajo su mínimo | ruta (auxiliar) · herramienta · composición | AssistantEndpointTest.php:206; AssistantToolsTest.php:112; OutcomeAndAnswerTest.php:74 |
| AST-23 | Existencia igual al mínimo | ruta (regente) · herramienta con control | AssistantEndpointTest.php:212; AssistantToolsTest.php:118 |
| AST-24 | Traslado recibido parcialmente | ruta (auxiliar) · herramienta · composición | AssistantEndpointTest.php:220; AssistantToolsTest.php:125; OutcomeAndAnswerTest.php:82 |
| AST-25 | Conteo por estado | ruta (auditor) · herramienta · composición | AssistantEndpointTest.php:229; AssistantToolsTest.php:134; OutcomeAndAnswerTest.php:96 |
| AST-26 | Traslado inexistente | ruta (regente) · herramienta | AssistantEndpointTest.php:237; AssistantToolsTest.php:147 |
| AST-27 | Respuesta inventada sin herramientas | ruta guionado · servicio guionado | AssistantEndpointTest.php:245; AssistantOrchestratorTest.php:46 |
| AST-28 | Pregunta ajena al inventario | ruta (auxiliar) | AssistantEndpointTest.php:255 |
| AST-29 | Pedido de escritura | ruta (regente), ambas frases, foto de la base igual | AssistantEndpointTest.php:262 |
| AST-30 | Pedido de SQL libre | ruta (auditor), registro de consultas sin `select * from users` | AssistantEndpointTest.php:276 |
| AST-31 | No sé responder | ruta guionado (`invalid_arguments`) · servicio · tabla (`solo failed`, `solo invalid_arguments`) | AssistantEndpointTest.php:288; AssistantOrchestratorTest.php:58; OutcomeAndAnswerTest.php:19 |
| AST-32 | Pregunta sobre un paciente | ruta (regente) con control de 1 llamada · servicio, dataset `paciente`, `prescripción`, `receta` | AssistantDefenseEndpointTest.php:80; AssistantOrchestratorTest.php:227 |
| AST-33 | Número de documento en la pregunta | ruta (auxiliar) · servicio, dataset `documento`, `documento con puntos`; umbral 6 dígitos | AssistantDefenseEndpointTest.php:92; AssistantOrchestratorTest.php:227, :247 |
| AST-34 | Carga del proveedor sin datos de pacientes | ruta, 4 herramientas, barrido de nombre, documento, teléfono y nacimiento + control `9999000777` en `notes` | AssistantDefenseEndpointTest.php:99 |
| AST-35 | Herramientas sin acceso a tablas de pacientes | arch por espacio de nombres + barrido S1 (§ 4) | tests/Arch/AssistantArchTest.php:27, :31 |
| AST-36 | Observación maliciosa en un traslado | ruta (auxiliar), foto de la base igual | AssistantDefenseEndpointTest.php:25 |
| AST-37 | Observación solo como dato | ruta (carga del `mock`) · servicio guionado · sobre no falsificable | AssistantDefenseEndpointTest.php:40; AssistantOrchestratorTest.php:173, :196 |
| AST-38 | Modelo comprometido pide escritura | ruta guionado · servicio guionado | AssistantDefenseEndpointTest.php:61; AssistantOrchestratorTest.php:155 |
| AST-39 | Pregunta que intenta redefinir las reglas | ruta (auditor) · servicio guionado | AssistantEndpointTest.php:365; AssistantOrchestratorTest.php:215 |
| AST-40 | Dos herramientas dentro del límite | ruta guionado · servicio guionado | AssistantEndpointTest.php:319; AssistantOrchestratorTest.php:81 |
| AST-41 | Modelo en bucle | ruta guionado · servicio guionado (tope de seguridad 12) · 6 llamadas en una ronda | AssistantEndpointTest.php:328; AssistantOrchestratorTest.php:96, :110 |
| AST-42 | Sin variable usa el modo simulado | ruta, dataset `nula`, `vacía`, `Http::assertNothingSent` | OllamaLlmProviderTest.php:98 |
| AST-43 | Ollama con llamada a herramienta | ruta con `Http::fake`, argumentos objeto y texto JSON | OllamaLlmProviderTest.php:36 |
| AST-44 | Ollama caído o lento | ruta con `Http::fake`: conexión rechazada, HTTP 500, tiempo agotado, JSON sin `message` · render 503 | OllamaLlmProviderTest.php:73; AssistantErrorRenderingTest.php:18 |
| AST-45 | Proveedor desconocido | ruta (regente) + `GET /api/stock` 200 | OllamaLlmProviderTest.php:110 |
| AST-46 | Misma pregunta, misma respuesta | ruta (regente) · proveedor | AssistantEndpointTest.php:342; MockLlmProviderTest.php:75 |
| AST-47 | Tildes y mayúsculas indiferentes | ruta (auxiliar) · proveedor | AssistantEndpointTest.php:350; MockLlmProviderTest.php:69 |
| AST-48 | Pregunta sin intención reconocible | ruta (auditor) · proveedor | AssistantEndpointTest.php:358; MockLlmProviderTest.php:47 |
| AST-49 | Línea de la consulta | canal real a archivo, `X-Correlation-Id: traza-asistente` | AssistantQueryLogTest.php:26 |
| AST-50 | Pregunta sensible fuera del log | canal real a archivo + control positivo del documento | AssistantQueryLogTest.php:48 |
| EVL-01 | Cobertura mínima | archivo versionado | EvaluationSetTest.php:17 |
| EVL-02 | Entrada incompleta | comando, dataset `sin outcome`, `herramienta fuera del catálogo`, `id repetido`, `menos de 10 entradas` | EvaluationSetTest.php:37 |
| EVL-03 | Todas aciertan con el modo simulado | comando · CLI `Aciertos: 23/23`, código 0 (§ 5) | AssistantEvalCommandTest.php:56 |
| EVL-04 | Una respuesta equivocada cuenta como fallo | comando con fragmento alterado · M9 (regla del `mock`) | AssistantEvalCommandTest.php:67 |
| EVL-05 | Archivo ausente o mal formado | comando, dataset `ausente`, `JSON inválido` | AssistantEvalCommandTest.php:83 |
| EVL-06 | Proveedor no disponible | comando con Ollama caído (`Http::fake`); ver desvío en § 6 | AssistantEvalCommandTest.php:101 |
| EVL-07 | Sin cambios persistentes | comando sobre la siembra completa; base efímera borrada | AssistantEvalCommandTest.php:118 |
| EVL-08 | Datos operativos alterados | ajuste + traslado operativos previos | AssistantEvalCommandTest.php:130 |
| EVL-09 | Base de evaluación no creable | dataset `usuario sin acceso para crearla`, `nombre igual al de la base operativa` | AssistantEvalCommandTest.php:150 |
| EVL-10 | Asistente correcto | pendiente 7.2 (devops) | — |
| EVL-11 | Regresión del asistente | pendiente 7.2 (devops); en local, M9 deja el CLI en 17/23, código 1 | — |
| EVL-12 | Sin secretos ni llaves | pendiente 7.2 (devops) | — |

| Prueba de apoyo (no es escenario) | Archivo:línea |
|---|---|
| precedencia de outcome por tabla (incluye la mezcla `ok` + `denied`, punto abierto 2 de design D4) | OutcomeAndAnswerTest.php:19 |
| comparador: primera expectativa incumplida en el orden de design D14 | EvaluationSetTest.php:68 |
| tabla de reglas del `mock` (17 preguntas) | MockLlmProviderTest.php:47 |
| plazo total agotado → `AssistantUnavailable` | AssistantOrchestratorTest.php:255 |
| 503 deja exactamente una línea `assistant_unavailable` y ninguna de error | AssistantQueryLogTest.php:68 |
| bodega ambigua → vacío, nunca la primera | AssistantToolsTest.php:81 |
| `get_transfer_status`: lista blanca sin usuarios ni actores | AssistantToolsTest.php:151 |
| solo el proveedor de servicios conoce `MockLlmProvider` / `OllamaLlmProvider` (control positivo en § 4, S9) | tests/Arch/AssistantArchTest.php:18, :22 |

## 2. Ancla de transporte: cláusula → ruta archivo:línea

| Comando | Hits | Control positivo |
|---|---|---|
| `/usr/bin/grep -c 'ancla:' specs/inventory-assistant/spec.md` | 34 (una fila por hit abajo) | — |
| `/usr/bin/grep -c 'ancla:' specs/assistant-evaluation/spec.md` | 0 | el mismo comando sobre `inventory-assistant` da 34 |

| # | Escenario | Ancla de la spec | Código archivo:línea |
|---|---|---|---|
| 1 | Pregunta respondida | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 2 | Sin sesión | middleware `auth:sanctum` | routes/api.php:37; app/Exceptions/ApiExceptionRenderer.php:24 |
| 3 | Sin token CSRF desde la SPA | middleware CSRF de S1 | bootstrap/app.php:50, :57; app/Exceptions/ApiExceptionRenderer.php:64 |
| 4 | Pregunta ausente o fuera de longitud | FormRequest de la pregunta | app/Http/Requests/Assistant/AskAssistantRequest.php:20; app/Exceptions/ApiExceptionRenderer.php:25 |
| 5 | Exceso de preguntas | limitador de la ruta del asistente + render de ThrottleRequestsException | routes/api.php:99; app/Providers/AssistantServiceProvider.php:42; app/Exceptions/ApiExceptionRenderer.php:49 |
| 6 | Herramienta fuera del catálogo | orquestador del asistente | app/Services/Assistant/AssistantOrchestrator.php:69–73 |
| 7 | Argumentos fuera de esquema | validador de argumentos de herramientas | app/Services/Assistant/Tools/ToolArgumentValidator.php:23–50; app/Services/Assistant/AssistantOrchestrator.php:104 |
| 8 | Auditor consulta existencias | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 9 | Médico pregunta por inventario | autorización de herramientas del asistente | app/Services/Assistant/Tools/ReadOnlyToolRunner.php:29 |
| 10 | Admin pregunta por traslados | autorización de herramientas del asistente | app/Services/Assistant/Tools/ReadOnlyToolRunner.php:29 |
| 11 | Rol pedido por el modelo ignorado | validador de argumentos de herramientas | app/Services/Assistant/Tools/ToolArgumentValidator.php:23–50; app/Services/Assistant/AssistantOrchestrator.php:104 |
| 12 | Pregunta de ejemplo de la parte C | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 13 | Plazo fuera de rango | validador de argumentos de herramientas | app/Services/Assistant/Tools/ToolArgumentValidator.php:23–50; app/Services/Assistant/AssistantOrchestrator.php:104 |
| 14 | Producto inexistente | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 15 | Producto sin existencias en la bodega | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 16 | Existencia igual al mínimo | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 17 | Traslado inexistente | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 18 | Respuesta inventada sin herramientas | orquestador del asistente | app/Services/Assistant/AssistantOrchestrator.php:64–66, :90 |
| 19 | Pregunta ajena al inventario | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 20 | Pedido de escritura | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 21 | Pedido de SQL libre | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 22 | No sé responder | orquestador del asistente | app/Services/Assistant/AssistantOrchestrator.php:90, :102–104 |
| 23 | Pregunta sobre un paciente | filtro previo del asistente | app/Services/Assistant/QuestionPreFilter.php:22–23; app/Actions/Assistant/AskAssistant.php:39 |
| 24 | Número de documento en la pregunta | filtro previo del asistente | app/Services/Assistant/QuestionPreFilter.php:22–23; app/Actions/Assistant/AskAssistant.php:39 |
| 25 | Observación maliciosa en un traslado | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 26 | Modelo comprometido pide escritura | orquestador del asistente | app/Services/Assistant/AssistantOrchestrator.php:69–73 |
| 27 | Pregunta que intenta redefinir las reglas | ruta `POST /api/assistant/ask` | routes/api.php:98; app/Http/Controllers/Assistant/AssistantController.php:16; app/Actions/Assistant/AskAssistant.php:39 |
| 28 | Dos herramientas dentro del límite | orquestador del asistente | app/Services/Assistant/AssistantOrchestrator.php:76–85 |
| 29 | Modelo en bucle | orquestador del asistente | app/Services/Assistant/AssistantOrchestrator.php:53–56, :79–81 |
| 30 | Sin variable usa el modo simulado | enlace del proveedor por configuración | app/Providers/AssistantServiceProvider.php:26–27 |
| 31 | Ollama con llamada a herramienta | proveedor Ollama | app/Services/Assistant/Llm/OllamaLlmProvider.php:72, :78 |
| 32 | Ollama caído o lento | proveedor Ollama + render de AssistantUnavailable | app/Services/Assistant/Llm/OllamaLlmProvider.php:39–45; app/Exceptions/ApiExceptionRenderer.php:50; bootstrap/app.php:78 |
| 33 | Proveedor desconocido | enlace del proveedor por configuración | app/Providers/AssistantServiceProvider.php:34 |
| 34 | Pregunta sin intención reconocible | proveedor simulado | app/Services/Assistant/Llm/MockLlmProvider.php:26, :67 |

## 3. [MUT]

Parches en `mutants/Mn.patch` (`git apply` desde la raíz). Cada uno: aplicado → `php -l` sin errores de sintaxis
→ prueba filtrada FALLA → `git checkout` del archivo → la misma prueba PASA.

| n | Mutación | Aplicado → FALLA m/k: prueba | Restaurado → PASA k/k |
|---|---|---|---|
| M1 | ReadOnlyToolRunner.php:35 sin `SET LOCAL transaction_read_only = on` | 3/3: Escritura dentro de una herramienta (`insert`, `update`, `delete` quedan `ok`, no `failed`) | 3/3 |
| M2 | ToolRegistry.php `find()` → `?? array_values($this->tools)[0]` (registro abierto: todo nombre ejecuta una herramienta) | 10/10: Herramienta fuera del catálogo (5 del registro, 3 del servicio, ronda mixta, ruta) | 10/10 |
| M3 | ReadOnlyToolRunner.php:29–31 sin el chequeo de la Policy | 2/2: Médico pregunta por inventario (ejecutor `ok`; ruta `answered`, no `not_permitted`) | 2/2 |
| M4 | GetStockTool.php importa y usa `App\Models\Patient` | 1/2: los servicios del asistente no usan modelos de pacientes (la regla de `App\Actions\Assistant` sigue verde, como debe) | 2/2 |
| M5 | QuestionPreFilter.php `blocks()` → `return false` | 6/6: Pregunta sobre un paciente (5 del servicio, 1 de la ruta: el proveedor recibe la pregunta) | 6/6 |
| M6 | AssistantOrchestrator.php:64 instrucciones + contenido de los mensajes `tool` | 2/2: Observación solo como dato (la nota aparece en `system`) | 2/2 |
| M7 | AssistantOrchestrator.php:73 `break` → anexar ronda y `continue` | 2/2: Modelo comprometido pide escritura (el proveedor recibe 3 rondas, no 2) | 2/2 |
| M8 | AssistantOrchestrator.php:79–82 sin el límite de 4 llamadas | 2/2: Modelo en bucle (5 llamadas registradas) | 2/2 |
| M9 | MockLlmProvider.php intención `venc\|caduc` → `get_stock` | 2/2: Todas aciertan con el modo simulado (código 1) y Una respuesta equivocada cuenta como fallo (más de una fila en fallo); CLI `Aciertos: 17/23`, código 1 | 2/2 |

| Hallazgo de un `[MUT]` | Arreglo | Commit |
|---|---|---|
| M4 sobrevivía: `arch()->expect([ns1, ns2])->not->toUse(...)` de Pest pasa con un arreglo de objetivos aunque haya uso (sonda: misma regla con un solo espacio de nombres falla) | una regla por espacio de nombres; M4 repetido → 1/2 FALLA | `b57602a` |

## 4. Barridos (`/usr/bin/grep`, desde `software/api`)

| n | Comando | Resultado | Control positivo |
|---|---|---|---|
| S1 | `/usr/bin/grep -rnE 'Patient\|Prescription\|Dispensation\|patients\|prescriptions\|dispensations\|patient_access' app/Services/Assistant app/Actions/Assistant \| wc -l` | 0 | mismo patrón con `-rl` sobre `app/Actions/Dispensation` = 2 archivos; `find -L app/Services/Assistant app/Actions/Assistant -type f \| wc -l` = 39 archivos leídos |
| S2 | `/usr/bin/grep -rn 'TransferResource\|creator\|requester\|approver' app/Services/Assistant` | 1: GetTransferStatusTool.php:14 (docblock que declara la exclusión) | `-rln 'TransferResource' app/Http/Controllers/Transfers` = 2 |
| S3 | `/usr/bin/grep -rln 'Facades\\Http;' app` | 1: `app/Services/Assistant/Llm/OllamaLlmProvider.php` (única salida de red) | el mismo hit es el control |
| S4 | `/usr/bin/grep -rn 'Log::' app/Services/Assistant app/Actions/Assistant app/Console app/Providers/AssistantServiceProvider.php` | 2: AssistantQueryLogger.php:18 (`assistant.query`, sin pregunta ni respuesta), EvaluationDatabase.php:87 (aviso sin contexto) | los 2 hits son el control |
| S5 | `/usr/bin/grep -rnE '\b(dd\|dump\|var_dump\|ray\|print_r)\(' app/Services/Assistant app/Actions/Assistant app/Console tests/Feature/Assistant tests/Support tests/Helpers/Assistant.php \| wc -l` | 0 | mismo patrón sobre un archivo temporal con `dump($x);` = 1 |
| S6 | `/usr/bin/grep -rniE 'api[_-]?key\|secret\|bearer\|password\|token' config/assistant.php app/Services/Assistant/Llm \| wc -l` | 0 | mismo patrón sobre un archivo temporal con `OPENAI_API_KEY=x` = 1 |
| S7 | `/usr/bin/grep -rnE 'TODO\|FIXME\|XXX' app/Services/Assistant app/Actions/Assistant app/Console tests/Feature/Assistant \| wc -l` | 0 | mismo patrón sobre un archivo temporal con `// TODO x` = 1 |
| S8 | `git diff --exit-code -- software/api/openapi.json` tras `composer openapi` (desde la raíz) | código 0, sin deriva | el export anterior a `df5f321` sí difería (196 líneas en § 0) |
| S9 | `toOnlyBeUsedIn` con una clase temporal `App\Probe\Leak` que recibe `MockLlmProvider` | la regla falla con la clase presente; verde al borrarla | — (es el control de AssistantArchTest.php:18) |

## 5. Corridas

| Corrida | Árbol | Comando | Resultado |
|---|---|---|---|
| Completa de cierre (9.2), única | `b6d6d02` | `docker compose -f software/compose.yaml --profile tools run --rm api-tools sh -c 'vendor/bin/pint --test && vendor/bin/phpstan analyse … && vendor/bin/pest --compact'` | Pint 396 archivos sin cambios; Larastan 0 errores; Pest 987 pasan, 0 fallan (3993 aserciones), 121.40 s; código 0 |
| `assistant:eval` con `mock` (8.1, comando del documento) | `b6d6d02` | `docker compose -f software/compose.yaml --profile tools run --rm api-tools php artisan assistant:eval` | `Aciertos: 23/23`, código 0; `pg_database` sin `*_assistant_eval` después |
| `assistant:eval` con `AI_PROVIDER=ollama` sin Ollama en el anfitrión (8.1, segundo comando del documento) | `b6d6d02` | `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=ollama api-tools php artisan assistant:eval` | `Aciertos: 2/23`, código 1 (las 2 filas que el filtro previo responde sin proveedor) |
| Deriva de tipos del cliente | `b6d6d02` | `npm run api:types:check` en `software/web` | código 0 |
| Lint del contrato | `df5f321` | `npm run openapi:lint` (raíz) | válido |

## 6. Desvíos y decisiones

| Tema | Decisión |
|---|---|
| EV «Proveedor no disponible» dice "cada fila dice fallo"; las 2 preguntas de pacientes las responde el filtro previo sin proveedor y aciertan | la prueba afirma: toda fila que llega al proveedor falla con `asistente no disponible`, código 1, sin excepción; las 2 filtradas aciertan. Ajuste de redacción para el spec-engineer (no cambia código) |
| design D1 nombra `App\Http\Controllers\AssistantController` | `App\Http\Controllers\Assistant\AssistantController`, como el resto de controladores del repo |
| design D2 `ChatRequest{system, messages, tools}` | gana `timeoutSeconds` (tiempo restante del plazo de D7) |
| `AskAssistantRequest` sin `authorize()` | toda sesión pregunta (supuesto 2); un `authorize()` hacía que Scramble documentara un 403 que la ruta no devuelve |
