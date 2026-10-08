# Verification — add-delivery-pipeline (S8, tier B)

Fuente: secciones de `journal.md` (devops-implementer y backend-implementer). Árbol medido: `dev` en `0876014`.
Prefijos: `DP` = `delivery-pipeline`, `PD` = `project-documentation`, `CI` = `ci-pipeline` (MODIFIED). `REPO` =
`pedrozopayares/Dispensart`. Workflow único: `.github/workflows/ci.yml` (design D1); humo: `software/docker/smoke.sh`.
Runs de `dev` con entrega completa: `37739984220` (SHA `2ef8dac`), `37782508066` (SHA `59db1e7`), `37783646165`
(SHA `26bc3df`). Negativos de 4.4 en ramas `feat/add-delivery-pipeline-neg-{a,b,c,d}` (borradas). Filas de 4.5
(run de `main` del usuario) pendientes: las completa el Orchestrator tras la fusión.

## 0. Reparto de líneas

Líneas añadidas por los commits de S8 (`git show --numstat <commit>`, suma de la primera columna por alcance).
Commits: `6d973aa 1b9ffbe 7650154 aee73fc 6eb376b 2ef8dac b74fc5d b730b19 3130190 59db1e7 916c735 3c8e709 df71b72
26bc3df 6a430f2 cde1889 5edcd0f ecae484 9272a2e 0876014`.

| Categoría | Alcance | Líneas |
|---|---|---|
| Producto — entrega | `.github/workflows/ci.yml` (205), `software/compose.yaml` (6), `software/.env.example` (5), `.gitignore` (3) | 219 |
| Producto — API (saldo D-auv-5/6) | `software/api/app/Services/Assistant/*` | 24 |
| Datos de evaluación | `software/api/resources/assistant/evaluation-set.json` | 8 |
| Prueba — humos | `software/docker/smoke.sh`, `software/docker/smoke/assistant-smoke.sh` | 120 |
| Prueba — API | `software/api/tests/Feature/Assistant/*` | 67 |
| Documentación | `README.md` (171), `AI_USAGE.md` (61), `software/docs/deployment.md` (66), `software/docs/asistente.md` (1) | 299 |
| Registro | `openspec/changes/add-delivery-pipeline/**` antes de este archivo | 693 |
| Otro | `openspec/ROADMAP.md` | 2 |

## 1. Matriz escenario → evidencia

| Capacidad | Escenarios en la spec (`/usr/bin/grep -c '^#### Scenario:'`) | Con evidencia | Pendientes (4.5) |
|---|---|---|---|
| delivery-pipeline | 22 | 19 | 3 |
| project-documentation | 13 | 13 | 0 |
| ci-pipeline | 3 | 3 | 0 |

### 1.1 delivery-pipeline

