# Journal — add-delivery-pipeline (S8)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv
- Fase: /proposal adelantada. Apply espera el archivo de S7.
- Tier objetivo según ROADMAP: B.
- Insumos para el README (fuera de alcance): endpoint de lectura de bitácora para el auditor (S3).
- Riesgo heredado de S2: usuario de base de la app superusuario y dueño de tablas (puede deshabilitar el trigger del kardex).

## 2026-10-07 — spec-engineer: proposal, deltas, tasks borrador

- Producido: `proposal.md`; `specs/delivery-pipeline/spec.md` (5 requisitos ADDED); `specs/project-documentation/spec.md`
  (3 requisitos ADDED); `specs/ci-pipeline/spec.md` (MODIFIED «Permisos mínimos y sin secretos», encabezado igual al
  vivo: S0 ya archivado). `tasks.md` borrador, grupos 0–6.
- `openspec validate add-delivery-pipeline --strict`: válido. Barrido de ancla de transporte: 3 líneas THEN con HTTP,
  las 3 anclan a escenarios vivos de `service-health` / `runtime-environment`.
- Decisiones: MODIFIED de ci-pipeline acota la prohibición de publicar/desplegar a los trabajos de calidad, así el
  requisito vive con un workflow o con dos. Guarda en producción: GitHub crea el entorno sin protección si falta; el
  primer paso falla si no hay revisor requerido. Producción solo desde `main`.
- Riesgo heredado (rol de base superusuario): fuera de S8. Un rol de mínimo privilegio toca permisos sobre la escritura
  del kardex (disparador tier A, condición 3) y excede la fila (condición 1). Va al README con motivo y como fila
  candidata de ROADMAP.
- Supuestos: 9 (proposal § Assumptions).
- Para el architect (criterio "superficie de seguridad"): un workflow o dos sin correr las compuertas dos veces por
  push; paso de digests entre trabajos; ruta del script de humo; concurrencia del trabajo de producción.
- Dependencias de usuario: crear entorno `production` si el token no puede (3.1); visibilidad pública de paquetes si
  la API no lo permite (3.2); fusionar a `main` y aprobar/rechazar (4.4).
- Preguntas abiertas: ninguna bloqueante.

## 2026-10-07 — architect: design.md y refinamiento de tasks

- Producido: `design.md` (D1–D8, ≤ 1 página). Sin endpoints ni datos: sin tabla de contrato ni migraciones.
- Decisiones: un solo workflow (`ci.yml` extendido, `needs: [backend, frontend]`); digests como salidas del
  trabajo `build` (dos pasos `build-push-action`); etiqueta única `github.sha`; caché `type=gha` por imagen;
  staging con el mismo `compose.yaml` por variable de imagen, `pull` + `up --no-build --wait`; humo en
  `software/docker/smoke.sh`; producción con `actions: read`, guarda `gh api` con `GITHUB_TOKEN` que falla
  cerrada (0, 404 o 403); política de rama `main` en el entorno; rollback = re-ejecutar producción.
- Rechazados: `workflow_run` (corre la versión de `main`, contexto privilegiado), `workflow_call` en dos
  archivos, matriz por imagen (salidas se pisan), etiquetas de rama/`latest` (móviles, prohibidas), caché
  `type=registry`, override `compose.staging.yaml`, PAT para leer entornos (viola solo `GITHUB_TOKEN`),
  `workflow_dispatch` de rollback (YAGNI; revisar si hace falta un run de más de 30 días).
- Tasks: rutas provisionales fijadas; 3.2 pasa a 4.2 (requiere el primer push) y 4.2–4.4 → 4.3–4.5; 4.4 suma
  (d) negativo real de la guarda con entorno temporal `guard-check`, que además prueba el permiso del token;
  3.1 suma política de rama. Grupo 5 paralelizable con 2–4 tras 1.1.
- Riesgos: aprobación pendiente en `main` encola runs siguientes; `GITHUB_TOKEN` sin acceso a entornos
  (falla cerrada, probado en 4.4(d)); paquetes privados al nacer (4.2).
- `openspec validate add-delivery-pipeline --strict`: válido.

## 2026-10-07 — spec-engineer: hallazgos del spec-validator

- proposal.md recortado a ≤ 450 palabras; tasks 4.2 con verificación ejecutable (dueño y SHA derivados); tasks 6.1 nombra escenarios. Validación estricta: válida. design.md y orden de tareas del architect intactos.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: strict válido; propuesta 416 palabras; 4.2 ejecutable; 6.1 nombra escenarios. Su último
  hallazgo (0.1, 0.2, 4.2 "Cimiento" sin escenario) contradice la convención aceptada en S0–S7: una tarea
  Cimiento con comando ejecutable cumple. Se descarta (Iron rule 12). Ancla de transporte: 3 hits, 3 con ancla.
