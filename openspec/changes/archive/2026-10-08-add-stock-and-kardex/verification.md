# Verification — add-stock-and-kardex (S2, tier A)

Fuente: secciones de `journal.md` (backend-implementer grupos 1–5, 6.2 y deuda D-auv-1; devops-implementer 6.1).
Árbol medido: `dev` en `ca7b329`. Prefijos: `INV` inventory, `KDX` kardex, `EXT` filas de diseño sin escenario.
Rutas de prueba relativas a `software/api/tests/Feature/`; de código, a `software/api/`.

## 0. Reparto de líneas

Líneas añadidas por S2. Comando: `git diff --numstat 855235e^ ca7b329 -- <alcance> | awk -F'\t' '$1!="-"{a+=$1} END{print a}'`.

| Categoría | Alcance | Líneas |
|---|---|---|
| Producto — API | `software/api/{app,config,database,routes,bootstrap,lang}` | 1247 |
| Producto — SPA | `software/web` | 0 |
| Producto — infraestructura | `software/compose.yaml`, `software/docker/{api,web,db}`, `.github` | 3 |
| Prueba — API | `software/api/tests`, `phpunit.xml` | 1522 |
| Prueba — humo del stack | `software/docker/smoke` | 123 |
| Generado | `software/api/openapi.json` | 602 |
| Registro | `openspec/changes/add-stock-and-kardex/**/*.md` (tamaño total antes de este archivo, `wc -l`) | 906 |

## 1. Matriz escenario → prueba → archivo:línea

Anclas comprobadas por script: cada `archivo:línea` de la tabla cae en una línea `it(`/`test(`.

| Comprobación | Referencias | Fuera de `it(`/`test(` | Control positivo |
|---|---|---|---|
| anclas de la tabla 1.1 | 89 | 0 | 1 referencia desplazada a la línea siguiente → 1 detectada |

### 1.1 Escenarios

| Capacidad | Escenarios en la spec | Escenarios con prueba |
|---|---|---|
| inventory | 34 | 34 |
| kardex | 22 | 22 |

