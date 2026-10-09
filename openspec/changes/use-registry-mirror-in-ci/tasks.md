# Tasks

Tier B. `[MUT]` declarados: 7 (M1–M7), solo en la guarda (`design.md` D3): una guarda que siempre sale 0, o que solo
mira el workflow, pasaría «Árbol conforme». Prefijo de escenario: CP = delta `ci-pipeline`.

Orden: 1 → 2.1 → 3 → 5 → 6. 4.2 y 4.3 (consultas de solo lectura al registro) pueden correr en paralelo con 3. Un
solo implementador (devops); no hay bloques api ni web.

Todo comando se corre en bash desde la raíz del repo. Cada comando `[MUT]` es autocontenido: copia el árbol
commiteado a un directorio temporal, aplica la mutación a la copia, comprueba que cambió el archivo, corre la guarda
sobre la copia mutada y luego sobre la copia restaurada. Nunca muta el árbol de trabajo.

## 1. Stack local y plantilla de entorno (infraestructura)

- [ ] 1.1 `software/compose.yaml:17`: imagen de `db` = `${DB_IMAGE:-postgres:16.15-alpine3.24}`, con comentario en
  español (local = Docker Hub; el CI la fija al espejo). Cubre CP › «Stack local sin DB_IMAGE», «Base de datos del
  staging desde el espejo». Verifica: `env -u DB_IMAGE docker compose -f software/compose.yaml config | /usr/bin/grep -c 'image: postgres:16.15-alpine3.24'`
  imprime 1 (si `software/.env` define `DB_IMAGE`, se registra y se quita para la prueba); `DB_IMAGE=mirror.gcr.io/library/postgres:16.15-alpine3.24 docker compose -f software/compose.yaml config | /usr/bin/grep -c 'image: mirror.gcr.io/library/postgres:16.15-alpine3.24'`
  imprime 1; `/usr/bin/grep -nE '^ARG (PHP|COMPOSER|NODE|NGINX)_IMAGE=|^# syntax=' software/docker/*/Dockerfile` muestra
  los valores de hoy sin cambio.
- [ ] 1.2 `software/.env.example`: `DB_IMAGE` documentada (comentada, valor por defecto de Docker Hub, una línea
  sobre el espejo en CI), como exige el requisito vivo «Secretos fuera del repositorio» de `runtime-environment`.
  Cubre CP › «Stack local sin DB_IMAGE». Verifica: `/usr/bin/grep -n 'DB_IMAGE' software/.env.example` ≥ 1 línea.

## 2. Guarda contra descargas directas de Docker Hub (infraestructura)

- [ ] 2.1 Script `software/docker/check-ci-images.sh [raíz]` según `design.md` D3 (bash + awk + grep ERE POSIX,
  comentarios en español): reglas (a) workflow, incluida la imagen `driver-opts` de `docker/setup-buildx-action`
  fijada como `mirror.gcr.io/moby/buildkit:vX.Y.Z`; (b) `ARG *_IMAGE=`, `# syntax=` y `FROM` literal de Docker Hub sin
  sobrescritura en el paso que declara `file:` de ese Dockerfile (`BUILDKIT_SYNTAX` para `# syntax=`); (c) compose.
  Salidas: hallazgos `archivo:línea: motivo` y exit 1; `OK: <n> referencias revisadas` y exit 0; exit 2 si falta un
  archivo de entrada o `n = 0`. Cubre CP › «Imagen sin registro en el workflow», «Imagen base nueva en un Dockerfile
  sin redirigir», «Servicio de compose descargado sin redirigir», «Constructor de BuildKit sin redirigir», «Imágenes
  construidas o de otros registros». Verifica (rojo previo, tras 1.1 y antes de 3.x):
  `bash software/docker/check-ci-images.sh; echo "exit=$?"` imprime `exit=1` y una línea que empieza por
  `.github/workflows/ci.yml:43:`; además `bash software/docker/check-ci-images.sh /nonexistent; echo "exit=$?"` imprime
  `exit=2`. Ambas salidas en `journal.md`.

## 3. Workflow de CI (devops)

