# Verification — add-project-skeleton (S0, tier B)

Fuente: secciones de `journal.md` (devops-implementer 1.1/2.2 y 4.x/5.x, frontend-implementer, backend-implementer).
Árbol medido: `dev` en `edb0cce`. Citas: `SH` = service-health, `RE` = runtime-environment, `CI` = ci-pipeline.

## 0. Reparto de líneas

| Categoría | Alcance | Comando | Líneas |
|---|---|---|---|
| Código de producto — API | `software/api/{app,routes,lang}`, `bootstrap/app.php` | `git ls-files <rutas> \| xargs cat \| wc -l` | 431 |
| Código de producto — configuración Laravel (esqueleto) | `software/api/config` | idem | 1307 |
| Código de producto — SPA | `software/web/src` sin `*.test.*` ni `src/test/`, `index.html` | `git ls-files … \| /usr/bin/grep -vE '\.test\.\|src/test/' \| xargs cat \| wc -l` | 364 |
| Código de producto — infraestructura | `software/docker`, `software/compose.yaml`, `software/.env.example`, `.github/workflows` | `git ls-files <rutas> \| xargs cat \| wc -l` | 688 |
| Código de prueba — API | `software/api/tests` | idem | 363 |
| Código de prueba — SPA | `software/web/src/**/*.test.*`, `src/test/` | `git ls-files … \| /usr/bin/grep -E '\.test\.\|src/test/' \| xargs cat \| wc -l` | 117 |
| Registro | `openspec/changes/add-project-skeleton/**` (antes de este archivo) | `git ls-files <ruta> \| xargs cat \| wc -l` | 902 |

## 1. Matriz escenario → prueba o evidencia

### 1.1 service-health

| Escenario | Prueba | Archivo:línea |
|---|---|---|
| SH › API viva con dependencias sanas | responde 200 con el cuerpo exacto {"status":"ok"} con la base disponible | `software/api/tests/Feature/Health/HealthTest.php:7` |
| SH › API viva con la base de datos caída | responde 200 con {"status":"ok"} aunque la base de datos esté caída | `software/api/tests/Feature/Health/HealthTest.php:16` |
| SH › Vivacidad sin estado ni datos internos | no emite cookies ni más clave que status | `software/api/tests/Feature/Health/HealthTest.php:28` |
| SH › API lista | responde 200 ready con base disponible y todas las migraciones aplicadas | `software/api/tests/Feature/Health/ReadyTest.php:7` |
| SH › Base de datos inalcanzable | responde 503 con database fail y migrations skipped si la base es inalcanzable | `software/api/tests/Feature/Health/ReadyDatabaseDownTest.php:13` |
| SH › Migraciones pendientes | responde 503 con migrations pending si existe una migración sin aplicar | `software/api/tests/Feature/Health/ReadyTest.php:13` |
| SH › Fallo sin filtrar detalles internos | no filtra detalles internos en el cuerpo y registra la excepción con el correlation_id | `software/api/tests/Feature/Health/ReadyDatabaseDownTest.php:19` |
| SH › Cabecera válida respetada | respeta un X-Correlation-Id válido; acepta el límite de 128 caracteres válidos | `software/api/tests/Feature/CorrelationIdTest.php:10`, `:16` |
| SH › Cabecera ausente | genera UUIDs distintos cuando la cabecera falta | `software/api/tests/Feature/CorrelationIdTest.php:23` |
| SH › Cabecera inválida reemplazada | reemplaza una cabecera inválida por un UUID y no la registra en el log (dataset) | `software/api/tests/Feature/CorrelationIdTest.php:32` |
| SH › Error no controlado conserva el identificador | responde 500 con X-Correlation-Id, sin traza en el cuerpo, y lo registra en la línea del error | `software/api/tests/Feature/CorrelationIdTest.php:52` |
| SH › Línea de cierre por petición | escribe exactamente una línea de cierre por petición con método, ruta, estado y duración | `software/api/tests/Feature/RequestLogTest.php:22` |
| SH › Cada línea es JSON válido | escribe cada línea como objeto JSON con timestamp, level, message y correlation_id | `software/api/tests/Feature/RequestLogTest.php:35` |
| SH › Query string, cuerpo y cabeceras excluidos | excluye query string, cuerpo, cookies y cabecera Authorization del log | `software/api/tests/Feature/RequestLogTest.php:53` |
| SH › Log fuera de una petición | escribe JSON válido con correlation_id null fuera de una petición | `software/api/tests/Feature/RequestLogTest.php:71` |

### 1.2 runtime-environment

