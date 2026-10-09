# Design — add-sensitive-operation-audit (S10, tier A)

## Context

Motivación y alcance en `proposal.md`; requisitos en `specs/audit-trail`. Costuras heredadas sin redefinir: S2
`StockLedger::apply()` (única escritura de existencias, se vuelve punto de guardado dentro de una transacción
abierta) y `AdjustStock`; S3 `AuditTrail::record()` (escribe en la conexión y transacción en curso), `audit_events`
de solo inserción con `CHECK` de detalle solo numérico; S4 D12 (reemplazo por nombre de los tres `CHECK`, `down()`
con `NOT VALID`). Grafo de llamadas verificado: `AdjustStock::handle()` lo llaman `StockAdjustmentController`,
`ResolveDiscrepancy` y ayudas de prueba (`AdjustStockTest`, `KardexEndpointTest`, `StockEndpointTest`,
`tests/Helpers/Alerts.php`, `AssistantEvalCommandTest`); `CreateUser::handle()` solo lo llama `UserController::store`.

## Goals / Non-Goals

**Goals**
- Una fila por operación exitosa, escrita en la misma transacción que la operación, por la misma pieza que ya usan
  S3 y S4 (`AuditTrail`). Cero filas en rechazos y en la resolución de discrepancias.
- Integridad en la base: acción ↔ tipo de objeto por `CHECK` con nombre, reversible, sin tocar el disparador.
- Cada `[MUT]` M1–M11 con una mutación que hace fallar su escenario siempre, y controles positivos donde el
  mutante es invisible para la prueba directa (M2, M6, M7, M11).

**Non-Goals**
- Ruta de lectura de bitácoras, cambio de rol, desactivación (proposal § Assumptions 1–2).
- Cambios a `StockLedger`, `StockChange`, `AdjustStock`, `ResolveDiscrepancy`, `AuditTrail`, al orden de bloqueo o
  a la dispensación (RN-03, RN-06, RN-09 intactos).
- Carrera por correo duplicado en el alta (violación `23505` → 500): comportamiento previo, sin escenario.

## Decisions

### D1. `user.created` se escribe dentro de `CreateUser`, en su propia transacción
`CreateUser::handle(User $actor, array $data): User` abre `DB::transaction`, guarda el usuario y llama a
`AuditTrail::record($actor->id, AuditAction::UserCreated, $user->id)` después de `save()`. `UserController::store`
pasa `$request->user()`. Único llamador: el cambio de firma no se propaga.
- Rechazada: escribir en el controlador (ley del repo: sin lógica en controladores; además la prueba `arch()`
  prohíbe `DB` en `App\Http\Controllers`).
- Rechazada: evento `created` del modelo `User` u observador (regla: sin lógica en ganchos del modelo; además
  factorías y semillas escribirían filas falsas sin actor admin).
- Revisar si: aparece otro camino de alta (importación, consola); debe pasar por `CreateUser`.

### D2. `stock.adjusted` se escribe en una acción nueva del endpoint, no en `AdjustStock` ni en el libro
Acción nueva `App\Actions\Inventory\AdjustStockManually` (final): `DB::transaction(fn () => { $movement =
$this->adjust->handle($user, $data); $this->audit->record($user->id, AuditAction::StockAdjusted, $movement->id,
['warehouse_id' => …, 'lot_id' => …]); return $movement; })`. `StockAdjustmentController` inyecta
`AdjustStockManually` en lugar de `AdjustStock`. `AdjustStock` y `ResolveDiscrepancy` no cambian: la resolución sigue
llamando a `AdjustStock::handle()` y solo escribe `transfer.discrepancy_resolved`. M7 fija esta frontera.
- Rechazada: escribir en `AdjustStock::handle()` → la resolución `returned_to_origin` escribiría además
  `stock.adjusted` (dos filas por una operación, rompe «Resolución de discrepancia sin fila de ajuste») y las cinco
  ayudas de prueba que llaman a `AdjustStock` directamente escribirían filas.
- Rechazada: parámetro `bool $audit` en `AdjustStock::handle()` → argumento bandera; un llamador nuevo que olvide el
  valor correcto audita mal sin que nada lo detecte.
- Rechazada: escribir en `StockLedger` cuando `type = ajuste` → la costura única de stock la comparten
  dispensación, traslados y resolución; acoplarla a la bitácora mezcla responsabilidades y duplica en la resolución.
