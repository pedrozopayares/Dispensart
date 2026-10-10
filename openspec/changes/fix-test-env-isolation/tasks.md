# Tasks

Tier A (fila S17, decisión del usuario 2026-10-10: aislamiento del arnés de pruebas). Con `design.md` (D1–D6) y pines
`[MUT]` declarados: M1, M2, M3, M4, M5 (más el control positivo PC1). Presupuesto de suite: línea base + una corrida
completa de cierre (2.1) + confirmación del auditor. Prefijo de escenario: CI = delta `ci-pipeline`. Orden: 1.1 → 1.2 →
1.3 → 1.4 → 1.5 → 1.6 → 2.1 → 2.2, secuencial (solo `software/api`; sin bloque web ni devops).

Archivo de prueba (fijo): `software/api/tests/Feature/TestEnvironmentIsolationTest.php`. Cada `test()` lleva como
título el nombre exacto de su escenario. Todos los comandos corren en bash desde la raíz del repo y son autocontenidos.
A propósito, ningún comando pasa `-e AI_PROVIDER=mock` salvo donde el entorno benigno es la condición probada. Todo
`vendor/bin/pest` lleva `--fail-on-empty-test-suite` (design, riesgo 1): un filtro que no casa es ROJO, no verde.

## 1. Aislamiento de la suite (api)

- [ ] 1.1 Escribir `TestEnvironmentIsolationTest.php` (design D2, D3, D6): `beforeEach(Http::preventStrayRequests())`;
  «Proveedor ollama en el entorno del proceso» afirma `config('assistant.provider')`, `getenv('AI_PROVIDER')`,
  `app(ModelCatalog::class)->defaultChoice()->provider` = `mock` y `app(LlmProvider::class)` instancia de
  `MockLlmProvider`; «Modelo de Ollama en el entorno del proceso» afirma `config('assistant.ollama.model')` y
  `getenv('OLLAMA_MODEL')` = `qwen2.5:3b`; «Prueba que fija su propio proveedor» fija `ollama` y otro modelo con
  `config([...])` y `Http::fake`, ve `defaultChoice()` = ollama con ese modelo y no aserta orden (las demás pruebas
  afirman `mock` por sí solas); «Pin retirado de la configuración de la suite» lee `base_path('phpunit.xml')` por XPath
  y exige `env` y `server` de `AI_PROVIDER=mock` y de `OLLAMA_MODEL=qwen2.5:3b`, con mensaje que nombra la variable.
  Cabecera en español citando CI › «Suite del backend aislada del entorno del anfitrión» y D-auv-9. Cubre CI ›
  «Proveedor ollama en el entorno del proceso», «Modelo de Ollama en el entorno del proceso», «Prueba que fija su
  propio proveedor», «Pin retirado de la configuración de la suite». Verifica ROJO hoy (registrar salida en
  `journal.md`): `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=ollama -e
  OLLAMA_MODEL=gemma4:e2b-mlx api-tools vendor/bin/pest tests/Feature/TestEnvironmentIsolationTest.php
  --fail-on-empty-test-suite` sale ≠ 0, con fallos en «Proveedor ollama…», «Modelo de Ollama…» y «Pin retirado…».
- [ ] 1.2 En `software/api/phpunit.xml`, junto al bloque `DB_*`: `<env name="AI_PROVIDER" value="mock" force="true"/>`,
  `<server name="AI_PROVIDER" value="mock" force="true"/>`, ídem `OLLAMA_MODEL` = `qwen2.5:3b`, con comentario en
  español (design D1). Nada más cambia. Cubre CI › «Proveedor ollama en el entorno del proceso», «Modelo de Ollama en
  el entorno del proceso», «Pin retirado de la configuración de la suite». Verifica, cada uno sale 0:
  `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=ollama -e
  OLLAMA_MODEL=gemma4:e2b-mlx api-tools vendor/bin/pest tests/Feature/TestEnvironmentIsolationTest.php
  --fail-on-empty-test-suite`; `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock
  api-tools vendor/bin/pint --test`.
