# Tasks

Tier B. Pruebas primero. `[MUT]` declarados: 5 (M1–M5), solo donde una implementación perezosa pasaría las pruebas.
Prefijos de escenario: SH = delta `service-health`, AE = delta `assistant-evaluation`.

Archivos de prueba (fijos, los usan los comandos): `software/api/tests/Feature/Health/HealthContractTest.php` y
`software/api/tests/Feature/Assistant/EvaluationMatcherCatalogTest.php`. Cada `test()` lleva como título el nombre
exacto de su escenario.

**Arnés** (bash, desde la raíz del repo). El `software/.env` local fija `AI_PROVIDER=ollama`: todo comando de
`api-tools` pasa `-e AI_PROVIDER=mock` explícito. `openapi:check` usa `git diff` y el contenedor no ve `.git`: la
deriva se comprueba exportando en el contenedor y comparando en el anfitrión. Un parche por mutante en
`openspec/changes/fix-assistant-eval-and-health-docs/mutants/M<n>.patch` (diff de una sola costura, con el árbol de
la tarea ya confirmado en git). Aplicar = `git apply`; revertir = `git apply -R`.

```bash
tools() { docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools "$@"; }
pest() { tools vendor/bin/pest "$1" --filter="$2" --fail-on-empty-test-suite; }
drift() { tools composer openapi && git diff --exit-code -- software/api/openapi.json; }
M=openspec/changes/fix-assistant-eval-and-health-docs/mutants
# mut <Mn> <archivo> <filtro>: aplica, exige FALLA, revierte, exige PASA y árbol limpio. Sale 0 solo si ocurre todo.
mut() { local p="$M/$1.patch" k; git apply "$p" || return 2; pest "$2" "$3"; k=$?
  git apply -R "$p" || return 3; pest "$2" "$3" || return 4; git diff --quiet -- software/api || return 5; [ "$k" -ne 0 ]; }
# ctl <Mn> <archivo> <filtro>: control positivo; con el mutante aplicado el filtro PASA.
ctl() { local p="$M/$1.patch" k; git apply "$p" || return 2; pest "$2" "$3"; k=$?
  git apply -R "$p" || return 3; git diff --quiet -- software/api || return 5; [ "$k" -eq 0 ]; }
```
Autocontrol del arnés, una vez antes del primer `[MUT]`: `! pest tests/Feature/Health/HealthContractTest.php
'no-existe-zzz'` sale 0. Si un acento impide que el filtro case, se sustituye por `.`; el filtro registrado en
`verification.md` es el que corrió.

## 1. Contrato OpenAPI de salud (api)

- [x] 1.1 `HealthContractTest.php`, leyendo `openapi.json`: `/health` 200 con solo `status: ok`; `/ready` 200 y 503
  con los enums de `status`, `checks.database` y `checks.migrations`; `X-Correlation-Id` en cada respuesta;
  servidor de las dos operaciones = raíz y las demás siguen bajo `/api`; sin seguridad de sesión ni 401/419; sin
  claves fuera de `status`, `checks`, `database`, `migrations`; si falta una de las dos operaciones, el mensaje la
  nombra. Cubre SH › «Vivacidad documentada», «Disponibilidad documentada con su fallo», «URL en la raíz del
  origen», «Operaciones públicas sin seguridad de sesión», «Contrato sin claves internas», «Salud ausente del
  contrato». Verifica: `pest tests/Feature/Health/HealthContractTest.php .` ROJA hoy (registrar salida en
  `journal.md`).
- [x] 1.2 Hacer que la exportación del código (`composer openapi`, Scramble) emita `GET /health` y `GET /ready` como
  pide 1.1 (mecánica a elección del implementador; nada editado a mano en `openapi.json`), regenerar `openapi.json`.
  Comentario en español en el punto de extensión. Cubre SH › «Vivacidad documentada», «Disponibilidad documentada
  con su fallo», «URL en la raíz del origen», «Operaciones públicas sin seguridad de sesión», «Contrato sin claves
  internas», «Contrato derivado del código». Verifica: `pest tests/Feature/Health/HealthContractTest.php .` VERDE;
  `drift` sale 0; `npm run openapi:lint` en la raíz sale 0.
- [x] 1.3 [MUT] Pines del contrato (una prueba que solo mire una de las dos rutas, o un `openapi.json` editado a mano,
  pasarían):
  M1 parche que borra `/health` de `openapi.json`: `mut M1 tests/Feature/Health/HealthContractTest.php 'Salud
  ausente del contrato'` sale 0; control `ctl M1 tests/Feature/Health/HealthContractTest.php 'Disponibilidad
  documentada con su fallo'` sale 0.
  M2 parche que borra `/ready`: `mut M2 tests/Feature/Health/HealthContractTest.php 'Salud ausente del contrato'`
  sale 0; control `ctl M2 tests/Feature/Health/HealthContractTest.php 'Vivacidad documentada'` sale 0.
  M3 parche que quita del código de exportación la emisión de las rutas de salud (deja `openapi.json` intacto):
  `git apply $M/M3.patch && ! drift; git checkout -- software/api/openapi.json && git apply -R $M/M3.patch && drift
  && git diff --quiet -- software/api` sale 0 (SH › «Edición manual del contrato»); control: el mismo `drift` sin
  parche sale 0. Verifica: filas M1–M3 en `verification.md`.