- [ ] 3.1 Trabajo backend: `services.postgres.image` = `mirror.gcr.io/library/postgres:16.15-alpine3.24` (comentario
  actualizado: misma imagen que compose, vía espejo). Cubre CP › «Servicio PostgreSQL del backend desde el espejo».
  Verifica: `bash software/docker/check-ci-images.sh 2>&1 | /usr/bin/grep -c '^\.github/workflows/ci\.yml:43:'` imprime 0;
  control: la misma tubería antes de 3.1 imprimió 1 (salida de 2.1).
- [ ] 3.2 Trabajo build (`design.md` D1, D2): en `docker/setup-buildx-action`, `with:` → `driver-opts:
  image=mirror.gcr.io/moby/buildkit:v0.33.1` (una línea). En cada `docker/build-push-action`, `build-args: |` con
  `BUILDKIT_SYNTAX=mirror.gcr.io/docker/dockerfile:1` más, en `api`, `PHP_IMAGE=mirror.gcr.io/library/php:8.5.11-fpm-alpine3.24`
  y `COMPOSER_IMAGE=mirror.gcr.io/library/composer:2.10.3`; en `web`, `NODE_IMAGE=mirror.gcr.io/library/node:22.23.1-alpine3.23`
  y `NGINX_IMAGE=mirror.gcr.io/nginxinc/nginx-unprivileged:1.30.3-alpine3.23`. Cubre CP › «Construcción de imágenes
  desde el espejo», «Constructor de BuildKit sin redirigir». Verifica:
  `bash software/docker/check-ci-images.sh 2>&1 | /usr/bin/grep -cE '^software/docker/|setup-buildx'` imprime 0; control:
  la salida de 2.1 tenía hallazgos de `software/docker/api/Dockerfile` y `software/docker/web/Dockerfile`; log de build en 6.2.
- [ ] 3.3 Trabajo staging: `env.DB_IMAGE: mirror.gcr.io/library/postgres:16.15-alpine3.24` a nivel de trabajo, de modo que
  `docker compose pull db` y `up` la usen. Cubre CP › «Base de datos del staging desde el espejo». Verifica:
  `bash software/docker/check-ci-images.sh 2>&1 | /usr/bin/grep -c '^software/compose\.yaml:'` imprime 0; control: la
  salida de 2.1 tenía un hallazgo `software/compose.yaml:`.
- [ ] 3.4 Paso «Guarda de imágenes en el espejo» en el trabajo frontend (`working-directory: .`, `run: bash
  software/docker/check-ci-images.sh`, sin registros en el texto del paso). Cubre CP › «Árbol conforme», «Imágenes
  construidas o de otros registros». Verifica: `bash software/docker/check-ci-images.sh; echo "exit=$?"` imprime
  `OK: <n> referencias revisadas` con n > 0 y `exit=0`. Control de rojo, ejecutable (árbol previo al cambio, commit
  `df7f657`):
  `t="$(mktemp -d)"; mkdir -p "$t/.github/workflows" "$t/software"; git show df7f657:.github/workflows/ci.yml > "$t/.github/workflows/ci.yml"; git show df7f657:software/compose.yaml > "$t/software/compose.yaml"; cp -R software/docker "$t/software/"; bash software/docker/check-ci-images.sh "$t"; echo "exit=$?"`
  imprime `exit=1` y una línea `.github/workflows/ci.yml:43:`. Paso sin registros:
  `/usr/bin/grep -n -A3 'Guarda de imágenes en el espejo' .github/workflows/ci.yml | /usr/bin/grep -cE 'gcr\.io|docker\.io|ghcr\.io'`
  imprime 0; control: `/usr/bin/grep -n -A3 'docker/login-action' .github/workflows/ci.yml | /usr/bin/grep -cE 'ghcr\.io'` ≥ 1.
- [ ] 3.5 Barrido de credenciales: `/usr/bin/grep -nE 'secrets\.|login-action|registry:' .github/workflows/ci.yml`
  muestra solo `secrets.GITHUB_TOKEN` y `registry: ghcr.io`, igual que
  `git show df7f657:.github/workflows/ci.yml | /usr/bin/grep -nE 'secrets\.|login-action|registry:'` (control, mismas
  líneas de contenido). Cubre CP › «Sin credenciales para el espejo».

## 4. Documentación y contenido (devops)

