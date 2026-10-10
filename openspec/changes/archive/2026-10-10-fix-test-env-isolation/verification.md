# Verification — fix-test-env-isolation (S17, Tier A)

Árbol de `software/api`: `dev` en `8954b1c` (producto y prueba). Base: `c4f8728`. Los commits posteriores de S16
(`52092df`, `db73b5b`) no tocan `software/api`. MUT declarados: M1–M5 + PC1.

## 0. Reparto de líneas

Fuente: `git diff --numstat c4f8728 8954b1c`; `wc -l` para los parches.

| Clase | Archivos | Líneas |
|---|---|---|
| Producto | ninguno (sin código de `app/`, rutas ni migraciones) | +0 / −0 |
| Prueba | `software/api/phpunit.xml` (pin `<env>` + `<server>` de `AI_PROVIDER` y `OLLAMA_MODEL`, comentario) | +6 / −0 |
| Prueba | `software/api/tests/Feature/TestEnvironmentIsolationTest.php` | +60 / −0 |
| Registro | `mutants/M1.patch`…`M5.patch` | 12 + 12 + 12 + 12 + 13 |
| Registro | `verification.md`, `journal.md` (sección del implementador), `tasks.md` (marcas) | — |

## 1. Escenarios → pruebas

`/usr/bin/grep -c '^#### Scenario:' specs/ci-pipeline/spec.md` → 5; filas abajo: 5.

| Scenario | Test | File:line |
|---|---|---|
| Proveedor ollama en el entorno del proceso | `Proveedor ollama en el entorno del proceso` (`config`, `getenv`, `defaultChoice()->provider`, enlace `LlmProvider` = `MockLlmProvider`; `Http::preventStrayRequests`) · M1, M2 · delta 1.5 bajo entorno hostil | `software/api/tests/Feature/TestEnvironmentIsolationTest.php:22` |
| Modelo de Ollama en el entorno del proceso | `Modelo de Ollama en el entorno del proceso` (`config('assistant.ollama.model')`, `getenv('OLLAMA_MODEL')`) · M3, M4 | `software/api/tests/Feature/TestEnvironmentIsolationTest.php:29` |
| Prueba que fija su propio proveedor | `Prueba que fija su propio proveedor` (`config([...])` + `Http::fake`: `defaultChoice()` = ollama/`gemma4:e2b-mlx`, enlace `OllamaLlmProvider`, catálogo lo contiene); «la siguiente vuelve a `mock`»: las dos primeras pruebas lo afirman por sí solas · delta 1.5 (pruebas de `Feature/Assistant` que fijan ollama) | `software/api/tests/Feature/TestEnvironmentIsolationTest.php:34` |
| Pin retirado de la configuración de la suite | `Pin retirado de la configuración de la suite` (XPath `/phpunit/php/env[@force='true']` y `/phpunit/php/server` por variable, mensaje con el nombre) · M5 + mensaje · PC1 | `software/api/tests/Feature/TestEnvironmentIsolationTest.php:47`; pin en `software/api/phpunit.xml:40` |
| Evaluación fuera de la suite sigue al entorno | sin prueba Pest (Artisan no carga `phpunit.xml`): comando 1.6 (§ 3) | `software/api/phpunit.xml:40` (alcance del pin) |

## 2. Mutaciones

Plantilla de la tarea 1.4 desde la raíz, con `git status --porcelain -- software/api` vacío antes y después de cada fila.
Pest con `--filter '<escenario>' --fail-on-empty-test-suite`.

| n | mutación | Applied → FAILS m/k: test | Restored → PASSES k/k |
|---|---|---|---|
| M1 | quita `<server name="AI_PROVIDER">`; `-e AI_PROVIDER=ollama` | aplicado=1 · 1/1: `Proveedor ollama…` («config(assistant.provider) no es mock») | restaurado=0 · 1/1 (4 aserciones) |
| M2 | quita `<env name="AI_PROVIDER">`; `-e AI_PROVIDER=ollama` | aplicado=1 · 1/1: `Proveedor ollama…` («getenv(AI_PROVIDER) no es mock») | restaurado=0 · 1/1 (4 aserciones) |
| M3 | quita `<server name="OLLAMA_MODEL">`; `-e OLLAMA_MODEL=gemma4:e2b-mlx` | aplicado=1 · 1/1: `Modelo de Ollama…` («config(assistant.ollama.model) no es qwen2.5:3b») | restaurado=0 · 1/1 (2 aserciones) |
| M4 | quita `<env name="OLLAMA_MODEL">`; `-e OLLAMA_MODEL=gemma4:e2b-mlx` | aplicado=1 · 1/1: `Modelo de Ollama…` («getenv(OLLAMA_MODEL) no es qwen2.5:3b») | restaurado=0 · 1/1 (2 aserciones) |
| M5 | quita las dos líneas de `AI_PROVIDER`; `-e AI_PROVIDER=mock` (entorno benigno) | aplicado=1 · 1/1: `Pin retirado…` («phpunit.xml debe fijar <env name="AI_PROVIDER" value="mock" force="true"/>») · comando de mensaje (falla Y nombra `AI_PROVIDER`) → exit 0 | restaurado=0 · 1/1 (5 aserciones) |
| PC1 | M5 aplicado; `-e AI_PROVIDER=mock`; filtro `Modelo de Ollama…` | control positivo: aplicado=0 · 1/1 passed (2 aserciones): M5 no rompe el XML | árbol limpio tras `git apply -R` |

