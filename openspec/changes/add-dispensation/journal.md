# Journal — add-dispensation (S3)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv
- Fase: /proposal adelantada (cuarto hilo, instrucción del usuario). Apply espera el archivo de S2.
- Tier objetivo según ROADMAP: A.
- Requisito heredado del architect de S0: mensajes de excepción con datos de paciente nunca llegan a logs (RN-10).

## 2026-10-07 — spec-engineer: proposal, deltas y tasks borrador

- Producido: `proposal.md`; deltas nuevos `patients`, `prescriptions`, `dispensation`, `audit-trail`; `tasks.md`
  borrador (41 tareas, `[MUT]` M1–M13). `openspec validate add-dispensation --strict` válido. Barrido del ancla de
  transporte (`/usr/bin/grep`, patrón de `CYCLE-TIERS.md`): todos los hits llevan `[ancla: …]`; control positivo:
  el mismo patrón sin filtro devuelve hits en los 4 deltas.
- Partición: NO se divide. La partición permitida movería la bitácora a `add-controlled-drug-authorization` y
  dejaría lecturas de pacientes sin bitácora (debilita RN-10). RN-05 por credenciales en línea no añade
  endpoints ni estados.
- Decisión RN-05: `authorizer_email` + `authorizer_password` en la misma petición; autorizador ≠ dispensador, con
  `controlled_drugs.authorize`; fallos indistinguibles (`invalid_authorizer`), limitador 5/60 s, fila de fallo en
  bitácora, contraseña fuera de huella de idempotencia, logs y bitácoras.
- Supuestos: 10 (proposal § Assumptions).
- Preguntas abiertas para el architect: (1) enlace línea de dispensación → movimiento del kardex sin tocar specs
  de S2; (2) limpieza de pruebas de carrera ante triggers que rechazan `DELETE`/`TRUNCATE` (kardex y bitácoras);
  (3) precedencia exacta de rechazos tras la idempotencia (validación → autorizador → prescripción → stock);
  (4) cómo hacer determinista la prueba de orden cruzado (M13).
- Deuda candidata (Orchestrator asigna id): consulta de bitácoras para `auditor` sin capacidad en S1.

## 2026-10-07 — spec-engineer: entrada del coordinador (log de S0)

- Añadido en `patients`: requisito "Rutas de pacientes registradas por patrón" (`path` = patrón de ruta resuelta,
  `unmatched` sin ruta, sin `id` de paciente ni mensaje de `ModelNotFoundException` en el log; RN-10) con 4
  escenarios, y escenario "Excepción con datos del paciente en el mensaje" en el requisito de logs.
- `tasks.md`: tarea 4.4 nueva con `[MUT]` M14; declarados pasan a 14; siembra pasa a 4.5.
- Validación estricta válida; barrido del ancla: 0 hits sin ancla, control positivo 18 hits en `patients`.
- Nota para el architect: cambia el comportamiento del middleware de log de S0 sin contradecir su escenario de
  `/ready` (ruta sin parámetros). No se escribe `MODIFIED` de `service-health` porque S0 no está archivado; si al
  archivar S0 el auditor lo exige, es un delta de una fila.

## 2026-10-07 — architect: design.md y refinamiento de tasks

Producido: `design.md` (D1–D10, contrato de 5 endpoints, 7 migraciones reversibles, 3 riesgos). `tasks.md`: sección 3
reordenada (idempotencia 3.8 → 3.5; acción 3.6/3.7; vista previa 3.8), tareas citan decisiones; specs y escenarios sin
cambios. `openspec validate add-dispensation --strict`: válido.

Respuestas a las preguntas abiertas:
1. Enlace (D2): `dispensation_lines.kardex_movement_id` NOT NULL, UNIQUE, FK → `kardex_movements`. Líneas escritas
   tras `apply()`, que devuelve movimientos en orden de cambios (0.1 lo confirma; respaldo por `lot_id`). S2 intacto.
   Rechazados: columna en `kardex_movements` (cambia la costura `StockChange`), FK compuesta movimiento + lote.
2. Limpieza (D10): S2 D7 sin cambios. `DatabaseMigrations` por prueba de carrera; `down()` hace `DROP`, que los
   triggers de solo inserción no interceptan. `DatabaseTruncation` sigue prohibido. Fallos forzados por DDL dentro de
   la transacción de `RefreshDatabase`, sin costura en producción.
3. Precedencia (D4, tabla de 12 pasos): CSRF/401 → 403 → formato de clave → validación → repetición rápida →
   autorizador (fuera de transacción) → transacción: candado de asesoramiento + relectura → ítems bloqueados, estado →
   pendiente → `is_controlled` releído → existencias bloqueadas, FEFO, 409 → escrituras. Repetición tras validar
   (huella sobre `validated()` canónico) y antes del autorizador (sin re-verificar credenciales ni gastar limitador;
   "Reintento después de agotar" da el 201 original). Autorizador antes de reglas de prescripción: bcrypt fuera de
   bloqueos y fila de fallo confirmada sin segunda conexión; coste: `vencida` + autorizador inválido da
   `invalid_authorizer`.