- [ ] 4.1 Una línea en `software/docs/deployment.md` § Estrategia de despliegue (`design.md` D4): el CI descarga las
  imágenes de Docker Hub desde `mirror.gcr.io` por el límite anónimo; antes de subir una versión se comprueba la
  etiqueta en el espejo; vuelta de emergencia = quitar el prefijo `mirror.gcr.io/library/` / `mirror.gcr.io/` de
  `ci.yml` y retirar el paso de la guarda en el mismo commit. Cubre CP › «Vuelta documentada a Docker Hub». Verifica:
  `/usr/bin/grep -c 'mirror.gcr.io' software/docs/deployment.md` = 1 y esa línea contiene `ci.yml` y `guarda`.
- [ ] 4.2 Igualdad de digest de las siete imágenes. Comando:
  `for r in library/postgres:16.15-alpine3.24 library/php:8.5.11-fpm-alpine3.24 library/composer:2.10.3 library/node:22.23.1-alpine3.23 nginxinc/nginx-unprivileged:1.30.3-alpine3.23 docker/dockerfile:1 moby/buildkit:v0.33.1; do printf '| %s | %s | %s |\n' "$r" "$(docker buildx imagetools inspect "mirror.gcr.io/$r" --format '{{json .Manifest}}' | jq -r .digest)" "$(docker buildx imagetools inspect "docker.io/$r" --format '{{json .Manifest}}' | jq -r .digest)"; done`
  Cubre CP › «Mismo contenido que Docker Hub». Verifica: tabla en `verification.md` con ambos digests iguales en cada
  fila; si una difiere, se detiene y se informa (etiqueta móvil).
- [ ] 4.3 Fallo cerrado del espejo. Cubre CP › «Espejo sin la etiqueta pedida». Verifica:
  `docker buildx imagetools inspect mirror.gcr.io/library/postgres:0.0.0-no-existe; echo "exit=$?"` imprime exit ≠ 0
  sin consultar `docker.io`; control positivo:
  `docker buildx imagetools inspect mirror.gcr.io/library/postgres:16.15-alpine3.24 >/dev/null; echo "exit=$?"` imprime
  `exit=0`; sin respaldo configurado: `bash software/docker/check-ci-images.sh; echo "exit=$?"` imprime `exit=0`
  (ninguna referencia directa a Docker Hub en CI); un pull fallido de servicio deja el run rojo:
  `gh run view 37990501600 --json conclusion -q .conclusion` imprime `failure`.

## 5. Commit local y pines de la guarda (devops)

- [ ] 5.1 Commit local en `dev`, sin push: `git add .github/workflows/ci.yml software/compose.yaml software/.env.example software/docker/check-ci-images.sh software/docs/deployment.md`;
  `git commit -m "fix: el CI descarga las imágenes de Docker Hub desde un espejo sin límite anónimo"` (cuerpo: «S11»).
  Puerta antes de cualquier `[MUT]`: `git status --porcelain -- .github software` no imprime nada; `git rev-parse --short HEAD`
  registrado en `journal.md`. Cubre CP › «Árbol conforme». Verifica: `bash software/docker/check-ci-images.sh; echo "exit=$?"`
  sobre ese commit imprime `exit=0`.
