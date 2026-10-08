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