- Condición 1 (alcance exacto de S8): OK. Imágenes en GHCR, staging simulado, producción con compuerta,
  documento de despliegue, README, AI_USAGE. Rol de base sin privilegios: fuera de alcance (README + fila candidata).
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): B, coincide con ROADMAP.
- Condición 4 (ADR / RN): costo cero (runners y GHCR gratuitos en repositorio público); ningún ADR reabierto.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier B
- Pasos del usuario previstos: Environment `production` con revisores si el token no alcanza; merge a `main`.

## 2026-10-08 — Orchestrator: apply parcial adelantado

- `openspec validate add-delivery-pipeline --strict`: válido. GATE 1 registrado.
- S5 está bloqueado esperando la decisión de tier del usuario; S7 depende de S5. Para no detener el run, se
  adelanta la parte de S8 independiente de S5/S7: compose parametrizado, workflow de entrega (construcción,
  GHCR, staging, producción con compuerta), entorno `production`, evidencia de ejecución en `dev` y rama de
  trabajo, documento de despliegue. Quedan para el final: 0.1, 4.5 (merge del usuario a `main`), README,
  AI_USAGE y `verification.md`.

## 2026-10-08 — devops-implementer: apply parcial (0.2, 1.1, 2.1–2.7, 3.1, 4.1–4.4, 5.1, 5.2)

- 0.2: `gh repo view --json visibility` = `PUBLIC`; `gh api repos/pedrozopayares/Dispensart/environments --jq .total_count` sale con 0 (valor 0 antes de 3.1).
- 1.1: `compose.yaml` con `image: ${API_IMAGE:-dispensart-api:local}` / `${WEB_IMAGE:-dispensart-web:local}`; `config --images` muestra las locales por defecto y la referencia `ghcr.io/x/dispensart-api@sha256:0…0` con la variable; `.env.example` con `API_IMAGE=`/`WEB_IMAGE=` (grep = 1).
- 2.1–2.7 en `.github/workflows/ci.yml` (347 líneas; D1 pedía revisar si pasa de ~250: se mantiene un archivo, la entrega es un bloque contiguo). Workflow `permissions: {}`; yq: backend/frontend `{"contents":"read"}`, build `{"contents":"read","packages":"write"}` (único), staging `{"contents":"read","packages":"read"}`, production `{"actions":"read"}`. Secretos: solo `secrets.GITHUB_TOKEN` (3 usos, ninguno en calidad). checkout 4 = `persist-credentials: false` 4. `:latest|type=ref,event=branch` sin salida; `github.sha` = 4; `add-mask` = 1. actionlint 1.7.12 en 0. Filtro de rutas sin cambios (D1). Acciones: checkout@v7, setup-buildx@v4, login@v4, build-push@v7; `provenance`/`sbom` apagados (un manifiesto por imagen).
- 2.4: `software/docker/smoke.sh`. Positivo en el stack local compartido y en uno aislado: 8 PASA, `Humo VERDE`, sale 0 (api uid 1000, web uid 101). Negativo (stack aislado, `stop db`, `SMOKE_TIMEOUT=20`): sale 1, `FALLA db … ausente`, `FALLA GET /ready: HTTP 503`, imprime `== docker compose logs`; `start db` al final.
- Aislamiento: para no tocar el stack compartido (S6 en curso), cierre en frío, humo negativo y 5.2 corrieron con `COMPOSE_PROJECT_NAME=dispensart-s8 WEB_PORT=8091 DB_PORT=5435 API_IMAGE=dispensart-api:s8 WEB_IMAGE=dispensart-web:s8`; `config -q` en 0, `down -v` + `up --build --wait` desde cero: 3 `healthy`, `/health` y `/ready` 200. Stack aislado bajado con `down -v` e imágenes `:s8` borradas al final.
- 3.1: entorno `production` creado por el agente con `gh api -X PUT` (token del dueño, alcance `repo`): revisor requerido `pedrozopayares` (id 7519384), `custom_branch_policies` con política de rama `main` (id 62336239). Guarda: consulta en `production` imprime 1; en `environments/no-existe` 404 y sale 1 (control positivo).
- 4.1: run 37739984220 (SHA 2ef8dac0c3717f39cd39443d02f7fc5563807d80, push a `dev`): backend, frontend, build, staging `success`; producción `skipped`. Digests: api `sha256:02d94905dff1c49b7c8b04c318f34d2f4eb7f32ed08c8f04cf82c2782acf57e6`, web `sha256:3cb7a1639b354be6950188345b1034679cb6bbab409f61bdbdefb4b8a1c3cb46`. Log: `@sha256:` = 47 líneas; en staging, 11 referencias por imagen y 0 `load build definition` (control positivo: 2 en el log completo, del trabajo build); paso de control `DB_PASSWORD=***`; humo 8 PASA. Segundo run de `dev` 37740614991 (SHA b74fc5d, doc de despliegue): mismas conclusiones; api `sha256:9ca111f14002894357f616d7cfa32c0e2348b9e249ac828e1bfd3a333e06c6e4`, web `sha256:a5e32f5117998fb59a022bddeee6613a38bda6ffd427220cea3420ea5a4cda92`.
- 4.2: sin acción. Los paquetes heredaron la visibilidad pública del repositorio; la API REST no permite cambiarla y el token no tiene `read:packages`. Comprobación sin credenciales (`DOCKER_CONFIG` vacío, equivalente a `docker logout`): `ok-api`, `ok-web` para la etiqueta 2ef8dac…; control negativo: etiqueta inexistente falla. `tags/list` anónimo: solo SHAs de 40 hex, ningún `latest`/`dev`/`main`.
- 4.3: push a `feat/add-delivery-pipeline` run 37740344904 y PR #1 a `dev` run 37740348254 (`pull_request`): backend y frontend `success`, build/staging/producción `skipped`. PR cerrado sin fusionar.
- 4.4: desviación de forma: cada negativo en su rama `feat/add-delivery-pipeline-neg-{a,b,c,d}` (en paralelo; en una sola rama `cancel-in-progress` cortaba el anterior). El commit temporal amplía el `if` de build a `startsWith(github.ref, 'refs/heads/feat/add-delivery-pipeline-neg-')`.
  - (a) run 37740345100: backend `failure` en el paso `Pruebas (Pest contra PostgreSQL)`; build, staging, producción `skipped`.
  - (b) run 37740344926: build `success`; staging `failure` en `Humo` (`FALLA GET /no-existe-s8: HTTP 200 sin 'nunca'`: el fallback de la SPA responde 200, falla el cuerpo), `== docker compose logs` impreso, `Logs del stack` y `Bajar el stack` ejecutados; producción `skipped`.
  - (c) run 37740344336: staging `failure` en `Descarga de las imágenes publicadas` (`manifest unknown`), arranque y humo `skipped`, 0 `load build definition` en staging; producción `skipped`.
  - (d) run 37740344567: staging `success`; producción en entorno temporal `guard-check`: `Guarda de revisor requerido` `failure` con `Reglas de revisor requerido en guard-check: 0` (el `GITHUB_TOKEN` con `actions: read` lee la API de entornos: 0, no 403), `Despliegue simulado` `skipped`.
  - Limpieza: 5 ramas remotas borradas (`git ls-remote --heads origin 'feat/add-delivery-pipeline*'` = 0 líneas), ramas locales y worktrees borrados, `guard-check` borrado (GET → 404); entornos: `["production"]`. Las imágenes de las ramas negativas quedan en GHCR solo con etiqueta SHA.
