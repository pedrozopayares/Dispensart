# Journal — add-project-skeleton (S0)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: arranque de autopiloto

- shard = auv
- Preflight de máquina: árbol limpio, `dev` al día, `openspec validate --all --strict` sin elementos,
  Docker 28.5.1 responde, puertos 8090 y 5434 libres, Node v26.5.0, PHP 8.4.8, Composer 2.8.9.
- Fase: /proposal. Tier objetivo según ROADMAP: B.

## 2026-10-07 — spec-engineer: proposal, deltas, tasks

- Producido: `proposal.md`, `specs/service-health/spec.md`, `specs/runtime-environment/spec.md`,
  `specs/ci-pipeline/spec.md`, `tasks.md` (5 grupos, orden base de datos → api → web → contenedores → CI).
- Capacidades nuevas: `service-health`, `runtime-environment`, `ci-pipeline` (S8 la extiende).
- `openspec validate add-project-skeleton --strict`: válido. Ancla de transporte: todos los hits del barrido
  de `CYCLE-TIERS.md` llevan `[ancla: …]` con archivo:línea a fijar en apply. Cada escenario citado en tasks.
- Supuestos (en `proposal.md` § Assumptions): `/health` y `/ready` en la raíz de la API y reenviados por
  `web`; arranque sin `.env` con contraseña de desarrollo local por defecto; `DB_PORT` solo en loopback;
  `APP_KEY` generada persistida en volumen; shell sin peticiones (sin estados carga/error/vacío hasta S6);
  formato válido de correlation id 1–128 `[A-Za-z0-9._-]`.
- Decisiones de alcance: datos semilla automáticos diferidos a S1; el healthcheck de `api` refleja
  disponibilidad, no solo vivacidad; el CI no incluye verificación de tipos ni build de imágenes.
- Preguntas abiertas para architect: mecanismo de persistencia de `APP_KEY`; base de pruebas separada en
  el mismo contenedor `db`; cómo simular base inalcanzable y migración pendiente en pruebas de feature.

## 2026-10-07 — architect: design.md y refinamiento de tasks

- Producido: `design.md` (D1–D7). `openspec validate add-project-skeleton --strict`: válido.
- Respuestas: (1) `APP_KEY` ausente → entrypoint la genera capturada en variable y la persiste en volumen
  `api_state` (`0600`); rechazados `.env` interno, base de datos, exigirla. (2) Base de pruebas
  `dispensart_test` en el mismo `db` vía `initdb`; CI con `POSTGRES_DB=dispensart_test`; `phpunit.xml`
  la fuerza; rechazado servicio `db-test`. (3) `/ready`: `ReadinessChecker` concreto; base caída = puerto
  cerrado + `DB::purge` en archivo sin `RefreshDatabase`; pendiente = `migrator->path(tmp)`; rechazada
  interfaz con doble (simula la propiedad bajo prueba).
- Otras: imagen `api` Nginx + FPM bajo supervisord (rechazado FPM solo con FastCGI desde `web`: acopla y
  complica S8); Pest local en servicio `api-tools` (perfil `tools`, etapa `dev`), PHP del host no se usa;
  log = formateador derivado de `JsonFormatter` vía `tap` + `Context` (rechazado `JsonFormatter` crudo:
  claves no cumplen spec); acciones de CI por etiqueta mayor hasta S8.
- tasks.md: nueva 2.2 (etapas `base`/`dev` + `api-tools`) antes de Pest; 2.2–2.8 renumeradas a 2.3–2.9;
  grupo 3 marcado paralelizable con grupo 2; dependencias de grupos 4 y 5 explícitas.
