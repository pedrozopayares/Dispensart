# Journal — add-lot-intake (S18)

Append-only. Dueño: Orchestrator. Modo `/nitro` + autopiloto: el Orchestrator asume los roles y no emite veredictos
de gate. shard = 6b6.

## 2026-10-14 — proposal (roles spec-engineer + architect)

- Origen: consulta «paso a paso de ingreso de lote como regente». Hallazgo: no existe alta de lote ni ingreso
  `entrada`; el único camino es el ajuste, sin pantalla.
- Producido: `proposal.md`, deltas `inventory` (8 escenarios), `audit-trail` (4), `inventory-screen` (5),
  `design.md` (D1–D6), `tasks.md` (`[MUT]` M1–M6). Rama `feat/add-lot-intake` desde `dev` (`0927eae`).
- `openspec validate add-lot-intake --strict` → válido. Ancla de transporte: 9 hits, 0 sin ancla
  (`/usr/bin/grep`, patrón de `CYCLE-TIERS.md`).
- Tier A: escritura de stock y kardex, migración de `CHECK`, superficie de permisos.
- **GATE 1: aprobado por instrucción del usuario (2026-10-14)**: *"Crea una rama para el feature y ejecuta en
  auto piloto con nitro"*, sobre el alcance propuesto en el mensaje anterior (alta de lote + ingreso `entrada`
  con pantalla). Condiciones de preaprobación: 1 alcance = fila S18 OK; 2 validación + ancla OK; 3 tier A = fila
  OK; 4 ningún ADR ni RN debilitado OK.

## 2026-10-14 — apply: línea base (corrida 1 de 3)

| Suite | Comando | Resultado |
|---|---|---|
| API | `api-tools vendor/bin/pest --parallel` | 1074 pasan, 1 falla (`EvaluationSetTest` «el comparador…», `relation "warehouses" does not exist`) |
| API delta | `api-tools vendor/bin/pest tests/Feature/Assistant/EvaluationSetTest.php` | 5 pasan, 1 falla: la prueba no migra su base; previa a S18 → D-6b6-1 |
| Web | `npx vitest run` | 33 archivos, 360 pasan |
