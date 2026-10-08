# Design — add-stock-and-kardex (S2, tier A)

## Context

Motivación y alcance en `proposal.md`; requisitos en `specs/inventory` y `specs/kardex`. Se apoya en S1
(`add-catalog-and-identity/design.md`: `lots`, mapa de capacidades D4, forma de rechazo D5, `BusinessCalendar` D6,
`SpaClient` D2, Scramble D9, seeders D11) y S0 (Pest en `api-tools` contra `dispensart_test`). S3 y S4 escriben por
la costura que fija este diseño. En compose el usuario de base de la app es `POSTGRES_USER`: superusuario y dueño.

## Goals / Non-Goals

**Goals**
- Una sola costura de escritura de existencias (`StockLedger`) con orden de bloqueo global, que S3 (FEFO) y S4
  (despacho/recepción) reutilizan sin redefinir locking ni kardex.
- Integridad defendida en la base: unicidad, `CHECK`, FKs compuestas, kardex inmutable por trigger.
- Prueba de carrera determinista: la mutación del bloqueo falla siempre, no "a veces".
- Migraciones reversibles, ejercitadas por la propia suite (D7).

**Non-Goals**
- Selección FEFO, idempotencia por clave, columnas de referencia a dispensación/traslado (S3/S4 las agregan).
- Trigger diferido que exija movimiento por cada `UPDATE` de cantidad (D6).
- Separación de rol de base dueño/ejecución (riesgo 3; S8).
- Entrada de mercancía con alta de lotes, idempotencia de ajustes (journal, candidatos de deuda).

## Decisions

### D1. Contexto acotado único `Inventory` para dos capacidades
`inventory` y `kardex` son capacidades separadas en la spec, pero un solo contexto de código: el kardex es el libro
del inventario y se escribe en la misma transacción que la existencia. Clases: `App\Models\Stock`,
`App\Models\KardexMovement`, `App\Enums\MovementType`, `App\Services\Inventory\StockLedger`,
`App\Services\Inventory\StockChange`, `App\Actions\Inventory\AdjustStock`, `App\Exceptions\InsufficientStock`,
`App\Exceptions\LotExpired`.
- Rechazada: contexto `Kardex` propio con evento "stock cambió" y listener. Partiría la transacción o la escondería
  en un listener síncrono; "un movimiento por cambio" dejaría de ser visible en una sola clase.
- Revisar si: el kardex se consume fuera de inventario (p. ej. contabilidad).

### D2. `StockLedger`: única puerta de escritura, orden de bloqueo global
Contrato (S3/S4 lo consumen tal cual):

```php
final class StockLedger {
    /** @param list<StockChange> $changes @return list<KardexMovement> */
    public function apply(array $changes): array;
}
final readonly class StockChange { // warehouseId, lotId, delta (≠ 0), MovementType $type, ?int $userId, ?string $reason }
```

`apply()` dentro de `DB::transaction()` (si el llamador ya abrió una, queda como savepoint y la transacción es la
del llamador; S3 escribe dispensación + clave de idempotencia + movimientos en una sola):
1. Carga los lotes de los cambios (`product_id`, `expires_on`) y ordena las claves por la **clave global de
   bloqueo** `(lots.expires_on, lots.id, warehouse_id)`.
2. Por cada clave, en ese orden: si `delta > 0`, `insertOrIgnore` de la fila con cantidad 0
   (`ON CONFLICT DO NOTHING`); luego `SELECT … FOR UPDATE` de esa fila. Si falta y `delta < 0` → `InsufficientStock`.
3. Con todas las filas bloqueadas calcula saldos en orden de los cambios; cualquiera < 0 → `InsufficientStock`
   **antes de escribir nada**.
4. Por cambio: `UPDATE stocks SET quantity = quantity + :delta … RETURNING quantity` e `INSERT` de un movimiento
   con `balance_after` = valor devuelto. El `CHECK` es respaldo: si salta, es un defecto (500), no un flujo.

