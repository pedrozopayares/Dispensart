# Proposal — use-registry-mirror-in-ci (S11)

## Why

El 2026-10-09 seis runs de `dev` (37990501600, 37993339483, 37994062067, 37994932108, 37995082129,
37995613480) fallaron al descargar `postgres:16.15-alpine3.24` de Docker Hub: `toomanyrequests` (límite
anónimo por IP, compartido entre runners de GitHub) en el servicio del trabajo backend y `500 Internal Server
Error` de `registry-1.docker.io` en el `docker compose pull` de staging. El código no tenía defecto; la
re-ejecución pasó. Un CI que falla por terceros quita valor a la compuerta (parte D) y a la evidencia de las
reglas RN-01..RN-11 que esa compuerta ejecuta.

## What Changes

- Todo pull de Docker Hub que hace el CI pasa por el espejo público y gratuito `mirror.gcr.io` (Google), con el
  mismo repositorio y la misma etiqueta: servicio PostgreSQL del backend, imágenes base de la construcción
  (`php`, `composer`, `node`, `nginxinc/nginx-unprivileged`), imágenes implícitas de BuildKit (frontend
  `docker/dockerfile:1`, constructor `moby/buildkit`) y la `db` del staging. Sin login ni secretos.
- `software/compose.yaml`: la imagen de `db` pasa a `${DB_IMAGE:-postgres:16.15-alpine3.24}`; en local, sin
  `DB_IMAGE`, sigue Docker Hub. Los valores por defecto de los `ARG` de los Dockerfiles no cambian.
- Una guarda versionada, en un paso de una compuerta de calidad, hace fallar el CI si reaparece una referencia
  directa a Docker Hub en el workflow, o si un Dockerfile o compose declara una imagen de Docker Hub que el CI
  no redirige al espejo.
- Una línea en `software/docs/deployment.md`: el espejo y cómo volver a Docker Hub. `DB_IMAGE` documentada en
  `software/.env.example`.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `ci-pipeline`: dos requisitos ADDED — imágenes de Docker Hub desde el espejo en todo trabajo del workflow
  (calidad y entrega), y guarda contra referencias directas. Se agregan a `ci-pipeline` y no a
  `delivery-pipeline` porque la regla rige el archivo de workflow completo; ningún requisito vivo cambia.

## Impact

- Archivos: `.github/workflows/ci.yml`, `software/compose.yaml`, `software/.env.example`, un script de guarda
  bajo `software/docker/`, `software/docs/deployment.md`.
- Sin cambios en imágenes publicadas, versiones, API, base, SPA ni lógica de producción. Dependencia externa
  nueva en CI: disponibilidad de `mirror.gcr.io`.
- Partes: D (CI/CD), E (calidad). RN: ninguna regla cambia; la compuerta que las prueba deja de fallar por
  límites de terceros.

## Assumptions

1. `mirror.gcr.io` sirve los siete repositorios con las etiquetas actuales (verificado el 2026-10-09: HTTP 200
   para los cinco base, `docker/dockerfile:1` y `moby/buildkit:buildx-stable-1`; digest de postgres
   `sha256:721873c3…`). Igualdad de digest con Docker Hub se comprueba en apply.
2. Un fallo del espejo hace fallar el pull, sin caída silenciosa a Docker Hub; volver a Docker Hub es una
   edición documentada, no automática.
3. La guarda es un script bajo `software/docker/` (dispara el CI por `software/**`) invocado desde un paso del
   trabajo frontend; el texto del paso no contiene registros, así «Sin secretos ni publicación» sigue válido.
4. Mecanismo de redirección de BuildKit (argumento `BUILDKIT_SYNTAX`, configuración de espejo de buildkitd u
   opción de imagen del driver) a elección del implementador; se acepta por el log del build.
5. Sin `design.md`: no hay contrato, migración, permisos ni concurrencia; la guarda es un script sin estado.
