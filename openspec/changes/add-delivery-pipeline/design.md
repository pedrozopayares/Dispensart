# Design — add-delivery-pipeline (S8, tier B)

## Context

`ci.yml` (S0) solo corre calidad; Dockerfiles y `compose.yaml` de S0 ya producen imágenes no root con `/health` y
`/ready`. Motivación: ver proposal.md › Why. Criterio de complejidad: superficie de seguridad (escritura al
registro, protección de entorno). Sin endpoints nuevos ni cambios de datos: sin tabla de contrato ni migraciones.

## Goals / Non-Goals

- Goals: un solo grafo de trabajos por push; digests inmutables de construcción a producción; staging reproducible
  en el runner; guarda de revisor que falla cerrada.
- Non-Goals: servidor real, `workflow_dispatch`, firma/SBOM de imágenes, escaneo de vulnerabilidades, multi-arquitectura.

## Decisions

**D1. Un workflow: extender `ci.yml`** con `build → staging → production` y `needs: [backend, frontend]`.
`if: github.event_name == 'push' && (github.ref == 'refs/heads/dev' || github.ref == 'refs/heads/main')` en
construcción; producción además `github.ref == 'refs/heads/main'` (en `dev` queda `skipped`). El filtro de rutas
existente (`software/**`, `.github/workflows/ci.yml`) no cambia: es el único archivo bajo `.github/workflows/`.
Concurrencia: `cancel-in-progress: ${{ github.ref != 'refs/heads/main' }}` (en `main` nunca se corta una entrega).
- Rechazado `delivery.yml` con `workflow_run`: corre la versión del workflow de la rama por defecto (`main`, solo
  usuario), así que un cambio en `dev` no se prueba hasta fusionar; además ejecuta en contexto privilegiado.
- Rechazado `delivery.yml` que llama `ci.yml` como `workflow_call`: obliga a excluir `dev`/`main` del push de CI
  para no correr dos veces las compuertas; dos archivos sin ganancia. Revisar si el archivo pasa de ~250 líneas.

**D2. Digests como salidas de trabajo.** Un trabajo `build` con dos pasos `docker/build-push-action` (`id: api`,
`id: web`); salidas `api_digest`, `web_digest` y `owner_lc` (dueño en minúsculas, GHCR lo exige). Staging y
producción leen `needs.build.outputs.*` y arman `ghcr.io/<owner_lc>/dispensart-<x>@<digest>`.
- Rechazada matriz por imagen: las salidas de una matriz se sobrescriben entre celdas. Rechazado artefacto con
  los digests: más pasos, mismo resultado.

**D3. Etiqueta única = `github.sha`**; labels OCI `image.source` (vincula el paquete al repo) e `image.revision`.
- Rechazadas etiquetas de rama (`dev`, `main`) y `latest`: móviles, prohibidas por el spec. La rama se lee del run.

**D4. Caché `type=gha`**, `mode=max`, `scope` por imagen. Gratuita en repo público (límite 10 GB, evicción LRU).
- Rechazado `type=registry`: escribe artefactos extra en GHCR y ensucia el listado de etiquetas.

**D5. Staging en el runner con el mismo `compose.yaml`.** Imagen de `api`/`web` por variable (`API_IMAGE`,
`WEB_IMAGE`, por defecto las locales de S0). Pasos: contraseña `openssl rand -hex 24` → `::add-mask::` → `GITHUB_ENV`;
paso de control que la imprime; `docker compose pull db api web` (digest ausente → fallo, escenario «Imagen
ausente»); `up --no-build --wait --wait-timeout 180`; `bash software/docker/smoke.sh`; `if: failure()` →
`docker compose logs`; `if: always()` → `down -v`.
- Rechazado override `compose.staging.yaml` con `build: !reset`: un archivo más que derivaría del base.
- Script de humo en `software/docker/smoke.sh` (no `.github/scripts/`): vive junto al stack que prueba, corre
  igual en local y cualquier cambio suyo dispara el workflow por `software/**`.

**D6. Producción.** `environment: production`, `needs: [build, staging]`, permisos `actions: read` (lo exige
`GET /repos/{repo}/environments/{name}` para el token de instalación). Primer paso: `gh api` con `GITHUB_TOKEN`
cuenta `protection_rules[] | select(.type=="required_reviewers")`; 0, 404 o 403 → `exit 1` (falla cerrada). Un
entorno inexistente lo crea GitHub sin protección: la guarda lo atrapa. Despliegue simulado: escribe digests y
el comando `docker compose pull/up` que ejecutaría en `GITHUB_STEP_SUMMARY`. El entorno restringe además ramas
desplegables a `main` (política de rama, defensa en profundidad del `if:`).
- Rechazado trabajo de guarda previo sin entorno: el spec exige el fallo en el primer paso de producción.
- Rechazado secreto/PAT para leer el entorno: viola «solo `GITHUB_TOKEN`». Si el token recibe 403 en la
  evidencia, la guarda queda cerrada (nunca despliega sin verificar) y se escala al usuario.

**D7. Permisos.** Workflow `permissions: {}`; cada trabajo declara: backend/frontend `contents: read`; build
`contents: read, packages: write`; staging `contents: read, packages: read` (paquetes privados hasta 4.2);
production `actions: read`. Login a GHCR con `docker/login-action` y `GITHUB_TOKEN`.

**D8. Rollback = re-ejecutar el trabajo de producción del último run bueno.** GitHub reutiliza las salidas de
`build` de ese run y la definición del workflow de su commit: vuelve al digest exacto, con nueva aprobación.
- Rechazado `workflow_dispatch` con digest de entrada: otra puerta a producción que validar; YAGNI. Revisar si
  hace falta volver a un run de más de 30 días (límite de re-ejecución de GitHub).

## Risks / Trade-offs

- [Aprobación pendiente en `main` retiene el grupo de concurrencia y encola el siguiente run] → aprobar o
  rechazar; documentado en `deployment.md`.
- [`GITHUB_TOKEN` sin acceso a la API de entornos] → falla cerrada; 4.4(d) lo prueba en run real antes de `main`.
- [Paquetes nacen privados; el evaluador no puede hacer pull] → 4.2 los hace públicos; staging autentica igual.

## Migration Plan

1.1 → workflow (2.x) → entorno (3.1) → push a `dev` (4.1) → visibilidad (4.2) → negativos (4.3–4.4) → fusión
a `main` por el usuario (4.5). Revertir S8 = revertir el commit del workflow; las imágenes publicadas quedan
inertes.
