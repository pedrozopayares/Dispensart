# Journal — add-transfers (S4)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv
- Fase: /proposal adelantada. Apply espera el archivo de S3.
- Tier objetivo según ROADMAP: A.

## 2026-10-07 — spec-engineer: proposal, delta y tareas borrador

- Producido: `proposal.md`, `specs/transfers/spec.md` (capacidad nueva, 14 requisitos, 83 escenarios),
  `tasks.md` (30 tareas, `[MUT]` M1–M17). `openspec validate add-transfers --strict` → válido.
- Ancla de transporte: barrido `/usr/bin/grep` sobre `specs/` → 71 hits, 0 sin `[ancla: …]`.
- Pines obligatorios presentes: cada clase de transición prohibida (matriz de 28 combinaciones + escenarios
  por clase; M1–M5 una mutación por acción), solicitante ≠ aprobador en API (M6) y en base (M7).
- Decisiones de alcance (Assumptions de la propuesta, 11): lote explícito por línea sin FEFO; crear no
  verifica stock; borrador no editable; despacho con `transfers.create` (sin capacidad nueva, el mapa de S1
  no cambia); solicitante = creador; anula creador o `transfers.approve` en `BORRADOR`/`SOLICITADO`/`APROBADO`;
  sin `Idempotency-Key` (la máquina de estados devuelve 409 al reintento); recepción admite lote vencido en
  tránsito; `RECIBIDO_PARCIAL` terminal; resolución mínima `returned_to_origin` (ajuste en origen; lote
  vencido → 422) o `written_off` (sin movimiento).
- Sin `MODIFIED`: S1–S3 no son specs vivas aún. La extensión de la bitácora de S3 (3 acciones nuevas) y la
  referencia del traslado en el kardex (vía `reason`) se escriben como requisitos `ADDED` de `transfers`.
- Preguntas abiertas para el architect: forma de la referencia kardex → traslado (`reason` basta para la spec;
  una FK es decisión de diseño); generalización del worker de carrera de S2 a rutas arbitrarias (si S3 no lo
  hizo); orden de bloqueo traslado → discrepancia → existencias frente a la dispensación.
- Bloqueos: ninguno.

## 2026-10-07 — architect: design.md y refinamiento de tasks

Producido: `design.md` (D1–D15, contrato de 9 endpoints, 4 migraciones reversibles, 3 riesgos). `tasks.md`: tarea 0.1
de preflight nueva, 1.5 nueva (extensión de `audit_events`), tareas citan D<n>, orden 0 → (1 ∥ 2) → (3 ∥ 4) → 5 → 6,
sin bloque `web`. Specs y escenarios sin cambios. `openspec validate add-transfers --strict`: válido.

Respuestas a las preguntas abiertas:
1. Referencia kardex → traslado (D11): `reason` `Traslado #<id>` y enlace del lado del traslado, como S3 D2:
   `dispatch_movement_id`, `receipt_movement_id`, `adjustment_movement_id` nulos, únicos, FK al kardex. S2 intacto.
2. Worker de carrera (D13): payload genérico `{user_id, method, uri, headers, body}` (S3 ya lo usa); S4 añade arranque
   escalonado (`launch` + `awaitWaiting(n)`), obligatorio para despacho contra dispensación.
3. Orden de bloqueo (D4): una sola cabecera (traslado; al resolver, solo la discrepancia) → `stocks` por la clave
   global de S2 en una sola llamada a `apply()`. Dispensación: candado → ítems → `stocks`. Sin ciclos.

Otras decisiones: segregación comparando con `created_by` (vale en `BORRADOR`) y lanzada desde la acción, no la
Policy; `transfers_requester_is_creator` en base; sin `CHECK` estado ↔ actor; en tránsito derivado de las líneas, sin
bodega virtual; devolución por `AdjustStock` de S2 (regla de vencido en un solo lugar); `down()` de la bitácora con
`NOT VALID` (filas `transfer.*` imborrables romperían el `rollback` de las carreras).
Rechazadas: códigos del skill (`transfer_state_conflict`), bodega virtual de tránsito, columna `in_transit_quantity`,
`UPDATE` condicional optimista, `apply()` por línea, `transfer_id` en `kardex_movements`, tabla `transfer_events`.

Pines `[MUT]` que tal como estaban en el borrador no podían fallar o fallaban a veces (corregidos en diseño y tareas):
- M15: equivalente si la resolución bloquea el traslado (el borrador decía "traslado → discrepancia"). D4: bloquea
  solo la discrepancia.
- M9: intermitente con arranque simultáneo (si el despacho llega primero, el mutante pasa) y equivalente si solo
  añade una lectura sin bloqueo antes de `apply()`. D13/D14: arranque escalonado alternado; mutante = escritura sin
  la costura.
- M12: equivalente si queda la transacción externa. D14 lo define.
- M7: enmascarable por cualquier otra restricción que rechace la misma sentencia. D3: la prueba afirma el nombre.

Bloqueante de redacción para el spec-engineer (riesgo 3; cambia el resultado de una prueba, no es solo redacción):
«Matriz de transiciones prohibidas» exige un actor "distinto del solicitante", imposible para `solicitar` en los
estados con solicitante (solicitante = creador y solo el creador pasa la Policy): leído al pie de la letra da 403,
no 409. Corrección propuesta de una línea: "…distinto del solicitante, salvo para solicitar, que la envía el
creador". «Estados terminales» con "un `regente_farmacia`" se cumple solo con dos regentes (R1 creador solicita, R2
aprueba): admitido por D15 sin cambio, o explicitarlo en la misma corrección.

Riesgos señalados: los 3 de `design.md`; menores: motivo recortado en el kardex de la devolución, 404 antes que 403
en traslados, sin consulta de en tránsito hasta S5/S7.

## 2026-10-07 — spec-engineer: corrección por hallazgo del architect (D15, riesgo 3)

- «Matriz de transiciones prohibidas»: actor «distinto del solicitante, salvo para solicitar, que la envía el
  creador». «Estados terminales»: R1 crea, solicita y envía solicitar/despachar/recibir/anular; R2 envía aprobar.
- Sin cambio de comportamiento ni de `tasks.md`. Validación estricta válida; ancla: 71 hits, 0 sin anclar.

## 2026-10-07 — spec-engineer: corrección por hallazgos del spec-validator

- `transfers` «Movimientos de traslado en el kardex»: escenario negativo «Despacho rechazado sin movimiento»
  (409 `insufficient_stock` y 422 `lot_expired`, sin `salida_traslado`), citado en tarea 5.4.
- `tasks.md`: 0.1, 4.2, 5.13, 6.2, 6.3 con comando ejecutable; 5.13, 6.2, 6.3 marcadas Cimiento; 4.2 nombra
  sus 11 escenarios 403. Orden y pines del architect intactos. Validación estricta válida; ancla: 72 hits, 0 sin anclar.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-transfers` tras correcciones de prosa (redacción de dos escenarios según D15,
  escenario negativo de kardex, comandos de verificación). Ancla de transporte: 72 hits, 72 con ancla.
- Pines M7, M9, M12 y M15 redefinidos por el architect para que puedan fallar.
- Condición 1 (alcance exacto de S4): OK. Máquina de estados, despacho/recepción, discrepancias con
  resolución mínima, segregación de funciones. Sin pantallas.
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): A, coincide con ROADMAP.
- Condición 4 (ADR / RN): ninguna decisión congelada ni regla debilitada.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier A
