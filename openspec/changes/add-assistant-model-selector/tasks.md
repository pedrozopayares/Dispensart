# Tasks — add-assistant-model-selector (S15)

Borrador del spec-engineer, **refinado por el architect** contra `design.md` (D1–D10). Tier A (disparador: superficie
del asistente; una entrada del cliente decide qué proveedor recibe la pregunta). Pins `[MUT]` declarados en la tabla
siguiente (detalle en design D10).

| Pin | Costura | Tarea |
|---|---|---|
| M1 | filtro `tools` | 1.5 |
| M2 | Ollama caído → solo `mock` | 1.5 |
| M3 | plazo total del descubrimiento | 1.5 |
| M4 | `model` validado contra la lista | 3.4 |
| M5 | proveedor por elección | 3.4 |
| M6 | `mock` sin red | 3.4 |
| M7 | log sin contenido | 3.4 |
| M8 | valor por defecto sin `model` | 3.4 |
| M9 | caché del catálogo | 3.4 |
| M10 | preferencia validada en la SPA | 5.8 |
| M11 | historial por `data.model` | 5.8 |
| M12 | `localStorage` solo con el `id` | 5.8 |
| M13 | persistencia al elegir | 5.8 |
| M14 | `auth:sanctum` en `GET /api/assistant/models` | 1.5 |

Orden obligatorio: 0 → 1 → 2 → 3 (api) → 4 (tipos) → 5 (web) → 7 (cierre). Contrato nuevo entre api y web: **sin
paralelo api ∥ web**. Paralelo permitido: 6 (documentación) en paralelo con 5, tras 3.5. Sin migraciones, sin
escritura de stock: sin tareas de bloqueo ni idempotencia.

Pruebas primero, en rojo, luego el código. API: Pest contra PostgreSQL por la ruta real; Ollama simulado solo en el
borde HTTP (`Http::fake`); `Http::preventStrayRequests()` ya rige en la suite. SPA: Vitest + Testing Library con MSW
(`src/test/http.ts`). El título de cada prueba nueva **empieza por el título literal de su escenario**; los filtros
`--filter` de Pest y `-t` de Vitest usan esos títulos literales. Escenarios citados como «Requisito»: «Escenario» de
`specs/inventory-assistant/spec.md` (IA) y `specs/assistant-screen/spec.md` (AS). Textos de la SPA solo en
`src/lib/strings.ts`; mensajes de la API en `lang/es`.

**Entorno.** `software/.env` fija `AI_PROVIDER=ollama` para el stack local y `api-tools` lo hereda: **todo** comando
Pest de este archivo lleva `-e AI_PROVIDER=mock`. Los comandos se corren desde la raíz del repo salvo los que
empiezan por `cd software/web`.

Presupuesto de suite (tier A): base (0.1) + un cierre del backend (3.5) + un cierre del frontend (5.9) +
confirmación del auditor, dentro del tope de corridas completas de `CLAUDE.md` § Cost discipline; superarlo detiene
y pregunta.

**Procedimiento `[MUT]` (vale para cada pin).** (a) Árbol con commit: `git status --porcelain` vacío, si no, no se
muta. (b) Escribir el parche en `openspec/changes/add-assistant-model-selector/mutants/M<n>.patch` (`git diff` del
cambio mutante) y revertir. (c) `git apply openspec/changes/add-assistant-model-selector/mutants/M<n>.patch`; en PHP,
`docker compose -f software/compose.yaml --profile tools run --rm api-tools php -l <archivo mutado>` sin errores de
sintaxis. (d) Correr la prueba nombrada → FALLA. (e) `git apply -R` del mismo parche; `git status --porcelain` vacío
salvo `mutants/`. (f) Misma prueba → PASA. Fila `| n | mutación | Applied → FAILS m/k | Restored → PASSES k/k |` en
`verification.md`.

## 0. Línea base