- Rechazada: mover `ResolveDiscrepancy` a una primitiva inferior y auditar en `AdjustStock` → toca código de S4
  aprobado y las ayudas de prueba; más superficie, misma garantía.
- Revisar si: otro flujo de negocio necesita un ajuste manual auditado; llama a `AdjustStockManually`.

### D3. Atomicidad: `AuditTrail` existente dentro de la transacción de la acción
Se reutiliza `AuditTrail::record()` sin cambios: inserta en la transacción en curso. En el ajuste, la transacción la
abre `AdjustStockManually`; `StockLedger::apply()` pasa a punto de guardado (comportamiento ya documentado en su
docblock). Orden dentro de la transacción: búsqueda del lote → regla de lote vencido → libro (bloqueos, saldo,
kardex) → fila de bitácora. Cualquier excepción (`LotExpired`, `InsufficientStock`, fallo del `INSERT` de la
bitácora) revierte todo; el renderizador de la API responde 500 `server_error` a la `QueryException`.
- Rechazada: `DB::afterCommit()`, evento o cola → la fila quedaría fuera de la transacción (es exactamente M3/M5).
- Rechazada: dependencia nueva (`spatie/laravel-activitylog`) → escribe por eventos de modelo, detalle libre;
  viola la política de dependencias y el `CHECK` de detalle. Cero dependencias nuevas.
- Revisar si: la bitácora debe sobrevivir al rechazo (como `controlled_drug.authorization_failed`); no es el caso.

### D4. Literales y contenido del detalle
| Acción | `subject_type` | `subject_id` | `details` | Excluido explícitamente |
|---|---|---|---|---|
| `user.created` | `user` | `id` del usuario creado | `{}` | nombre, correo, contraseña, hash, `remember_token`, tokens Sanctum, rol |
| `stock.adjusted` | `kardex_movement` | `id` del movimiento `ajuste` | `{"warehouse_id", "lot_id"}` | `reason`, cantidad, saldo, cualquier dato de paciente |

`subject_type` sigue el singular de la tabla, como `prescription`, `dispensation`, `transfer`. `warehouse_id` y
`lot_id` siguen el precedente de `dispensation.created` (filtrar por bodega sin unir tablas); la cantidad y el saldo
viven solo en el kardex (de solo inserción), una fuente por dato. El rol del usuario creado no cabe (texto) y se lee
de `users.role` por `subject_id` (proposal § Assumption 4). `actor_id` = quien opera; `correlation_id` lo toma
`AuditTrail` del `Context`. El `CHECK audit_events_details_ids_only` ya rechaza cualquier texto en el detalle.
- Rechazada: detalle vacío también en el ajuste → obliga a unir con el kardex para la consulta más común.
- Rechazada: guardar el rol como número (índice del enum) → acopla la bitácora al orden del enum.
- Revisar si: aparece una acción de cambio de rol; escribe su propia fila con el rol previo/nuevo por id de catálogo.

### D5. Migración nueva que reemplaza por nombre los tres `CHECK`
`database/migrations/2026_10_12_000001_extend_audit_events_for_sensitive_operations.php`, mismo patrón que
`2026_10_10_000004`: `DROP CONSTRAINT` + `ADD CONSTRAINT` por nombre de `audit_events_action_check`,
`audit_events_subject_type_check`, `audit_events_action_subject_check`. Constantes literales propias (la migración es
autocontenida; no lee las de S3/S4). `up()` valida; suma `user.created`/`stock.adjusted`, `user`/`kardex_movement`, y
dos ramas de emparejamiento `(action = 'user.created' AND subject_type = 'user') OR (action = 'stock.adjusted' AND
subject_type = 'kardex_movement')`. No toca `audit_events_details_ids_only` ni el disparador
`audit_events_append_only` (de sentencia, `ENABLE ALWAYS`, cubre toda fila nueva). El enum `AuditAction` gana
`UserCreated` y `StockAdjusted` con su `subjectType()`.

**Comportamiento de `down()`, declarado**: repone los conjuntos de S3 + traslados con `NOT VALID`. No falla si hay
filas nuevas: quedan (la tabla es de solo inserción), la restricción queda `convalidated = false` y toda inserción
posterior de las acciones nuevas se rechaza. Un `up()` posterior valida de nuevo y pasa, porque toda fila existente
pertenece al conjunto ampliado. Lo fija `SensitiveOperationMigrationTest` (retroceso y reaplicación con filas nuevas presentes,
`convalidated` falso y luego verdadero) y lo pina M11.
- Rechazada: `down()` que lanza si existen filas nuevas → `StockAdjustmentRaceTest` y `TransferRaceTest` usan
  `DatabaseMigrations`, que hace `migrate:rollback` al salir con filas confirmadas (`stock.adjusted` tras esta
  tajada): la suite se rompería. También bloquearía el retroceso en desarrollo sin ganancia.
