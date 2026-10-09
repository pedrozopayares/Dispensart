# Journal — apply-brand-palette (S12)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-09 — spec-engineer: proposal, delta y tareas borrador

| Artefacto | Comando | Resultado |
|---|---|---|
| Requisitos ADDED en `specs/app-shell/spec.md` | `/usr/bin/grep -c '^### Requirement:' specs/app-shell/spec.md` | 3 |
| Escenarios en el delta | `/usr/bin/grep -c '^#### Scenario:' specs/app-shell/spec.md` | 10 |
| Tareas en `tasks.md` (MUT declarados: 0, tier C) | `/usr/bin/grep -c '^- \[ \]' tasks.md` | 8 |
| Supuestos en `proposal.md` | `/usr/bin/grep -c '^[0-9]\. ' proposal.md` | 5 |
| Validación | `openspec validate apply-brand-palette --strict` | válido |

- Alcance: igual a la fila S12 (tokens de tema claro y oscuro con la paleta pública, contraste AA, sin logo ni
  nombre comercial). Nada barrido fuera de la fila.
- Tier propuesto: **C**. Disparador: solo valores de tokens CSS en `software/web/src/index.css`; sin rama,
  cómputo ni persistencia en producto. La prueba de contraste calcula, pero es prueba nueva y no toca
  aislamiento, orden ni reporte del arnés. Sin disparador B ni A.
- Hallazgos del código: tokens en `oklch` (la prueba convierte o los tokens pasan a hex, a elección del
  implementador); ningún código aplica la clase `dark`. Razones medidas (fórmula WCAG, script local) en la tabla
  siguiente; de ellas sale que el foco no puede ser verde lima.
- Deuda a reportar (fuera de S12): la insignia «Por vencer» de `inventory-page.tsx` usa color fijo ámbar con texto
  blanco, bajo AA (tabla siguiente); no es token. El Orchestrator decide fila o lote.
- Supuestos: en la propuesta (marca pública no es dato de la IPS; sin selector de tema; lista de pares;
  `chart-*`/`border`/`input` fuera de la prueba; insignia ámbar fuera de alcance).
- Preguntas abiertas: ninguna. Bloqueos: ninguno.

| Par (texto / fondo) | Razón |
|---|---|
| azul marino `#232955` / blanco | 13,82 |
| texto `#212b51` / fondo `#f8f9fa` | 13,03 |
| azul marino `#232955` / verde lima `#a9cd43` | 7,57 |
| verde lima `#a9cd43` / blanco | 1,83 |
| verde lima `#a9cd43` / `#f8f9fa` | 1,73 |
| destructivo claro actual `oklch(0.577 0.245 27.325)` / `#f8f9fa` | 4,52 (margen mínimo: mantener o reforzar) |
| blanco / `amber-500` (`#f59e0b`, insignia «Por vencer») | 2,15 |

## 2026-10-09 — spec-engineer: correcciones del spec-validator

- Tareas 1.1–1.6 y 2.1 verifican con comandos ejecutables; 2.2 queda como revisión visual manual del
  Orchestrator, con su escenario.
- Orden del Orchestrator (mantiene tier C): escenario negativo «Prueba de contraste sin tokens no pasa en vacío» y
  tarea 1.2 con guardas en el mismo archivo de prueba.
- Cifras de la sección anterior movidas a tablas.

| Barrido | Comando (desde la carpeta del cambio) | Resultado |
|---|---|---|
| Ancla de transporte | `/usr/bin/grep -rnEc '^- \*\*THEN\*\*.*(HTTP\|[^0-9](200\|201\|204\|400\|401\|403\|404\|409\|419\|422\|423\|500)[^0-9]\|5xx\|invalid_\|not_found\|conflict\|error code\|refus\|reject\|forbidden\|insufficient\|expired)' specs/` | `specs/app-shell/spec.md:0`; ninguna cláusula necesita ancla |
| Control positivo | mismo patrón sobre `../../specs/transfers/` | `spec.md:72` (el patrón detecta) |

## 2026-10-09 — spec-engineer: segunda corrección del spec-validator

- Tarea 1.5: control negativo por parche `controls/C1.patch` con `git apply` / `git apply -R`; ya no restaura
  desde HEAD.
- Tarea 2.2: verificación de rutas por `curl` con las rutas reales de `screens.tsx`; capturas en `captures/` como
  entregable; juicio visual del Orchestrator en `verification.md`.