- [x] 0.1 Stack sano y suites base en verde antes de tocar código; contrato sin deriva (design D9, D10). Habilita
  todos los escenarios del cambio; ancla de partida de IA «Proveedor configurable por entorno»:
  «Sin variable usa el modo simulado». Verifica: `docker compose -f software/compose.yaml up -d --wait` sano;
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest`
  verde; `cd software/web && npx vitest run` verde;
  `docker compose -f software/compose.yaml --profile tools run --rm api-tools composer openapi:check` y
  `npm --prefix software/web run api:types:check` sin diferencias; resultados en tabla de `journal.md`.

## 1. API — catálogo de modelos y ruta `GET /api/assistant/models`

- [x] 1.1 Arnés de caché (design D3, D10): `phpunit.xml` gana
  `<env name="ASSISTANT_MODELS_CACHE_STORE" value="array" force="true"/>` (cada prueba con caché vacía).
  `config/assistant.php` gana `models.budget_seconds`, `models.cache_ttl_seconds` y `models.cache_store`
  (`env('ASSISTANT_MODELS_CACHE_STORE', 'file')`), con los valores de design D2 y D3. Cimiento de IA
  «Disponibilidad de modelos de Ollama»: «Ollama caído» (sin caché compartida entre pruebas no hay falso verde).
  Verifica: `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools php artisan tinker --execute="echo config('assistant.models.cache_store');"`
  imprime `file`; dentro de Pest, la prueba «Ollama caído» de 1.2 afirma además
  `config('assistant.models.cache_store') === 'array'`.
- [x] 1.2 Pruebas HTTP reales en rojo en `tests/Feature/Assistant/AssistantModelsEndpointTest.php` (design D2, D6):
  IA «Lista de modelos disponibles»: «Modelos con Ollama disponible», «Respuesta sin datos del servidor»,
  «Lista sin sesión», «Lista con proveedor por defecto desconocido», «Lista sin efectos en la base» (vacía la caché
  del catálogo entre los dos estados de Ollama, design D10); IA «Disponibilidad de modelos de Ollama»:
  «Modelo sin herramientas omitido», «Ollama caído», «Ollama lento» (plazo reducido por config + `usleep` en el fake
  de `/api/tags`; afirma `/api/show` no llamado y `timeout` de las opciones ≤ plazo),
  «Ollama con error o respuesta mal formada» (dataset: HTTP 500, JSON sin `models`, texto no JSON),
  «Ficha de un modelo que falla», «Ollama sin modelos descargados». Verifica:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Feature/Assistant/AssistantModelsEndpointTest.php`
  falla por 404 de ruta inexistente, no por error de sintaxis.
- [x] 1.3 `ModelChoice` (design D1: `parse`, `id`, alfabeto del nombre) y `ModelCatalog` (design D2, D3:
  `available`, `contains`, `defaultChoice`; descubrimiento con plazo total, `Http::pool` para `/api/show`, filtro
  `tools`, toda excepción → lista vacía; caché de nombres en el almacén configurado). Cubre IA
  «Disponibilidad de modelos de Ollama»: «Modelo sin herramientas omitido», «Ollama caído», «Ollama lento»,
  «Ollama con error o respuesta mal formada», «Ficha de un modelo que falla», «Ollama sin modelos descargados».
  Verifica: `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Feature/Assistant/AssistantModelsEndpointTest.php --filter 'Modelo sin herramientas omitido|Ollama caído|Ollama lento|Ollama con error o respuesta mal formada|Ficha de un modelo que falla|Ollama sin modelos descargados'`
  verde una vez exista la ruta (1.4).