| Id | Capacidad | Escenario | Prueba | Archivo:línea |
|---|---|---|---|---|
| INV-01 | inventory | Existencia válida | guarda una existencia válida con el producto de su lote y cantidad 0 | Inventory/StockIntegrityTest.php:29 |
| INV-02 | inventory | Cantidad negativa rechazada por la base | rechaza en la base una cantidad negativa y conserva la anterior | Inventory/StockIntegrityTest.php:38 |
| INV-03 | inventory | Existencia duplicada | rechaza una segunda existencia para la misma bodega, producto y lote | Inventory/StockIntegrityTest.php:49 |
| INV-04 | inventory | Producto que no corresponde al lote | rechaza una existencia cuyo producto no es el de su lote | Inventory/StockIntegrityTest.php:61 |
| INV-05 | inventory | Borrar bodega o lote con existencias | rechaza borrar una bodega … · rechaza borrar un lote … | Inventory/StockIntegrityTest.php:74, :85 |
| INV-06 | inventory | Roles con lectura de inventario | devuelve a los roles con lectura las mismas existencias en el orden contractual (dataset 3) | Inventory/StockEndpointTest.php:28 |
| INV-07 | inventory | Filtros combinados | combina los filtros de bodega y producto con Y | Inventory/StockEndpointTest.php:53 |
| INV-08 | inventory | Lote vencido con existencia visible | muestra la existencia de un lote vencido con is_expired verdadero | Inventory/StockEndpointTest.php:69 |
| INV-09 | inventory | Existencia agotada omitida | omite una existencia que quedó en 0 tras un ajuste | Inventory/StockEndpointTest.php:79 |
| INV-10 | inventory | Filtro sin resultados | devuelve una lista vacía para un filtro sin resultados | Inventory/StockEndpointTest.php:89 |
| INV-11 | inventory | Filtro mal formado | rechaza un filtro mal formado | Inventory/StockEndpointTest.php:95 |
| INV-12 | inventory | Roles sin lectura de inventario (existencias) | rechaza con 403 a los roles sin lectura (dataset 2) · Policy por rol (dataset 5) | Inventory/StockEndpointTest.php:102; Inventory/InventoryPolicyTest.php:14 |
| INV-13 | inventory | Sin sesión (existencias) | responde 401 sin sesión | Inventory/StockEndpointTest.php:111 |
| INV-14 | inventory | Ajuste negativo exitoso | aplica un ajuste negativo y escribe exactamente un movimiento | Inventory/StockAdjustmentEndpointTest.php:33 |
| INV-15 | inventory | Ajuste positivo sobre existencia inexistente | crea la existencia con un ajuste positivo cuando no existía | Inventory/StockAdjustmentEndpointTest.php:52 |
| INV-16 | inventory | Campos del servidor ignorados | ignora usuario, saldo, fecha y tipo enviados por el cliente · (acción) fija tipo ajuste, el usuario autenticado… | Inventory/StockAdjustmentEndpointTest.php:65; Inventory/AdjustStockTest.php:54 |
| INV-17 | inventory | Datos inválidos o incompletos | rechaza datos inválidos o incompletos por campo (dataset 10) | Inventory/StockAdjustmentEndpointTest.php:178 |
| INV-18 | inventory | Otro rol intenta ajustar | rechaza con 403 a los demás roles (dataset 4) · Policy por rol | Inventory/StockAdjustmentEndpointTest.php:200; Inventory/InventoryPolicyTest.php:14 |
| INV-19 | inventory | Sin sesión (ajuste) | responde 401 sin sesión | Inventory/StockAdjustmentEndpointTest.php:216 |
| INV-20 | inventory | Sin token CSRF desde la SPA | rechaza con 419 un ajuste desde la SPA sin X-XSRF-TOKEN y lo acepta con él | Inventory/StockAdjustmentEndpointTest.php:226 |
| INV-21 | inventory | Reintento del mismo ajuste | aplica dos veces el mismo ajuste repetido (HTTP · acción) | Inventory/StockAdjustmentEndpointTest.php:107; Inventory/AdjustStockTest.php:66 |
| INV-22 | inventory | Ajuste mayor que la existencia | rechaza con 409 un ajuste mayor que la existencia, sin efecto · (libro) rechaza un cambio mayor… | Inventory/StockAdjustmentEndpointTest.php:120; Inventory/StockLedgerTest.php:75 |
| INV-23 | inventory | Ajuste negativo sin existencia previa | rechaza con 409 un ajuste negativo sin existencia previa y no la crea · (libro) | Inventory/StockAdjustmentEndpointTest.php:130; Inventory/StockLedgerTest.php:96 |
| INV-24 | inventory | Ajuste que deja exactamente cero | deja la existencia exactamente en cero (HTTP · libro) | Inventory/StockAdjustmentEndpointTest.php:81; Inventory/StockLedgerTest.php:66 |
| INV-25 | inventory | Carrera por la última unidad | serializa la carrera por la última unidad: un 201, un 409 y nunca negativo, 10 de 10 | Inventory/StockAdjustmentRaceTest.php:24 |
| INV-26 | inventory | Ajustes positivos simultáneos sobre existencia inexistente | acumula ajustes positivos simultáneos … en una sola fila, 10 de 10 | Inventory/StockAdjustmentRaceTest.php:51 |
| INV-27 | inventory | Baja de existencia vencida | permite dar de baja la existencia de un lote vencido (HTTP · acción) | Inventory/StockAdjustmentEndpointTest.php:148; Inventory/AdjustStockTest.php:37 |
| INV-28 | inventory | Ingreso a lote vencido rechazado | rechaza con 422 un ingreso a un lote que vence hoy en Bogotá (HTTP · acción) | Inventory/StockAdjustmentEndpointTest.php:158; Inventory/AdjustStockTest.php:27 |
| INV-29 | inventory | Ingreso a lote que vence mañana | acepta un ingreso a un lote que vence mañana (HTTP · acción) | Inventory/StockAdjustmentEndpointTest.php:168; Inventory/AdjustStockTest.php:46 |
| INV-30 | inventory | Siembra inicial | siembra existencias en las 3 bodegas con lote vencido, controlado vigente y 2 lotes vigentes en una bodega | Inventory/StockSeedTest.php:21 |
| INV-31 | inventory | Un movimiento de entrada por existencia sembrada | escribe un único movimiento entrada por existencia sembrada … | Inventory/StockSeedTest.php:37 |
| INV-32 | inventory | Siembra repetida | repite la siembra sin crear existencias ni movimientos ni cambiar cantidades | Inventory/StockSeedTest.php:52 |
| INV-33 | inventory | Ajustes sobreviven a la resiembra | conserva un ajuste del regente tras resembrar, sin segunda entrada (HTTP) | Inventory/StockSeedTest.php:63 |
| INV-34 | inventory | Existencia previa no sembrada rechazada por la siembra | no toma una existencia ya creada por un ajuste antes de la siembra | Inventory/StockSeedTest.php:75 |
| KDX-01 | kardex | Inserción aceptada | acepta la inserción directa de un movimiento válido con fecha puesta por la base | Kardex/KardexIntegrityTest.php:36 |
| KDX-02 | kardex | Edición rechazada por la base | rechaza en la base editar un movimiento y la fila no cambia (cantidad, motivo) · sigue rechazando con session_replication_role = replica | Kardex/KardexIntegrityTest.php:46, :84 |
| KDX-03 | kardex | Borrado rechazado por la base | rechaza en la base borrar un movimiento y la fila permanece | Kardex/KardexIntegrityTest.php:61 |
| KDX-04 | kardex | Vaciado rechazado por la base | rechaza en la base vaciar la tabla y las filas permanecen | Kardex/KardexIntegrityTest.php:72 |
| KDX-05 | kardex | Sin ruta de edición ni borrado | no tiene ruta para editar ni borrar un movimiento (PATCH, DELETE) | Kardex/KardexEndpointTest.php:148 |
| KDX-06 | kardex | Tipos futuros ya admitidos | admite ya los tipos de traslado con su signo · (dominio) declara los 5 tipos; signo por tipo | Kardex/KardexIntegrityTest.php:100; Kardex/MovementTypeTest.php:8, :13 |
| KDX-07 | kardex | Tipo fuera del conjunto | rechaza un tipo fuera del conjunto | Kardex/KardexIntegrityTest.php:109 |
| KDX-08 | kardex | Signo contrario al tipo | rechaza un signo contrario al tipo o una cantidad 0 (dataset 6) · (dominio) dataset 12 + StockChange | Kardex/KardexIntegrityTest.php:120; Kardex/MovementTypeTest.php:13, :30 |
| KDX-09 | kardex | Saldo resultante negativo | rechaza un saldo resultante negativo | Kardex/KardexIntegrityTest.php:138 |
| KDX-10 | kardex | Ajuste sin usuario o sin motivo | rechaza un ajuste sin usuario o sin motivo (dataset 3) | Kardex/KardexIntegrityTest.php:149 |
| KDX-11 | kardex | (D4) movimiento sin existencia | rechaza un movimiento sin existencia que lo respalde | Kardex/KardexIntegrityTest.php:164 |
| KDX-12 | kardex | Ajuste escribe un solo movimiento | aplica un ajuste negativo y escribe exactamente un movimiento · (libro) | Inventory/StockAdjustmentEndpointTest.php:33; Inventory/StockLedgerTest.php:26 |
| KDX-13 | kardex | Cadena de saldos tras varios cambios | encadena los saldos tras +4, -2 y -1 (HTTP · libro) · varios cambios en una llamada | Inventory/StockAdjustmentEndpointTest.php:91; Inventory/StockLedgerTest.php:39, :54 |
| KDX-14 | kardex | Ajuste rechazado no deja movimiento | `expectUntouched` en 403, 401, 419, 422 (validación, lote vencido) y 409 · (libro) valida todo antes de escribir | Inventory/StockAdjustmentEndpointTest.php:120, :158, :178, :200, :216, :226; Inventory/StockLedgerTest.php:84 |
| KDX-15 | kardex | Fallo al escribir el movimiento revierte la existencia | revierte la existencia si falla la escritura del movimiento después de actualizarla | Inventory/StockLedgerTest.php:122 |
| KDX-16 | kardex | Roles con lectura consultan el kardex | devuelve a los roles con lectura los movimientos … paginados (dataset 3) · ordena por fecha antes que por id | Kardex/KardexEndpointTest.php:28, :54 |
| KDX-17 | kardex | Filtro por producto, lote y bodega | filtra por bodega, producto y lote combinados | Kardex/KardexEndpointTest.php:73 |
| KDX-18 | kardex | Movimiento de ajuste con usuario y motivo | muestra en el ajuste el nombre del regente y su motivo | Kardex/KardexEndpointTest.php:92 |
| KDX-19 | kardex | Movimiento de siembra sin usuario | muestra un único movimiento entrada sin usuario para una existencia semilla | Kardex/KardexEndpointTest.php:105 |
| KDX-20 | kardex | Página fuera de rango o filtro sin resultados | devuelve data vacío (dataset 2) | Kardex/KardexEndpointTest.php:119 |
| KDX-21 | kardex | Parámetros mal formados | rechaza parámetros mal formados (dataset 2) | Kardex/KardexEndpointTest.php:125 |
| KDX-22 | kardex | Roles sin lectura de inventario | rechaza con 403 a los roles sin lectura (dataset 2) · Policy por rol | Kardex/KardexEndpointTest.php:135; Inventory/InventoryPolicyTest.php:14 |
| KDX-23 | kardex | Sin sesión | responde 401 sin sesión | Kardex/KardexEndpointTest.php:144 |
| EXT-01 | — (4.1, D10) | render 409/422 y CHECK como 500 | mapea cada rechazo de inventario … · trata una violación de restricción como defecto | Inventory/InventoryErrorTest.php:10, :21 |
| EXT-02 | — (1.4) | Fábricas válidas | Factory de existencia (con entrada · en cero) | Inventory/StockFactoryTest.php:11, :22 |
| EXT-03 | — (D2) | Clave global de bloqueo | ordena las existencias por la clave global de bloqueo | Inventory/StockLedgerTest.php:133 |

