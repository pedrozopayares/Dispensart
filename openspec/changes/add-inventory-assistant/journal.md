# Journal — add-inventory-assistant (S7)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv
- Fase: /proposal adelantada. Apply espera el archivo de S6.
- Tier objetivo según ROADMAP: A.

## 2026-10-07 — spec-engineer: proposal, deltas y tareas

- Producido: `proposal.md`, `specs/inventory-assistant/spec.md` (14 requisitos), `specs/assistant-evaluation/spec.md`
  (4 requisitos), `tasks.md` (tier A, 33 tareas, `[MUT]` M1–M9). Todo requisito con escenario negativo.
  `openspec validate add-inventory-assistant --strict` válido.
- Ancla de transporte: `/usr/bin/grep -rnE '<patrón de CYCLE-TIERS>' specs/` → 34 hits, 34 con `[ancla: …]`, 0 sin
  ancla (control positivo: el filtro `ancla:` cuenta los mismos 34 hits).
- Capacidades nuevas `inventory-assistant` y `assistant-evaluation`; ninguna modificada (S0–S5 no están vivas; el
  paso de CI vive en `assistant-evaluation`, sin tocar `ci-pipeline`).
- Supuestos (7, en `proposal.md`): sin panel SPA (condición 1 de la preaprobación); endpoint abierto a toda sesión
  con autorización por herramienta (`medico`/`admin` → `not_permitted`, no 403); sin proveedor de pago, Ollama del
  anfitrión sin servicio compose; `notes` viaja al modelo como dato delimitado (proveedor local); ventana 90 días,
  rango 1–365; 20 preguntas/min y 503 `assistant_unavailable`; solo español.
- Sin escenarios de concurrencia ni idempotencia: el cambio no escribe stock (transacción de solo lectura).
- Preguntas abiertas para el Orchestrator / architect:
  1. Mecanismo de aislamiento de `assistant:eval` (EV «Evaluación aislada…»): base propia, transacción revertida u
     otro; S4 no siembra traslados, la evaluación necesita los suyos (incluida la observación maliciosa).
  2. Datos parciales (`ok` + `denied` en una consulta) no son alcanzables con el mapa de S1: todo rol con
     `inventory.view` tiene `transfers.view` y viceversa. La precedencia queda escrita; no hay escenario de mezcla.
  3. Códigos nuevos de rechazo `too_many_requests` (429) y `assistant_unavailable` (503) extienden la tabla D5 de S1;
     sin `MODIFIED` porque S1 no está vivo. Revisar al archivar.
  4. Modelo Ollama por defecto con soporte de herramientas: lo fija `design.md`.
- Ninguna decisión de ADR ni regla RN-xx debilitada.

## 2026-10-07 — architect: design.md y refinamiento de tareas

- Producido: `design.md` (D1–D15, contrato de 1 endpoint, sin migraciones). `tasks.md` refinado: sección 3 reordenada
  (3.1 `OutcomeResolver` + `AnswerComposer` puros antes del orquestador), plantillas de respuesta movidas del `mock` al
  servidor, mecanismos de D5/D14 en 1.3/6.2, marcas de paralelismo. `[MUT]` M1–M9 sin cambios.
  `openspec validate add-inventory-assistant --strict` válido.
- Decisiones: `answer` compuesto por el servidor desde los datos, texto del modelo descartado siempre (D8; rechazado:
  texto del modelo + post-chequeo). Solo lectura por `SET LOCAL transaction_read_only` en punto de guardado + reversión
  siempre (D5; rechazado: rol de base `SELECT`-only, a S8). Autorización con la Policy de la fuente, no literal de
  capacidad (D4). Catálogo fijo + subconjunto de JSON Schema propio, sin dependencia (D3; rechazado `opis/json-schema`).
  Sobre con `JSON_HEX_TAG` en vez de nonce (D9). Ollama nativo `/api/chat`, `qwen2.5:3b`, plazo total 50 s (D11;
  rechazado endpoint compatible OpenAI). Sin paquete LLM (rechazado `prism-php/prism`).
- Puntos abiertos del spec-engineer: (1) base efímera `{DB_DATABASE}_assistant_eval` creada/migrada/sembrada/borrada por
  corrida, nombre derivado, guardia contra la base operativa (D14; rechazados: transacción revertida — nombres de bodega
  únicos y kardex de solo inserción —, esquema temporal, SQLite). (2) Mezcla `ok` + `denied` inalcanzable con el mapa de
  S1: se fija en la prueba unitaria de `OutcomeResolver` (3.1), sin escenario. (3) `too_many_requests` 429 y
  `assistant_unavailable` 503 extienden la tabla D5 de S1 (D12); nota para el archivo de S1.
- Toca código de S5: la consulta de vencimiento gana `days`/`productId` opcionales con valores por defecto idénticos
  (D6); no regresión = pruebas de S5 intactas.
- Riesgos: falso verde del arnés (D15, M9, T ≥ 10); calidad/latencia del modelo local (D8, plazo, `mock` en CI); `notes`
  con dato personal tecleado llega al proveedor local (spec lo exige; nunca a `answer` ni log). Menor: `CREATEDB`
  requerido por la evaluación.