- [x] 1.4 Ruta `GET /api/assistant/models` (`assistant.models`) en el grupo `auth:sanctum` junto a `assistant.ask`, sin
  `throttle`; `AssistantModelController` invocable de una línea; `AssistantModelResource` con solo `id`, `provider`,
  `name` (design D6). Cubre IA «Lista de modelos disponibles»: «Modelos con Ollama disponible»,
  «Respuesta sin datos del servidor», «Lista sin sesión», «Lista con proveedor por defecto desconocido»,
  «Lista sin efectos en la base». Verifica:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Feature/Assistant/AssistantModelsEndpointTest.php`
  verde completo; `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Arch`
  verde.
- [x] 1.5 Commit en español (p. ej. `feat: la API lista los modelos del asistente disponibles en Ollama`). `[MUT]` M1,
  M2, M3, M14 con el procedimiento del encabezado (design D2, D6, D10). M1 (aceptar todo modelo de `/api/tags`) → IA
  «Modelo sin herramientas omitido» FALLA; M2 (relanzar la `ConnectionException` de `/api/tags`) →
  «Ollama caído» FALLA; M3 (quitar el corte por plazo restante antes de `/api/show`) → «Ollama lento» FALLA; M14
  (sacar `GET /api/assistant/models` del grupo `auth:sanctum` en `routes/api.php`) → «Lista sin sesión» FALLA, y como
  control, con M14 aplicado, «Modelos con Ollama disponible» PASA (la ruta sigue respondiendo; el fallo es solo de
  autenticación). Verifica, por pin:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Feature/Assistant/AssistantModelsEndpointTest.php --filter '<título del escenario>'`
  FALLA aplicado y PASA restaurado; control de M14:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Feature/Assistant/AssistantModelsEndpointTest.php --filter 'Modelos con Ollama disponible'`
  PASA con M14 aplicado; `mutants/M1.patch`, `M2.patch`, `M3.patch`, `M14.patch` presentes; filas en `verification.md`.

## 2. API — proveedor por petición y validación de `model`

- [x] 2.1 Pruebas HTTP reales en rojo en `tests/Feature/Assistant/AssistantModelChoiceTest.php` (design D3, D4, D5):
  IA «Modelo elegido por pregunta»: «Pregunta con un modelo de Ollama disponible», más su variante de caché (título que
  empieza igual y afirma un solo `GET /api/tags` entre `GET /api/assistant/models` y la pregunta, design D3),
  «Modelo simulado sin red» (`AI_PROVIDER=ollama` por config; `Http::assertNothingSent()`),
  «Sin modelo usa el valor por defecto»,
  «Modelo fuera de la lista» (dataset: `ollama:modelo-inexistente`, `openai:gpt-4o`, `http://atacante.example/api`),
  «Modelo descargado sin herramientas», «Modelo elegido con Ollama caído»,
  «Modelo con tipo inválido» (dataset: `7`, `["mock"]`), «Ollama falla al responder con el modelo elegido»,
  «Pregunta sobre un paciente con un modelo de Ollama», «El servidor no recuerda la elección». Verifica:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Feature/Assistant/AssistantModelChoiceTest.php`
  falla por `model` ignorado o `data.model` ausente.
- [x] 2.2 `AvailableModel` (`App\Rules`) y `AskAssistantRequest`: `model` → `sometimes`, `bail`, `string`, `max:220`,
  `AvailableModel`; `rules(ModelCatalog $catalog)`; `model(): ?ModelChoice`; `lang/es/assistant.php` gana
  `model_unavailable` = "El modelo elegido no está disponible." (design D5). Cubre IA «Modelo fuera de la lista»,
  «Modelo descargado sin herramientas», «Modelo elegido con Ollama caído», «Modelo con tipo inválido». Verifica:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Feature/Assistant/AssistantModelChoiceTest.php --filter 'Modelo fuera de la lista|Modelo descargado sin herramientas|Modelo elegido con Ollama caído|Modelo con tipo inválido'`
  verde.
- [x] 2.3 `LlmProviderResolver` (`for(?ModelChoice)`; `null` → enlace `LlmProvider` del contenedor) y fábrica única en
  `AssistantServiceProvider` usada por el enlace por defecto y por el resolver (design D4).
  `AssistantOrchestrator::answer()` recibe el `LlmProvider` por argumento; `AskAssistant::handle(User, string,
  ?ModelChoice = null)` resuelve proveedor e `id`; el controlador pasa `$request->model()`. Cubre IA
  «Pregunta con un modelo de Ollama disponible», «Modelo simulado sin red», «Sin modelo usa el valor por defecto»,
  «Ollama falla al responder con el modelo elegido», «El servidor no recuerda la elección»; sin regresión en IA
  «Proveedor configurable por entorno»: «Sin variable usa el modo simulado», «Ollama con llamada a herramienta»,
  «Ollama caído o lento», «Proveedor desconocido». Verifica:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Feature/Assistant tests/Arch`
  verde (reglas `arch()` de proveedores concretos **sin modificar**:
  `git diff --exit-code -- software/api/tests/Arch/AssistantArchTest.php`).
- [x] 2.4 `data.model` en `AssistantAnswer`/`AssistantAnswerResource` con el `id` sellado por `AskAssistant`, también
  con el filtro previo (design D4). Cubre IA «Pregunta con un modelo de Ollama disponible»,
  «Pregunta sobre un paciente con un modelo de Ollama»; sin regresión en «Modo simulado determinista»:
  «Misma pregunta, misma respuesta» (spec viva). Verifica:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Feature/Assistant/AssistantModelChoiceTest.php`
  verde completo y `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest --filter 'Misma pregunta, misma respuesta'`
  verde.