### 1.2 Escenarios en el stack real (tarea 6.1, `docker compose down -v` + `up --build --wait`)

Conteos con `psql` sobre `dispensart` (consulta en el scratchpad de la sesión).

| Escenario | Paso | stocks | negativas | kardex | entrada | entrada sin usuario | existencia sin entrada | último saldo ≠ cantidad | Σ cantidad |
|---|---|---|---|---|---|---|---|---|---|
| INV "Siembra inicial", "Un movimiento de entrada por existencia sembrada" | arranque en frío | 14 | 0 | 14 | 14 | 14 | 0 | 0 | 434 |
| INV "Siembra repetida" | `up --force-recreate api web` (migra 0, resiembra) | 14 | 0 | 14 | 14 | 14 | 0 | 0 | 434 |
| INV "Ajustes sobreviven a la resiembra" | humo S2 (ajuste −1) + `up --force-recreate api` | 14 | 0 | 15 | 14 | 14 | 0 | 0 | 433 |
| Control positivo (transacción revertida) | +1 a una existencia y existencia nueva sin movimiento | 15 | 0 | 15 | 14 | 14 | 1 | 1 | 435 |
| Control positivo de "negativas = 0" | `UPDATE stocks SET quantity = -1` | — | rechazo `stocks_quantity_non_negative` | — | — | — | — | — | — |