`Stock::scopeInLockOrder()` encapsula la clave global; S3 la usa en su consulta FEFO
(`WHERE warehouse_id, product_id, expires_on > hoy, quantity > 0 ORDER BY expires_on, lot_id FOR UPDATE OF stocks`):
para una bodega + producto es exactamente FEFO, luego id de lote. Las columnas de orden son inmutables, así que
PostgreSQL bloquea en el orden de la salida ordenada sin reordenamientos por actualización concurrente.
- La regla de lote vencido **no** vive en el libro: la siembra debe crear existencia en un lote vencido
  (inventory "Siembra inicial") y S4 rechaza el vencido al despachar. Cada llamador aplica su regla; el libro
  garantiza saldo ≥ 0, bloqueo y un movimiento por cambio.
- Rechazada: desempate por `stocks.id` (lo que sugiere el skill). No existe antes de crear la fila; una recepción
  S4 que crea un destino y bloquea otro existente quedaría fuera de orden → interbloqueo posible.
- Rechazada: aislamiento `SERIALIZABLE` + reintentos. Reintentar no es posible dentro de la transacción del
  llamador (S3) y convierte la carrera en 500 si se agotan.
- Rechazada: `firstOrCreate` para la fila faltante (dos inserciones simultáneas → violación de unicidad → 500; es
  la mutación M10). Rechazada: `DB::transaction(..., attempts: 3)` (oculta errores de orden).
- Revisar si: aparece un camino que bloquee existencias fuera de `StockLedger`/`scopeInLockOrder`.

### D3. Coherencia producto-lote por FK compuesta; S2 añade su propio índice único en `lots` (pregunta 2)
Migración propia de S2: `ALTER TABLE lots ADD CONSTRAINT lots_id_product_id_unique UNIQUE (id, product_id)`;
`down()` la retira. No edita la migración de S1 (S1 se archiva antes del apply de S2; una migración aplicada es una
foto). `stocks (lot_id, product_id)` → `lots (id, product_id)` `ON DELETE RESTRICT`.
- Rechazada: trigger que compare `product_id` con el del lote. Más código, y no defiende un `UPDATE lots SET
  product_id` posterior; la FK sí (RESTRICT).
- Rechazada: quitar `product_id` de `stocks` (3FN pura). La spec lo exige como columna y escenario
  ("Producto que no corresponde al lote"); además sirve el índice FEFO de S3 por bodega + producto.
- Coste: un índice redundante con la PK en una tabla de decenas de filas.

### D4. Kardex: FK compuesta a la existencia y saldo guardado por movimiento
`kardex_movements (warehouse_id, product_id, lot_id)` → `stocks (warehouse_id, product_id, lot_id)` `RESTRICT`.
Garantiza bodega, producto y lote existentes y coherentes en una sola restricción, y que todo movimiento pertenece
a una existencia (cadena de saldos por fila). Efecto: una existencia con historia no se puede borrar.
`balance_after` se guarda, calculado bajo bloqueo (D2).
- Rechazada: tres FKs sueltas. Admiten un movimiento con producto ajeno al lote o sin existencia.
- Rechazada: saldo calculado al leer (`SUM() OVER`). O(n) por consulta, rompe la paginación descendente y no se
  puede defender con `CHECK (balance_after >= 0)`.
- Revisar si: el kardex necesita movimientos sin existencia (no hay caso).

### D5. Inmutabilidad: un trigger de sentencia `ENABLE ALWAYS`
Función `kardex_movements_reject_mutation()` (`RAISE EXCEPTION 'kardex_movements is append-only: % rejected',
TG_OP`) y trigger `kardex_movements_append_only BEFORE UPDATE OR DELETE OR TRUNCATE … FOR EACH STATEMENT`, luego
`ALTER TABLE kardex_movements ENABLE ALWAYS TRIGGER kardex_movements_append_only`.
- Sentencia, no fila: rechaza también un `UPDATE` que no toca filas; un solo trigger cubre los tres verbos.
- `ENABLE ALWAYS`: el trigger dispara aun con `session_replication_role = replica`, que de otro modo lo apaga en
  silencio para cualquier superusuario.
- Lo que no cubre: el dueño de la tabla puede `ALTER TABLE … DISABLE TRIGGER` o `DROP TRIGGER` (DDL). Riesgo 3.
- Rechazada: `REVOKE UPDATE, DELETE` al rol de la app. Sin efecto sobre dueño/superusuario, que es el rol actual.
- Rechazada: RULE `DO INSTEAD NOTHING`. Silencia en vez de rechazar; el escenario exige error.