| Escenario | Prueba o comando de evidencia → resultado | Archivo:línea |
|---|---|---|
| RE › Arranque desde cero sin .env | `test -e software/.env` → no existe; `down -v` → `up --build -d --wait` → exit 0, db/api/web `healthy`; `curl :8090/ready` → 200 | `software/compose.yaml:1` |
| RE › Puertos configurables | `WEB_PORT=8095 DB_PORT=5440 up -d --wait` → `:8095/ready` 200, `pg_isready -p 5440` acepta, `curl :8090` exit 7, `lsof :5434` 0 líneas (control: `lsof :5440` 1) | `software/compose.yaml:24`, `:80` |
| RE › Datos conservados entre reinicios | fila sintética en `users` → `down` (sin -v) → `up -d --wait` → `count(*)` = 1, log `"applied":0` | `software/compose.yaml:26` |
| RE › Reinicio desde cero con volúmenes borrados | `down -v` (borra `db_data`, `api_state`) → `up --build` → log `"applied":3`, `select count(*) from migrations` = 3, tres servicios `healthy` | `software/docker/api/entrypoint.sh:34` |
| RE › Migraciones aplicadas al arrancar | migración en el entrypoint antes de `exec supervisord`; healthcheck por `/ready` → `"applied":3` antes de `healthy` | `software/docker/api/entrypoint.sh:34`, `software/compose.yaml:63` |
| RE › Arranque repetido idempotente | `down` → `up -d --wait` → log `"applied":0`, api `healthy` | `software/docker/api/entrypoint.sh:39` |
| RE › APP_KEY ausente | sha256 de la clave en `bootstrap/cache/config.php` = sha256 de `/var/lib/dispensart/app_key` (`600 app`); tras `down`/`up` mismo sha256 y `"source":"volume"`; `logs api \| /usr/bin/grep -c 'base64:'` → 0 (control con línea sintética → 1) | `software/docker/api/entrypoint.sh:15-25` |
| RE › APP_KEY provista | `APP_KEY=<sintética> up -d --wait api` → sha256 efectivo = sha256 provisto, archivo del volumen sin cambio, `"source":"env"` | `software/docker/api/entrypoint.sh:15`, `software/compose.yaml:50` |
| RE › Solo dos puertos publicados | `ps --format json` → api `[]`, db `127.0.0.1:5434->5432`, web `0.0.0.0:8090->8080` | `software/compose.yaml:24`, `:80` |
| RE › API inalcanzable desde el host | `curl localhost:8080` → exit 7; `curl <ip-api>:8080` → exit 28; control `curl :8090/health` → 200 | `software/compose.yaml:57` |
| RE › Base de datos solo en loopback | `config --format json` y `ps` → `127.0.0.1:5434->5432` | `software/compose.yaml:24` |
| RE › Servicios sanos tras el arranque | `up --build -d --wait` → exit 0; `ps` → db, api, web `(healthy)` | `software/compose.yaml:29`, `:61`, `:84` |
| RE › Dependencia no sana bloquea el arranque | proyecto `dispensart-depcheck` con healthcheck de db `exit 1` → `dependency failed to start`; api y web `Created` | `software/compose.yaml:58-60`, `:81-83` |
| RE › Base caída marca la API como no sana | `stop db` → `/ready` 503 `{"database":"fail","migrations":"skipped"}`; api `unhealthy` a los 30 s (interval 10 s × retries 3); `/health` 200 | `software/compose.yaml:61-66` |
| RE › Procesos sin root | `exec api id -u` → 1000; `exec web id -u` → 101; `ps -o user` → supervisord, php-fpm, nginx como `app` | `software/docker/api/Dockerfile:102`, `software/docker/web/Dockerfile:28` |
| RE › Imagen de API sin dependencias de desarrollo | `ls vendor` en la imagen → sin pestphp, larastan, phpstan, phpunit, tests; `command -v node` → ausente (control: `software/api/vendor/pestphp` existe en el árbol); el build exige su ausencia | `software/docker/api/Dockerfile:98-100` |
| RE › Imagen web solo con artefactos compilados | `find / -name node_modules -o -name '*.ts' -o -name '*.tsx' -o -name .env` → 0; `ls html` → `index.html`, `favicon.svg`, `assets/` (control: `software/web/node_modules` existe en el árbol) | `software/docker/web/Dockerfile.dockerignore:8` |
| RE › Archivo .env local excluido de las imágenes | `find / -name .env` en api y web → 0 (control: `software/api/.env` existe en el árbol); `test ! -e .env` en el build | `software/docker/api/Dockerfile.dockerignore:5`, `software/docker/api/Dockerfile:98` |
| RE › Plantilla completa | `/usr/bin/grep -oE '\$\{[A-Z_]+'` en compose vs claves de `.env.example` → 9 = 9 (`$${POSTGRES_*}` son del contenedor); `APP_KEY=` vacía | `software/.env.example:16` |
| RE › .env ignorado por git | `git check-ignore -v software/.env software/api/.env software/web/.env` → las tres ignoradas | `.gitignore:6`, `software/api/.gitignore:3` |
| RE › Credencial reemplazable | proyecto `dispensart-depcheck` con `DB_PASSWORD=s0_synthetic_override` → `/ready` 200; `POSTGRES_PASSWORD` y `DB_PASSWORD` del contenedor = valor provisto | `software/compose.yaml:13`, `:22` |
| RE › Shell en la raíz (componente) | muestra el nombre del producto y el mensaje de bienvenida en español | `software/web/src/App.test.tsx:35` |
| RE › Shell en la raíz (stack) | `curl :8090/` → 200 `text/html`, `<html lang="es">`; `chrome-headless-shell --dump-dom` → `<h1>Dispensart</h1>`, `Bienvenido`, mensaje | `software/docker/web/default.conf.template:28-29` |
| RE › Enlace profundo de la SPA | `curl :8090/inventario` → 200, `diff` con `/` → idéntico; DOM renderizado con el shell | `software/docker/web/default.conf.template:29` |
| RE › Ruta de API desconocida no cae en la SPA (API) | responde 404 en JSON con X-Correlation-Id a una ruta desconocida bajo /api (con y sin Accept) | `software/api/tests/Feature/ApiNotFoundTest.php:3` |
| RE › Ruta de API desconocida no cae en la SPA (stack) | `curl -H 'Accept: application/json' :8090/api/no-existe` → 404 `application/json` `{"code":"not_found",…}`, cid `s0-cold-002` | `software/docker/web/default.conf.template:10` |
| RE › Textos del shell desde el módulo central | cada texto visible coincide con un valor del módulo central; control positivo: un texto fuera del módulo es detectado; declara lang="es" en <html> | `software/web/src/App.test.tsx:52`, `:59`, `software/web/src/document.test.ts:6` |