| Existencia del humo tras la resiembra | Cantidad | Kardex (tipo, cantidad, saldo, usuario) |
|---|---|---|
| bodega 3, lote 5 | 39 | `entrada 40 40 null`, `ajuste -1 39 2` |

| Humo | Comando | Comprobaciones | Fallas | Salida |
|---|---|---|---|---|
| S1 autenticación | `software/docker/smoke/auth-smoke.sh` | 38 | 0 | 0 |
| S2 existencias y kardex (nuevo) | `software/docker/smoke/stock-smoke.sh` | 13 | 0 | 0 |
| Control positivo S2 | `SEED_USER_PASSWORD=wrong-on-purpose …/stock-smoke.sh` | 4 | 3 | 1 |

| Paso del humo S2 | Esperado | Obtenido |
|---|---|---|
| regente `GET /api/stock` | 200 con cantidades | 200 |
| regente `POST /api/stock-adjustments` −1 | 201, `balance_after` = cantidad − 1 | 201 |
| regente `GET /api/stock` filtrado | cantidad − 1 | 200, 39 |
| regente `GET /api/kardex` filtrado | movimiento `ajuste` con el motivo + `entrada` semilla | 200 |
| regente ajuste mayor que la existencia | 409 `insufficient_stock`, existencia intacta | 409, 39 |
| auxiliar `POST /api/stock-adjustments` | 403 `forbidden` | 403 |

