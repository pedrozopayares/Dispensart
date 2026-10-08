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

## 2026-10-08 — Orchestrator: /apply adelantado

- `openspec validate add-dispensation --strict`: válido. GATE 1 registrado.
- S2 tiene backend y devops cerrados; espera auditoría. S3 usa `StockLedger` de S2 ya construido. Se adelanta
  el backend de S3 (paralelismo autorizado por el usuario). El backend de S3 no modifica archivos de S2.

## 2026-10-08 — backend-implementer: grupos 1–5, 6.2, 6.3 y deuda D-auv-2 (`software/api`)

Tareas `[x]`: 1.1–1.6, 2.1–2.4, 3.1–3.8, 4.1–4.5, 5.1–5.15, 6.2, 6.3. Pendientes: **0.1** (la costura se leyó y se
confirmó, ver abajo; su verificación exige `inventory` y `kardex` vivos y S2 sigue sin archivar) y **6.1** (script de
humo sobre el stack: devops). Commits en `dev`: `0dfb7fc`, `3c89072`, `fabb1c9`, `f8008c0`, `43598bc`, `1f53aa7`,
`183d9ee`, `28e8432`, `cbbc9fe`, `85fc059`, `237126f`. Sin dependencias nuevas. Trazabilidad, `[MUT]`, anclas de
transporte, barridos y reparto de líneas: `verification.md` §§ 0–5.

### 0.1 — hallazgos de la costura

| Pregunta | Hallazgo | Archivo:línea |
|---|---|---|
| Orden de retorno de `StockLedger::apply()` | movimientos en el orden de los cambios (bucle sobre `$changes`); D2 sin respaldo por `lot_id` | app/Services/Inventory/StockLedger.php:39 |
| Nombre del scope de bloqueo | `Stock::scopeInLockOrder` (alias de join `lock_lots`) | app/Models/Stock.php:68 |
| Hash ficticio del login | vivía en `LoginAction`; extraído a `Services\Identity\CredentialVerifier`, `LoginAction` lo usa sin cambiar comportamiento (pruebas de login de S1 verdes) | app/Services/Identity/CredentialVerifier.php |

### Decisiones de implementación (sin cambio de spec)

- Limitador del autorizador con `hash('sha256', correo)`, no `sha1` (D6): el preset `security` de Pest prohíbe `sha1`.
  Mismo comportamiento: el correo nunca queda en claro en la caché.
- `FefoAllocator` ordena él mismo por (`expires_on`, `lot.id`) además de recibir la consulta ordenada: M1 se observa
  en el asignador sin mutar el scope de S2.
- `DispensationPlanner` expone `check()` (pasos 8–9) y `allocate()` (paso 11): la acción relee `is_controlled` (paso
  10) entre ambos, como fija D4; la vista previa usa `plan()` = ambos.
- `RedactExceptionProcessor`: `code` es el SQLSTATE de `errorInfo` o del prefijo `SQLSTATE[…]` (un fallo de
  conexión trae código PDO numérico); nunca el resto del mensaje.
- Sin fábrica de `DispensationLine`: una línea exige un movimiento real del kardex; fabricarla fuera del libro rompería
  la cadena de saldos. Las líneas solo nacen por la acción (pruebas HTTP) o por inserción directa en las pruebas de base.
- `PatientResource`/`PrescriptionResource`/`DispensationPreviewResource` con colecciones tipadas: Scramble infiere
  esquemas completos; `POST /dispensations` documenta 201 + `Idempotent-Replayed` vía `@response` y el transformador.
- Archivos de S0/S1/S2 tocados por diseño: `AssignCorrelationId` y `JsonLineTap` (D9); `LoginAction` (D6);
  `InsufficientStock` con `shortages` (contrato); `RaceRunner`/`race-worker` generalizados a cualquier ruta, cabeceras y
  barrera propia (D10; `postAdjustments` intacto). Pruebas de S0 ajustadas al comportamiento nuevo de D9:
  `CorrelationIdTest` y `ReadyDatabaseDownTest` (mensaje = clase, contexto con SQLSTATE), `RequestLogTest` (`POST
  /health` ya no resuelve ruta → `unmatched`; la prueba usa `GET` con cuerpo para conservar su control `/health`).
  Prueba de S2 `KardexIntegrityTest` "vaciar la tabla": `TRUNCATE … CASCADE` (la FK de `dispensation_lines` hace que
  un `TRUNCATE` simple choque antes con la FK, 0A000); en cascada solo el trigger lo detiene.

### D-auv-2 (deuda del coordinador)

| Ítem | Evidencia |
|---|---|
| Migración reversible `created_at DEFAULT clock_timestamp()` en `kardex_movements` | database/migrations/2026_10_08_000004_set_kardex_created_at_to_clock_timestamp.php |
| Prueba: dos transacciones solapadas que escriben en orden inverso a su inicio; el listado sigue `balance_after` | Kardex/KardexTimestampOrderTest.php:35 |
| Con el default viejo → FALLA 1/1; con la migración → PASA 1/1 | `verification.md` § 3 fila D-auv-2 |
| Hallazgo: `created_at` es `timestamptz(0)`; dentro de un mismo segundo el desempate por `id` ocultaba el defecto, la prueba separa inicio y escrituras > 1 s | commit `fabb1c9` |

### Corridas

