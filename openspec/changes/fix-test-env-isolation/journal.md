# Journal — fix-test-env-isolation (S17)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-10 — spec-engineer: proposal, delta y tareas borrador

- Producido: `proposal.md`; delta `specs/ci-pipeline/spec.md` (requisito ADDED «Suite del backend aislada del
  entorno del anfitrión», con escenarios negativos «Pin retirado de la configuración de la suite» y «Evaluación
  fuera de la suite sigue al entorno»); `tasks.md` con arnés `dc`/`iso`/`ctl` y parches de control C1–C2 a escribir
  en `controls/` al aplicar. `openspec validate fix-test-env-isolation --strict` → válido.
- Barrido de anclas (CYCLE-TIERS § transporte) sobre `specs/` con `/usr/bin/grep -rnE …`: sin coincidencias; control
  positivo: el mismo patrón sobre `openspec/specs/service-health/spec.md` sí coincide. La salida 1 de `assistant:eval`
  se ancla en assistant-evaluation › «Proveedor no disponible».
- Capacidad elegida: `ci-pipeline` (ya posee la regla gemela «Pruebas contra PostgreSQL» y su prueba
  `tests/Feature/DatabaseConnectionTest.php`). `inventory-assistant` › «Proveedor configurable por entorno» describe
  el comportamiento en ejecución y no cambia.
- Hallazgos del código: `config/assistant.php` usa `env('AI_PROVIDER') ?: 'mock'` y `env('OLLAMA_MODEL',
  'qwen2.5:3b')`; `compose.yaml` pasa ambas a `api-tools` desde `software/.env` (local: `ollama`); `software/api/.env`
  no las define; `phpunit.xml` ya fija `DB_*` como `<env>` y `<server>` porque `$_SERVER` gana. Pruebas que dependen
  del valor por defecto `mock` sin fijarlo: `OllamaLlmProviderTest.php:99` (afirma `mock` antes de cambiarlo),
  `MockLlmProviderTest.php:28`, `AssistantEndpointTest.php` (comentario de cabecera). Ninguna depende de heredar
  `ollama`; las que usan Ollama lo fijan con `config()` y `Http::fake`. `assistant:eval` es Artisan y no carga
  `phpunit.xml`; CI ya le pasa `AI_PROVIDER: mock` y no define `AI_PROVIDER` en el paso de Pest.
- Supuestos: en la propuesta (modelo `qwen2.5:3b`; pin en `<env>` y `<server>`; `OLLAMA_BASE_URL` y
  `OLLAMA_TIMEOUT` sin fijar; la prueba falla al retirar el pin aun sin `AI_PROVIDER` en el proceso; tier C).
- Pregunta abierta para el Orchestrator (no bloquea la redacción): `CYCLE-TIERS.md` pone «the test harness's
  isolation» entre los disparadores de Tier A, y la fila S17 dice C. Se respetó C (la preaprobación ata el tier de la
  fila); los controles C1–C2 dan la discriminación que un `[MUT]` daría. Si el Orchestrator lee el cambio como A, la
  preaprobación de S17 no aplica tal cual y hace falta `design.md`.
- Bloqueos: ninguno. Sin código, sin commit; la carpeta de S16 no se tocó.

## 2026-10-10 — Orchestrator: tier

- shard = auv
- `CYCLE-TIERS.md` lista el aislamiento del arnés de pruebas como disparador de tier A; la fila decía C. Condición 3 de la
  preaprobación fallaba, así que se preguntó. Respuesta del usuario: «Subir a tier A (Recomendado)». Fila S17 actualizada.
- Siguiente: architect (`design.md` corto y pins `[MUT]`).

## 2026-10-10 — architect: design.md y tareas refinadas (tier A)

- Producido: `design.md` (D1–D6) y `tasks.md` reescrito para tier A. Sin código, sin commit; `add-postman-collection`
  sin tocar.
- Hecho verificado en `vendor/`: `Env::getRepository` lee `ServerConstAdapter` → `EnvConstAdapter` → `PutenvAdapter`;
  PHPUnit `PhpHandler::handleServerVariables` escribe `$_SERVER` siempre (el `force` de `<server>` no opera) y
  `handleEnvVariables` hace `putenv` + `$_ENV` con `force`. De ahí D1: `<server>` gana en `config()`, `<env>` en
  `getenv()` y procesos hijos (`RaceRunner::launch`).
- Decisiones: D1 pin doble (rechazados: solo `<env>`, solo `<server>`, `.env.testing`, `config()` en `Pest.php`); D2
  prueba por lector (`config`, `getenv`, `defaultChoice`, enlace `LlmProvider`) con `preventStrayRequests` propio
  (rechazado: vía `POST /api/assistant/ask`); D3 guarda XPath sobre `phpunit.xml` porque `api-tools` siempre define
  `AI_PROVIDER` y CI nunca (rechazados: `Registry` interno de PHPUnit, `/proc/self/environ`); D4 eval intacto; D5 CI
  sin cambios; D6 `config()` en prueba sigue ganando.
- Tareas: controles C1–C2 sustituidos por `[MUT]` M1–M5 + PC1 en `mutants/`, uno por línea del pin (aísla `<server>`
  de `<env>`); commit + puerta `git status --porcelain` (1.3) antes de mutar; `--fail-on-empty-test-suite` en todo
  Pest; cierre 2.1 = corrida completa sin `-e AI_PROVIDER=mock` con `software/.env` en `ollama`. Modelo hostil:
  `gemma4:e2b-mlx` (el del WHEN del delta). Orden secuencial, solo api.
- Riesgos: filtro vacío (falso verde), guarda D3 atada al nombre del XML, prueba futura que herede `ollama`.
- Validación: `openspec validate fix-test-env-isolation --strict` → válido. Barrido de anclas `/usr/bin/grep -rnE …
  specs/` → sin coincidencias (salida 1); control positivo: mismo patrón con `-c` sobre
  `openspec/specs/service-health/spec.md` → 9.

## 2026-10-10 — architect: correcciones del spec-validator

- `proposal.md` supuesto de tier pasa a A; 2.1 y 2.2 nombran sus escenarios y decisiones; M5 gana comando que exige
  salida ≠ 0 y mensaje con `AI_PROVIDER`; conteos en prosa retirados de `design.md` y de la sección del spec-engineer
  (por orden del Orchestrator). `openspec validate fix-test-env-isolation --strict` → válido; barrido de anclas sin
  coincidencias (salida 1), control positivo sobre `service-health` → 9.

## 2026-10-10 — Orchestrator: GATE 1

- GATE 1: preaprobado (ROADMAP 2026-10-10, «Aplica S16, junto con D-auv-9»), tier A por decisión del usuario.
  1. Alcance = fila S17. 3. Tier A = columna actualizada. 4. Ningún ADR ni RN se debilita.
  2. `openspec validate --strict` limpio y ancla sin hits. Lo único que el spec-validator dejó en la tercera pasada son
     frases descriptivas («dos líneas de», «una sola de las dos») que no son cifras de evidencia: redacción, regla 12.
     Decisión del Orchestrator: condición cumplida.