4. M13 (D10): barrera de filas — el padre bloquea las dos existencias; mutante por ítem en orden de la petición
   interbloquea siempre (cada worker obtiene su primera fila al liberar) → `40P01`. Correcto: una sola consulta en
   orden global → dos 201. N = 10, mutantes 10/10.

Otras decisiones: idempotencia por usuario, cuerpo `text` (no `jsonb`), `pg_advisory_xact_lock`; enmascarado por lista
de permitidos en `PatientPolicy::viewIdentifiable` (sin capacidad nueva en S1); ficha sin enlace implícito de modelo
(403 antes de 404, sin oráculo de ids para `admin`); bitácora de acceso en la acción, antes de serializar; procesador
Monolog que sustituye mensajes de excepción por clase + SQLSTATE + restricción; `audit_events_details_ids_only`
(JSONB solo números) y `dispensations_authorizer_differs` en base; regla de vencido solo en `FefoAllocator`.
Conflictos con el skill `laravel-backend` resueltos a favor del repo: observer/middleware para bitácora,
`DatabaseTruncation`, `lockForUpdate()` sobre join, clave UUID.

**Riesgo bloqueante para GATE 1 (riesgo 1)**: M3 del borrador es mutante equivalente para "Carrera por la última
unidad" — `StockLedger::apply()` revalida el saldo bajo su propio bloqueo y el perdedor igual da 409. Propuesta al
spec-engineer, requisito "Dispensación atómica y segura ante concurrencia": escenario "Carrera con lote siguiente
disponible" — WHEN dos peticiones simultáneas piden 1 unidad cada una y la bodega tiene L1 (vence antes, 1 u.) y L2
(1 u.) THEN ambas responden 201, L1 y L2 quedan en 0. Con el mutante el perdedor toma L1 desactualizado → 409.
Alternativa si no se agrega: M3 = quitar el bloqueo de la consulta FEFO **y** del libro (duplica M4 de S2).

Deuda candidata (consulta de bitácoras para `auditor`): **fuera de S3, se fila como deuda**. RN-10 y la parte A piden
la bitácora, no su consulta; abrirla exige capacidad nueva en `identity-access` (MODIFIED de escenarios "son
exactamente…") y pantalla en S6. Sin criterio calificado que la exija; revisar en S6 si sobra presupuesto.

Riesgos menores señalados: bloqueo de todos los lotes candidatos del producto en la bodega; limitador por
dispensador + correo (no global); `ILIKE` depende de la configuración regional de la imagen `postgres:16`.

## 2026-10-07 — spec-engineer: riesgo 1 del architect (M3)

- Añadido en `dispensation`, requisito "Dispensación atómica y segura ante concurrencia", escenario "Carrera con
  lote siguiente disponible" (L1 y L2 de 1 u.; ambas 201, L1 y L2 en 0, dos movimientos), con ancla.
- `tasks.md`: 5.9 lo cubre; 5.11 apunta M3 a él. Orden del architect intacto. Validación estricta válida; barrido
  del ancla sin hits sin ancla.

## 2026-10-07 — spec-engineer: hallazgos del spec-validator

- `prescriptions` "Estado de la prescripción": escenario negativo "Estado no vigente rechaza la dispensación"
  (422 `prescription_expired`/`prescription_exhausted`, con ancla); citado en 5.6, excluido de 2.1 (unitaria).
- `tasks.md` 4.1 y 6.1 citan escenarios y comando de verificación. Orden del architect intacto. Validación
  estricta válida; barrido del ancla sin hits sin ancla.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-dispensation` tras correcciones de prosa (citas de tareas, escenario negativo
  de estado de prescripción). Ancla de transporte: 69+ hits, todos con ancla.
- Pin M3 reorientado al escenario "Carrera con lote siguiente disponible" (hallazgo del architect: el pin
  original sobrevivía a su escenario porque `StockLedger::apply()` vuelve a bloquear).
- Condición 1 (alcance exacto de S3): OK. Pacientes, prescripciones, FEFO, bloqueo, idempotencia,
  coautorización RN-05, bitácora de acceso, enmascarado. Logs por patrón de ruta: parte de RN-10. Sin división.
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): A, coincide con ROADMAP.
- Condición 4 (ADR / RN): ninguna decisión congelada ni regla debilitada.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier A
- Fuera de alcance (no deuda; va al README en S8): endpoint para que el auditor lea la bitácora.