### 1.3 ci-pipeline

| Escenario | Prueba o comando de evidencia → resultado | Archivo:línea |
|---|---|---|
| CI › Cambio en el código de la aplicación | push `7851138` (incluye `b41420a` en `software/`) → run `37727682908` success | `.github/workflows/ci.yml:6-8` |
| CI › Cambio en el workflow | push `18baa0a` (solo `ci.yml`) → run `37727881919` success | `.github/workflows/ci.yml:8` |
| CI › Cambio solo de documentación de proceso | push `4f1fef2` (solo `openspec/`) → `gh run list` sin corrida para ese SHA (control: `18baa0a` y `7851138` listados) | `.github/workflows/ci.yml:4-12` |
| CI › Backend correcto | run `37727881919` backend: Pint PASS, Larastan `[OK] No errors`, Pest 26 passed (143) | `.github/workflows/ci.yml:84`, `:87`, `:90` |
| CI › Violación de formato | local `pint --test` sobre archivo mal formateado → exit 1, md5 sin cambio; control limpio → exit 0 | `.github/workflows/ci.yml:84` |
| CI › Hallazgo de análisis estático o prueba fallida | local `phpstan analyse` (int en retorno string) → exit 1, control → exit 0; `pest` prueba rota → exit 1 | `.github/workflows/ci.yml:87`, `:90` |
| CI › Pruebas contra PostgreSQL | corre las pruebas contra PostgreSQL en la base de pruebas; local `DB_HOST=db-no-existe pest DatabaseConnectionTest` → exit 2 `SQLSTATE[08006]` (control → exit 0) | `software/api/tests/Feature/DatabaseConnectionTest.php:6`, `.github/workflows/ci.yml:33-45` |
| CI › Frontend correcto | run `37727881919` frontend: `npm ci`, lint, typecheck, Vitest success | `.github/workflows/ci.yml:112-121` |
| CI › Error de lint o prueba fallida | local `eslint` sobre archivo con errores → exit 1; `vitest run` prueba rota → exit 1; controles → exit 0 | `.github/workflows/ci.yml:115`, `:121` |
| CI › Lockfile desincronizado | copia en scratchpad con `left-pad` ausente del lock → `npm ci` exit 1 `Missing: left-pad@1.3.0 from lock file` | `.github/workflows/ci.yml:112` |
| CI › Permisos de solo lectura | lectura: `permissions: contents: read` a nivel de workflow; `/usr/bin/grep -n 'permissions' ci.yml` → 1 coincidencia, ningún job | `.github/workflows/ci.yml:15-16` |
| CI › Sin secretos ni publicación | `/usr/bin/grep -nE 'secrets\.\|docker push\|registry\|environment:' ci.yml` → 0 (exit 1); control: mismo patrón sobre `scratchpad/bad-secrets.yml` con `secrets.X` → 1 | `.github/workflows/ci.yml:1` |
| CI › Pull request desde un fork | disparo `pull_request` sin secretos → mismas compuertas | `.github/workflows/ci.yml:10` |