## 3. API — registro, contrato y cierre

- [x] 3.1 Pruebas en rojo y luego el log con `model` (design D7): IA «Registro de consultas sin contenido»:
  «Línea de la consulta» (actualizada: proveedor `mock`, modelo `mock`), «Línea con un modelo de Ollama» (nueva),
  «Pregunta sensible fuera del log» (sin cambio, con su control positivo). Verifica:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest tests/Feature/Assistant/AssistantQueryLogTest.php`
  verde; el barrido de la prueba encuentra el control positivo.
- [x] 3.2 `openapi.json` regenerado (design D9): operación `GET /assistant/models` con su 401; `model` opcional en el
  cuerpo de `assistant.ask`; `model` en `data`; `errors.model` posible en el 422. Ancla de IA
  «Lista de modelos disponibles»: «Modelos con Ollama disponible», «Lista sin sesión». Verifica:
  `docker compose -f software/compose.yaml --profile tools run --rm api-tools composer openapi` y, tras commit,
  `docker compose -f software/compose.yaml --profile tools run --rm api-tools composer openapi:check` limpio;
  `/usr/bin/grep -c '"/assistant/models"' software/api/openapi.json` distinto de cero, con control positivo
  `/usr/bin/grep -c '"/assistant/ask"' software/api/openapi.json` distinto de cero.
- [x] 3.3 `assistant:eval` sin cambio de comportamiento (sin `model` → `AI_PROVIDER`; design D4). Cubre
  assistant-evaluation «Comando que reporta aciertos»: «Todas aciertan con el modo simulado» (spec viva) e IA
  «Sin modelo usa el valor por defecto». Verifica:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools php artisan assistant:eval`
  código 0, total igual al número de entradas, en tabla de `verification.md`.
- [x] 3.4 Commit en español (p. ej. `feat: el asistente atiende con el modelo elegido entre los disponibles`).
  `[MUT]` M4–M9 con el procedimiento del encabezado (design D3, D4, D5, D7, D10): M4 (`AvailableModel` pasa siempre)
  → IA «Modelo fuera de la lista» FALLA; M5 (fábrica de Ollama con `assistant.ollama.model`) →
  «Pregunta con un modelo de Ollama disponible» FALLA; M6 (`contains('mock')` pasa por `available()`) →
  «Modelo simulado sin red» FALLA; M7 (agregar `question` al log) → «Línea con un modelo de Ollama» FALLA; M8
  (resolver con `null` devuelve siempre `MockLlmProvider`) → «Sin modelo usa el valor por defecto» FALLA; M9 (quitar
  `remember`) → variante de caché de «Pregunta con un modelo de Ollama disponible» FALLA. Verifica, por pin:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest --filter '<título del escenario>'`
  FALLA aplicado y PASA restaurado; `mutants/M4.patch` a `M9.patch`; filas en `verification.md`.
- [x] 3.5 Pint, Larastan y corrida completa de cierre del backend (cuenta en el presupuesto; design D10). Cubre todos
  los escenarios IA del cambio por la suite; ancla nombrada: IA «Registro de consultas sin contenido»:
  «Pregunta sensible fuera del log» (sin regresión en S7). Verifica:
  `docker compose -f software/compose.yaml --profile tools run --rm api-tools vendor/bin/pint --test`,
  `docker compose -f software/compose.yaml --profile tools run --rm api-tools vendor/bin/phpstan analyse`,
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest`
  verdes; cuentas en `journal.md`; commit si hubo ajustes.