| Salud | Resultado |
|---|---|
| `docker compose ps` tras el arranque en frío | db, api, web `healthy` |
| `GET /health` por web | 200 |
| `GET /ready` por web | 200 `{"status":"ready","checks":{"database":"ok","migrations":"ok"}}` |
| `docker compose exec api id -u` / `web id -u` | 1000 / 101 (no root) |

## 2. Mutaciones `[MUT]` (M1–M10; M3 en dos sentidos: M3a, M3b)

| n | Mutación | Aplicada → FALLA m/k: prueba | Restaurada → PASA k/k |
|---|---|---|---|
| M1 | migración del kardex sin el bloque función + trigger (`if (false) DB::unprepared(...)`) | FALLA 5/5: editar (cantidad, motivo), borrar, vaciar, replica | PASA 5/5 |
| M2 | migración de existencias sin `stocks_quantity_non_negative` | FALLA 1/1: rechaza en la base una cantidad negativa | PASA 1/1 |
| M9 | migración de existencias sin `stocks_lot_product_foreign` | FALLA 1/1: rechaza una existencia cuyo producto no es el de su lote | PASA 1/1 |
| M3a | `StockLedger::write` sin `$movement->save()` | FALLA 2/2: escribe exactamente un movimiento (HTTP y libro) | PASA 2/2 |
| M3b | `StockLedger::write` guarda el movimiento dos veces | FALLA 2/2: ídem | PASA 2/2 |
| M5 | bloqueo y validación en la transacción, escrituras fuera | FALLA 1/1: revierte la existencia si falla la escritura (QueryException: el UPDATE quedó fuera del punto de guardado y la transacción de la prueba abortó) | PASA 1/1 |
| M4 | `StockLedger::lockStocks` sin `lockForUpdate()` | FALLA 1/1, **10/10 iteraciones** `[201, 500]` `server_error` (el `CHECK` rechaza el -1 tras re-evaluar el UPDATE) | PASA 1/1, 10/10 |
| M10 | `insertOrIgnore` → `insert` | FALLA 1/1, **10/10 iteraciones** (`[201, 500]`, violación de unicidad) | PASA 1/1, 10/10 |
| M6 | `AdjustStock` sin la regla de lote vencido (`if (false && ...)`) | FALLA 2/2: ingreso a lote que vence hoy (HTTP y acción) | PASA 2/2 |
| M7 | `StockPolicy::adjust` devuelve `true` | FALLA 4/4: rechaza con 403 a los demás roles (dataset 4) | PASA 4/4 |
| M8 | `StockSeeder` sin comprobar existencia previa | FALLA 1/1: repite la siembra sin crear existencias ni movimientos | PASA 1/1 |

### 2.1 Pin de la deuda D-auv-1 (`SpaClient` sin token CSRF en lecturas, commit `1349b07`)