- [ ] 1.3 Commit en `dev` de 1.1 + 1.2 (asunto sugerido: `fix: la suite del backend usa siempre el asistente simulado`).
  Puerta antes de cualquier mutante: `test -z "$(git status --porcelain -- software/api)"` sale 0. Cubre CI › «Pin
  retirado de la configuración de la suite» (los mutantes de 1.4 parten de este árbol; design D1).
- [ ] 1.4 `[MUT]` Escribir `openspec/changes/fix-test-env-isolation/mutants/M<n>.patch`: editar
  `software/api/phpunit.xml`, `git diff -- software/api/phpunit.xml > …/M<n>.patch`, `git checkout --
  software/api/phpunit.xml`. M1 quita solo `<server name="AI_PROVIDER">`; M2 quita solo `<env name="AI_PROVIDER">`; M3
  quita solo `<server name="OLLAMA_MODEL">`; M4 quita solo `<env name="OLLAMA_MODEL">`; M5 quita las dos líneas de
  `AI_PROVIDER`. Cubre CI › «Proveedor ollama en el entorno del proceso», «Modelo de Ollama en el entorno del proceso»,
  «Pin retirado de la configuración de la suite» (design D1, D2, D3). Verifica cada fila: aplicado sale ≠ 0, restaurado
  sale 0 y el árbol queda limpio. Plantilla (sustituir `<n>`, `<-e…>`, `<filtro>` según la tabla):
  `git apply openspec/changes/fix-test-env-isolation/mutants/M<n>.patch && { docker compose -f software/compose.yaml
  --profile tools run --rm <-e…> api-tools vendor/bin/pest tests/Feature/TestEnvironmentIsolationTest.php --filter
  '<filtro>' --fail-on-empty-test-suite; echo "aplicado=$?"; }; git apply -R
  openspec/changes/fix-test-env-isolation/mutants/M<n>.patch && test -z "$(git status --porcelain -- software/api)" &&
  { docker compose -f software/compose.yaml --profile tools run --rm <-e…> api-tools vendor/bin/pest
  tests/Feature/TestEnvironmentIsolationTest.php --filter '<filtro>' --fail-on-empty-test-suite; echo "restaurado=$?"; }`

  | Pin | `<-e…>` | `<filtro>` | Seam (design) |
  |---|---|---|---|
  | M1 | `-e AI_PROVIDER=ollama` | `Proveedor ollama en el entorno del proceso` | `<server>` gana en `config()` (D1) |
  | M2 | `-e AI_PROVIDER=ollama` | `Proveedor ollama en el entorno del proceso` | `<env>` gana en `getenv()` (D1) |
  | M3 | `-e OLLAMA_MODEL=gemma4:e2b-mlx` | `Modelo de Ollama en el entorno del proceso` | `<server>` (D1) |
  | M4 | `-e OLLAMA_MODEL=gemma4:e2b-mlx` | `Modelo de Ollama en el entorno del proceso` | `<env>` (D1) |
  | M5 | `-e AI_PROVIDER=mock` | `Pin retirado de la configuración de la suite` | guarda estática, entorno benigno (D3); además, verificación de mensaje abajo |
  | PC1 | `-e AI_PROVIDER=mock`, con M5 aplicado | `Modelo de Ollama en el entorno del proceso` | control positivo: aplicado sale 0 (M5 no rompe el XML) |

  Mensaje de M5 (escenario «Pin retirado de la configuración de la suite»: «su mensaje nombra `AI_PROVIDER`»), sale 0
  solo si falla Y nombra la variable: `git apply openspec/changes/fix-test-env-isolation/mutants/M5.patch && { out=$(docker
  compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest
  tests/Feature/TestEnvironmentIsolationTest.php --filter 'Pin retirado de la configuración de la suite'
  --fail-on-empty-test-suite 2>&1); k=$?; git apply -R openspec/changes/fix-test-env-isolation/mutants/M5.patch && test
  -z "$(git status --porcelain -- software/api)" && [ "$k" -ne 0 ] && printf '%s' "$out" | /usr/bin/grep -q AI_PROVIDER; }`