## 4. Tipos del contrato

- [x] 4.1 `npm --prefix software/web run api:types` sobre el `openapi.json` de 3.2 (design D9); `src/lib/api-types.ts`
  gana `AssistantModel` (`ResponseOf<'/assistant/models','get'>['data'][number]`) y `AskAssistantBody`
  (`BodyOf<'/assistant/ask','post'>`). Cimiento de AS «Envío de una pregunta»: «Pregunta enviada» (el cuerpo con
  `model` tipa). Verifica: tras commit, `npm --prefix software/web run api:types:check` limpio y
  `npm --prefix software/web run typecheck` sin errores.

## 5. SPA — selector, preferencia e historial

- [ ] 5.1 Textos en `src/lib/strings.ts` (design D8; `assistant.model`: "Modelo", "Simulado (sin red)",
  "Ollama · {name}", "Cargando modelos…", "No se pudo consultar los modelos disponibles. Se usa Simulado (sin red).",
  "El modelo que elegiste ya no está disponible. Se usa Simulado (sin red).", "Respondió: {model}",
  "Modelo desconocido"); `modelLabel(id)` en `assistant-labels.ts`; manejador por defecto `GET /api/assistant/models`
  (solo `mock`) en el `openAssistant` de las pruebas. Cubre AS «Modelo que respondió»:
  «Modelo desconocido en la respuesta» (etiqueta sin el texto recibido). Verifica:
  `cd software/web && npx vitest run src/features/assistant/assistant-page.test.tsx` sigue verde con el manejador por
  defecto (ninguna petición sin manejar).
- [ ] 5.2 Pruebas en rojo en `src/features/assistant/assistant-model.test.tsx` (design D8): AS «Selector de modelo»:
  «Selector con Ollama disponible», «Ollama no disponible», «Apertura consulta solo la lista», «Carga de la lista»,
  «Fallo de la lista», «Pregunta con un modelo de Ollama», «Selector durante una consulta»,
  «Modelo rechazado por el servidor», «Admin sin consulta de modelos». Verifica:
  `cd software/web && npx vitest run src/features/assistant/assistant-model.test.tsx` falla por selector ausente.
- [ ] 5.3 `listAssistantModels()`, `askAssistant({ question, model })`, `useAssistantModels()` (`retry: false`,
  `staleTime` igual al TTL de design D3), `assistant-model-select.tsx` con `NativeSelect` en `Field` sobre la caja;
  deshabilitado en carga y con la pregunta en curso; "Preguntar" espera la lista; `errors.model` junto al selector e
  `invalidateQueries(['assistant','models'])` ante ese 422 (design D8). Cubre AS «Selector de modelo»:
  «Selector con Ollama disponible», «Ollama no disponible», «Apertura consulta solo la lista», «Carga de la lista»,
  «Fallo de la lista», «Pregunta con un modelo de Ollama», «Selector durante una consulta»,
  «Modelo rechazado por el servidor», «Admin sin consulta de modelos». Verifica:
  `cd software/web && npx vitest run src/features/assistant/assistant-model.test.tsx -t 'Selector con Ollama disponible|Ollama no disponible|Apertura consulta solo la lista|Carga de la lista|Fallo de la lista|Pregunta con un modelo de Ollama|Selector durante una consulta|Modelo rechazado por el servidor|Admin sin consulta de modelos'`
  verde.
- [ ] 5.4 Pruebas en rojo y luego la preferencia (design D8; `model-preference.ts`: clave
  `dispensart.assistant.model`, lectura/escritura en `try/catch`, `resolveModel` puro, escritura solo en `onChange`):
  AS «Elección de modelo conservada al recargar»: «La elección sobrevive a una recarga», «Primera carga»,
  «Modelo guardado que ya no está disponible», «Valor guardado manipulado» (cada valor del escenario),
  «Almacenamiento no disponible». Verifica:
  `cd software/web && npx vitest run src/features/assistant/assistant-model.test.tsx -t 'La elección sobrevive a una recarga|Primera carga|Modelo guardado que ya no está disponible|Valor guardado manipulado|Almacenamiento no disponible'`
  verde.
