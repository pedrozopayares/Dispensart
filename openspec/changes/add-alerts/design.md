# Design — add-alerts (S5, tier A)

## Context

Motivación y alcance en `proposal.md`; requisitos en `specs/inventory-alerts/spec.md`. Tier A por decisión del
usuario (journal 2026-10-08): la migración nueva lleva `CHECK`. Se apoya en S1 (`BusinessCalendar`, regla de
vencido de `catalog`, mapa de capacidades, forma de rechazo, Scramble), S2 (`stocks`, `StockPolicy`,
`InventoryQuery`, `StockSeeder`) y S4 (despacho resta en origen, recepción suma en destino, ambos por
`StockLedger`).

## Goals / Non-Goals

**Goals**
- Mínimo por bodega + producto defendido en la base: `CHECK`, unicidad, FKs `RESTRICT`.
- Dos consultas de solo lectura que calculan las alertas en SQL con una sola fecha de negocio, sin bloqueos.
- Cada costura con propiedad fijada por un `[MUT]` que falla siempre (M1–M23 en `tasks.md`): restricciones de la
  tabla (`CHECK`, único, `RESTRICT`), día 89/90/91, hoy en Bogotá y no en la base, vencidos dentro de la lista de
  vencimiento, `quantity > 0`, orden, vence hoy, igual al mínimo, par sin existencias, falta de fila ≠ mínimo,
  bodega propia, tránsito en origen y en destino, siembra sin pisar ni duplicar, autorización, filtro por bodega y
  `warehouse_id=0`.
- Mutantes reproducibles: un parche por mutante y un arnés `mut`/`ctl` que aplica, exige FALLA, revierte y exige
  PASA (cabecera de `tasks.md`).

**Non-Goals**
- Edición de mínimos por API, pantalla (S6), notificaciones, correo, ventana configurable.
- Alertas sobre unidades en tránsito: no están en `stocks` mientras el traslado está `EN_TRANSITO` y no se
  listan en ninguna de las dos listas.
- Índices nuevos sobre `stocks` o `lots` (D6).
- Nueva capacidad en el mapa de roles (D5).

## Decisions

### D1. Tabla `stock_minimums`: una fila por bodega + producto, sin fila = sin mínimo
Columnas: `id` identity; `warehouse_id` bigint NOT NULL FK `warehouses` `RESTRICT`; `product_id` bigint NOT NULL FK
`products` `RESTRICT`; `minimum_quantity integer NOT NULL` sin valor por defecto; `timestampsTz`. Restricciones con
nombre explícito (los `[MUT]` las quitan por nombre):
- `stock_minimums_warehouse_product_unique UNIQUE (warehouse_id, product_id)`.
- `stock_minimums_minimum_quantity_positive CHECK (minimum_quantity > 0)`.
- FKs `stock_minimums_warehouse_id_foreign` y `stock_minimums_product_id_foreign`, ambas `ON DELETE RESTRICT`.

El `CHECK` dice exactamente "el mínimo es un entero estrictamente positivo". `NOT NULL` es parte de la defensa: en
PostgreSQL un `CHECK` evaluado sobre `NULL` es desconocido y la fila pasa; sin `NOT NULL`, `NULL` sería un tercer
estado sin significado. Mínimo 0 se rechaza porque "alertar si disponible < 0" nunca dispara: sería una segunda
forma de decir "sin mínimo" junto a la ausencia de fila, y dos representaciones del mismo hecho divergen (una
consulta filtra una, otra la otra). Con la ausencia de fila como única forma, la consulta de stock bajo parte de
`stock_minimums` y nunca necesita un caso especial. M23 lo fija: partir de todos los pares bodega × producto con
`COALESCE(minimum_quantity, 1)` alerta un producto sin mínimo y rompe «Producto sin mínimo». Un valor por defecto 0
sería un mutante equivalente (0 < 0 nunca alerta). Es el mismo argumento por el que 0 no se admite.
- Rechazada: columna `minimum_quantity` en `stocks`. `stocks` es por lote; el mínimo es por bodega + producto. Se
  repetiría por lote y un producto sin existencias no tendría dónde guardar su mínimo (escenario «Sin existencias con
  mínimo definido»).