| Deuda | Cambio | Mutación → resultado | Evidencia de la deuda |
|---|---|---|---|
| D-auv-1: `SpaClient` enviaba `X-XSRF-TOKEN` también en GET; "Lectura sin token CSRF" no podía fallar | software/api/tests/Support/SpaClient.php:127–128 — el token viaja solo en POST/PUT/PATCH/DELETE | `ValidateCsrfToken::isReading` deja de tratar `GET /api/auth/me` como lectura → CsrfTest.php:57 FALLA 1/1; restaurado → PASA 1/1 | misma mutación con el `SpaClient` anterior → PASA 1/1 (la prueba no la detectaba) |

## 3. Anclas de transporte (cláusula → archivo:línea)

| Cláusula del ancla | Archivo:línea |
|---|---|
| ruta `GET /api/stock` | routes/api.php:44 |
| ruta `GET /api/kardex` | routes/api.php:45 |
| ruta `POST /api/stock-adjustments` | routes/api.php:46 |
| tabla de rutas sin `kardex/{id}` + render de NotFoundHttpException | routes/api.php:44–46 (solo GET); app/Exceptions/ApiExceptionRenderer.php:31, :42 |
| middleware `auth:sanctum` | routes/api.php:26 |
| middleware CSRF | bootstrap/app.php:35 (`statefulApi`), :42; app/Exceptions/ApiExceptionRenderer.php:44 |
| FormRequest de consulta de existencias | app/Http/Requests/Inventory/ListStockRequest.php:24; InventoryFilters.php (reglas `integer|min:1`) |
| FormRequest de consulta del kardex | app/Http/Requests/Inventory/ListKardexRequest.php:26, :27 |
| FormRequest de ajuste | app/Http/Requests/Inventory/StoreStockAdjustmentRequest.php:30–33 |
| Policy de existencias / de ajuste | app/Policies/StockPolicy.php:16 / :21 |
| Policy de movimientos | app/Policies/KardexMovementPolicy.php:15 |
| recurso de existencia (`lot.is_expired`) | app/Http/Resources/LotSummaryResource.php:26 (vía StockResource) |
| recurso de movimiento (`user`, `type`) | app/Http/Resources/KardexMovementResource.php:25, :33 |
| render de InsufficientStock / LotExpired | app/Exceptions/ApiExceptionRenderer.php:29 / :30 |
| regla de lote vencido (acción) | app/Actions/Inventory/AdjustStock.php:32 |
| servicio de libro de stock (transacción, insertOrIgnore, FOR UPDATE, rechazo, UPDATE … RETURNING, movimiento) | app/Services/Inventory/StockLedger.php:34, :88, :101, :128, :145, :160 |
| trigger de solo inserción del kardex | database/migrations/2026_10_08_000003_create_kardex_movements_table.php:69, :73, :77 |
| existencias con cantidad > 0 | app/Queries/InventoryQuery.php:28 |

## 4. Corridas de suite

| # | Dónde | Comando | Resultado |
|---|---|---|---|
| delta | api-tools | `php artisan test tests/Feature/Inventory tests/Feature/Kardex` | 125 pasan / 501 aserciones |
| cierre 1 | api-tools | `pint --test && phpstan analyse && php artisan test` | Pint pasa; Larastan aborta por el límite de 128M de PHP en sus workers; Pest no corre |
| cierre 2 | api-tools | `pint --test && phpstan analyse --memory-limit=1G && php artisan test` | Pint pasa (161 archivos); Larastan 0 errores; Pest 293 pasan / 1201 aserciones |
| delta D-auv-1 | api-tools | suites con `SpaClient` | 119 pasan / 562 aserciones |
| aislamiento | api-tools, `-e DB_DATABASE=dispensart` | `pest tests/Feature/Inventory/StockFactoryTest.php` | 2 pasan; base `dispensart` intacta (14 / 15 / 433, misma fila de 1.2); `<server force>` de `phpunit.xml` gana al entorno |
| CI | GitHub Actions, run `37732568878` sobre `ca7b329` | trabajo backend: Pint, Larastan `--memory-limit=1G`, Pest, deriva y lint OpenAPI | success: Pint 161 archivos; Larastan `[OK] No errors`; Pest 293 pasan / 1201 aserciones |
| CI | GitHub Actions, run `37732568878` | trabajo frontend: `api:types:check` | failure: `src/lib/api-schema.ts` no regenerado tras el OpenAPI de S2 (ver 6) |