## 2. Anclas de transporte

| Cláusula | Ruta | Archivo:línea |
|---|---|---|
| SH › API viva (`GET /health`) | ruta `GET /health` | `software/api/routes/health.php:8` |
| SH › API lista / Base inalcanzable / Migraciones pendientes (`GET /ready`) | ruta `GET /ready` | `software/api/routes/health.php:9` |
| SH › Error no controlado conserva el identificador | render JSON + respaldo de cabecera | `software/api/bootstrap/app.php:36`, `:57` |
| SH › correlation id en toda respuesta | middleware global antepuesto | `software/api/bootstrap/app.php:27`, `software/api/app/Http/Middleware/AssignCorrelationId.php:28` |
| RE › Ruta de API desconocida no cae en la SPA (API) | JSON para `api/*` | `software/api/bootstrap/app.php:30` |
| RE › Ruta de API desconocida no cae en la SPA (web) | proxy `/api`, `/sanctum` | `software/docker/web/default.conf.template:10` |
| RE › Arranque desde cero sin .env (`/ready` vía web) | proxy `/health`, `/ready` | `software/docker/web/default.conf.template:15` |
| RE › Shell en la raíz / Enlace profundo | respaldo SPA `try_files $uri /index.html` | `software/docker/web/default.conf.template:29` |
| correlation id a través de web | cabecera reenviada | `software/docker/web/api-proxy.conf:8` |
| RE › Base caída marca la API como no sana | healthcheck `GET /ready` | `software/compose.yaml:63` |

## 3. Corridas de suite

| # | Quién | Comando | Resultado |
|---|---|---|---|
| 1 | backend-implementer | `run --rm api-tools vendor/bin/pest` | 1 fallida / 25 pasan: `DatabaseConnectionTest` vio `dispensart` (entorno de compose ganaba a `<env force>`) |
| 2 | backend-implementer | `run --rm api-tools sh -c 'pint --test && phpstan analyse && pest'` | 26 pasan, 143 aserciones, 0 fallidas (`<server force>` en `phpunit.xml`) |
| 3 | frontend-implementer | `npm run lint && npm run typecheck && npm test -- --run && npm run build` | lint 0, tipos 0, 8/8 pruebas en 3 archivos, build exit 0 |
| 4 | CI (devops) | run `37727682908` (`7851138`) | success; Pest 4 passed + 22 warnings (lectura de `.env` ausente) |
| 5 | CI (devops) | run `37727881919` (`18baa0a`, `cp .env.example .env`) | success; Pest 26 passed (143), 0 warnings; frontend success |

| Corrida parcial (no cuenta como suite) | Comando | Resultado |
|---|---|---|
| Negativas backend | `pest tests/Feature/Neg/FailTest.php` (temporal, borrado), `pest tests/Feature/DatabaseConnectionTest.php` con y sin host | exit 1 / exit 2 / control exit 0 |
| Diagnóstico warnings | `pest ApiNotFoundTest.php HealthTest.php` en copia sin `.env` y con `.env` de plantilla | 5 warnings / 5 passed |
| Negativas frontend | `vitest run src/neg-ci.test.ts` (temporal), `vitest run src/App.test.tsx` | exit 1 / control exit 0 |

## 4. Barridos (`/usr/bin/grep`, control positivo)

