# Design — use-registry-mirror-in-ci (S11)

## Context

Seis runs de `dev` fallaron por límite anónimo / 500 de Docker Hub. El CI descarga de Docker Hub cuatro bases,
`postgres` (servicio y staging) y, de forma implícita, el frontend `docker/dockerfile:1` y `moby/buildkit`.
Se redirige todo a `mirror.gcr.io` en CI, sin tocar el stack local; una guarda impide la regresión.

## Goals / Non-Goals

- Goals: cero pulls de Docker Hub en CI, sin login; local idéntico; regresión detectada por una compuerta de calidad.
- Non-Goals: espejo en local; caída automática a Docker Hub; firma o fijación por digest de las bases; cambiar
  versiones de imagen.

## Decisions

**D1 — Frontend de BuildKit: build-arg `BUILDKIT_SYNTAX=mirror.gcr.io/docker/dockerfile:1`** en cada paso
`docker/build-push-action`. Es un argumento integrado documentado («Dockerfile reference › BuildKit built-in build
args»: *Set frontend image*); el frontend `dockerfile.v0` de BuildKit lo evalúa antes que la directiva
`# syntax=` detectada en el archivo, y la directiva queda intacta para el build local.
Rechazadas: reescribir `# syntax=mirror.gcr.io/…` (cambia el build local, viola «Stack local sin DB_IMAGE»);
quitar la línea `# syntax` (el frontend pasa a depender de la versión de BuildKit, cambio semántico en local);
espejo en `buildkitd.toml` (`[registry."docker.io"] mirrors`: cae en silencio a Docker Hub, contradice «Espejo
sin la etiqueta pedida», invisible para la guarda). Prueba: el log del build contiene
`resolve image config for docker-image://mirror.gcr.io/docker/dockerfile:1` y ninguna línea
`docker-image://docker.io/docker/dockerfile`. Revisar si: el log muestra `docker.io` pese al argumento → pasar
a reescribir `# syntax` solo en CI (sed en el runner) y actualizar M4.

**D2 — Constructor: `driver-opts: image=mirror.gcr.io/moby/buildkit:v0.33.1`** en `docker/setup-buildx-action`.
`v0.33.1` es la versión a la que apunta hoy `buildx-stable-1` (mismo digest `sha256:cec9f139…` en el espejo,
2026-10-09): mismo binario que hoy, ahora fijado. Rechazada: `buildx-stable-1` vía espejo (etiqueta móvil, el
digest cambia sin commit). Prueba: el paso de setup registra `pulling image mirror.gcr.io/moby/buildkit:v0.33.1`.
Revisar si: un fix de seguridad de BuildKit → subir la etiqueta en un commit.

**D3 — Guarda `software/docker/check-ci-images.sh [raíz]`** (bash + awk + grep ERE POSIX, sin `yq`: no está en
las máquinas de desarrollo y sería dependencia nueva). Raíz por defecto: la del repo (`git rev-parse
--show-toplevel`). Lee `<raíz>/.github/workflows/ci.yml`, `<raíz>/software/compose.yaml` y todo `Dockerfile*` bajo
`<raíz>/software/docker/`. Referencia «de Docker Hub» = sin host de registro (primer segmento sin `.`, `:` ni
`localhost`) o con `docker.io/`. Forma de espejo canónica: `mirror.gcr.io/library/<r>` si `<r>` no tiene `/`;
si no, `mirror.gcr.io/<r>`; misma etiqueta exacta. Hallazgos:
(a) `ci.yml`: `image:` (servicios, `container`) de Docker Hub; cualquier `docker.io/`; `uses: docker://` de Docker
Hub; paso `docker/setup-buildx-action` sin `image=mirror.gcr.io/moby/buildkit:vX.Y.Z` en `driver-opts`.
(b) Dockerfile: `ARG *_IMAGE=<hub>`, `# syntax=<hub>` y `FROM <hub literal>` (no alias de etapa ni `${…}`) sin
la línea `<ARG>=<forma de espejo>` (o `BUILDKIT_SYNTAX=…`) dentro del mismo paso de `ci.yml` que declara
`file: software/docker/<x>/Dockerfile` (paso = bloque entre marcas `- ` de nivel de paso). Dockerfile sin paso que
lo construya: cada referencia de Docker Hub es hallazgo (falla cerrada).
(c) `compose.yaml`: servicio sin `build:` con imagen literal de Docker Hub, o `${VAR:-<hub>}` sin `VAR:
<forma de espejo>` en algún `env` de `ci.yml`. Servicios con `build:` y `ghcr.io/…` no se reportan.
Salida: cada hallazgo `archivo:línea: motivo` en stderr; exit 1 si hay alguno; exit 0 con
`OK: <n> referencias revisadas` (n > 0) si no; exit 2 si falta un archivo de entrada o `n = 0` (falla cerrada).
CI: paso «Guarda de imágenes en el espejo» del trabajo frontend, `working-directory: .`, `run: bash
software/docker/check-ci-images.sh`. Revisar si: el workflow se parte en varios archivos → leer `workflows/*.yml`.

**D4 — Local intacto y vuelta atrás.** `compose.yaml`: `${DB_IMAGE:-postgres:16.15-alpine3.24}`; el trabajo staging
fija `DB_IMAGE: mirror.gcr.io/library/postgres:16.15-alpine3.24` en su `env`. Los `ARG` de Dockerfile no cambian.
Espejo sin la etiqueta: el pull falla y el run queda rojo (sin respaldo automático). Procedimiento
(`deployment.md`): 1) antes de subir una versión, `docker buildx imagetools inspect mirror.gcr.io/<forma>:<tag>`;
2) si falta, elegir una etiqueta que el espejo sí sirva; 3) emergencia: un commit que quita el prefijo
`mirror.gcr.io/library/` / `mirror.gcr.io/` de `ci.yml` y retira el paso de la guarda, con fila en ROADMAP para
restaurarlo. Rechazada: interruptor `ALLOW_DOCKERHUB` en la guarda (puerta de escape que se queda abierta).

## API contract

Ninguno.

## Data impact

Ninguno (sin migraciones). Caché `type=gha` del build se invalida una vez por cambio de referencia `FROM`.

## Risks

1. `mirror.gcr.io` sin SLA ni etiqueta en caché → run rojo. Mitigación: D4 paso 1–3; tarea 4.3 prueba el fallo cerrado.
2. Precedencia `BUILDKIT_SYNTAX` no observada en este repo → frontend aún desde Docker Hub. Mitigación: barrido del
   log en 6.2 con control positivo; revisión de D1 si falla.
3. Guarda por texto (awk) frágil ante formas YAML no previstas (imagen en la línea siguiente, anclas). Mitigación:
   falla cerrada ante entrada ilegible (archivo ausente o `n = 0` → 2); pines M1–M6 sobre copia del árbol commiteado.