### D6. Sin trigger diferido "cantidad = último saldo" (pregunta 3)
La regla "un movimiento por cambio" se defiende en `StockLedger` (única ruta), M3 y M5. No se añade constraint
trigger `DEFERRABLE INITIALLY DEFERRED` sobre `stocks`.
- Motivos: ningún escenario lo exige (comportamiento sin prueba = código sin evidencia); dentro de `RefreshDatabase`
  la transacción nunca confirma y el trigger diferido nunca dispara salvo `SET CONSTRAINTS ALL IMMEDIATE`; cada
  escritura de S3/S4 pagaría una consulta extra.
- Revisar si: aparece un segundo escritor de `stocks` fuera de `StockLedger`, o el jurado pide la garantía en base.

### D7. Aislamiento de las pruebas de carrera (pregunta 1)
- El trigger conserva `TRUNCATE` (escenario "Vaciado rechazado por la base"). `RefreshDatabase` no se ve afectado:
  `migrate:fresh` hace `DROP`, no `TRUNCATE`, y cada prueba se revierte con `ROLLBACK`.
- `DatabaseTruncation` queda prohibido en la suite (fallaría ruidosamente contra el trigger; nunca en silencio).
- Las pruebas de carrera usan `DatabaseMigrations`: esquema nuevo con `migrate:fresh` al entrar y
  `migrate:rollback` al salir. Cada iteración usa filas dedicadas (bodega + lote propios) confirmadas en la base.
  El `rollback` ejecuta todos los `down()` en cada corrida: reversibilidad probada gratis. La siguiente prueba con
  `RefreshDatabase` vuelve a migrar (`RefreshDatabaseState::$migrated = false`).
- Rechazada: `session_replication_role = replica` para limpiar. Requiere superusuario y, con `ENABLE ALWAYS`, ya no
  apaga el trigger; aunque lo hiciera, la suite llevaría un atajo que demuestra cómo saltarse RN-06.
- Rechazada: `DISABLE TRIGGER` en la limpieza. Mismo motivo, más DDL dentro de las pruebas.
- Rechazada: filas confirmadas sin limpieza. Contaminan conteos de pruebas posteriores (siembra, listados): falso
  rojo o falso verde según el orden.
- Rechazada: trigger sin `TRUNCATE`. Debilita la spec.

### D8. Mecanismo de carrera: procesos reales + barrera por bloqueo de tabla (pregunta 4)
`tests/Support/RaceRunner` lanza N procesos con `Illuminate\Support\Facades\Process::start()` que ejecutan
`php tests/Support/race-worker.php <payload>`. Cada worker arranca la app, fija `DB_DATABASE` recibido del padre
(nombre real, compatible con `--parallel`), `application_name = race-worker`, pone el usuario con
`Auth::guard('web')->setUser()` y despacha `POST /api/stock-adjustments` por el kernel HTTP real; imprime
`{status, code}`. Las pruebas usan la pila completa: FormRequest, Policy, acción, libro, render D5.

Barrera determinista: antes de lanzar, una conexión aparte del padre abre transacción y toma
`LOCK TABLE kardex_movements IN SHARE MODE`. El padre sondea `pg_stat_activity` hasta ver N backends
`race-worker` con `wait_event_type = 'Lock'` (tope 15 s → falla con el stderr de los workers) y hace `ROLLBACK`.
- Con bloqueo correcto: A bloquea la fila, actualiza y espera en el `INSERT` del kardex; B espera la fila de A.
  Al soltar: A confirma, B lee 0 → 409.
- Mutante M4 (sin `FOR UPDATE`): A y B leen 1, A espera en el `INSERT`, B en el `UPDATE` de la fila de A. Al
  soltar: B re-evalúa `quantity - 1` = -1 → `CHECK` → 500. Falla **siempre**.
- Mutante M10 (`insert` simple en vez de `insertOrIgnore`): B espera la clave sin confirmar de A y luego viola la
  unicidad → 500. Falla siempre.
