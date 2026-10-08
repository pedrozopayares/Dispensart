# Proposal — add-delivery-pipeline (S8)

## Why

La parte D pide build de imágenes, staging simulado, aprobación manual para producción y un documento de
despliegue; la parte E y § 7 piden README y AI_USAGE.md. Hoy el CI de S0 solo valida calidad. DevOps pesa
12 % y documentación 8 % (§ 10).

## What Changes

- Entrega solo tras las compuertas de calidad del mismo commit, en push a `dev` o `main`; nunca en pull request.
- Imágenes `api` y `web` publicadas en GHCR con etiqueta = SHA completo. Solo ese trabajo tiene `packages: write`.
- Staging simulado: el runner levanta las imágenes publicadas por digest, sin reconstruir, y hace humo sobre
  `/health`, `/ready`, `/` y usuario no root. Fallo → producción no corre.
- Producción solo desde `main`, en el entorno `production` con revisor requerido, con los digests de staging.
  Entorno sin revisor → falla antes de desplegar.
- Mínimo privilegio por trabajo; solo `GITHUB_TOKEN`; contraseña de staging efímera y enmascarada.
- `software/docs/deployment.md` (≤ 1 página) con respaldo y restauración probados de ida y vuelta;
  `README.md` final y `AI_USAGE.md` con fuentes verificables.

## Capabilities

### New Capabilities
- `delivery-pipeline`: disparo, imágenes, staging con humo, producción con aprobación, privilegios y secretos.
- `project-documentation`: documento de despliegue, README y AI_USAGE.md verificables.

### Modified Capabilities
- `ci-pipeline`: «Permisos mínimos y sin secretos» se acota a los trabajos de calidad.

## Impact

Partes D y E, § 7, § 8. Sin RN-xx nuevas: las compuertas protegen todas antes de entregar. Archivos:
`.github/`, `software/compose.yaml` (imagen por variable), `software/docs/deployment.md`, `README.md`,
`AI_USAGE.md`. Sin código de aplicación ni migraciones. Tier B; superficie de seguridad → architect. Apply
requiere S7 archivado.

## Assumptions

1. Producción solo desde `main`; en `dev` queda omitida, no fallida.
2. Paquetes GHCR públicos (costo cero); si la API no lo permite, el usuario. Sin etiquetas móviles.
3. Staging y producción simulados: producción publica digests y comando en el resumen del run.
4. Revisor = dueño del repo (autoaprobación). Entorno por `gh api`; si no, el usuario en la UI.
5. Rollback = re-ejecutar producción del último run bueno. Esquema incompatible → restaurar respaldo.
6. Rol de base de mínimo privilegio (riesgo de S2): fuera de alcance, es tier A. Va al README y al ROADMAP.
7. Lectura de bitácora por el auditor (S3): README, fuera de alcance.
8. "1 página" = ≤ 550 palabras. AI_USAGE cita solo hechos de journals existentes, con ruta.
9. La corrida de la evaluación en CI es de S7; S8 documenta el comando.