| Id | Escenario | Evidencia (run, comando o archivo:línea) |
|---|---|---|
| DP-01 | Push a dev con compuertas verdes | runs 37739984220, 37782508066, 37783646165: backend y frontend `success`, después build `success` sobre el mismo SHA; `ci.yml:177` `needs: [backend, frontend]` |
| DP-02 | Compuerta de calidad fallida | run 37740345100 (neg. a, Pest roto): backend `failure`; build, staging, producción `skipped`; run `failure` |
| DP-03 | Pull request | run 37740348254 (PR #1 `feat/add-delivery-pipeline` → `dev`, evento `pull_request`): backend y frontend `success`; build, staging, producción `skipped`; `ci.yml:178` exige `github.event_name == 'push'` |
| DP-04 | Push a una rama de trabajo | run 37740344904 (push a `feat/add-delivery-pipeline`): backend y frontend `success`; build, staging, producción `skipped`; `ci.yml:178` restringe a `dev`/`main` |
| DP-05 | Cambio solo de documentación de proceso | filtro `ci.yml:9-14` (`software/**`, `ci.yml`, `package*.json`); `gh run list --commit <sha>`: `5edcd0f` (README, AI_USAGE, openspec) = 0 runs, `916c735` y `b730b19` (solo openspec) = 0 runs; control positivo `59db1e7` (toca `software/`) = 1 run |
| DP-06 | Publicación de ambas imágenes | run 37739984220: build `success`, digests api `sha256:02d94905…acf57e6`, web `sha256:3cb7a163…a1c3cb46` como salidas (`ci.yml:185-186`); `docker manifest inspect ghcr.io/pedrozopayares/dispensart-{api,web}:2ef8dac…` sin credenciales: `ok-api`, `ok-web`; etiqueta OCI `source` en `ci.yml:221`, `:237` |
| DP-07 | Construcción fallida | estructural: staging `needs: [build]` (`ci.yml:254`), producción `needs: [build, staging]` (`ci.yml:312`); run 37740345100 muestra la cadena omitida (build no corre, staging y producción `skipped`) |
| DP-08 | Sin etiquetas móviles | `ghcr.io/v2/pedrozopayares/dispensart-{api,web}/tags/list` anónimo (2026-10-08): 14 etiquetas por paquete, 14 de 40 hex, 0 otras; `/usr/bin/grep -nE ':latest\|type=ref,event=branch' .github/workflows/*.yml` sin salida (control positivo `github.sha` = 4) |
| DP-09 | Humo verde | runs 37739984220 (8 PASA, `Humo VERDE`), 37782508066 y 37783646165 (`Humo VERDE`, 6 `PASA  humo de dominio`); `smoke.sh:50-58` healthy, `:74-75` `/health` y `/ready`, `:80-81` `id -u` |
| DP-10 | Humo fallido | run 37740344926 (neg. b, ruta inexistente): staging `failure` en el paso de humo, `== docker compose logs` impreso, `Bajar el stack` (`down -v`, `ci.yml:305-306`) ejecutado, producción `skipped`; local: `stop db` → sale 1, `FALLA GET /ready: HTTP 503` |
| DP-11 | Imagen ausente en el registro | run 37740344336 (neg. c, digest inexistente): staging `failure` en `Descarga de las imágenes publicadas` (`manifest unknown`); arranque y humo `skipped`; 0 `load build definition` en staging |
| DP-12 | Sin reconstrucción en staging | run 37739984220: en el log de staging 11 referencias `@sha256:` por imagen y 0 `load build definition` (control positivo: 2 en el log completo, del trabajo build); `ci.yml:261-262` imágenes por digest, `:293` `up --no-build` |
| DP-13 | Espera de aprobación | pendiente: 4.5, run de main del usuario |
| DP-14 | Aprobado | pendiente: 4.5, run de main del usuario |
| DP-15 | Rechazado | pendiente: 4.5, run de main del usuario |
| DP-16 | Push a dev | runs 37739984220, 37782508066, 37783646165: staging `success`, producción `skipped` (no `failure`, sin aprobación pedida); `ci.yml:313` solo `refs/heads/main` |
| DP-17 | Staging fallido | estructural: `ci.yml:312` `needs: [build, staging]`; runs 37740344926 y 37740344336: staging `failure`, producción `skipped`, run `failure` (en esas ramas el `if` de `main` también omite producción; el run de `main` con staging fallido no se forzó) |
| DP-18 | Entorno sin revisor requerido | run 37740344567 (neg. d, entorno temporal `guard-check`): `Guarda de revisor requerido` `failure` con `Reglas de revisor requerido en guard-check: 0`, `Despliegue simulado` `skipped`; `ci.yml:320-335`; local: `environments/no-existe` → 404 y sale 1; `production` → 1 |
| DP-19 | Escritura al registro acotada | yq sobre `.jobs[].permissions`: backend y frontend `{"contents":"read"}`, build `{"contents":"read","packages":"write"}` (único), staging `{"contents":"read","packages":"read"}`, producción `{"actions":"read"}`; `ci.yml:23` `permissions: {}`; `/usr/bin/grep -nE 'id-token\|contents: write'` sin salida (control positivo: `packages: write` en `ci.yml:183`) |
| DP-20 | Sin secretos guardados | `/usr/bin/grep -rhoE 'secrets\.[A-Za-z_]+' .github/workflows/ \| sort \| uniq -c` = `3 secrets.GITHUB_TOKEN`; en las líneas de calidad (`ci.yml:31-169`) 0 coincidencias de `secrets.\|docker push\|ghcr.io\|environment:\|packages:\|id-token` (control positivo: 15 en `ci.yml:170-`) |
| DP-21 | Contraseña de staging fuera de los logs | `ci.yml:270-273` genera y aplica `::add-mask::` antes de exportar; paso de control imprime `DB_PASSWORD=***` en run 37739984220 (`success`), 37740344926 y 37740344336 (`failure`) |
| DP-22 | Checkout sin credenciales persistidas | `.github/workflows/ci.yml`: `actions/checkout` = 4, `persist-credentials: false` = 4 (único archivo de workflow) |

### 1.2 project-documentation

| Id | Escenario | Evidencia (run, comando o archivo:línea) |
|---|---|---|
| PD-01 | Extensión y secciones | `wc -w < software/docs/deployment.md` = 498 (≤ 550); encabezados `deployment.md:6` estrategia, `:32` rollback, `:42` respaldo y restauración |
| PD-02 | Respaldo y restauración de ida y vuelta | 5.2 sobre stack aislado con los comandos de `deployment.md:50` (`pg_dump -Fc`) y `:57` (`pg_restore --clean --if-exists`): conteos `kardex_movements stocks transfers` = `19 14 2` antes, `14 14 0` tras `down -v` + `up` (control positivo), `19 14 2` tras restaurar; `curl -fsS …/ready` sale 0 |
| PD-03 | Rollback con esquema incompatible | `deployment.md:34` re-ejecutar producción del último run verde (digest anterior); `:37-39` esquema incompatible → restaurar el respaldo previo y re-ejecutar |
| PD-04 | Sin credenciales reales | `/usr/bin/grep -nE 'PASSWORD=[^$]\|APP_KEY=.+\|ghp_\|base64:' software/docs/deployment.md` sin salida (control positivo: `pg_dump` = 2) |
| PD-05 | Comando único desde un clon limpio | 5.5: clon en `9272a2e`, sin `.env` ni `vendor/`, `docker compose -f software/compose.yaml up --build` literal con `COMPOSE_PROJECT_NAME=dispensart-clon WEB_PORT=8092 DB_PORT=5436 API_IMAGE/WEB_IMAGE=…:clon` (desviación: puertos del stack de desarrollo ocupados); `api`, `db`, `web` healthy; `GET /` 200 y `lang="es"` = 1 (control `lang="en"` = 0) |
| PD-06 | Compromisos de diseño | `/usr/bin/grep -cE '^### Compromiso' README.md` = 6 (`README.md:53`, `:61`, `:69`, `:77`, `:85`, `:93`), cada uno con filas Elegido / Descartado / Costo |
| PD-07 | Fuera de alcance con motivo | `README.md:124` sección; `:128` bitácoras para el `auditor`, `:130` rol de mínimo privilegio, `:138` deuda abierta = ninguna (D-auv-4/5/6 saldadas, § 3); `/usr/bin/grep -ciE 'bitácora\|mínimo privilegio' README.md` = 4 |
| PD-08 | Rutas citadas existentes | bucle de 5.4 sobre `README.md` y `AI_USAGE.md` (24 y 25 rutas): sin salida; incluye `software/api/openapi.json` y `software/docs/deployment.md` |
| PD-09 | Ruta citada inexistente | control positivo de 5.4: copia con `` `software/no-existe.md` `` → `FALTA README-copia software/no-existe.md` |
| PD-10 | Comando de evaluación del asistente | 5.5: `docker compose -f software/compose.yaml --profile tools run --rm api-tools php artisan assistant:eval` literal en el clon: sale 0, `Aciertos: 24/24` (antes del arreglo `9272a2e` salía 255: control positivo); CI run 37783646165: `Aciertos: 24/24` |
| PD-11 | Secciones exigidas | `/usr/bin/grep -ciE '^## .*(herramientas\|tareas\|aceptad\|rechazad\|corregid)' AI_USAGE.md` = 4 (`AI_USAGE.md:8`, `:21`, `:34`, `:41`) |
| PD-12 | Ejemplos con fuente verificable | tabla de citas de 5.6 en `journal.md` (sección 0.1/5.3/5.4/5.6): cada ruta citada abierta y su hecho localizado con `/usr/bin/grep -n` (p. ej. `archive/2026-10-08-add-dispensation/journal.md:212`, `archive/2026-10-08-add-alerts/journal.md:95`) |
| PD-13 | Ejemplo sin fuente | el bucle de 5.4 sobre `AI_USAGE.md` falla con una ruta inexistente (mismo control positivo que PD-09); los ejemplos de `AI_USAGE.md:34-50` traen ruta cada uno |

### 1.3 ci-pipeline (MODIFIED)

| Id | Escenario | Evidencia (run, comando o archivo:línea) |
|---|---|---|
| CI-01 | Permisos de solo lectura | `ci.yml:35-36` backend y `:136-137` frontend `contents: read`, sin otra clave; yq de DP-19 |
| CI-02 | Sin secretos ni publicación | barrido de DP-20 sobre `ci.yml:31-169`: 0 coincidencias (control positivo 15 en el bloque de entrega) |
| CI-03 | Pull request desde un fork | estructural: calidad sin `secrets.` (DP-20) y entrega exige `push` (`ci.yml:178`); run 37740348254 (`pull_request` propio): solo calidad, entrega `skipped` |

## 2. Cláusula → ancla (líneas con HTTP)

| Escenario | Cláusula con HTTP | Ancla (spec vivo) | Evidencia |
|---|---|---|---|
| Humo verde | THEN `GET /health` y `GET /ready` vía `web` 200, `GET /` 200 con la SPA | SH › API viva con dependencias sanas (`service-health/spec.md:15`); SH › API lista (`:33`); RE › Shell en la raíz (`runtime-environment/spec.md:139`) | DP-09 |
| Humo fallido | WHEN `GET /ready` no responde 200 | SH › Base de datos inalcanzable (`service-health/spec.md:37`); SH › Migraciones pendientes (`:41`) | DP-10 (`stop db` → `/ready` 503) |
| Respaldo y restauración de ida y vuelta | THEN `/ready` responde HTTP 200 | SH › API lista (`service-health/spec.md:33`) | PD-02 |
| Comando único desde un clon limpio | THEN la URL indicada responde HTTP 200 con la SPA | RE › Arranque desde cero sin .env (`runtime-environment/spec.md:15`) | PD-05 |

## 3. Saldo de deuda cargada a S8

| Fila | Qué saldó | Commit | Evidencia |
|---|---|---|---|
| D-auv-4 | humos de dominio en staging sobre base nueva | `59db1e7` | run 37782508066: 6 `PASA  humo de dominio`; base nueva en orden CI e inverso, 0 fallas; control: stub fallido → sale 1 |
| D-auv-5 | filtro previo bloquea lo dispensado a una persona sin «paciente» | `3c8e709` | `AssistantDefenseEndpointTest.php:92`, `AssistantOrchestratorTest.php:254`, `:261`; sin el cambio 8 fallas / 34 pasan; con él 205 pasan |
| D-auv-6 | nombre de herramienta inventado saneado en log y `tool_calls` | `df71b72` | `AssistantOrchestratorTest.php:155`, `AssistantQueryLogTest.php:83`; mismas corridas delta que D-auv-5 |
| CI de ambos saldos | Pint, Larastan, Pest, `assistant:eval`, build, staging | `26bc3df` | run 37783646165: backend, frontend, build, staging `success`, producción `skipped`, `Aciertos: 24/24` |

## 4. Tareas Cimiento y precondiciones

| Tarea | Comando | Salida |
|---|---|---|
| 0.1 | `test -f openspec/specs/assistant-evaluation/spec.md && npx openspec validate --all --strict` | sale 0; 23 passed |
| 0.1 | `/usr/bin/grep -cE '^\| D-' openspec/DEBT.md` | 6 (control `^\| Id` = 2) |
| 0.2 | `gh repo view --json visibility -q .visibility` | `PUBLIC` |
| 1.1 | `docker compose -f software/compose.yaml config --images` | `dispensart-api:local`, `dispensart-web:local`; con `API_IMAGE=…@sha256:0…0` esa referencia |
| 3.1 | guarda de 2.6 sobre `environments/production` | 1 (revisor `pedrozopayares`, política de rama `main`) |
| 4.2 | `docker manifest inspect` sin credenciales | `ok-api`, `ok-web`; etiqueta inexistente falla |
| 5.3 | `/usr/bin/grep -c 'última tajada' README.md` | 0 (control `compose.yaml` = 8) |
| 5.5 | limpieza del clon | 0 proyectos y 0 volúmenes `dispensart-clon`; stack de desarrollo intacto |
| actionlint | `docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:latest` | sale 0 |

## 5. Pendiente del usuario (4.5)

| Paso | Estado |
|---|---|
| Fusión `dev` → `main` | pendiente: 4.5, run de main del usuario |
| Rechazo y aprobación en `production`; `gh api repos/$REPO/actions/runs/<id>/approvals` | pendiente: 4.5, run de main del usuario |
| Digests del resumen = digests del log de staging del mismo run | pendiente: 4.5, run de main del usuario |