- 10 iteraciones dentro de una sola prueba (filas nuevas por iteración); un ciclo de migración por prueba.
- Rechazada: `pcntl_fork`. Extensión nueva en la imagen `dev` y el hijo hereda el socket PDO del padre.
- Rechazada: `Concurrency::run()` (driver de procesos). Bloquea hasta terminar (no deja sondear la barrera) y los
  hijos cargan `.env`, no el entorno de `phpunit.xml`: riesgo de escribir en `dispensart`.
- Rechazada: barrera por `pg_advisory_lock` antes de la petición. Libera a ambos a la vez pero no garantiza que se
  solapen: el mutante pasaría a veces.
- Rechazada: comando artisan de prueba. Quedaría registrado en la imagen de producción.

### D9. Ajuste: reglas en la acción, contrato en el FormRequest
`StoreStockAdjustmentRequest`: `authorize()` → `can('adjust', Stock::class)`; reglas `warehouse_id`/`lot_id`
`required|integer|exists`, `quantity` `required|integer|not_in:0|between:-1000000,1000000`, `reason`
`required|string|max:500` (`TrimStrings` + `ConvertEmptyStringsToNull` globales convierten espacios en `null`).
Solo `validated()` llega a la acción. `AdjustStock`: carga el lote; `delta > 0` y `isExpiredOn(BusinessCalendar::
today())` → `LotExpired`; delega en `StockLedger::apply()` con `type = ajuste` y el usuario autenticado.
Consultas: filtros `integer|min:1` sin `exists` (un id inexistente da lista vacía, no 422); scopes de modelo
`filter()`; orden de existencias `warehouses.name, products.name, products.id, lots.expires_on, lots.id`; kardex
`created_at DESC, id DESC`, `paginate(per_page ?? 50)`.
Policies: `StockPolicy::viewAny` → `inventory.view`, `StockPolicy::adjust` → `inventory.adjust`,
`KardexMovementPolicy::viewAny` → `inventory.view`. El mapa de S1 no cambia.

### D10. Errores nuevos sobre la forma D5 de S1

| Excepción | HTTP | `code` |
|---|---|---|
| `App\Exceptions\InsufficientStock` | 409 | `insufficient_stock` |
| `App\Exceptions\LotExpired` | 422 | `lot_expired` |

Mensajes en `lang/es/errors.php`; sin `details` (la forma D5 no lo tiene; ni cantidades ni SQL). Un
`QueryException` del `CHECK` o del trigger sigue siendo 500 `server_error`: indica un defecto, no un flujo.

## API contract

Rechazos con la forma D5. CSRF solo en escrituras desde el origen de la SPA.

| Método y ruta | Sesión / CSRF | Petición | Respuestas | Prueba HTTP real |
|---|---|---|---|---|
| `GET /api/stock` | sí / no | `warehouse_id?`, `product_id?`, `lot_id?` (entero ≥ 1) | 200 `{data:[{id,quantity,warehouse{id,code,name},product{id,code,name,is_controlled},lot{id,lot_code,expires_on,is_expired}}]}` solo `quantity > 0`; 401; 403; 422 | 5.1 |
| `GET /api/kardex` | sí / no | filtros anteriores, `per_page?` 1–100 (50), `page?` | 200 `{data:[{id,type,quantity,balance_after,reason,created_at,warehouse,product,lot,user\|null}],links,meta{current_page,per_page,total}}`; 401; 403; 422 | 5.2 |
| `PATCH`/`DELETE /api/kardex/{id}` | — | — | 404 `not_found` (ruta inexistente) | 5.2 |
| `POST /api/stock-adjustments` | sí / sí | `warehouse_id`, `lot_id`, `quantity` (entero ≠ 0, \|q\| ≤ 1 000 000), `reason` (≤ 500) | 201 `{data: movimiento}`; 401; 403; 409 `insufficient_stock`; 419; 422 `validation_failed` / `lot_expired` | 5.3, 5.4 (carrera) |

`created_at` ISO 8601 con zona (UTC, como S1); `expires_on` `AAAA-MM-DD`.

## Data impact

