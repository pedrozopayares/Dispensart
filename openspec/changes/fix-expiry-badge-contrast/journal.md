# Journal — fix-expiry-badge-contrast (S14)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-09 — spec-engineer: proposal, delta y tareas borrador

| Artefacto | Comando (desde la carpeta del cambio) | Resultado |
|---|---|---|
| Requisitos ADDED en `specs/inventory-screen/spec.md` | `/usr/bin/grep -c '^### Requirement:' specs/inventory-screen/spec.md` | 1 |
| Escenarios en el delta | `/usr/bin/grep -c '^#### Scenario:' specs/inventory-screen/spec.md` | 5 |
| Tareas en `tasks.md` (MUT declarados: 0, tier C) | `/usr/bin/grep -c '^- \[ \]' tasks.md` | 7 |
| Supuestos en `proposal.md` | `/usr/bin/grep -c '^[0-9]\. ' proposal.md` | 4 |
| Validación | `openspec validate fix-expiry-badge-contrast --strict` | válido |
| Ancla de transporte | `/usr/bin/grep -rnEc '^- \*\*THEN\*\*.*(HTTP\|[^0-9](200\|201\|204\|400\|401\|403\|404\|409\|419\|422\|423\|500)[^0-9]\|5xx\|invalid_\|not_found\|conflict\|error code\|refus\|reject\|forbidden\|insufficient\|expired)' specs/` | `specs/inventory-screen/spec.md:0`; ninguna cláusula necesita ancla |
| Control positivo del ancla | mismo patrón sobre `../../specs/transfers/` | `spec.md:72` (el patrón detecta) |

- Alcance: igual a la fila S14 (insignia «Vence en N días» con contraste AA, con prueba). Nada barrido fuera.
- Delta: ADDED en `inventory-screen` (dueña de la insignia). `app-shell` › «Contraste AA de los pares de tokens»
  no se modifica: ya exige AA a todo par de tokens de tema, y el par nuevo cae bajo esa regla.
- Tier propuesto: **C**. Disparador: valores de tokens CSS y clases de una insignia; sin rama, cómputo ni
  persistencia en producto. La prueba suma pares al arnés de S12 sin tocar aislamiento, orden ni reporte.
- Hallazgo del código: la insignia está en `software/web/src/features/inventory/inventory-page.tsx`
  (`bg-amber-500 text-white`); el resaltado de fila usa `bg-amber-100` / `dark:bg-amber-950/40` y queda igual.
- Razones medidas (fórmula WCAG, script local):

| Par (texto / fondo) | Razón |
|---|---|
| blanco / `#f59e0b` (actual) | 2,15 |
| `#212b51` / `#f59e0b` | 6,39 |
| `#232955` / `#f59e0b` | 6,43 |
| blanco / `#b45309` | 5,02 |
| blanco / `#92400e` | 7,09 |

- Supuestos: en la propuesta (nombre del token, valores a elección, resaltado de fila fuera, sin selector de tema).
- Preguntas abiertas: ninguna. Bloqueos: ninguno.

## 2026-10-09 — Orchestrator: GATE 1

- shard = auv
- GATE 1: preaprobado (ROADMAP 2026-10-09, «Resuelve D-auv-8 también»), condiciones 1-4 OK, tier C.
  1. Alcance = fila S14 (badge «Vence en N días» con contraste AA y prueba). 2. spec-validator VALID tras quitar una
  cifra en prosa; ancla sin hits. 3. Tier C = columna. 4. Ningún ADR ni RN se debilita.