- Rechazada: columna en `products`. RN-11 exige mínimo por bodega.
- Rechazada: nombre `warehouse_product_minimums`. Más largo sin más información; `stock_minimums` / `StockMinimum`
  sigue la familia `stocks` y el término de RN-11 ("stock mínimo").
- Rechazada: tope superior en el `CHECK`. Ningún escenario lo pide; el tipo `integer` acota. YAGNI.
- Revisar si: aparece edición por API (entonces el FormRequest replica `> 0` y el `CHECK` queda de respaldo).

### D2. "Hoy" sale de `BusinessCalendar` y entra a SQL como parámetro
`AlertQuery` calcula una vez por petición `$today = BusinessCalendar::today()` y `$horizon = $today + 90 días`
(constante `AlertQuery::EXPIRY_WINDOW_DAYS = 90`), y los pasa como fechas `AAAA-MM-DD` enlazadas. Todo lo demás
ocurre en la base: el filtro de ventana, el cálculo `days_to_expiry = lots.expires_on - :today::date` (resta de
`date` en PostgreSQL = entero de días) y el predicado de no vencido `lots.expires_on > :today` del disponible.
- Rechazada: `CURRENT_DATE` o `(now() AT TIME ZONE 'America/Bogota')::date` en SQL. Duplica la regla de zona de
  `catalog` en un segundo lugar y no obedece a `travelTo`: la frontera de Bogotá solo sería probable a la hora real.
  Es el mutante M16.
- Rechazada: traer las existencias a PHP y filtrar con `Lot::isExpiredOn`. Correcto pero carga todas las filas y
  repite en PHP una suma que la base hace en una sentencia. El predicado SQL `expires_on > :today` es la negación
  literal de `isExpiredOn` (`expires_on <= today`); M11 fija la frontera "vence hoy".
- `lot.is_expired` se sirve con `LotSummaryResource` de S2 (misma regla, mismo reloj): no se recalcula en SQL.
- Revisar si: la ventana pasa a ser configurable (parámetro validado, no constante).

### D3. Consulta de vencimiento: existencias, no lotes
```
SELECT stocks.*, (lots.expires_on - :today::date) AS days_to_expiry
FROM stocks JOIN lots ON lots.id = stocks.lot_id JOIN warehouses ON warehouses.id = stocks.warehouse_id
WHERE stocks.quantity > 0 AND lots.expires_on <= :horizon [AND stocks.warehouse_id = :warehouse_id]
ORDER BY lots.expires_on, lots.id, warehouses.name
```
Sin límite inferior: los vencidos con existencia entran (supuesto 2), con `days_to_expiry` ≤ 0. Una fila por
existencia: el mismo lote en dos bodegas da dos elementos. `warehouses.name` es único (S1), así que el orden es
total. Relaciones `warehouse`, `product`, `lot` con carga ansiosa.
- Rechazada: agrupar por lote sumando bodegas. El escenario «Mismo lote en dos bodegas» exige uno por bodega y el
  filtro de bodega perdería sentido.
- Revisar si: el volumen exige paginación (hoy decenas de filas; la spec dice sin paginación).

### D4. Consulta de stock bajo: parte de los mínimos, disponible por subconsulta agregada
```
SELECT stock_minimums.*, COALESCE(available.quantity, 0) AS available_quantity
FROM stock_minimums
JOIN warehouses ON … JOIN products ON …
LEFT JOIN (
  SELECT stocks.warehouse_id, stocks.product_id, SUM(stocks.quantity) AS quantity
  FROM stocks JOIN lots ON lots.id = stocks.lot_id
  WHERE lots.expires_on > :today
  GROUP BY stocks.warehouse_id, stocks.product_id
) available ON available.warehouse_id = stock_minimums.warehouse_id
           AND available.product_id = stock_minimums.product_id
WHERE COALESCE(available.quantity, 0) < stock_minimums.minimum_quantity [AND stock_minimums.warehouse_id = :w]
ORDER BY warehouses.name, products.name, products.id
```
- El predicado de no vencido vive **dentro** de la subconsulta. Ponerlo en el `WHERE` exterior, o usar `JOIN`
  interno, borra los pares sin existencias o con solo existencias vencidas (M5).