Tres migraciones nuevas, posteriores a las de S1, todas con `down()` y restricciones con nombre explícito (las
mutaciones las quitan por nombre):

| # | Migración | Contenido | `down()` |
|---|---|---|---|
| 1 | `add_id_product_id_unique_to_lots_table` | `lots_id_product_id_unique UNIQUE (id, product_id)` | `DROP CONSTRAINT` |
| 2 | `create_stocks_table` | `id` identity; `warehouse_id`, `product_id`, `lot_id` bigint NOT NULL; `quantity integer NOT NULL DEFAULT 0`; `timestampsTz`. `stocks_warehouse_product_lot_unique (warehouse_id, product_id, lot_id)`; `stocks_quantity_non_negative CHECK (quantity >= 0)`; FK `stocks_warehouse_id_foreign` RESTRICT; FK `stocks_lot_product_foreign (lot_id, product_id) → lots (id, product_id)` RESTRICT; índices `(lot_id, product_id)`, `(product_id)` | `DROP TABLE` |
| 3 | `create_kardex_movements_table` | `id` identity; `warehouse_id`, `product_id`, `lot_id` bigint NOT NULL; `type varchar(32)`; `quantity integer`; `balance_after integer`; `reason varchar(500) NULL`; `user_id` NULL FK `users` RESTRICT; `created_at timestamptz NOT NULL DEFAULT now()`, sin `updated_at` (modelo con `$timestamps = false`; la fecha la pone la base). `kardex_movements_type_check` (5 literales); `kardex_movements_quantity_sign_check` (`quantity <> 0` y entradas > 0, salidas < 0, `ajuste` libre); `kardex_movements_balance_non_negative CHECK (balance_after >= 0)`; `kardex_movements_adjustment_attribution_check` (`type <> 'ajuste' OR (user_id IS NOT NULL AND btrim(coalesce(reason,'')) <> '')`); FK `kardex_movements_stock_foreign (warehouse_id, product_id, lot_id) → stocks` RESTRICT; índices `(warehouse_id, product_id, lot_id, created_at)`, `(product_id)`, `(lot_id)`, `(created_at, id)`, `(user_id)`; función + trigger D5 | `DROP TRIGGER`, `DROP FUNCTION`, `DROP TABLE` |

- Literales de tipo escritos en la migración, no desde `MovementType::cases()` (como `role` en S1).
- `varchar` + `CHECK` en vez de `ENUM` de PostgreSQL: misma razón que S1 (cambiar el conjunto es una sentencia).
- `integer` basta: el tope de ajuste es 10⁶ y las existencias semilla son de decenas.
- Bloqueo: `SELECT … FOR UPDATE` por fila en la clave global `(lots.expires_on, lots.id, warehouse_id)` (D2).
- Reversión: `migrate:rollback --step=3` deja S1 intacto; la prueba de carrera la ejecuta en cada corrida (D7).

## Risks / Trade-offs

1. [Prueba de carrera frágil o lenta: barrera que no se forma, worker que apunta a otra base] → barrera por espera
   observable en `pg_stat_activity` con tope y stderr de los workers en el fallo; `DB_DATABASE` pasado
   explícitamente; mutaciones M4/M10 deben fallar 10/10, no "alguna vez".
2. [Interbloqueo cuando S3/S4 bloqueen varias filas] → una sola clave global (D2) en `StockLedger` y
   `scopeInLockOrder`; S3/S4 no escriben `FOR UPDATE` propio sobre `stocks`. Un `40P01` sale como 500 y se registra.
3. [El rol de base de la app es superusuario y dueño: puede `DISABLE TRIGGER` o `DROP TRIGGER`] → el trigger
   rechaza todo DML de ese rol (lo que pide la spec) y `ENABLE ALWAYS` cierra `session_replication_role`; la defensa
   contra DDL exige un rol de ejecución sin propiedad ni superusuario: candidato de deuda para S8.

## Migration Plan

Requisito previo: S1 archivado (sus migraciones de `lots` existen). Arranque: `migrate --force` → `db:seed --force`
(el seeder de existencias corre tras `LotSeeder`, idempotente por clave bodega + lote). Reversión: revertir commits
+ `migrate:rollback --step=3`, o `down -v` en local.