- [ ] 1.5 Corrida delta de la zona del asistente bajo entorno hostil (design D6). Cubre CI › «Prueba que fija su propio
  proveedor», «Proveedor ollama en el entorno del proceso». Verifica: `docker compose -f software/compose.yaml
  --profile tools run --rm -e AI_PROVIDER=ollama -e OLLAMA_MODEL=gemma4:e2b-mlx api-tools vendor/bin/pest
  tests/Feature/Assistant tests/Arch tests/Feature/TestEnvironmentIsolationTest.php --fail-on-empty-test-suite` sale
  0; `/usr/bin/grep -rlE 'putenv|_SERVER\[|_ENV\[' software/api/tests` no lista archivo fuera de
  `tests/Feature/TestEnvironmentIsolationTest.php` (ninguna prueba fija el proveedor por entorno; control positivo:
  `/usr/bin/grep -rlE 'AI_PROVIDER' software/api/tests` lista
  `software/api/tests/Feature/Assistant/AssistantModelChoiceTest.php`).
- [ ] 1.6 `assistant:eval` sigue al proceso (design D4). Cubre CI › «Evaluación fuera de la suite sigue al entorno».
  Verifica: `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=ollama -e
  OLLAMA_BASE_URL=http://127.0.0.1:9 -e LOG_LEVEL=warning api-tools php artisan assistant:eval` sale 1 y las filas que
  llaman al proveedor dicen `asistente no disponible`; control positivo: `docker compose -f software/compose.yaml
  --profile tools run --rm -e AI_PROVIDER=mock -e LOG_LEVEL=warning api-tools php artisan assistant:eval` sale 0 con
  aciertos igual a total.

## 2. Cierre

- [ ] 2.1 Corrida completa de cierre SIN `-e AI_PROVIDER=mock`, con el `software/.env` del anfitrión en `ollama`
  (design D1, D2, D3, D5). Cubre CI › «Proveedor ollama en el entorno del proceso», «Modelo de Ollama en el entorno
  del proceso», «Prueba que fija su propio proveedor», «Pin retirado de la configuración de la suite». Verifica: precondición `/usr/bin/grep -c '^AI_PROVIDER=ollama$'
  software/.env` imprime `1` (control positivo: el mismo comando con `'^AI_PROVIDER='` también imprime `1`); luego
  `docker compose -f software/compose.yaml --profile tools run --rm api-tools vendor/bin/pest
  --fail-on-empty-test-suite` sale 0. Registrar total de pruebas y salida en `verification.md`.
- [ ] 2.2 `verification.md` en tablas: § 0 con líneas de producto, de prueba y de registro; una fila por escenario del
  delta (`| Scenario | Test | File:line |`); una por M1–M5 y PC1 (`| n | mutación | Aplicado → FALLA m/k: prueba |
  Restaurado → PASA k/k |`); filas de 1.5, 1.6 y 2.1 con comando y salida (design D1–D6). Cubre CI › «Proveedor ollama en el entorno del
  proceso», «Modelo de Ollama en el entorno del proceso», «Prueba que fija su propio proveedor», «Pin retirado de la
  configuración de la suite», «Evaluación fuera de la suite sigue al entorno». Verifica:
  `/usr/bin/grep -c '^#### Scenario:' openspec/changes/fix-test-env-isolation/specs/ci-pipeline/spec.md` igual al número
  de filas de escenario.

## Workflow follow-up

- Orchestrator: mover D-auv-9 a «Settled» en `openspec/DEBT.md` tras GATE 2.
- Archivar con `openspec archive fix-test-env-isolation -y` tras GATE 2 = APPROVED, antes de S16.