| # | Comando | Resultado |
|---|---|---|
| deltas | archivos de S3 por grupo; `--filter` por `[MUT]` | ver `verification.md` § 3 |
| cierre | `pint --test && phpstan analyse --memory-limit=1G && php artisan test` (`85fc059`) | Pint pasa; Larastan 0 errores; Pest 488 pasan / 1972 aserciones |
| delta posterior | `PatientEndpointTest.php` (`237126f`, fila `medico` del 404) | 28 pasan / 167 aserciones |
| OpenAPI | `composer openapi` ×2 + `cmp`; `npm run openapi:lint` | idéntico; Redocly válido |

### Deuda y pendientes (en prosa; el Orchestrator asigna ids)

- `openapi.json` cambió: los tipos de la SPA (`software/web`, `api-schema.ts`) deben regenerarse o el control de deriva
  de CI fallará. Es tarea del frontend-implementer.
- 6.1 (script de humo de dispensación sobre el stack, ruta bajo `software/docker/smoke`) queda para devops-implementer.
- 0.1 se cierra cuando S2 se archive (`inventory` y `kardex` vivos); los hallazgos ya están arriba.
- La línea de cierre del log registra ahora el patrón de ruta o `unmatched` (D9): `service-health` de S0 no cambia de
  escenario para `/ready`, pero un `POST` a una ruta solo `GET` ya no registra su ruta literal. Si el auditor lo exige
  al archivar S0, es un delta de una fila (ya señalado por el spec-engineer).

## 2026-10-08 — devops-implementer: 6.1 humo de dispensación sobre el stack

- Tarea `[x]`: 6.1. Script `software/docker/smoke/dispensation-smoke.sh` (bash, curl, jq; patrón de `stock-smoke.sh`).
- Stack: `docker compose up -d --build --wait api`; sin `down -v` (web y db intactos). Migraciones de S3 aplicadas.
- Decisión: el médico semilla crea una prescripción nueva por corrida (MED-004 ×2, MED-006 ×1); las semillas no se
  agotan y el humo es repetible. La prescripción sembrada se comprueba en la ficha, no se consume.
- Evidencia y controles negativos (contraseña errónea, clave distinta en la repetición): `verification.md` § 6.
- Deuda: ninguna nueva. Sin cambios en `app`, `src`, compose ni workflows; el humo no corre en CI (igual que S1/S2).

## 2026-10-08 — spec-engineer: hallazgos de la auditoría final (RN-10)

- [major] `patients` "Búsqueda de pacientes": `auditor` solo coincide por documento completo exacto; escenarios
  "Auditor con prefijo de documento sin resultados" y "Auditor con fragmento de nombre sin resultados" (con ancla).
- [minor] "Ficha del paciente con prescripciones": `{id}` de 1 a 18 dígitos; escenario "Identificador fuera de
  rango" → 404 `not_found` (con ancla).
- `tasks.md` grupo 7 (7.1–7.4) con `[MUT]` M15; cabecera: declarados 14, total 15. Validación estricta válida;
  barrido del ancla sin hits sin ancla.

## 2026-10-08 — backend-implementer: grupo 7 (hallazgos de final-auditor)

Tareas 7.1–7.4 `[x]`. Commit `1aea2be`. Filas nuevas PAT-30..32, M15 y comprobación de 7.3 en `verification.md`.

| Hallazgo | Corrección | Evidencia |
|---|---|---|
| [major] búsqueda del auditor por prefijo o nombre: oráculo para reconstruir datos enmascarados | `SearchPatients`: sin `viewIdentifiable`, solo `document_number = q` exacto | PatientEndpointTest.php:117 (auditor vacío + control positivo del auxiliar); M15 2/2 FALLA → 2/2 PASA |
| [minor] id de paciente sin tope: `whereNumber` admitía ids fuera de bigint → 500 | ruta `[0-9]{1,18}` → sin ruta → 404 `not_found` | PatientEndpointTest.php:201; sin el tope 2/5 filas dan 500, con él 5/5 |

- Otros parámetros numéricos: en S3 solo `{patient}`. `{warehouse}` y `{product}` (S1) usan `whereNumber` con enlace
  implícito y probablemente comparten el defecto (19 dígitos sobre el máximo de bigint → error de PostgreSQL → 500).
  No son de esta tajada: los señalo como deuda. `{transfer}`/`{discrepancy}` son de S4, que trabaja en paralelo.
- `openapi.json` no se regeneró: el árbol de trabajo tiene rutas de S4 sin confirmar y la exportación las incluiría.
  Hay que re-exportar (y regenerar los tipos de la SPA si cambian) en la próxima corrida limpia. Es tarea de
  frontend/S4.
- Incidente: al editar `routes/api.php` lo trunqué. Una lectura y escritura en la misma expresión de Python abrió el
  archivo en modo escritura antes de leerlo, y eso borró los cambios sin confirmar de S4. Restauré la versión
  confirmada y reapliqué el reemplazo exacto de S4, tomado de su transcripción (+24 líneas, idénticas). Solo mi línea
  entró al commit, por blob preparado. S4 debe confirmar que su `routes/api.php` está como lo dejó.

## 2026-10-08 — Orchestrator: GATE 2

- final-auditor: OBSERVATIONS (1 major RN-10 búsqueda del auditor, 1 minor id desbordado) → corregidas en
  `8960355` (spec), `1aea2be` (código + pruebas, M15), `81b6b11` (registro). Re-auditoría delta: `APPROVED`.
- GATE 2: APPROVED
- DEBT: D-auv-2 saldado. Filado D-auv-3 (minor, mismo desborde de id en rutas de S1) con arrastre a S4.