- [ ] 5.2 [MUT] Pines de la guarda, tras 5.1. En cada comando la salida esperada es: ninguna línea `MUTACION NO APLICADA`;
  `mutado exit=1` con la línea de hallazgo indicada; `restaurado exit=0`.
  M1 (CP › «Imagen sin registro en el workflow»), espera `.github/workflows/ci.yml:43:`:
  `t="$(mktemp -d)"; mkdir -p "$t/.github/workflows" "$t/software"; cp .github/workflows/ci.yml "$t/.github/workflows/"; cp software/compose.yaml "$t/software/"; cp -R software/docker "$t/software/"; f="$t/.github/workflows/ci.yml"; cp "$f" "$t.orig"; sed -i.bak 's#image: mirror.gcr.io/library/postgres:#image: postgres:#' "$f"; rm -f "$f.bak"; cmp -s "$f" "$t.orig" && echo "MUTACION NO APLICADA"; bash software/docker/check-ci-images.sh "$t"; echo "mutado exit=$?"; cp "$t.orig" "$f"; bash software/docker/check-ci-images.sh "$t"; echo "restaurado exit=$?"`
  M2 (CP › «Imagen base nueva en un Dockerfile sin redirigir»), espera `software/docker/web/Dockerfile:6:` y `NGINX2_IMAGE`:
  `t="$(mktemp -d)"; mkdir -p "$t/.github/workflows" "$t/software"; cp .github/workflows/ci.yml "$t/.github/workflows/"; cp software/compose.yaml "$t/software/"; cp -R software/docker "$t/software/"; f="$t/software/docker/web/Dockerfile"; cp "$f" "$t.orig"; sed -i.bak 's/^ARG NGINX_IMAGE=/ARG NGINX2_IMAGE=/' "$f"; rm -f "$f.bak"; cmp -s "$f" "$t.orig" && echo "MUTACION NO APLICADA"; bash software/docker/check-ci-images.sh "$t"; echo "mutado exit=$?"; cp "$t.orig" "$f"; bash software/docker/check-ci-images.sh "$t"; echo "restaurado exit=$?"`
  M3 (CP › «Servicio de compose descargado sin redirigir», imagen literal), espera `software/compose.yaml:`:
  `t="$(mktemp -d)"; mkdir -p "$t/.github/workflows" "$t/software"; cp .github/workflows/ci.yml "$t/.github/workflows/"; cp software/compose.yaml "$t/software/"; cp -R software/docker "$t/software/"; f="$t/software/compose.yaml"; cp "$f" "$t.orig"; sed -i.bak 's#\${DB_IMAGE:-postgres:16.15-alpine3.24}#postgres:16.15-alpine3.24#' "$f"; rm -f "$f.bak"; cmp -s "$f" "$t.orig" && echo "MUTACION NO APLICADA"; bash software/docker/check-ci-images.sh "$t"; echo "mutado exit=$?"; cp "$t.orig" "$f"; bash software/docker/check-ci-images.sh "$t"; echo "restaurado exit=$?"`
  M4 (CP › «Imagen base nueva en un Dockerfile sin redirigir», `# syntax=` sin `BUILDKIT_SYNTAX`, D1), espera `software/docker/web/Dockerfile:1:`:
  `t="$(mktemp -d)"; mkdir -p "$t/.github/workflows" "$t/software"; cp .github/workflows/ci.yml "$t/.github/workflows/"; cp software/compose.yaml "$t/software/"; cp -R software/docker "$t/software/"; f="$t/.github/workflows/ci.yml"; cp "$f" "$t.orig"; sed -i.bak '/BUILDKIT_SYNTAX=mirror\.gcr\.io\/docker\/dockerfile:1/d' "$f"; rm -f "$f.bak"; cmp -s "$f" "$t.orig" && echo "MUTACION NO APLICADA"; bash software/docker/check-ci-images.sh "$t"; echo "mutado exit=$?"; cp "$t.orig" "$f"; bash software/docker/check-ci-images.sh "$t"; echo "restaurado exit=$?"`
  M5 (CP › «Constructor de BuildKit sin redirigir», sin `driver-opts`, D2), espera `.github/workflows/ci.yml:` y `setup-buildx`:
  `t="$(mktemp -d)"; mkdir -p "$t/.github/workflows" "$t/software"; cp .github/workflows/ci.yml "$t/.github/workflows/"; cp software/compose.yaml "$t/software/"; cp -R software/docker "$t/software/"; f="$t/.github/workflows/ci.yml"; cp "$f" "$t.orig"; sed -i.bak '/driver-opts: image=mirror\.gcr\.io\/moby\/buildkit:/d' "$f"; rm -f "$f.bak"; cmp -s "$f" "$t.orig" && echo "MUTACION NO APLICADA"; bash software/docker/check-ci-images.sh "$t"; echo "mutado exit=$?"; cp "$t.orig" "$f"; bash software/docker/check-ci-images.sh "$t"; echo "restaurado exit=$?"`
  M6 (CP › «Constructor de BuildKit sin redirigir», etiqueta móvil, D2), espera `.github/workflows/ci.yml:` y `buildx-stable-1`:
  `t="$(mktemp -d)"; mkdir -p "$t/.github/workflows" "$t/software"; cp .github/workflows/ci.yml "$t/.github/workflows/"; cp software/compose.yaml "$t/software/"; cp -R software/docker "$t/software/"; f="$t/.github/workflows/ci.yml"; cp "$f" "$t.orig"; sed -i.bak 's#mirror.gcr.io/moby/buildkit:v0.33.1#mirror.gcr.io/moby/buildkit:buildx-stable-1#' "$f"; rm -f "$f.bak"; cmp -s "$f" "$t.orig" && echo "MUTACION NO APLICADA"; bash software/docker/check-ci-images.sh "$t"; echo "mutado exit=$?"; cp "$t.orig" "$f"; bash software/docker/check-ci-images.sh "$t"; echo "restaurado exit=$?"`
  M7 (CP › «Servicio de compose descargado sin redirigir», variable que el workflow no fija), espera `software/compose.yaml:` y `DB_IMAGE`:
  `t="$(mktemp -d)"; mkdir -p "$t/.github/workflows" "$t/software"; cp .github/workflows/ci.yml "$t/.github/workflows/"; cp software/compose.yaml "$t/software/"; cp -R software/docker "$t/software/"; f="$t/.github/workflows/ci.yml"; cp "$f" "$t.orig"; sed -i.bak '/^ *DB_IMAGE: mirror\.gcr\.io\/library\/postgres:/d' "$f"; rm -f "$f.bak"; cmp -s "$f" "$t.orig" && echo "MUTACION NO APLICADA"; bash software/docker/check-ci-images.sh "$t"; echo "mutado exit=$?"; cp "$t.orig" "$f"; bash software/docker/check-ci-images.sh "$t"; echo "restaurado exit=$?"`
  Verifica: filas M1–M7 en `verification.md` (mutación | mutado exit + línea | restaurado exit), commit de 5.1 citado.