- Unión por bodega **y** producto: sumar por producto en todas las bodegas es M7.
- Comparación estricta `<`: igual al mínimo no alerta (M3). `SUM` de `integer` es `bigint`; se castea a entero.
- **Tránsito excluido por construcción.** Desde S4, despachar resta en origen y recibir suma en destino, ambos por
  `StockLedger`. Mientras el traslado está `EN_TRANSITO` sus unidades solo existen en `transfer_lines`, no en
  `stocks`. Sumar solo `stocks` ya da "disponible sin tránsito" en ambas bodegas. La consulta **no** toca
  `transfers` ni `transfer_lines`. Sumar las líneas en tránsito al destino (M12) o restarlas otra vez del origen
  (M13) son los errores plausibles de quien lee la regla al revés. Ambos se fijan con «Unidades en tránsito no
  cuentan hasta la recepción», que afirma origen y destino antes y después de recibir.
- Rechazada: subconsulta correlacionada por fila de mínimo. Equivalente a este volumen, pero el `LEFT JOIN` agregado
  hace visible en una sola sentencia la diferencia entre "sin existencias" y "sin mínimo".
- Rechazada: vista SQL o vista materializada. Una migración más y una invalidación que mantener (la materializada
  quedaría vieja tras cada dispensación). Revisar si: la consulta se reutiliza fuera de la API (asistente de IA S7).

### D5. Autorización: `StockPolicy::viewAny` existente (`inventory.view`)
`ListAlertsRequest::authorize()` → `can('viewAny', Stock::class)`, igual que `ListStockRequest`. Las alertas son una
lectura derivada de las existencias: quien lee existencias lee alertas. Precedencia de S2: `auth:sanctum` (401) →
`authorize()` (403) → reglas (422). Por eso un `medico` con `warehouse_id=abc` recibe 403, no 422.
- Rechazada: `AlertPolicy` nueva. No hay modelo "alerta" al que enlazarla, y repetiría el mismo `allows()`.
- Rechazada: capacidad nueva `alerts.view` en el mapa de S1. Cambia una superficie de permisos congelada en
  `identity-access` sin que ningún escenario lo pida.
- El ancla de la spec «Policy de alertas» se resuelve a `StockPolicy::viewAny` (archivo:línea al aplicar),
  invocada desde `ListAlertsRequest::authorize()`. Es un cambio de redacción, no de comportamiento (regla 12).
  M19 fija la llamada.
- Revisar si: un rol debe ver alertas sin ver existencias.

### D6. Índices y ausencia de bloqueos
- Índices nuevos solo en la tabla nueva: el único `(warehouse_id, product_id)` sirve al filtro por bodega y a la
  unión con la subconsulta. `stock_minimums_product_id_index` sirve la comprobación `RESTRICT` al borrar un producto
  (mismo patrón que `stocks_product_id_index`).
- En `stocks` la subconsulta agrupa por `(warehouse_id, product_id)`, prefijo del único
  `stocks_warehouse_product_lot_unique`. El filtro por bodega usa el mismo prefijo. A decenas de filas el
  planificador hará barrido secuencial de todos modos.
- Rechazado: índice en `lots (expires_on)` o índice parcial `stocks … WHERE quantity > 0`. Sin medición que lo pida.
  Revisar si: `stocks` supera ~10⁵ filas o `EXPLAIN ANALYZE` de `/api/alerts` pasa de 50 ms.