- Riesgos: `initdb` solo con volumen vacío; bindings de `QueryException` en logs pueden filtrar datos
  personales desde S1 (candidato a deuda, RN-10); propiedad del volumen `api_state` para usuario no root.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-project-skeleton`. Ancla de transporte: 11 hits, 11 con ancla.
- Condición 1 (alcance exacto de la fila S0): OK. Esqueletos api/web, compose, `/health` `/ready`, CI con lint y pruebas; imágenes publicadas, despliegues y documentos quedan en S8.
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): B, coincide con ROADMAP.
- Condición 4 (ADR / RN): ninguna decisión congelada ni regla debilitada.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier B
- Riesgo del architect (mensajes de excepción con datos de paciente en logs): pertenece a RN-10, alcance de S3. No se fila deuda; se pasa como requisito a la propuesta de S3.

## 2026-10-07 — Orchestrator: /apply

- `openspec validate add-project-skeleton --strict`: válido.
- Preflight de suite: no aplica (S0 crea el stack y las suites; no hay línea base previa).
- Delegación: hilo 1 devops-implementer (1.1, 2.2: servicio `db`, etapas `base`/`dev` y `api-tools`) ∥ hilo 2
  frontend-implementer (grupo 3, sin contrato con la API). Luego backend-implementer (2.1, 2.3–2.9).
  Luego devops-implementer (grupo 4 y 5).

## 2026-10-07 — Orchestrator: incidente de harness

- Los tipos `backend-implementer`, `frontend-implementer` y `devops-implementer` no se registran en esta
  sesión (`Agent type ... not found`); su frontmatter está sano y los demás tipos sí cargan.
- Rodeo: agentes `general-purpose` que leen su archivo de rol en `.claude/agents/` como definición
  obligatoria. Mismo alcance, misma ley. Lección candidata para RETROSPECTIVES al cierre.

## 2026-10-07 — Orchestrator: rodeo revertido, apply en espera

- Instrucción del usuario: no usar `general-purpose`; solo agentes declarados y skills instaladas.
  Los dos agentes del rodeo se detuvieron a mitad de trabajo.
- Estado parcial: `software/web/` sin commit (scaffolding Vite incompleto del rodeo); ningún archivo de
  compose ni Dockerfile creado. El frontend-implementer real decide si lo reutiliza o lo regenera.
- Causa probable: la sesión arrancó 19:49 y los tres `*-implementer.md` se crearon después; los tipos de
  agente se registran al iniciar la sesión y `/clear` no los recarga. Desbloqueo: reiniciar Claude Code
  desde la raíz del repo.
- Mientras tanto avanzan las propuestas que hacen los agentes registrados (S1 diseño, S2 propuesta).
- Commit `cdaf9b5` (sin push) renombrado a `ce7b09c spec: propone esqueleto Laravel + React, Docker
  Compose, /health y CI` por la regla de títulos legibles para el jurado.

## 2026-10-07 — Orchestrator: handoff para la sesión nueva

Punto de reanudación: S0 en fase /apply, GATE 1 registrado, ninguna tarea `[x]`, sin código en `software/`.
- El scaffolding parcial de `software/web` del rodeo se retiró del repo (árbol limpio para el preflight).
- Primer paso en la sesión nueva: quitar `bloqueado` de S0 (volver a `en curso`) y delegar según la sección
  /apply de arriba: devops-implementer (1.1, 2.2) ∥ frontend-implementer (grupo 3); luego
  backend-implementer (2.1, 2.3–2.9); luego devops-implementer (grupos 4 y 5).
- Commits con títulos legibles para el jurado (Git law, `CLAUDE.md`).
- S1 y S2: propuestas en curso en sus carpetas; ver sus `journal.md`.

## 2026-10-07 — Orchestrator: reanudación tras reinicio

- Sesión reiniciada; los tipos `*-implementer` ya se registran. S0 vuelve a `en curso`.
- Preflight: Docker 28.5.1, puertos 8090 y 5434 libres, `dev` en `86a0f0f`.
- Delegación: devops-implementer (1.1, 2.2) ∥ frontend-implementer (grupo 3).

## 2026-10-07 — devops-implementer: tareas 1.1 y 2.2

Archivos: `software/compose.yaml`, `software/docker/db/init/01-create-test-db.sql`,
`software/docker/api/Dockerfile`, `software/docker/api/Dockerfile.dockerignore`.

| Comprobación | Comando | Resultado |
|---|---|---|
| Config compose | `docker compose -f software/compose.yaml config -q` | sale 0 |
| `db` en frío | `down -v` → `up -d db --wait` | `healthy`, `127.0.0.1:5434->5432/tcp` |
| Bases creadas | `psql -Atc "select datname ... like 'dispensart%'"` | `dispensart`, `dispensart_test` |
| Versión base | `select version()` | PostgreSQL 16.15 |
| PHP en `api-tools` | `--profile tools run --rm api-tools php -v` | PHP 8.5.11 (NTS), OPcache |
| Extensiones | `php -m` | `pdo_pgsql`, `intl`, `bcmath`, `pcov` |
| Composer | `composer --version` | 2.10.3 |
| Usuario | `id -u` en `api-tools` | 1000 (no root) |
| Enlace a la base de pruebas | `new PDO("pgsql:host=db;...dbname=dispensart_test")` | conecta |
| Etapa `base` sola | `docker build --target base` | construye |
| Puertos | `config --format json` | `api-tools` sin puertos; `db` solo `127.0.0.1` |
| Barrido de secretos | `/usr/bin/grep -rnEi '(api_key\|secret\|token\|password)\s*[:=]'` | 2 aciertos, ambos la contraseña local marcada `dispensart_local_dev_only`; control positivo (`API_KEY=sk-test123` en scratchpad) detectado |

Decisiones:
- Imágenes fijadas: `postgres:16.15-alpine3.24`, `php:8.5.11-fpm-alpine3.24`, `composer:2.10.3`.
- Contexto de construcción de la API = `software/` (la etapa `prod` de 4.1 copiará `api/` y `docker/api/`);
  `Dockerfile.dockerignore` excluye `.env`, `web/`, `vendor/`, `.git`. 4.1 lo afina.
- `api-tools` recibe `DB_PORT: 5432` literal (puerto interno), no el `DB_PORT` del host.
- `pcov` en `dev` para cobertura/mutación de Pest; no entra en `prod`.
- El bind mount crea `software/api/` vacío al correr `api-tools`; se borró (2.1 lo crea el backend-implementer).
- Pendiente de 2.1: la verificación `php artisan about` (`pgsql`, `es`) de 2.2 la cierra el backend-implementer
  tras el scaffolding. Sin `actionlint` en el host (no hubo workflows en esta entrega).

## 2026-10-07 — frontend-implementer: grupo 3 (SPA React)

- Andamiaje `create-vite` (react-ts). La plantilla trae oxlint; se reemplaza por ESLint (flat config,
  typescript-eslint, react-hooks, react-refresh, `--max-warnings 0`): la pila fija ESLint y la spec de CI lo nombra.
- shadcn/ui iniciado con base Radix, estilo new-york, color neutral; componentes `card` y `button`. El CLI
  instala el paquete oficial `cn` (shadcn-ui/cn) en lugar de clsx + tailwind-merge.
- Compatibilidad Node 22: `.nvmrc` = `22` en `software/web` (para `setup-node` de 5.1); `engines.node`
  `>=22.22.2`, piso exigido por jsdom 30.
- Decisión: `QueryClient` con `mutations.retry = false`; un reintento automático no debe duplicar escrituras.
- Corrida de cierre única: `npm run lint && npm run typecheck && npm test -- --run && npm run build`.

| Comando | Resultado |
|---|---|
| `npm run lint` | 0 errores, 0 avisos |
| `npm run typecheck` | 0 errores |
| `npm test -- --run` | 3 archivos, 8/8 pruebas en verde |
| `npm run build` | sale con 0 |
| `npm ci` | sale con 0 (lockfile sincronizado) |

| Escenario | Prueba | Archivo:línea |
|---|---|---|
| RE › Shell en la raíz (componente) | muestra el nombre del producto y el mensaje de bienvenida en español | `software/web/src/App.test.tsx:35` |
| RE › Textos del shell desde el módulo central (textos) | cada texto visible coincide con un valor del módulo central | `software/web/src/App.test.tsx:52` |
| RE › Textos del shell desde el módulo central (control positivo) | control positivo: un texto fuera del módulo es detectado | `software/web/src/App.test.tsx:59` |
| RE › Textos del shell desde el módulo central (`lang="es"`) | declara lang="es" en <html> | `software/web/src/document.test.ts:6` |
| 3.2 Cimiento shadcn renderiza | monta un componente de shadcn/ui (tarjeta) | `software/web/src/App.test.tsx:42` |
| 3.3 Proveedor TanStack Query en la raíz | entrega un QueryClient a los descendientes | `software/web/src/app/providers.test.tsx:13` |
| 3.3 Proveedor (negativo) | sin el proveedor, useQueryClient falla | `software/web/src/app/providers.test.tsx:22` |
| Decisión reintentos | las mutaciones no se reintentan solas | `software/web/src/app/providers.test.tsx:29` |
| CI › Frontend correcto (local) | corrida de cierre arriba | `software/web/package.json` scripts |

| Barrido (`/usr/bin/grep`, sobre `find -L src -type f`) | Coincidencias | Control positivo |
|---|---|---|
| `console\.` | 0 | `printf 'console.log(1)'` → 1 |
| `fetch\(\|localStorage\|sessionStorage` | 0 | muestra de 2 líneas → 2 |
| texto literal en JSX fuera de pruebas | 0 | `printf '<p>Hola</p>'` → 1 |
| `console` (amplio) | 1 (`providers.test.tsx:24`, spy silenciador) | — |
| archivos leídos / presentes | 13 / 13 | — |

- Pendiente para 4.5: comprobación renderizada contra el stack compose (no listo en esta tarea).
- Ancla de transporte de "RE › Shell en la raíz" (Nginx de `web`) queda para 4.3.

## 2026-10-07 — backend-implementer: tareas 2.1 y 2.3–2.9

Scaffolding con `composer create-project laravel/laravel:^13.0` dentro de `api-tools` (lockfile resuelto en
PHP 8.5.11). Retirado del esqueleto: assets de Vite, vista `welcome`, ruta `/up`, `AGENTS.md`/`CLAUDE.md`
de Boost (instrucciones de harness ajenas a la raíz), `database.sqlite`, PHPUnit (reemplazado por Pest).

| Comprobación | Comando (en `api-tools`) | Resultado |
|---|---|---|
| Versiones | `php artisan about` | Laravel 13.35.0, PHP 8.5.11, Timezone UTC, Locale es, Database pgsql, Logs stderr |
| Herramientas | `composer show` | pestphp/pest 5.3.1, pest-plugin-laravel 5.0.1, larastan 3.13.0, phpstan 2.3.0, pint (esqueleto) |
| Formato | `vendor/bin/pint --test` | sale 0 |
| Análisis estático | `vendor/bin/phpstan analyse` (nivel 6; `app`, `bootstrap/app.php`, `config`, `database`, `routes`) | `[OK] No errors` |
| Suite, corrida 1 | `vendor/bin/pest` | 1 fallida / 25 pasan: `DatabaseConnectionTest` vio `dispensart` en vez de `dispensart_test` |
| Suite, corrida 2 (cierre) | `pint --test && phpstan analyse && pest` | 26 pasan, 143 aserciones, 0 fallidas |

Decisión — base de pruebas forzada también en `$_SERVER`: compose inyecta `DB_DATABASE=dispensart` en el
entorno del proceso y Laravel lee `$_SERVER` antes que `$_ENV`, así que `<env force>` de `phpunit.xml`
perdía y la corrida 1 ejecutó `migrate:fresh` sobre la base de desarrollo (solo tablas por defecto). La prueba
de conexión lo detectó; `phpunit.xml` añade `<server force>` para `DB_CONNECTION`, `DB_DATABASE`, `DB_URL`.

Decisión — Larastan no analiza `tests/`: no entiende el `$this` ligado de los cierres de Pest y reporta
falsos positivos sobre `TestCall`. El código bajo prueba sí se analiza completo.

Decisión — forma de error JSON `{code, message}` para `api/*` y peticiones JSON (`bootstrap/app.php`), con
mensajes en `lang/es/errors.php`, sin traza ni mensaje interno aun con `APP_DEBUG=true`. Validación,
autenticación y `HttpResponseException` conservan el render de Laravel (S1 los ajusta).

### Anclas de transporte (SH, RE)

| Escenario | Ancla | Archivo:línea |
|---|---|---|
| SH › API viva con dependencias sanas | ruta `GET /health` | `software/api/routes/health.php:8` |
| SH › API viva con la base de datos caída | ruta `GET /health` | `software/api/routes/health.php:8` |
| SH › API lista | ruta `GET /ready` | `software/api/routes/health.php:9` |
| SH › Base de datos inalcanzable | ruta `GET /ready` | `software/api/routes/health.php:9` |
| SH › Migraciones pendientes | ruta `GET /ready` | `software/api/routes/health.php:9` |
| SH › Error no controlado conserva el identificador | manejador de excepciones (render + respaldo de cabecera) | `software/api/bootstrap/app.php:36`, `software/api/bootstrap/app.php:57` |
| RE › Ruta de API desconocida no cae en la SPA | enrutador de la API (JSON para `api/*`) | `software/api/bootstrap/app.php:30` (lado API; el proxy de `web` lo fija 4.3) |
| (todas) correlation id | middleware global antepuesto | `software/api/bootstrap/app.php:27`, `software/api/app/Http/Middleware/AssignCorrelationId.php:28` |

### Escenario → prueba

| Escenario | Prueba | Archivo:línea |
|---|---|---|
| CI › Pruebas contra PostgreSQL | corre las pruebas contra PostgreSQL en la base de pruebas | `software/api/tests/Feature/DatabaseConnectionTest.php:6` |
| SH › API viva con dependencias sanas | responde 200 con el cuerpo exacto {"status":"ok"} con la base disponible | `software/api/tests/Feature/Health/HealthTest.php:7` |
| SH › API viva con la base de datos caída | responde 200 con {"status":"ok"} aunque la base de datos esté caída | `software/api/tests/Feature/Health/HealthTest.php:16` |
| SH › Vivacidad sin estado ni datos internos | no emite cookies ni más clave que status | `software/api/tests/Feature/Health/HealthTest.php:28` |
| SH › API lista | responde 200 ready con base disponible y todas las migraciones aplicadas | `software/api/tests/Feature/Health/ReadyTest.php:7` |
| SH › Migraciones pendientes | responde 503 con migrations pending si existe una migración sin aplicar | `software/api/tests/Feature/Health/ReadyTest.php:13` |
| SH › Base de datos inalcanzable | responde 503 con database fail y migrations skipped si la base es inalcanzable | `software/api/tests/Feature/Health/ReadyDatabaseDownTest.php:13` |
| SH › Fallo sin filtrar detalles internos | no filtra detalles internos en el cuerpo y registra la excepción con el correlation_id | `software/api/tests/Feature/Health/ReadyDatabaseDownTest.php:19` |
| SH › Cabecera válida respetada | respeta un X-Correlation-Id válido; acepta el límite de 128 caracteres válidos | `software/api/tests/Feature/CorrelationIdTest.php:10`, `:16` |
| SH › Cabecera ausente | genera UUIDs distintos cuando la cabecera falta | `software/api/tests/Feature/CorrelationIdTest.php:23` |
| SH › Cabecera inválida reemplazada | reemplaza una cabecera inválida por un UUID y no la registra en el log (4 casos) | `software/api/tests/Feature/CorrelationIdTest.php:32` |
| SH › Error no controlado conserva el identificador | responde 500 con X-Correlation-Id, sin traza en el cuerpo, y lo registra en la línea del error | `software/api/tests/Feature/CorrelationIdTest.php:52` |
| SH › Línea de cierre por petición | escribe exactamente una línea de cierre por petición con método, ruta, estado y duración | `software/api/tests/Feature/RequestLogTest.php:22` |
| SH › Cada línea es JSON válido | escribe cada línea como objeto JSON con timestamp, level, message y correlation_id | `software/api/tests/Feature/RequestLogTest.php:35` |
| SH › Query string, cuerpo y cabeceras excluidos | excluye query string, cuerpo, cookies y cabecera Authorization del log | `software/api/tests/Feature/RequestLogTest.php:53` |
| SH › Log fuera de una petición | escribe JSON válido con correlation_id null fuera de una petición | `software/api/tests/Feature/RequestLogTest.php:71` |
| RE › Ruta de API desconocida no cae en la SPA (lado API) | responde 404 en JSON con X-Correlation-Id a una ruta desconocida bajo /api (con y sin Accept) | `software/api/tests/Feature/ApiNotFoundTest.php:3` |
| CI › Backend correcto (base) | reglas `arch()` base | `software/api/tests/Arch/ArchitectureTest.php:5`, `:8`, `:11`, `:15` |

### Barridos (`/usr/bin/grep`, control positivo en `scratchpad/control.php`)

| Barrido | Comando | Resultado | Control positivo |
|---|---|---|---|
| Depuración | `/usr/bin/grep -rnE '\b(dd\|dump\|var_dump\|ray\|print_r)\(' app bootstrap config routes tests lang database` | 0 (exit 1) | 1 en el control |
| Petición hacia el log | `/usr/bin/grep -rnE 'Log::.*(request\|->all\(\)\|->input\|->header\|->cookie)' app bootstrap routes` | 1: `AssignCorrelationId.php:51`, revisado: solo método, ruta, estado, duración | 1 en el control |
| Claves de aplicación | `/usr/bin/grep -rnE 'base64:[A-Za-z0-9+/=]{20,}' --exclude-dir=vendor --exclude=.env .` | 0 (exit 1) | 1 en el control |
| SQLite en pruebas | `/usr/bin/grep -rnE 'sqlite\|:memory:' phpunit.xml tests` | 0 (exit 1) | 3 en `config/database.php` |
| `.env` ignorado | `git check-ignore -v software/api/.env` | `software/api/.gitignore:3` | `vendor/autoload.php` también ignorado |

### Deuda (prosa; el Orchestrator la fila)

- La línea de cierre registra la ruta literal; cuando entren rutas con identificadores de paciente (S3) debe
  registrar el patrón de la ruta para no filtrar datos personales (RN-10).
- `phpunit.xml` fuerza `APP_KEY` vacía (paridad con CI sin `.env`); S1 (sesión Sanctum, cookies cifradas)
  necesitará una clave de prueba generada en la corrida, no escrita en el repo.
- La corrida 1 consumió presupuesto de suite: el cambio lleva dos corridas completas del backend.

## 2026-10-08 — devops-implementer: tareas 4.1–4.5, 5.1, 5.2

Commits: `b41420a` (imágenes + compose), `7851138` (CI), `18baa0a` (CI: `.env` desde plantilla), `4f1fef2` (tareas).
Imágenes fijadas: `php:8.5.11-fpm-alpine3.24`, `composer:2.10.3`, `node:22.23.1-alpine3.23`,
`nginxinc/nginx-unprivileged:1.30.3-alpine3.23`, `postgres:16.15-alpine3.24`. Acciones: `checkout@v7`,
`setup-php@v2`, `cache@v6`, `setup-node@v7` (etiqueta mayor, design D7).

### Verificación integral (4.5), stack del proyecto `dispensart` únicamente

| Comprobación | Comando | Resultado clave |
|---|---|---|
| Sin `.env` | `test -e software/.env` | no existe |
| Config | `docker compose -f software/compose.yaml config -q` | exit 0 |
| Arranque en frío | `down -v` (borra `db_data`, `api_state`) → `up --build -d --wait` | exit 0 en 16 s; db, api, web `healthy` |
| Puertos | `ps --format json` (Publishers con PublishedPort) | api `[]`; db `127.0.0.1:5434->5432`; web `0.0.0.0:8090->8080` |
| /health vía web | `curl -H 'X-Correlation-Id: s0-cold-002' :8090/health` | 200 `application/json`, cid `s0-cold-002` |
| /ready vía web | idem `/ready` | 200 `{"status":"ready","checks":{"database":"ok","migrations":"ok"}}` |
| API desconocida | `curl -H 'Accept: application/json' :8090/api/no-existe` | 404 JSON `{"code":"not_found",…}`, cid presente |
| Shell y enlace profundo | `curl :8090/` y `:8090/inventario` + `diff` | 200 `text/html`, `<html lang="es">`, mismo documento |
| Shell renderizado | `chrome-headless-shell --dump-dom :8090/inventario` | `<h1>Dispensart</h1>`, `Bienvenido`, mensaje de bienvenida |
| No root | `exec api id -u` / `exec web id -u`; `ps -o user` | 1000 / 101; supervisord, php-fpm, nginx como `app` |
| Migraciones al arrancar | log de arranque + `select count(*) from migrations` | `"applied":3` y 3 filas; bases `dispensart`, `dispensart_test` |
| Log JSON | cada línea de `logs api` y `logs web` por `jq -e` | api 6/6, web 5/5 JSON; cid `s0-cold-002` en ambos |
| Sin query string en log | `GET /inventario?documento=SINTETICO123`, `/health?…` | 0 coincidencias en logs (control positivo: 1) |
| API inalcanzable | `curl localhost:8080`, `curl <ip-api>:8080` desde el host | exit 7 / exit 28; control vía web 200 (`:9000` responde 403: es `kitepms-minio-1`, ajeno) |
| APP_KEY ausente | sha256 de `bootstrap/cache/config.php` vs `/var/lib/dispensart/app_key` | iguales (`9a49a645…`), archivo `600 app`; `base64:` en logs: 0, fragmento de clave: 0 (control 1) |
| Reinicio con datos | fila sintética en `users` → `down` (sin -v) → `up -d --wait` | fila presente (1), misma clave, log `"source":"volume"`, `"applied":0` |
| APP_KEY provista | `APP_KEY=<sintética> up -d --wait api` | clave efectiva = sha256 provista; volumen sin cambio; log `"source":"env"` |
| Puertos alternativos | `WEB_PORT=8095 DB_PORT=5440 up -d --wait` | 8095 `/ready` 200; 8090 exit 7; 5440 acepta; 5434 sin escucha |
| Base caída | `stop db`, sondeo cada 5 s | `/ready` 503 inmediato `{"database":"fail","migrations":"skipped"}`; api `unhealthy` a los 30 s (3 intervalos); `/health` 200 |
| Dependencia no sana | proyecto aparte `dispensart-depcheck` con healthcheck de db `exit 1` | `dependency failed to start`; api y web `Created`, nunca iniciados; luego `down -v` |
| Credencial reemplazable | proyecto aparte con `DB_PASSWORD=s0_synthetic_override` | `/ready` 200; `POSTGRES_PASSWORD` y `DB_PASSWORD` = valor provisto; luego `down -v` |
| Imagen api | `find / -name .env`; `vendor/` | 0 `.env` (control: `software/api/.env` existe en el árbol); sin pestphp, larastan, phpstan, phpunit, tests, node; código `root`, `storage` `app` |
| Imagen web | `find / -name node_modules -o -name '*.ts*' -o -name .env` | 0; solo `index.html`, `favicon.svg`, `assets/` |
| `.env` ignorado | `git check-ignore -v software/.env software/api/.env software/web/.env` | las tres ignoradas |
| Plantilla | `${VAR}` de compose vs claves de `software/.env.example` | 9 = 9 (`$${POSTGRES_*}` son del contenedor); `APP_KEY=` vacía |
| api-tools intacto | `run --rm api-tools php -v` | PHP 8.5.11 |

### CI (5.1, 5.2)

| Escenario | Verificado | Evidencia |
|---|---|---|
| CI › Cambio en el código de la aplicación | push real | run `37727682908` (`7851138`, incluye `b41420a` en `software/`): success |
| CI › Cambio en el workflow | push real | run `37727881919` (`18baa0a`, solo `ci.yml`): success |
| CI › Cambio solo de documentación de proceso | push real | `4f1fef2` (solo `openspec/`): `gh run list` sin corrida para ese SHA |
| CI › Backend correcto / Pruebas contra PostgreSQL | push real | backend: PHP 8.5.11, Pint PASS 42 archivos, Larastan `[OK] No errors`, Pest 26 passed (143) sobre `postgres:16.15` |
| CI › Frontend correcto | push real | `npm ci`, lint, typecheck, Vitest: success |
| CI › Violación de formato | local, mismo comando | `pint --test` sobre archivo mal formateado: exit 1, md5 sin cambio; control limpio exit 0 |
| CI › Hallazgo de análisis estático o prueba fallida | local | `phpstan analyse` (retorno int en método string): exit 1, control exit 0; `pest` prueba rota: exit 1 |
| CI › Pruebas contra PostgreSQL (sin servicio) | local | `DB_HOST=db-no-existe pest DatabaseConnectionTest`: exit 2 `SQLSTATE[08006]`, sin SQLite; control exit 0 |
| CI › Error de lint o prueba fallida | local | `eslint` archivo con 3 errores: exit 1; `vitest run` prueba rota: exit 1; controles exit 0 |
| CI › Lockfile desincronizado | local, copia en scratchpad | `npm ci` con `left-pad` ausente del lock: exit 1 `Missing: left-pad@1.3.0 from lock file` |
| CI › Permisos / sin secretos / fork | lectura + barrido | `permissions: contents: read` (ci.yml:15-16), ningún job lo amplía; `secrets.\|docker push\|registry\|environment:` 0 coincidencias |
| actionlint | `rhysd/actionlint:1.7.12` | ci.yml exit 0; control con expresión inválida exit 1 |

Las negativas se hicieron en local con los mismos comandos del workflow, no con commits rotos en
`feat/add-project-skeleton` (instrucción del Orchestrator: menor costo; el árbol quedó limpio, `git status` vacío).

### Anclas de transporte (RE)

| Escenario | Ancla | Archivo:línea |
|---|---|---|
| RE › Arranque desde cero sin .env (`/ready` vía web) | proxy `/health`, `/ready` | `software/docker/web/default.conf.template:15` |
| RE › Ruta de API desconocida no cae en la SPA | proxy `/api`, `/sanctum` | `software/docker/web/default.conf.template:10` |
| RE › Shell en la raíz / Enlace profundo | respaldo SPA | `software/docker/web/default.conf.template:28-29` |
| (todas por web) correlation id | cabecera reenviada | `software/docker/web/api-proxy.conf:8` |
| RE › Base caída marca la API como no sana | healthcheck `/ready` | `software/compose.yaml:61-63` |
| RE › Solo dos puertos / loopback | publicación | `software/compose.yaml:24`, `:80` |
| RE › Migraciones aplicadas al arrancar | entrypoint | `software/docker/api/entrypoint.sh:34` |
| RE › APP_KEY ausente / provista | entrypoint | `software/docker/api/entrypoint.sh:15-25` |

### Barridos (`/usr/bin/grep`)

| Barrido | Resultado | Control positivo |
|---|---|---|
| Secretos (`base64:…`, `api_key/secret/token=`, `sk-`, `AKIA`, llave privada) en `software/docker`, `compose.yaml`, `.env.example`, `.github/workflows` | 0 (exit 1) | 2 en `scratchpad/secret-control.txt` |
| `set -x` fuera de comentarios en `software/docker` | 0 (exit 1) | la línea comentada sí aparece sin el filtro |

### Decisiones

- `.dockerignore` por imagen como lista blanca (`docker/<img>/Dockerfile.dockerignore`, BuildKit): contexto `software/`.
- `web` resuelve `api` en cada petición (`resolver` + variable): sobrevive a la recreación de `api`.
- Log de acceso de `web` en JSON sin IP ni query string (`$request_path`); `api` no duplica log de Nginx.
- `cap_drop: [ALL]` y `no-new-privileges` en `api` y `web`.
- CI copia `.env.example` a `.env`: sin archivo, Pest marcaba 22 pruebas como advertencia (lectura de `.env`).
- `verification.md` no se escribe aquí: el registro se escribe una vez, al cierre (Cost discipline).

### Deuda (prosa; el Orchestrator la fila)

- Sin `.env`, Pest reporta como advertencia la lectura del archivo (Dotenv) en cada prueba de Feature; CI lo
  evita copiando la plantilla. Causa raíz en el manejador de errores de Pest, no investigada (backend).
- El healthcheck de `api` escribe una línea de log `/ready` cada 10 s; filtrable cuando haya agregador de logs.
- `web` publica en `0.0.0.0`; restringir a loopback si el puesto del jurado lo exige.
- Imagen `api` 306 MB (supervisord/Python, design D1).
- Sin seeders al arrancar en S0 (el `DatabaseSeeder` por defecto usa Faker, dependencia de desarrollo); S1
  debe añadir semillas idempotentes sin Faker y `db:seed --force` al entrypoint.