## 3. Comandos y resultados

Todos desde la raíz con `docker compose -f software/compose.yaml --profile tools run --rm <-e…> api-tools <cmd>`.
Salida guardada en archivo antes de leer conteos.

| Paso | `<-e…>` y comando | Resultado |
|---|---|---|
| Línea base (antes de 1.1) | `-e AI_PROVIDER=mock` · `pint --test; phpstan analyse --memory-limit=1G; pest --fail-on-empty-test-suite` | pint=0 · phpstan=0 `[OK] No errors` · pest=0 · 1071 passed (4469 assertions) |
| 1.1 roja (sin pin) | `-e AI_PROVIDER=ollama -e OLLAMA_MODEL=gemma4:e2b-mlx` · `pest tests/Feature/TestEnvironmentIsolationTest.php --fail-on-empty-test-suite` | exit 1 · 3 failed, 1 passed (7 assertions) · fallan `Proveedor ollama…`, `Modelo de Ollama…`, `Pin retirado…` |
| 1.2 verde | mismo comando que 1.1 | exit 0 · 4 passed (14 assertions) |
| 1.2 pint | `-e AI_PROVIDER=mock` · `pint --test` | exit 0 · PASS 414 files |
| 1.3 puerta | `test -z "$(git status --porcelain -- software/api)"` tras `8954b1c` | exit 0 |
| 1.5 delta | `-e AI_PROVIDER=ollama -e OLLAMA_MODEL=gemma4:e2b-mlx` · `pest tests/Feature/Assistant tests/Arch tests/Feature/TestEnvironmentIsolationTest.php --fail-on-empty-test-suite` | exit 0 · 250 passed (1102 assertions) |
| 1.5 barrido | `/usr/bin/grep -rlE 'putenv\|_SERVER\[\|_ENV\[' software/api/tests` | 0 archivos (exit 1) · control positivo: `/usr/bin/grep -rlE 'AI_PROVIDER' software/api/tests` lista 5 archivos, entre ellos `Feature/Assistant/AssistantModelChoiceTest.php` · enlaces simbólicos en `software/api/tests`: 0 (`find -type l`) |
| 1.6 eval ollama | `-e AI_PROVIDER=ollama -e OLLAMA_BASE_URL=http://127.0.0.1:9 -e LOG_LEVEL=warning` · `php artisan assistant:eval` | exit 1 · Aciertos: 3/24 · 21 filas `FALLO` con `asistente no disponible` |
| 1.6 control | `-e AI_PROVIDER=mock -e LOG_LEVEL=warning` · `php artisan assistant:eval` | exit 0 · Aciertos: 24/24 |
| 2.1 precondición | `/usr/bin/grep -c '^AI_PROVIDER=ollama$' software/.env` | 1 · control positivo `'^AI_PROVIDER='` → 1 |
| 2.1 cierre (sin `-e`) | ninguno · `pint --test; phpstan analyse --memory-limit=1G --no-progress; pest --fail-on-empty-test-suite`; el proceso imprime `AI_PROVIDER=ollama OLLAMA_MODEL=gemma4:e2b-mlx` | pint=0 PASS 414 files · phpstan=0 `[OK] No errors` · pest=0 · 1075 passed (4483 assertions) |

## 4. Presupuesto de suite

| Corrida completa | Entorno | Resultado |
|---|---|---|
| 1 línea base | `-e AI_PROVIDER=mock` | 1071 passed |
| 2 cierre (2.1) | `software/.env` del anfitrión (`ollama`) | 1075 passed |
| 3 auditor | — | pendiente |