| Barrido | Comando | Resultado | Control positivo |
|---|---|---|---|
| Secretos en infraestructura y CI | `/usr/bin/grep -rnEi 'base64:[A-Za-z0-9+/=]{20,}\|(api[_-]?key\|secret\|token)\s*[:=]\s*…{12,}\|sk-…\|AKIA…\|BEGIN … PRIVATE KEY' software/docker software/compose.yaml software/.env.example .github/workflows` | 0 (exit 1) | 2 en `scratchpad/secret-control.txt` |
| Secretos en compose/db (1.1) | `/usr/bin/grep -rnEi '(api_key\|secret\|token\|password)\s*[:=]'` | 2, ambas la contraseña local marcada `dispensart_local_dev_only` | `API_KEY=sk-test123` en scratchpad → 1 |
| Claves de aplicación en el repo | `/usr/bin/grep -rnE 'base64:[A-Za-z0-9+/=]{20,}' --exclude-dir=vendor --exclude=.env .` | 0 (exit 1) | 1 en `scratchpad/control.php` |
| `set -x` en scripts de arranque | `/usr/bin/grep -rnE '^[^#]*set -x' software/docker` | 0 (exit 1) | sin el filtro de comentario: 1 (`entrypoint.sh:2`) |
| APP_KEY en logs | `docker compose logs api \| /usr/bin/grep -c 'base64:'`; idem con fragmento de la clave | 0 / 0 | línea sintética con el fragmento → 1 |
| Query string en logs | `logs web api \| /usr/bin/grep -c SINTETICO123` tras `GET ?documento=SINTETICO123` | 0 | `echo '…?documento=SINTETICO123' \| /usr/bin/grep -c` → 1 |
| CI sin secretos ni publicación | `/usr/bin/grep -rnE 'secrets\.\|docker push\|registry\|environment:' .github/workflows/ci.yml` | 0 (exit 1) | `scratchpad/bad-secrets.yml` → 1 |
| actionlint | `docker run rhysd/actionlint:1.7.12 -no-color -oneline .github/workflows/ci.yml` | exit 0 | `bad.yml` con `github.nope` → exit 1 |
| Depuración en la API | `/usr/bin/grep -rnE '\b(dd\|dump\|var_dump\|ray\|print_r)\(' app bootstrap config routes tests lang database` | 0 (exit 1) | 1 en `scratchpad/control.php` |
| Petición hacia el log | `/usr/bin/grep -rnE 'Log::.*(request\|->all\(\)\|->input\|->header\|->cookie)' app bootstrap routes` | 1: `AssignCorrelationId.php:51`, revisado (método, ruta, estado, duración) | 1 en `scratchpad/control.php` |
| SQLite en pruebas | `/usr/bin/grep -rnE 'sqlite\|:memory:' phpunit.xml tests` | 0 (exit 1) | 3 en `config/database.php` |
| `console.` en la SPA | `find -L src -type f -print0 \| xargs -0 /usr/bin/grep -n 'console\.'` | 0 | `printf 'console.log(1)'` → 1; archivos leídos 13 / presentes 13 |
| Almacenamiento y fetch en la SPA | `… /usr/bin/grep -nE 'fetch\(\|localStorage\|sessionStorage'` | 0 | muestra de 2 líneas → 2 |
| Texto literal en JSX fuera de pruebas | `… /usr/bin/grep` de texto entre etiquetas | 0 | `printf '<p>Hola</p>'` → 1 |
| JSON en logs | cada línea de `logs api` y `logs web` por `jq -e .` | no JSON: 0 de 6 (api), 0 de 5 (web) | `echo 'texto plano' \| jq -e .` → exit 5 |

## 5. Deuda señalada (prosa; el Orchestrator asigna ids)

| Origen | Deuda |
|---|---|
| backend | La línea de cierre registra la ruta literal; con rutas que lleven identificadores de paciente (S3) debe registrar el patrón de la ruta (RN-10). |
| backend | `phpunit.xml` fuerza `APP_KEY` vacía; S1 (sesión Sanctum, cookies cifradas) necesita una clave de prueba generada en la corrida, no escrita en el repo. |
| backend | Mensajes de excepción en logs pueden llevar bindings con datos personales desde S1; el formateador debe recortarlos (design, Riesgo 2). |
| devops | Sin `.env`, Pest reporta como advertencia la lectura del archivo en cada prueba de Feature; CI lo evita copiando la plantilla; causa raíz en el manejador de errores de Pest sin investigar. |
| devops | El healthcheck de `api` escribe una línea de log `/ready` por intervalo; filtrar cuando haya agregador de logs. |
| devops | `web` publica en `0.0.0.0`; restringir a loopback si el entorno del jurado lo exige. |
| devops | Imagen `api` pesada por supervisord/Python (design D1, revisar en S8). |
| devops | Sin semillas al arrancar: el `DatabaseSeeder` por defecto usa Faker (dependencia de desarrollo); S1 añade semillas idempotentes sin Faker y `db:seed --force` al entrypoint. |