| Presupuesto | Corridas completas |
|---|---|
| local (backend: cierre 1 abortado, cierre 2) | 2 de 3 |
| local (devops) | 0 |
| CI | 1 |

## 5. Barridos (`/usr/bin/grep`, control positivo plantado en el scratchpad)

| # | Patrón | Alcance | Hits | Control positivo |
|---|---|---|---|---|
| 1 | escrituras de `stocks`/`kardex_movements` (`table('stocks')`, `Stock::…update/delete/insert`, `UPDATE stocks`) fuera de `StockLedger` | app database/seeders routes | 1: StockSeeder.php:57, lectura `exists()` | archivo con `DB::table("stocks")->update` y `KardexMovement::query()->where…->delete()` → 2 |
| 2 | `new KardexMovement` | app database routes | 2: StockLedger.php:150; StockFactory.php:37 (solo pruebas) + 1 falso positivo (`new KardexMovementResource`) | `(new KardexMovement)->forceFill` plantado → 1 |
| 3 | `lockForUpdate`, `sharedLock`, `FOR UPDATE` fuera de `StockLedger` | app database | 0 | plantado → 1 |
| 4 | `dd(`, `dump(`, `var_dump(`, `ray(` | app database routes bootstrap tests de S2 | 0 | plantado → 1 |
| 5 | `Log::` en código de S2 | servicios, acción, controladores, requests, query, seeder, runner, worker | 0 | plantado → 1 |
| 6 | `Route::patch/put/delete` sobre `kardex` | routes | 0 | plantado → 1 |
| 7 | `DatabaseTruncation`, `DISABLE TRIGGER`, `session_replication_role` | app database tests | 2: comentario de la migración (:63) y la prueba ENABLE ALWAYS (KardexIntegrityTest.php:84, :89), ambos a propósito | `use …DatabaseTruncation;` plantado → 1 |
| 8 | `firstOrCreate/updateOrCreate/firstOrNew` sobre `Stock`/`KardexMovement` | app database | 0 | plantado → 1 |
| 9 | `sqlite` | pruebas de S2, Support, Helpers | 0 | config/database.php → 3 |
| 10 | `Facades\DB`, `->save(`, `::create(` en controladores de inventario | app/Http/Controllers/Inventory | 0 | `use …Facades\DB;` plantado → 1 |
| 11 | `sk-…`, `AKIA…`, `ghp_…`, `PRIVATE KEY`, `api_key=<16+>` | `software/compose.yaml`, `software/docker/smoke/stock-smoke.sh` | 0 | `OPENAI_API_KEY=sk-…` plantado → 1 |

## 6. Deuda y riesgos (prosa; el Orchestrator asigna ids)

| Punto | Estado | Evidencia |
|---|---|---|
| Larastan aborta con 128M | saldado: CI ya corre `phpstan analyse --no-progress --memory-limit=1G` | `.github/workflows/ci.yml` paso "Análisis estático (Larastan)" |
| Comando de cierre de `tasks.md` 6.2 sin `--memory-limit=1G` | texto fuera del alcance de devops; la corrida real (cierre 2) ya lo usó | sección 4, cierre 2 |
| `api-tools` apuntaba a la base de desarrollo | saldado: `DB_DATABASE: dispensart_test` por defecto; `api` conserva `dispensart` | `software/compose.yaml` servicio `api-tools`; `docker compose config` resuelto; fila "aislamiento" de 4 |
| Riesgo 3 del design: el rol de base es superusuario y dueño, puede deshabilitar o borrar el trigger del kardex | riesgo aceptado, sin fila de deuda; se documenta en el README de S8 | `KardexIntegrityTest.php:84` cubre `session_replication_role`, no `DISABLE TRIGGER` por un superusuario |
| Tipos de la SPA desactualizados frente a `openapi.json` de S2 | abierto: `software/web/src/lib/api-schema.ts` sin regenerar tras `83d0860`; falla `api:types:check` | CI runs `37732220017` y `37732568878`, trabajo frontend |