- Rechazada: `down()` que borra las filas nuevas → el disparador lo impide y borraría evidencia de auditoría.
- Rechazada: tabla de catálogo de acciones con FK o tipo `ENUM` → `ALTER TYPE … ADD VALUE` no se revierte; cambio
  mayor que no es puerta de dos vías.
- Revisar si: el número de acciones pasa de ~15; entonces catálogo con FK.

### D6. Concurrencia: sin bloqueos nuevos, sin cambio de orden
El único bloqueo de filas del ajuste sigue siendo el de `StockLedger` (clave global `(lots.expires_on, lots.id,
warehouse_id)`). El `INSERT` en `audit_events` va después de los bloqueos y solo toma `FOR KEY SHARE` sobre la fila
del actor en `users` (FK), compatible con cualquier otro `KEY SHARE`; ningún camino de esta tajada actualiza
`users` dentro de una transacción de stock. La carrera por la última unidad: el perdedor recibe
`InsufficientStock` antes de llegar a la bitácora → una sola fila. Dos ajustes positivos simultáneos: dos movimientos,
dos filas. El alta no bloquea filas.

### D7. Fallo forzado en prueba: disparador de prueba sobre `audit_events`
Patrón de `PatientEndpointTest` (fallo forzado de `patient_access_logs`): dentro de la prueba, con
`RefreshDatabase`, `DB::unprepared` crea `test_fail_<accion>()` y un `BEFORE INSERT ON audit_events FOR EACH ROW WHEN
(NEW.action = '<accion>') EXECUTE FUNCTION …` que lanza `RAISE EXCEPTION`. El DDL es transaccional en PostgreSQL:
el rollback de `RefreshDatabase` lo elimina. El `WHEN` limita el fallo a la acción probada. La prueba afirma 500
`server_error` con `assertExactJson` y el estado previo (sin usuario con ese correo y mismo conteo; existencia en 10
y sin `ajuste` nuevo).
- Rechazada: gancho de prueba en código de producción (variable de entorno, bandera) → superficie de producción
  para pruebas.
- Rechazada: doble de `AuditTrail` en el contenedor → la clase es `final` y un doble que lanza precocina el
  comportamiento; no prueba la atomicidad en la base.
- Rechazada: provocar la violación de un `CHECK` real → exige mandar texto al detalle desde código de producción.

### D8. Ubicación de las pruebas y mutaciones exactas
Archivos: `tests/Feature/Audit/SensitiveOperationIntegrityTest.php` (sentencias directas, requisito «Integridad»),
`tests/Feature/Audit/SensitiveOperationMigrationTest.php` (`DatabaseMigrations`: retroceso y reaplicación con filas
nuevas presentes, fija el comportamiento declarado de `down()` en D5; M11),
`tests/Feature/Identity/UserCreationAuditTest.php`, `tests/Feature/Inventory/StockAdjustmentAuditTest.php` (endpoint,
rechazos, frontera con resolución y dispensación). Las carreras amplían los dos casos existentes de
`StockAdjustmentRaceTest`, con el título del escenario como prefijo del título actual, agregando al arreglo de resultado por iteración las filas `stock.adjusted` y su objeto (sin
pruebas de carrera nuevas: mismo costo de procesos).

