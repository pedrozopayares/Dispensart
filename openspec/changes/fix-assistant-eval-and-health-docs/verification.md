# Verification — fix-assistant-eval-and-health-docs (S9, Tier B)

Árbol: `dev` en `0393c29`. Comandos con el prefijo `tools` = `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools`.

## 0. Reparto de líneas

Fuente: `git diff --numstat 5db3449..0393c29` filtrado por ruta (agregadas/borradas).

| Clase | Archivos | Líneas |
|---|---|---|
| Producto | `app/OpenApi/HealthDocumentTransformer.php`, `app/Providers/OpenApiServiceProvider.php`, `app/Services/Assistant/Evaluation/EvaluationMatcher.php` | +116 / −5 |
| Prueba | `tests/Feature/Health/HealthContractTest.php`, `tests/Feature/Assistant/EvaluationMatcherCatalogTest.php` | +338 / −0 |
| Generado (exportado, no editado a mano) | `software/api/openapi.json`, `software/web/src/lib/api-schema.ts` | +292 / −0 |
| Documentación de producto | `software/docs/asistente.md` | +1 / −0 |
| Registro | `verification.md`, `journal.md` (sección del implementador), `tasks.md` (marcas), `mutants/M1–M5.patch` | +99 / −11 de prosa y marcas (`verification.md` +67, `journal.md` +21, `tasks.md` +11 / −11) · +246 de parches |

## 1. Escenarios → pruebas

| Escenario | Prueba | Archivo:línea | clause → route file:line |
|---|---|---|---|
| SH › Vivacidad documentada | `Vivacidad documentada` | `software/api/tests/Feature/Health/HealthContractTest.php:81` | 200 `{status: ok}` → `software/api/routes/health.php:8` (HealthController) |
| SH › Disponibilidad documentada con su fallo | `Disponibilidad documentada con su fallo` | `software/api/tests/Feature/Health/HealthContractTest.php:93` | 200/503 → `software/api/app/Http/Controllers/Health/ReadyController.php:17` |
| SH › URL en la raíz del origen | `URL en la raíz del origen` | `software/api/tests/Feature/Health/HealthContractTest.php:114` | — |
| SH › Operaciones públicas sin seguridad de sesión | `Operaciones públicas sin seguridad de sesión` | `software/api/tests/Feature/Health/HealthContractTest.php:137` | sin 401/419 → `software/api/routes/health.php:7-9` (sin grupo de middleware) |
| SH › Contrato sin claves internas | `Contrato sin claves internas` | `software/api/tests/Feature/Health/HealthContractTest.php:152` | — |
| SH › Salud ausente del contrato | `Salud ausente del contrato` | `software/api/tests/Feature/Health/HealthContractTest.php:163` | — |
| SH › Contrato derivado del código | `drift` exit 0 · `npm run openapi:lint` exit 0 · `npm run api:types:check` exit 0 (§ 3) | `software/api/app/OpenApi/HealthDocumentTransformer.php:31`, registro `software/api/app/Providers/OpenApiServiceProvider.php:33` | — |
| SH › Edición manual del contrato | M3 (§ 2): sin el transformador, `drift` exit ≠ 0 | `openspec/changes/fix-assistant-eval-and-health-docs/mutants/M3.patch` | — |
| AE › Misma bodega con otra redacción | `Misma bodega con otra redacción` (comparador + comando guionado) | `software/api/tests/Feature/Assistant/EvaluationMatcherCatalogTest.php:106` | — |
| AE › Mismo producto con otra redacción | `Mismo producto con otra redacción` | `software/api/tests/Feature/Assistant/EvaluationMatcherCatalogTest.php:120` | — |
| AE › Otra bodega sigue fallando | `Otra bodega sigue fallando` (comparador + comando guionado, código ≠ 0) | `software/api/tests/Feature/Assistant/EvaluationMatcherCatalogTest.php:125` | — |
| AE › Otro producto sigue fallando | `Otro producto sigue fallando` | `software/api/tests/Feature/Assistant/EvaluationMatcherCatalogTest.php:141` | — |
| AE › Texto obtenido ambiguo o inexistente | `Texto obtenido ambiguo o inexistente` (datasets ambiguo, inexistente, ausente) | `software/api/tests/Feature/Assistant/EvaluationMatcherCatalogTest.php:146` | — |
| AE › Texto esperado sin resolución | `Texto esperado sin resolución` | `software/api/tests/Feature/Assistant/EvaluationMatcherCatalogTest.php:155` | — |
| AE › Demás argumentos sin cambio | `Demás argumentos sin cambio` | `software/api/tests/Feature/Assistant/EvaluationMatcherCatalogTest.php:161` | — |
| AE › Modo simulado sin regresión | `assistant:eval` mock (§ 3) · `Todas aciertan con el modo simulado` | `software/api/tests/Feature/Assistant/AssistantEvalCommandTest.php:66` | — |