- 5.1: `software/docs/deployment.md`: 498 palabras; 3 encabezados `## (estrategia|rollback|respaldo)`; grep de credenciales sin salida (control positivo: `pg_dump` = 2). El respaldo va a `$HOME/dispensart.dump` (fuera del repositorio: `*.dump` no está en `.gitignore`).
- 5.2: ida y vuelta con los comandos del documento tal como están escritos, sobre el stack aislado (`HOME` = scratchpad). Conteos `kardex_movements stocks transfers`: semilla + humos de dispensación y traslado = `19 14 2`; tras `down -v` + `up` = `14 14 0` (control positivo: la restauración es la que devuelve los datos); tras restaurar = `19 14 2`. `curl -fsS …/ready` sale 0 con `{"status":"ready",…}`. Primer intento sin `up --wait` tras `restart api`: el doc se corrigió para esperar `healthy` antes del `curl`, y se repitió literal.
- Barrido de secretos (`/usr/bin/grep -nE 'ghp_|gho_|github_pat_|AKIA…|-----BEGIN|base64:…|sk-…'`) sobre los 5 archivos tocados: sin salida (rc 1); control positivo con `ghp_` sintético en scratchpad = 1.
- Deuda (prosa): `ci.yml` superó el umbral de ~250 líneas de D1; si crece más, valorar dividir la entrega con `workflow_call` sin duplicar compuertas. `*.dump` no está en `.gitignore` (raíz fuera del alcance de este agente).
- Pendiente: 0.1, 4.5 (fusión del usuario a `main`, aprobar y rechazar), 5.3–5.6, 6.1.