- **Sin bloqueos.** Dos `SELECT` simples en `READ COMMITTED`: cada sentencia ve su propia instantánea MVCC. Un
  `SELECT` no espera los `FOR UPDATE` de `StockLedger` ni los provoca. Nada se escribe, así que no hay saldo que
  proteger.
- Rechazado: `FOR SHARE`. Haría esperar a dispensaciones y despachos detrás de una lectura informativa. Además
  metería un lector en el orden de bloqueo global de S2 (D2), una superficie de interbloqueo sin beneficio.
- Rechazada: transacción `REPEATABLE READ READ ONLY` para que ambas listas compartan instantánea. Las listas son
  independientes y ningún escenario las cruza. Revisar si: S6 muestra una cifra que combine ambas.

### D7. Siembra por clave natural, solo crea
`StockMinimumSeeder` corre tras `StockSeeder` en `DatabaseSeeder`. Constante `MINIMUMS` con
`[código de bodega, código de producto, mínimo]`. Resuelve ids por `warehouses.code` / `products.code`, omite la
fila si falta alguno (patrón de `StockSeeder`) y hace `firstOrCreate(['warehouse_id', 'product_id'],
['minimum_quantity' => …])`. Nunca actualiza: un mínimo cambiado a mano sobrevive (M17). Nunca inserta a ciegas: la
resiembra no choca con el único (M18).

Distribución propuesta sobre las existencias semilla de S2 (días desde hoy en `LotSeeder`):

| Bodega | Producto | Mínimo | Disponible semilla | Efecto |
|---|---|---|---|---|
| FC | MED-006 (controlado) | 20 | 8 | alerta |
| BH | MED-004 | 64 | 60 (el lote vencido de 6 no cuenta) | alerta; contando vencidos serían 66 y no alertaría |
| BH | MED-006 | 5 | 0 (sin filas) | alerta con `available_quantity` 0 |
| FC | MED-001 | 50 | 140 | con mínimo, sin alerta |

Los demás pares con existencias no tienen mínimo. Los valores siguen siendo válidos mientras los lotes envejecen:
el disponible de los pares alertados solo baja, y FC/MED-001 conserva ≥ 100 hasta el día 200.
- Rechazada: `updateOrCreate`. Pisa el cambio del regente (escenario «Mínimo cambiado a mano…»).
- Rechazada: `insertOrIgnore` masivo. Correcto, pero rompe el patrón legible de los seeders S1–S4 sin ganancia
  (la siembra no es concurrente).

### D8. Forma de la respuesta y piezas de código
- `App\Queries\AlertQuery` (`expiringLots(?int $warehouseId)`, `lowStock(?int $warehouseId)`), aparte de
  `InventoryQuery`: tiene su propio reloj y su propia constante.
- `App\Models\StockMinimum` + `StockMinimumFactory`; `App\Http\Requests\Inventory\ListAlertsRequest`
  (`warehouse_id` `sometimes|integer|min:1`, sin `exists`: id inexistente → listas vacías, como S2; `min:1`
  fijado por M22, porque sin él `warehouse_id=0` respondería 200 con listas vacías en vez de 422);
  `App\Http\Controllers\Inventory\AlertController` invocable y delgado.
- Recursos: `ExpiringLotResource` (sobre `Stock` con `days_to_expiry`), `LowStockResource` (sobre `StockMinimum`
  con `available_quantity`) y `AlertsResource`, que envuelve ambas colecciones bajo `data`. Las listas vacías
  serializan `[]`, nunca se omiten. `warehouse`, `product` y `lot` reutilizan `WarehouseResource`,
  `ProductSummaryResource` y `LotSummaryResource`.
- Ruta `GET /api/alerts` dentro del grupo `auth:sanctum`, junto a las de inventario.
- Rechazada: devolver `response()->json([...])` armado en el controlador. Scramble no infiere la forma y el
  controlador dejaría de ser delgado.

## API contract

Rechazos con la forma de S1. Sin CSRF (lectura).