## 2. Mutaciones

Arnés de `tasks.md` (`mut`/`ctl`/`drift`), cada parche con `php -l` del archivo PHP mutado («No syntax errors detected») o JSON válido (`python3 -c json.load`) para `openapi.json`. Árbol limpio tras cada revertir (`git diff --quiet -- software/api`).

| n | mutación | Aplicada → FALLA m/k: prueba | Restaurada → PASA k/k | Control positivo (mutante aplicado, PASA) | Salida |
|---|---|---|---|---|---|
| M1 | borra `/health` de `openapi.json` | 1/1: `Salud ausente del contrato` («openapi.json no documenta la operación GET /health.») | 1/1 | `Disponibilidad documentada con su fallo` 1/1 | `mut` 0 · `ctl` 0 |
| M2 | borra `/ready` de `openapi.json` | 1/1: `Salud ausente del contrato` («… GET /ready.») | 1/1 | `Vivacidad documentada` 1/1 | `mut` 0 · `ctl` 0 |
| M3 | quita el registro de `HealthDocumentTransformer` en `OpenApiServiceProvider` | `drift` exit ≠ 0 (182 líneas borradas en `openapi.json`) | `drift` exit 0 tras `git checkout` + revertir | `drift` sin parche exit 0 | cadena 0 · control 0 |
| M4 | el argumento de catálogo cumple si el obtenido resuelve a cualquier entidad | 1/1: `Otra bodega sigue fallando` | 1/1 | `Misma bodega con otra redacción` 1/1 | `mut` 0 · `ctl` 0 |
| M5 | compara ids sin respaldo de texto (null = null cumple) | 1/1: `Texto esperado sin resolución` | 1/1 | `Otra bodega sigue fallando` 1/1 | `mut` 0 · `ctl` 0 |

Autocontrol del arnés: `! pest tests/Feature/Health/HealthContractTest.php 'no-existe-zzz'` → «No tests found», exit 0. Filtros con tilde (`redacción`, `resolución`) casaron tal cual (control: 1 prueba ejecutada en cada corrida).

## 3. Comandos y resultados

| Paso | Comando | Resultado |
|---|---|---|
| Línea base | `tools vendor/bin/pest` (árbol `5db3449`) | 997 passed (4048 assertions) |
| 1.1 roja | `tools vendor/bin/pest tests/Feature/Health/HealthContractTest.php --filter=.` | 6 failed / 6 |
| 1.2 verde | ídem | 6 passed (74 assertions) |
| 2.1 roja | `tools vendor/bin/pest tests/Feature/Assistant/EvaluationMatcherCatalogTest.php` | 2 failed, 7 passed (ver journal: «Otra bodega» corregida en la prueba, no en el código) |
| 2.2 verde | ídem · `tools vendor/bin/pest tests/Feature/Assistant` | 9 passed · 206 passed (866 assertions) |
| Cierre (una corrida completa) | `tools sh -c 'vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G --no-progress && vendor/bin/pest'` | exit 0 · 1012 passed (4145 assertions) |
| 2.4 / 5.1 evaluación simulada | `… -e AI_PROVIDER=mock -e LOG_LEVEL=warning api-tools php artisan assistant:eval` (árbol final) | `Aciertos: 24/24`, exit 0 |
| Deriva del contrato | `tools composer openapi && git diff --exit-code -- software/api/openapi.json` | exit 0 |
| Lint del contrato | `npm run openapi:lint` (raíz) | exit 0 · 2 avisos `operation-4xx-response` (`/health`, `/ready`); control: el contrato de `HEAD` previo (`git show HEAD:…` → `/tmp`) da 0 avisos con el mismo `redocly lint` |
| Tipos de la SPA | `npm run api:types` · `npm run api:types:check` · `npm run typecheck` (software/web) | +110 líneas · exit 0 · exit 0 |
| Documento | `git diff --numstat -- software/docs/asistente.md` · `/usr/bin/grep -c "resuelt" software/docs/asistente.md` vs `git show 225e911:software/docs/asistente.md \| /usr/bin/grep -c "resuelt"` | `1 0` · 1 vs 0; control positivo del 0: `git show 225e911:software/docs/asistente.md \| /usr/bin/grep -c "Evaluación"` = 1 |
| Imagen reconstruida | `docker compose -f software/compose.yaml up -d --build api` · `curl localhost:8090/health` · `curl localhost:8090/ready` | healthy · 200 · 200 `{"status":"ready","checks":{"database":"ok","migrations":"ok"}}` |