| M | Mutación (texto exacto del parche) | Debe FALLAR | Control positivo (PASA con el mutante) |
|---|---|---|---|
| M1 | migración: quitar la rama `(action = 'stock.adjusted' AND subject_type = 'kardex_movement')` del emparejamiento | «Acciones nuevas admitidas» | — |
| M2 | migración: ramas nuevas sin condición de tipo: `action IN ('user.created','stock.adjusted')` | «Emparejamiento cruzado rechazado por la base» | «Acciones nuevas admitidas» |
| M3 | `CreateUser`: `record()` movido fuera del cierre de `DB::transaction`, después de él | «Fallo al registrar revierte el alta» | — |
| M4 | `CreateUser`: actor = `$user->id` en lugar de `$actor->id` | «Alta registrada» | — |
| M5 | `AdjustStockManually`: `record()` después de `DB::transaction` | «Fallo al registrar revierte el ajuste» | — |
| M6 | `AdjustStockManually`: `record()` antes de `DB::transaction`, objeto `$data['lot_id']` | «Ajuste rechazado sin fila» (409); «Carrera por la última unidad con una sola fila» | «Fallo al registrar revierte el ajuste» |
| M7 | `record()` movido de `AdjustStockManually` a `AdjustStock::handle()` tras el libro | «Resolución de discrepancia sin fila de ajuste» | «Ajuste registrado» |
| M8 | `AdjustStockManually`: objeto = `$data['lot_id']` en lugar de `$movement->id` | «Ajuste registrado» | — |
| M9 | migración: `audit_events_action_check` sin `'user.created'` | «Acciones nuevas admitidas» | — |
| M10 | migración: `audit_events_subject_type_check` sin `'kardex_movement'` | «Acciones nuevas admitidas» | — |
| M11 | migración: `down()` repone los `CHECK` validados (sin `NOT VALID`) | «Filas nuevas inmutables» en `SensitiveOperationMigrationTest` (el retroceso falla con filas nuevas) | «Filas nuevas inmutables» en `SensitiveOperationIntegrityTest` (ciega al retroceso) |

Aserción por nombre de restricción determinista: PostgreSQL evalúa los `CHECK` de una tabla en orden alfabético de
nombre (`…_action_check` < `…_action_subject_check` < `…_details_ids_only` < `…_subject_type_check`), así que el
emparejamiento cruzado lo nombra `audit_events_action_subject_check` y el detalle con texto sobre una acción y tipo
válidos lo nombra `audit_events_details_ids_only`.

Mutantes de migración (M1, M2): cada corrida de `pest` es un proceso nuevo y `RefreshDatabase` ejecuta
`migrate:fresh` sobre `dispensart_test` en su primera prueba, así que la migración parchada se aplica y la restaurada
también. Si M1 no FALLA, se investiga el arnés antes de seguir.

## API contract

Sin endpoint nuevo; contrato de éxito y de rechazo sin cambio; `openapi.json` sin diff (no documenta 500).

| Método y ruta | Cambio | Prueba HTTP real |
|---|---|---|
| `POST /api/users` | efecto nuevo: fila `user.created`; 500 `server_error` si la fila falla | 2.1 |
| `POST /api/stock-adjustments` | efecto nuevo: fila `stock.adjusted`; 500 `server_error` si la fila falla | 3.1, 3.3 |
| `POST /api/transfers/{transfer}/discrepancies/{discrepancy}/resolve` | sin cambio; prueba de frontera | 3.2 |

## Data impact

| Objeto | Cambio | Reversible |
|---|---|---|
| `audit_events_action_check` | + `user.created`, `stock.adjusted` | sí, `down()` con `NOT VALID` (D5) |
| `audit_events_subject_type_check` | + `user`, `kardex_movement` | sí, ídem |
| `audit_events_action_subject_check` | + dos ramas acción ↔ tipo | sí, ídem |
| `audit_events_details_ids_only` | sin cambio | — |
| disparador `audit_events_append_only` | sin cambio; cubre las filas nuevas | — |
| `stocks` / `kardex_movements` | sin cambio de esquema; bloqueo de S2 intacto | — |

Sin índices nuevos: `audit_events_subject_index (subject_type, subject_id)` ya sirve a las consultas por objeto.

## Risks / Trade-offs

1. [Una futura llamada a `AdjustStock` desde un flujo manual no audita] → D2 nombra `AdjustStockManually` como la
   entrada del ajuste manual; M7 fija la frontera; el docblock de `AdjustStock` lo dice.
2. [`down()` con `NOT VALID` deja filas que no cumplen la restricción vigente] → comportamiento declarado (D5); un
   `up()` posterior revalida; la tabla es de solo inserción, borrar no es opción.
3. [El disparador de prueba se filtra a otras pruebas] → se crea dentro de la transacción de `RefreshDatabase` (DDL
   transaccional) y con `WHEN` por acción; nunca en archivos con `DatabaseMigrations`.

## Migration Plan

Desarrollo: `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock -e DB_DATABASE=dispensart api-tools
php artisan migrate --force` (nunca `migrate:fresh` sobre `dispensart`). Retroceso: `migrate:rollback --step=1`
(D5). CI: la suite migra `dispensart_test` desde cero.

## Open Questions

Ninguna.
