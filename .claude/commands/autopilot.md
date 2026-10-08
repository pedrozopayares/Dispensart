---
name: "Autopilot"
description: "Ejecuta el roadmap aprobado de principio a fin: proposal → apply → archive por tajada, con GATE 1 preaprobado y GATE 2 del final-auditor"
category: "Workflow"
tags: ["workflow", "autopilot", "roadmap"]
---

Eres el Orchestrator (`CLAUDE.md`). Encadena las tajadas de `openspec/ROADMAP.md` sin detenerte entre
ellas, salvo por un bloqueo real. Reply to this command only with `AUTOPILOT: <primera tajada a ejecutar>`
and then start. Everything below is the law of the run.

## 0. Arranque (una sola vez por sesión)

1. Lecturas de sesión de `CLAUDE.md` (incluye `docs/adr/*`, `openspec/RETROSPECTIVES.md`, `openspec list`,
   `openspec/DEBT.md`, `openspec/ROADMAP.md`, `openspec/CYCLE-TIERS.md`, `openspec/ID-CONVENTION.md`).
2. Preflight de máquina, todo debe pasar o DETENTE y reporta:
   - `git status --porcelain` vacío; rama `dev` al día: `git checkout dev && git pull --ff-only`.
   - `openspec validate --all --strict` limpio.
   - `docker info` responde. Puertos `8090` y `5434` libres (`lsof -nP -iTCP:<p> -sTCP:LISTEN`), o los
     valores que `.env` defina.
   - Node ≥ 20.19; PHP ≥ 8.3 y Composer presentes (solo para scaffolding en S0).
3. Reclama el shard del ledger (`ID-CONVENTION.md`) y anótalo en el primer `journal.md` que escribas.
4. Punto de reanudación: la primera fila de `ROADMAP.md` cuyo Estado no sea `archivado`. Si una fila está
   `en curso` o `propuesto`, retoma esa tajada desde la fase que indique su `journal.md`; no la rehagas.

## 1. Por cada tajada, en orden

### /proposal
1. `openspec new change <change-id>` con el id de la fila. spec-engineer → proposal, deltas, tasks.
2. Criterios de complejidad (`CLAUDE.md` § Cast) → architect → `design.md` + tasks refinadas.
3. spec-validator hasta `VALID`. Ancla de transporte (`CYCLE-TIERS.md`): cero hits sin anchor.
4. **GATE 1 preaprobado** (`ROADMAP.md` § Preaprobación) si y solo si se cumplen sus cuatro condiciones.
   Verifícalas una a una y escribe el resultado en `journal.md`: `GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier <X>`.
   Una condición falla → Estado `bloqueado: GATE 1 <condición>` y DETENTE.
5. Estado de la fila → `propuesto`. Commit en `dev`: `spec: propone <objetivo de la prueba en palabras del jurado>` (Git law, nunca el change id solo).

### /apply
1. `openspec validate <id> --strict` pasa.
2. Preflight de suite: stack compose sano (`docker compose -f software/compose.yaml up -d --build --wait`
   desde S1 en adelante); suites backend y frontend en verde como línea base.
3. Delegación por bloques: backend-implementer → frontend-implementer (api ∥ web solo sin contrato nuevo
   entre ellos) → devops-implementer. Cada implementer cierra con su corrida verde y commits en `dev`.
4. Presupuesto: máximo 3 corridas de suite completa por tajada. La cuarta es DETENTE.
5. Verificación de cierre del Orchestrator: tasks `[x]`, `verification.md` en tablas, § 0 con el reparto
   producto/prueba/registro, pantallas alcanzables desde navegación.
6. final-auditor. OBSERVATIONS → rutea por responsable, exige evidencia de barridos bajo `/usr/bin/grep`,
   re-audita en modo delta. APPROVED → `journal.md`: `GATE 2: APPROVED`. Estado → `aprobado`.
   Hallazgos solo de prosa → spec-validator, nunca segunda auditoría (Iron rule 12).

### /archive
1. `openspec archive <id> -y`. Capability nueva → spec-engineer escribe el `## Purpose` en la spec viva.
2. spec-validator post-archivo: `openspec validate --all --strict` limpio.
3. Reconcilia `DEBT.md`: ninguna deuda blocker/major abierta; minor con una tajada de arrastre máximo.
4. Estado → `archivado`. Commit en `dev`: `chore: cierra <objetivo de la prueba en palabras del jurado>` (cuerpo cita el change id). `git push`.
5. Reporte al usuario ≤ 200 palabras: qué se construyó, tests (números de la tabla), deuda abierta, tiempo.
6. Siguiente fila.

## 2. Detenerse (y solo entonces)

Marca la fila `bloqueado: <motivo>`, escribe el `journal.md`, commit, push, y reporta en ≤ 150 palabras:
- una condición de preaprobación de GATE 1 no se cumple;
- cuarta corrida de suite completa;
- deuda blocker/major que impide archivar;
- algo facturable o un secreto necesario (Iron rules 5 y 9);
- preflight de máquina roto (Docker caído, puerto ocupado, `dev` desactualizado);
- S8 necesita crear el Environment `production` con revisores en GitHub: hazlo con `gh api` si el token
  alcanza; si no, DETENTE y pide al usuario que lo cree.

No te detengas por redacción, por cifras de un documento ni por mejoras contiguas: eso es deuda o ruido
(Iron rule 12).

## 3. Lo que /autopilot NO concede

Lo mismo que `/nitro`: ningún gate se levanta, ningún ADR se reabre, ningún alcance se ensancha, `main` no se
toca. La velocidad viene de no volver a derivar lo decidido.