- Sin dependencia nueva, ADR intacto, RN-xx sin debilitar.

## 2026-10-07 — spec-engineer: corrección de hallazgos del spec-validator

- `proposal.md` recortado a 435 palabras (`wc -w`).
- Verificaciones ejecutables en 0.1, 1.1, 4.4, 5.3, 7.1 y 8.1 (`docker compose -f software/compose.yaml --profile tools run --rm api-tools …`); 9.1 cita escenarios y comandos.
- EV «Evaluación aislada…» gana escenario negativo «Base de evaluación no creable» (código 2, base operativa intacta; design D14); lo cubre 6.2.
- Orden y pins del architect intactos. `openspec validate add-inventory-assistant --strict` válido; ancla de transporte: 0 hits sin ancla.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-inventory-assistant` tras correcciones de prosa (largo de la propuesta,
  comandos ejecutables, escenario negativo de aislamiento). Ancla de transporte: 34 hits, 34 con ancla.
- Condición 1 (alcance exacto de S7): OK. Interfaz de proveedor, mock y Ollama, herramientas de solo
  lectura con permisos por rol, defensa contra inyección, set de evaluación + `assistant:eval`. Sin panel en
  la SPA (fila candidata). Parámetros opcionales sobre la consulta de vencimientos de S5: mismo valor por defecto.
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): A, coincide con ROADMAP.
- Condición 4 (ADR / RN): costo cero (sin API externa de pago); ningún dato de paciente hacia el modelo (RN-10).
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier A
- Compromiso para el README: el servidor redacta la respuesta con datos de herramientas; el modelo solo elige herramientas.

## 2026-10-08 — backend-implementer: apply (0.1, 1–6, 8.1, 9.2, 9.3)

- **0.1 contraste** (`openspec validate --all --strict` válido; `/usr/bin/grep -rn 'class StockPolicy\|class TransferPolicy\|function scopeFilter\|function lowStock\|function expiringLots\|class RedactExceptionProcessor' software/api/app` con hit en cada costura). Diferencias, ninguna cambia un escenario:
  - S5 no tiene Policy propia de alertas: `ListAlertsRequest` usa `StockPolicy::viewAny` (inventory.view). `get_low_stock_alerts` autoriza con el mismo sujeto (`Stock::class`).
  - Firmas reales de S5: `AlertQuery::expiringLots(?int $warehouseId)` y `lowStock(?int $warehouseId)`. `expiringLots` gana `int $days = EXPIRY_WINDOW_DAYS, ?int $productId = null`; la ruta de alertas no los pasa (pruebas de S5 verdes en la corrida completa).
  - `Transfer::scopeFilter` combina `status`/origen/destino con Y; "por bodega" del asistente es origen O destino: `where` propio en la herramienta, `status` por `filter()`.
  - `ThrottleRequestsException` caía en `HttpExceptionInterface` → `too_many_attempts`; ahora tiene su rama `too_many_requests` (único `throttle:` del repo: el del asistente).
- Commits: `e62b004` herramientas, `08930f5` asistente, `4de4a0e` pruebas de defensa, `b57602a` reglas arch, `df5f321` OpenAPI + `api-schema.ts`, `f9f931b` evaluación, `8699ed1` datasets de Ollama, `b6d6d02` documento.
- Corrida completa única (9.2) en `b6d6d02`: Pint, Larastan y Pest verdes (cifras en `verification.md` § 5). Corridas delta solo por archivo o filtro. `assistant:eval` con `mock`: todas aciertan, código 0.
- `[MUT]` M1–M9: cada uno falla aplicado y pasa restaurado (`verification.md` § 3, parches en `mutants/`). M4 sobrevivió al primer intento y destapó que `arch()->expect([ns1, ns2])->not->toUse()` de Pest pasa con arreglo de objetivos; se separó una regla por espacio de nombres.
- Falsos verdes corregidos antes de cerrar: datasets de Pest con cierres anidados no llegaban a la prueba como se suponía (Ollama, comando); `Artisan::output()` devolvía la salida del `migrate` anidado; un filtro de log comparaba `ERROR` contra niveles PSR en minúscula.
- Decisiones: controlador en `App\Http\Controllers\Assistant\` (convención del repo, no la ruta de design D1); `ChatRequest` lleva el tiempo restante del plazo; `AskAssistantRequest` sin `authorize()` (evita un 403 documentado que la ruta no devuelve); `AssistantEvalSeeder` es la única fuente de datos de los escenarios de ruta y del comando.
- Deuda para el Orchestrator (en prosa, sin id):
  - EV «Proveedor no disponible» dice que cada fila falla; las preguntas de pacientes las responde el filtro previo sin proveedor y aciertan. La prueba afirma el comportamiento real; corresponde ajustar la redacción del escenario (spec-engineer).
  - La forma `expect([...])->not->toUse()` de Pest no protege nada; ninguna regla actual del repo la usa, pero conviene dejarlo escrito para reglas futuras.
  - La calidad con Ollama real no se midió (sin Ollama en el anfitrión); el documento da el comando.
  - `composer openapi:check` no corre dentro de `api-tools` (sin repositorio git en el contenedor, código 129); la deriva se verificó con `git diff` en el anfitrión. Preexistente.
  - `assistant:eval` imprime también las líneas JSON `assistant.query` por stderr junto a la tabla; cosmético en el log de CI.
- Pendiente de devops-implementer: 7.1 (variables y `extra_hosts` en compose y `.env.example`), 7.2 (paso de CI) y 9.1 (humo en el stack).

## 2026-10-08 — spec-engineer: redacción de EV «Proveedor no disponible»

- Origen: deuda en prosa del backend-implementer (escenario decía "cada fila dice fallo"). Sin cambio de código ni de prueba.
- Fuente contrastada: `AssistantEvalCommandTest.php:101` (código 1; una fila por entrada; toda fila FALLO lleva `asistente no disponible`; FALLO = entradas − 2), `AssistantEvalCommand.php:67` (captura `AssistantUnavailable` por fila), `QuestionPreFilter` (paciente o documento → `out_of_scope` sin proveedor), dataset: `patient-name` y `patient-document` esperan `out_of_scope`; `verification.md` § 5 (`Aciertos: 2/23`, código 1) y § 6.
- Cambio: THEN de `specs/assistant-evaluation/spec.md` ahora dice: una fila por entrada; fila que necesita proveedor → fallo `asistente no disponible`; filas del filtro previo (preguntas de paciente, `out_of_scope`) → acierto; sin excepción sin capturar; código 1, distinto de 0. Requisito y título intactos: la fila EVL-06 de `verification.md` sigue mapeando por título.
- `openspec validate add-inventory-assistant --strict`: válido.
- Ancla de transporte: 34 hits, 0 sin ancla; escenario editado 0 hits (no afirma HTTP ni forma de error). Control positivo: línea `HTTP 422 … validation_failed` sin ancla → 1 hit, 0 anclas (el barrido la detectaría).
- Supuestos: 0. Preguntas abiertas: ninguna. Queda para el Orchestrator cerrar la fila de § 6 de `verification.md` (registro, no código).

## 2026-10-08 — devops-implementer: 7.1, 7.2, 9.1

- Commits: `258258b` (variables del asistente en compose y plantillas), `b400e99` (paso de CI), `98214d5` (humo).
- 7.1: `AI_PROVIDER` (`mock`), `OLLAMA_BASE_URL`, `OLLAMA_MODEL`, `OLLAMA_TIMEOUT` y `extra_hosts` `host.docker.internal:host-gateway` en `api` y `api-tools`; documentadas en `software/.env.example` y `software/api/.env.example`, sin secretos. `config --quiet` código 0; bucle de variables sin salida (control `FALSA_X` impreso). El bucle marcaba `POSTGRES_*` por el `$${…}` del healthcheck de db: pasado a `$$VAR`, misma semántica. Ese cambio recreó el contenedor db de desarrollo al correr `api-tools` (volumen intacto).
- 7.2: paso «Evaluación del asistente» tras Pest, `AI_PROVIDER: mock`, `LOG_LEVEL: warning` (oculta las 23 líneas `assistant.query` por stderr; sin cambio de código). CI `37781226508` sobre `98214d5`: éxito, log `Aciertos: 23/23`, 0 líneas `assistant.query`. `secrets.`: 3 hits previos, 0 nuevos. actionlint 1.7.7 código 0.
- 9.1: `up -d --build --wait --no-deps api` código 0 (solo api, por instrucción); `/health` y `/ready` 200; api uid 1000, web uid 101. `assistant:eval` en `api-tools`: `Aciertos: 23/23`, código 0. Humo `software/docker/smoke/assistant-smoke.sh`: 12 comprobaciones, 0 fallas. Control con dos expectativas invertidas: 2 fallas, código 1. Contraseña nunca impresa (0 hits; control 1).
- Respuestas del humo por `/api/assistant/ask`:
  - anónimo → 401.
  - regente, parte C (60 días, acetaminofén, farmacia central) → 200 `answered`, `find_expiring_lots` ok, lotes solo de Farmacia Central.
  - regente, traslados en tránsito → 200 `no_results` (0 en tránsito en los datos de desarrollo), `get_transfer_status` ok.
  - regente, estado del traslado 26 → 200 `answered`, `get_transfer_status` ok.
  - regente, paciente Ana Sintética Pérez → 200 `out_of_scope`, sin herramientas.
  - médico, lotes a 30 días → 200 `not_permitted`, `find_expiring_lots` denied, respuesta fija.
- Ollama no ejercitado (no corre en el anfitrión; sin descarga de modelos).
- Deuda (prosa, sin id): el humo del asistente no corre en el staging del CI (solo `smoke.sh`); tampoco lo hacen los humos de S1–S5. Agregarlos al staging exigiría usuarios sembrados en esa etapa.