## 2. Evaluación del asistente por entidad resuelta (api)

- [x] 2.1 `EvaluationMatcherCatalogTest.php` con el catálogo de evaluación sembrado (mismo sembrador que
  `assistant:eval`) y llamadas a herramienta construidas en la prueba; cada fallo afirma que la expectativa
  incumplida nombra el argumento. Incluye un caso de comando con un proveedor guionado que llama a la entrada que
  espera «farmacia urgencias» con «farmacia de urgencias» (fila acierto) y con «farmacia central» (fila fallo, código
  ≠ 0). Cubre AE › «Misma bodega con otra redacción», «Mismo producto con otra redacción», «Otra bodega sigue
  fallando», «Otro producto sigue fallando», «Texto obtenido ambiguo o inexistente», «Texto esperado sin
  resolución», «Demás argumentos sin cambio». Verifica: `pest
  tests/Feature/Assistant/EvaluationMatcherCatalogTest.php .` ROJA hoy solo en «Misma bodega con otra redacción»
  (registrar en `journal.md`).
- [x] 2.2 `EvaluationMatcher` compara `warehouse` y `product` con la misma resolución que usan las herramientas
  (`CatalogResolver`): cumple si ambos resuelven al mismo id; si el esperado no resuelve, texto como hoy; demás
  argumentos sin cambio. Docblock en español actualizado (orden de design D14 intacto). Sin cambios en prompt,
  herramientas, filtro previo ni `evaluation-set.json`. Cubre AE › «Misma bodega con otra redacción», «Mismo
  producto con otra redacción», «Otra bodega sigue fallando», «Otro producto sigue fallando», «Texto obtenido
  ambiguo o inexistente», «Texto esperado sin resolución», «Demás argumentos sin cambio». Verifica: `pest
  tests/Feature/Assistant/EvaluationMatcherCatalogTest.php .` VERDE y `tools vendor/bin/pest tests/Feature/Assistant
  --fail-on-empty-test-suite` VERDE.
- [x] 2.3 [MUT] Pines del comparador (una comparación laxa pasaría los casos de acierto):
  M4 parche que da por cumplido el argumento si el obtenido resuelve a cualquier entidad: `mut M4
  tests/Feature/Assistant/EvaluationMatcherCatalogTest.php 'Otra bodega sigue fallando'` sale 0; control `ctl M4
  tests/Feature/Assistant/EvaluationMatcherCatalogTest.php 'Misma bodega con otra redacción'` sale 0.
  M5 parche que compara ids sin respaldo de texto (null = null cumple): `mut M5
  tests/Feature/Assistant/EvaluationMatcherCatalogTest.php 'Texto esperado sin resolución'` sale 0; control `ctl M5
  tests/Feature/Assistant/EvaluationMatcherCatalogTest.php 'Otra bodega sigue fallando'` sale 0. Verifica: filas
  M4–M5 en `verification.md`.
- [x] 2.4 Evaluación simulada: `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock
  -e LOG_LEVEL=warning api-tools php artisan assistant:eval` imprime `Aciertos: 24/24` y sale 0. Cubre AE › «Modo
  simulado sin regresión». Verifica: salida y código en `verification.md`.

## 3. Tipos de la API (web)

- [x] 3.1 Regenerar `software/web/src/lib/api-schema.ts` con `npm run api:types` en `software/web`. Cubre SH ›
  «Contrato derivado del código». Verifica: `npm run api:types:check` y `npm run typecheck` en `software/web` salen 0.

## 4. Documentación

- [x] 4.1 `software/docs/asistente.md`, una sola línea en § Evaluación: el comparador usa la bodega y el producto
  resueltos, no el texto. Cubre AE › «Misma bodega con otra redacción». Verifica: `git diff --numstat --
  software/docs/asistente.md` muestra `1` línea agregada y `/usr/bin/grep -c "resuelt" software/docs/asistente.md`
  sube en 1 frente a `git show HEAD:software/docs/asistente.md | /usr/bin/grep -c "resuelt"` (control positivo).

## 5. Cierre

- [x] 5.1 Corrida completa de cierre (Tier B, una): `tools sh -c 'vendor/bin/pint --test && vendor/bin/phpstan
  analyse && vendor/bin/pest'` VERDE, más 2.4 repetido sobre el árbol final (24/24, código 0). Cubre AE › «Modo
  simulado sin regresión», SH › «Contrato derivado del código». Verifica: comandos y resultados en `journal.md` y
  `verification.md` § 0 (líneas de producto, de prueba y de registro).
- [x] 5.2 `verification.md` en tablas: una fila por escenario (prueba, archivo:línea; columna `clause → route
  file:line` para las anclas de SH › «Vivacidad documentada», «Disponibilidad documentada con su fallo»,
  «Operaciones públicas sin seguridad de sesión») y una por mutación M1–M5. Cubre todos los escenarios de SH y AE
  nombrados en 1.1–4.1. Verifica: cada `#### Scenario:` de los dos deltas tiene fila.

## Workflow follow-up

- Orchestrator: mover D-auv-7 a «Settled» en `openspec/DEBT.md` tras GATE 2.
- Archivar con `openspec archive fix-assistant-eval-and-health-docs -y` tras GATE 2 = APPROVED.