- [ ] 5.5 Pruebas en rojo y luego el modelo en el historial (design D8; etiqueta desde `entry.answer.model`): AS
  «Modelo que respondió»: «Respuesta de Ollama», «Cambiar el selector no reescribe el historial»,
  «Modelo desconocido en la respuesta». Verifica:
  `cd software/web && npx vitest run src/features/assistant/assistant-model.test.tsx -t 'Respuesta de Ollama|Cambiar el selector no reescribe el historial|Modelo desconocido en la respuesta'`
  verde.
- [ ] 5.6 Pruebas existentes de `assistant-page.test.tsx` llevadas a los requisitos modificados (design D8): AS
  «Envío de una pregunta»: «Pregunta enviada» (cuerpo con `model` `mock`); AS
  «Pantalla Asistente para los roles de operación»: «Apertura sin preguntas enviadas» (la única petición al asistente
  es la lista); AS «Historial de la pantalla solo en memoria»:
  «Pregunta con un documento fuera del navegador persistente» y la nueva «Almacenamiento solo con el modelo». Sin
  cambio de aserción y en verde: «Médico abre el asistente desde el menú», «Acceso directo sin sesión»,
  «Admin escribe la dirección del asistente», «Pregunta vacía», «Pregunta demasiado corta»,
  «Tope de 500 caracteres», «Doble clic produce una sola pregunta», «Enter repetido», «Salto de línea sin envío»,
  «Dos preguntas seguidas», «Undécima pregunta», «Salir y volver vacía el historial». Verifica:
  `cd software/web && npx vitest run src/features/assistant/assistant-page.test.tsx` verde completo.
- [ ] 5.7 Revisión de experiencia (design D8): disposición común (título, formulario arriba, resultados abajo), solo
  kit y tokens de tema (S12), selector alcanzable con teclado y con etiqueta asociada. Cubre AS «Selector de modelo»:
  «Selector con Ollama disponible» (consulta por `getByLabelText('Modelo')`). Verifica:
  `cd software/web && npx vitest run src/features/assistant/assistant-model.test.tsx -t 'Selector con Ollama disponible'`
  verde; captura citada en `verification.md` en 7.3.
- [ ] 5.8 Commit en español (p. ej. `feat: la pantalla Asistente permite elegir el modelo y lo recuerda`). `[MUT]`
  M10–M13 con el procedimiento del encabezado, sin `php -l` (design D8, D10): M10 (usar el valor guardado sin
  comprobar la lista) → AS «Modelo guardado que ya no está disponible» FALLA; M11 (etiquetar con la selección actual)
  → «Cambiar el selector no reescribe el historial» FALLA; M12 (guardar además la pregunta) →
  «Almacenamiento solo con el modelo» FALLA; M13 (no escribir en `onChange`) → «La elección sobrevive a una recarga»
  FALLA. Verifica, por pin: `cd software/web && npx vitest run src/features/assistant -t '<título del escenario>'`
  FALLA aplicado y PASA restaurado; `mutants/M10.patch` a `M13.patch`; filas en `verification.md`.
- [ ] 5.9 ESLint, `tsc` y corrida completa de cierre del frontend (cuenta en el presupuesto; design D10). Cubre todos
  los escenarios AS del cambio por la suite; ancla nombrada: AS «Historial de la pantalla solo en memoria»:
  «Pregunta con un documento fuera del navegador persistente» (sin regresión en S8). Verifica:
  `npm --prefix software/web run lint`, `npm --prefix software/web run typecheck`,
  `cd software/web && npx vitest run` verdes; cuentas en `journal.md`.

## 6. Documentación (paralelo con 5, tras 3.5)

- [ ] 6.1 `software/docs/asistente.md` y README (design D2, D3, D4, D8): selector, regla de disponibilidad (descargado
  + `tools`, plazo y caché de design D2 y D3, Ollama caído → solo simulado), `AI_PROVIDER` como valor por defecto para
  preguntas sin `model`, `assistant:eval` y CI; cómo ofrecer un modelo local (`ollama pull` de un modelo con
  herramientas). Cubre IA «Disponibilidad de modelos de Ollama»: «Ollama caído» e IA
  «Sin modelo usa el valor por defecto» (comportamiento documentado). Verifica: comandos documentados corridos tal
  cual; `/usr/bin/grep -rnE 'https?://[^ )]*' software/docs/asistente.md` solo con direcciones de `.env.example`.

