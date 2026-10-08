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
