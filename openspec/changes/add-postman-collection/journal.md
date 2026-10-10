# Journal — add-postman-collection (S16)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-10 — spec-engineer: proposal, deltas y tareas borrador

- Producido: `proposal.md`; deltas `specs/api-collection/spec.md` (capacidad nueva, con `## Purpose`),
  `specs/delivery-pipeline/spec.md`, `specs/ci-pipeline/spec.md`, `specs/project-documentation/spec.md` (solo
  ADDED); `tasks.md` con arnés autocontenido y `[MUT]` M1–M5 más autocontrol M0. `openspec validate
  add-postman-collection --strict` → válido. Barrido de ancla de transporte con `/usr/bin/grep` desde la carpeta del
  cambio: todo hit lleva `[ancla: …]` (control positivo: el patrón encuentra hits en `api-collection`).
- Fuentes: live specs `identity-access`, `dispensation`, `transfers`, `inventory`, `inventory-alerts`, `kardex`,
  `inventory-assistant`, `catalog`, `patients`, `service-health`, `ci-pipeline`, `delivery-pipeline`,
  `project-documentation`; humos de `software/docker/smoke/`; `openapi.json`; `ci.yml`; semillas. El enunciado de la
  prueba no se leyó (alcance dado por la fila S16 y el encargo).
- Tier propuesto: **B**, igual a la fila. Disparadores A revisados y descartados: sin migración ni escritura nueva de
  stock o kardex (la colección consume la API existente), sin cambio de autenticación ni permisos (solo los
  ejerce), sin tocar el asistente. Punto límite: «aislamiento, orden o reporte del arnés». El paso de newman se
  agrega después de los humos en staging, sin cambiar su orden, su reporte ni el de Pest o Vitest; un falso rojo de
  la colección detiene staging pero no fabrica un verde falso de otra suite. Si el Orchestrator lo lee como arnés,
  el tier sube a A y la preaprobación de S16 no aplica.
- Criterio de complejidad «dependencia nueva»: newman es herramienta de CI y local vía `npx` con versión exacta, no
  dependencia de la app. Sin costura nueva de producto; `design.md` no parece necesario en Tier B. Decide el
  Orchestrator.
- Lectura del alcance: la guarda de cobertura (pedida «si es barata»; lo es: bash + jq) exige toda operación del
  contrato, por eso entra la carpeta `07 Catálogo y administración` (usuarios, bodegas, productos, lotes). Es la
  única pieza que no nombra la fila literalmente; queda en Supuestos 1. Si se juzga fuera de la fila, se quita la
  carpeta y la guarda pasa a supuesto, sin tocar lo demás.
- Supuestos (en la propuesta): carpeta de catálogo por la guarda; repetibilidad igual a la de los humos (stock
  semilla finito, `down -v` lo repone); un tarro de cookies por corrida → logout/login por carpeta; carrera por la
  última unidad (RN-03) fuera de la colección, cubierta por Pest; aserciones de alertas estables (forma y
  `L-ACE-2401`); `/sanctum/csrf-cookie` en lista explícita de la guarda; tier B.
- Decisiones de redacción: login sin `Origin` alineado al humo (`auth-smoke.sh:69`: sin `Origin`, `Referer` ni
  token → 403 `forbidden`); contraseña equivocada → 422 `invalid_credentials` (live spec), no `validation_failed`;
  el 419 del folder denegado usa `medico` + `POST /api/warehouses`, que con token daría 403: así M4 distingue.
  FEFO: en `BH` la semilla de `MED-004` tiene un solo lote vigente (`L-LOS-2403`), así que el orden ascendente es
  trivial en la colección; el pin M5 fija la exclusión del vencido, y el orden entre varios lotes sigue en Pest.
- Preguntas abiertas (no bloqueantes): Node 26 del anfitrión con newman 6.2.3 sin probar (tarea indica caer a
  `software/web/.nvmrc`); el runner de staging puede necesitar `setup-node` fijado por SHA.
- Bloqueos: ninguno. No se tocó código ni la carpeta de `fix-test-env-isolation`. Sin commit.

## 2026-10-10 — Orchestrator: alcance

- shard = auv
- La guarda de cobertura exige una carpeta de catálogo y usuarios. Está dentro de lo que el usuario aprobó («un control de
  que la colección cubre las operaciones del OpenAPI»); la fila S16 se precisa con esa carpeta y la guarda.

## 2026-10-10 — spec-engineer: correcciones del spec-validator

- Dictamen del Orchestrator aplicado: carpeta de catálogo y administración y guarda de cobertura dentro del alcance
  (fila S16 enmendada); la alternativa de quitarlas queda descartada.
- `api-collection` › «Salud y disponibilidad»: escenario negativo «Base de datos detenida» (503 de `/ready`, newman
  sale distinto de 0), con ancla en `service-health` › «Base de datos inalcanzable»; verificado en la tarea 2.1.
- `tasks.md`: autocontrol M0 dentro de 2.10; 2.10 ordena escribir los filtros `$M/M0.jq`…`M5.jq` antes de correr
  los `mut`; 5.2 cita escenarios nombrados con su verificación; sección final en español.
- `proposal.md` recortada a menos de una página, encabezado de supuestos en español; sin conteos en prosa en este
  journal.

## 2026-10-10 — Orchestrator: tier y GATE 1 (pendiente de VALID)

- Tier B (disparador: un job de CI y una colección con aserciones; sin superficie de autorización nueva ni cambio de API).
- GATE 1: preaprobado (ROADMAP 2026-10-10, «Aplica S16, junto con D-auv-9») si el spec-validator da VALID.
- Encabezados en inglés de la plantilla de `openspec instructions` en `proposal.md`: estructurales, se conservan (ley de idioma).
- GATE 1 registrado: condiciones 1-4 OK (alcance = fila S16 precisada en `676c2dc`; spec-validator VALID en la segunda
  pasada; tier B = columna; ningún ADR ni RN se debilita).