## 7. Cierre

- [ ] 7.1 Reconstruir el stack y comprobar disponibilidad (design D6, D8). Cubre AS
  «Pantalla Asistente para los roles de operación»: «Médico abre el asistente desde el menú» (pantalla servida por el
  stack real). Verifica: `docker compose -f software/compose.yaml up -d --build --wait`;
  `curl -fsS -o /dev/null -w '%{http_code}' http://localhost:8090/ready` imprime `200`;
  `curl -fsS -o /dev/null -w '%{http_code}' http://localhost:8090/assistant` imprime `200` (documento de la SPA);
  salida en `journal.md`.
- [x] 7.2 Humo del endpoint en el stack real (design D2, D6): `software/docker/smoke/assistant-smoke.sh` gana, con su
  patrón de tarro de cookies y `expect`: `GET /api/assistant/models` sin sesión → 401; con sesión de regente → 200 y
  `.data[0].id == "mock"`; `POST /api/assistant/ask` con `"model": "mock"` → 200 y `.data.model == "mock"`; si
  `SMOKE_EXPECT_OLLAMA_MODEL` está definida, `.data | map(.id) | index("ollama:" + $m) != null`; si
  `SMOKE_EXPECT_MOCK_ONLY=1`, `.data | map(.id) == ["mock"]`. Cubre IA «Lista de modelos disponibles»:
  «Lista sin sesión», «Modelos con Ollama disponible»; IA «Modelo elegido por pregunta»: «Modelo simulado sin red».
  Verifica: `bash software/docker/smoke/assistant-smoke.sh` sale con código 0 sobre el stack de 7.1; `bash -n` del
  script sin errores.
- [ ] 7.3 Revisión del Orchestrator con Ollama del anfitrión encendido (el stack local usa `AI_PROVIDER=ollama` por
  `software/.env`; la SPA arranca igual en `mock`; design D2, D8): aparece "Ollama · gemma4:e2b-mlx", una pregunta de
  ejemplo responde con "Respondió: Ollama · gemma4:e2b-mlx" y la elección sobrevive a la recarga. Cubre AS
  «La elección sobrevive a una recarga» y «Respuesta de Ollama». Verifica:
  `SMOKE_EXPECT_OLLAMA_MODEL=gemma4:e2b-mlx bash software/docker/smoke/assistant-smoke.sh` sale con código 0;
  capturas en `captures/` citadas en `verification.md` como artefacto.
- [ ] 7.4 Revisión del Orchestrator con Ollama inalcanzable (servicio detenido u `OLLAMA_BASE_URL` a un puerto cerrado;
  esperar a que venza el TTL de design D3): solo "Simulado (sin red)", aviso de modelo ya no disponible si estaba
  guardado, respuestas con "Respondió: Simulado (sin red)" (design D2, D3, D8). Cubre AS
  «Modelo guardado que ya no está disponible» e IA «Ollama caído». Verifica:
  `SMOKE_EXPECT_MOCK_ONLY=1 bash software/docker/smoke/assistant-smoke.sh` sale con código 0; capturas citadas en
  `verification.md` como artefacto; pantalla alcanzable desde el menú (sin ruta huérfana).
- [ ] 7.5 `verification.md` con matrices completas (design D10): una fila escenario → prueba → archivo:línea por cada
  escenario del cambio, columna `cláusula → ruta archivo:línea` para cada THEN anclado, una fila por pin de la tabla
  del encabezado, § 0 con líneas de producto, de prueba y de registro. Cubre todos los escenarios del cambio; ancla
  nombrada: IA «Lista de modelos disponibles»: «Lista sin sesión» (fila `cláusula → ruta archivo:línea`). Verifica:
  `npx openspec validate add-assistant-model-selector --strict` válido.

## Workflow follow-up

- `final-auditor` en modo completo (tier A); GATE 2 solo con APPROVED.
- `/archive add-assistant-model-selector`; `openspec validate --all --strict` limpio después.