| Método y ruta | Sesión / CSRF | Petición | Respuestas | Prueba HTTP real |
|---|---|---|---|---|
| `GET /api/alerts` | sí / no | `warehouse_id?` (entero ≥ 1) | 200 `{data:{expiring_lots:[{warehouse{id,code,name},product{id,code,name,is_controlled},lot{id,lot_code,expires_on,is_expired},quantity,days_to_expiry}],low_stock:[{warehouse{id,code,name},product{id,code,name,is_controlled},minimum_quantity,available_quantity}]}}`; 401 `unauthenticated`; 403 `forbidden`; 422 `validation_failed` | 4.2 |

`expires_on` `AAAA-MM-DD`; `days_to_expiry` entero con signo; `quantity`, `minimum_quantity` y
`available_quantity` enteros.

## Data impact

Una migración nueva, posterior a las de S4, con `down()` y restricciones con nombre explícito:

| # | Migración | Contenido | `down()` |
|---|---|---|---|
| 1 | `2026_10_11_000001_create_stock_minimums_table` | `id` identity; `warehouse_id`, `product_id` bigint NOT NULL; `minimum_quantity integer NOT NULL`; `timestampsTz`. `stock_minimums_warehouse_product_unique (warehouse_id, product_id)`; `stock_minimums_minimum_quantity_positive CHECK (minimum_quantity > 0)` (vía `DB::statement`, como `stocks`); FK `stock_minimums_warehouse_id_foreign` → `warehouses` RESTRICT; FK `stock_minimums_product_id_foreign` → `products` RESTRICT; índice `stock_minimums_product_id_index` | `DROP TABLE` |

- Sin escritura de `stocks` ni `kardex_movements`; sin trigger; sin bloqueo de filas (D6).
- Reversión probada en cada corrida: las pruebas de carrera de S2–S4 usan `DatabaseMigrations`, que ejecuta todos
  los `down()`. Además, `migrate:rollback --step=1` + `migrate` en la tarea 1.1.

## Risks / Trade-offs

1. [El predicado SQL de vencido diverge de `Lot::isExpiredOn` (`>=` contra `>`)] → una sola expresión
   `lots.expires_on > :today` en `AlertQuery`, fecha de `BusinessCalendar`. M11 usa el lote que vence hoy, la
   frontera exacta de `catalog`. El reloj de prueba se fija en 2027 (lejos de la fecha real), así que M16
   (`CURRENT_DATE`) falla siempre.
2. [La exclusión del tránsito es por construcción: si S4 cambia su flujo (reservar al aprobar, sumar al destino al
   despachar), las alertas mienten sin que nadie toque `AlertQuery`] → la prueba de «Unidades en tránsito no
   cuentan hasta la recepción» recorre despacho y recepción reales de S4, no filas armadas a mano. Un cambio de ese
   flujo la pone en rojo. M12 y M13 fijan destino y origen.
3. [Seed frágil con el paso del tiempo: un staging de larga vida pierde la alerta o la gana por vencimientos]
   → distribución D7 elegida para que el disponible de los alertados solo baje y el no alertado conserve margen
   por 200 días. La prueba de siembra corre sobre base recién sembrada, nunca sobre un staging viejo.

Compromisos aceptados: alertas con milisegundos de retraso frente a una escritura concurrente (son informativas;
RN-11 no condiciona escrituras); mínimos solo por siembra o SQL directo hasta que exista la fila de deuda.

## Migration Plan

Requisito previo: S4 archivado (hecho). Arranque: `migrate --force` → `db:seed --force`. El seeder de mínimos va
tras `StockSeeder` y es idempotente por bodega + producto. Reversión: revertir commits + `migrate:rollback
--step=1`, o `down -v` en local. Sin impacto en datos existentes: la tabla nace vacía.

## Open Questions

Ninguna. La única pendiente (escenario de tránsito en la spec delta) la resolvió el spec-engineer con «Unidades en
tránsito no cuentan hasta la recepción»; ya no bloquea GATE 1.