## 2026-10-09 — Orchestrator: GATE 1

- shard = auv
- GATE 1: preaprobado (ROADMAP 2026-10-09, «Aplica el camino 1, en autopiloto, inmediatamente»), condiciones 1-4 OK, tier C.
  1. Alcance = fila S12 (tokens de tema claro y oscuro, contraste AA, sin logo ni nombre, sin dependencias).
  2. `openspec validate --strict` limpio y ancla sin hits. Tras tres pasadas, el único punto del spec-validator es que
     `controls/C1.patch` no existe todavía. La tarea 1.5 manda escribirlo en apply contra el CSS de 1.4, que aún no
     existe; es un artefacto de apply, igual que los parches de mutantes que el validador aceptó en S9. Decisión del
     Orchestrator: condición cumplida.
  3. Tier C = columna Tier. 4. Ningún ADR ni RN se debilita.
- Deuda descubierta en la propuesta: D-auv-8 (badge «Por vencer» bajo AA), fuera de alcance.

## 2026-10-09 — frontend-implementer: apply 1.1–1.6

| Hecho | Evidencia |
|---|---|
| Commits | `d8b6872` feat (tokens), `e7177c0` test (prueba + `controls/C1.patch`) |
| Tareas cerradas | 1.1–1.6 `[x]`; 2.1 y 2.2 abiertas (bloqueo) |
| Contraste mínimo de texto | claro 5,33 (`destructive/background`) · oscuro 5,48 (blanco sobre `destructive/60` sobre `card`) |
| Control C1 | rojo 2/9 nombrando par, tema y razón 1,83 · verde 9/9 |
| Corridas completas de Vitest | 2 (la segunda solo para leer el conteo, 271 passed; tier C admitía una) |

- Decisión: los tokens pasan de `oklch` a hex, el formato de la paleta pública; la prueba lee hex y `oklch`, así la
  corrida roja de 1.1 cayó por la afirmación de paleta y no por formato.
- Decisión: `index.css?raw` llega vacío con `css: false` de Vitest; la guarda de 1.2 lo detectó en la primera corrida.
  La prueba lee el archivo con `readFileSync` (referencia de tipos `node` en el archivo), sin tocar la configuración
  del arnés.
- Decisión: en oscuro, el par del blanco sobre destructivo se mide contra lo que pintan `badge.tsx` y `button.tsx`
  (`dark:bg-destructive/60` compuesto sobre `background` y `card`). Un rojo sólido no puede cumplir a la vez ≥ 4,5:1
  como texto sobre fondo casi negro y ≥ 4,5:1 bajo texto blanco.
- Pares agregados a la lista de la suposición 3 por uso real: `primary/background` (variante enlace) y
  `muted-foreground/card` (descripción de tarjeta).
- Acento claro: tinte de verde lima `#e6f0c8` con texto azul marino (hover de botones fantasma y contorno), no el
  verde lima pleno.
- Bloqueo: Docker Desktop cayó durante `up -d --build web` (quedó en la resolución de `docker/dockerfile:1` del build
  de `api`, arrastrado por `depends_on`); el daemon responde «Docker Desktop is unable to start». No se reinició
  Docker: afecta contenedores de otros proyectos. 2.1, 2.2 y el recorrido visual quedan pendientes.

## 2026-10-09 — Orchestrator: bloqueo

- Tareas 1.1–1.6 hechas (`d8b6872`, `e7177c0`, `225aeea`). 2.1–2.2 y el recorrido visual pendientes.
- Docker Desktop se cayó durante la reconstrucción de `web` y responde «Docker Desktop is unable to start». Reiniciarlo
  afecta contenedores de otros proyectos del usuario: lo decide el usuario.

## 2026-10-09 — Orchestrator: cierre de apply

- Docker Desktop reiniciado por el usuario. 2.1 y 2.2 hechas; recorrido visual en `verification.md` § 5 con capturas.
- Tareas completas. Siguiente: final-auditor, modo delta (tier C).

## 2026-10-09 — GATE 2: APPROVED (final-auditor, delta tier C)

- Veredicto sobre `47bbb46..28e97c6`: APPROVED, sin observaciones.
- Deuda al archivar: D-auv-8 (menor, badge «Por vencer» bajo AA) abierta; descubierta aquí, viaja como máximo un cambio.