## 6. Cierre (devops)

- [ ] 6.1 Antes del push, control de log: `gh run list --branch dev --status success --limit 1 --json databaseId -q '.[0].databaseId'`
  registrado en `journal.md` como run de control (último verde previo a S11, con trabajo build construido). Luego
  `git push origin dev`; `gh run watch <id>` hasta terminar. Cubre CP › «Servicio PostgreSQL del backend desde el espejo»,
  «Construcción de imágenes desde el espejo», «Base de datos del staging desde el espejo», «Árbol conforme». Verifica:
  backend, frontend, build y staging en `success`, producción `skipped`; pasos `api` y `web` del build ejecutados (no
  `REUTILIZA`); run id en `journal.md` y `verification.md`.
- [ ] 6.2 Barrido del log del run de 6.1: `gh run view <id> --log > /tmp/s11.log`;
  `/usr/bin/grep -cE 'registry-1\.docker\.io|docker-image://docker\.io/|load metadata for docker\.io/|docker pull (docker\.io/)?(library/)?[a-z0-9_-]+(/[a-z0-9_-]+)?:' /tmp/s11.log` = 0;
  `/usr/bin/grep -c 'docker-image://mirror.gcr.io/docker/dockerfile:1' /tmp/s11.log` ≥ 1 (D1);
  `/usr/bin/grep -c 'mirror.gcr.io/moby/buildkit:v0.33.1' /tmp/s11.log` ≥ 1 (D2);
  `/usr/bin/grep -c 'mirror.gcr.io/library/postgres:16.15-alpine3.24' /tmp/s11.log` ≥ 1. Control positivo del cero: el
  primer patrón sobre `gh run view <run de control de 6.1> --log` da ≥ 1. Cubre CP › «Servicio PostgreSQL del backend
  desde el espejo», «Construcción de imágenes desde el espejo». Verifica: fila en `verification.md` por conteo, con el control.
- [ ] 6.3 `verification.md` en tablas: § 0 líneas de producto / prueba / registro; escenario → evidencia, una fila por
  cada escenario del delta; tabla de digests de 4.2; M1–M7. Cubre CP › «Mismo contenido que Docker Hub» (tabla de
  digests) y «Árbol conforme» (fila de 5.1). Verifica: `/usr/bin/grep -c '^#### Scenario' specs/ci-pipeline/spec.md`
  igual al número de filas escenario → evidencia.

## Seguimiento del flujo

- final-auditor en modo delta sobre el diff; GATE 2.
- `openspec archive use-registry-mirror-in-ci -y`; `openspec validate --all --strict` limpio; ROADMAP S11 → archivado.
