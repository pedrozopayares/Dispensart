# Journal — add-stock-and-kardex (S2)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv
- Fase: /proposal adelantada mientras S0 espera el reinicio de sesión. Apply espera el archivo de S1.
- Tier objetivo según ROADMAP: A.

## 2026-10-07 — spec-engineer: proposal, deltas, tasks borrador

Producido: `proposal.md`; deltas `specs/inventory` y `specs/kardex` (capacidades nuevas, `## Purpose` incluido,
sin MODIFIED sobre S1); `tasks.md` borrador (6 grupos, `[MUT]` declarados M1–M10). Alineado con
`add-catalog-and-identity/design.md`: capacidades `inventory.view`/`inventory.adjust` de D4 sin tocar el mapa,
forma de rechazo D5 ampliada con 409 `insufficient_stock` y 422 `lot_expired`, `BusinessCalendar` y
`Lot::isExpiredOn` de D6, seeders por clave natural de D11, envoltura `{data}`, `SpaClient` para el 419.
`openspec validate add-stock-and-kardex --strict`: válido. Ancla de transporte barrida con `/usr/bin/grep`:
todo hit lleva `[ancla: …]`; control positivo con una línea sin ancla en el scratchpad, detectada. Cobertura
escenario → tarea barrida: todo escenario citado; control positivo con un escenario inventado, detectado.

Supuestos (9, en `proposal.md` § Assumptions): literales de RN-06 como tipos; unidades enteras; `entrada`
solo por siembra, sin alta de lotes; ajuste positivo a lote vencido rechazado y negativo permitido; ajuste
positivo crea la existencia faltante; ajustes sin `Idempotency-Key`; RN-05 no aplica a ajustes; stock sin
paginar y solo cantidades > 0, kardex paginado; movimientos de siembra con usuario nulo.

Preguntas abiertas para el architect:
1. Aislamiento de las pruebas de carrera (3.3): necesitan filas confirmadas en dos conexiones, y el trigger
   rechaza `TRUNCATE`; elegir entre `DatabaseMigrations`/`migrate:fresh` por prueba u otra vía, sin abrir una
   puerta de vaciado en producción.
2. Mecanismo de la coherencia producto-lote (1.1): FK compuesta a `lots(id, product_id)` exige un índice único
   nuevo sobre `lots`, tabla de S1.
3. Si la base debe impedir también un `UPDATE` directo de la cantidad sin movimiento (trigger de restricción
   diferido); hoy la regla "un movimiento por cambio" se defiende en el servicio único y con M3.
4. Barrera y mecanismo de concurrencia de 3.3 (procesos, `pcntl_fork` o pool de `Process`; bloqueo de
   asesoramiento como barrera).

Candidatos de deuda (el Orchestrator asigna id): endpoint de entrada de mercancía con alta de lotes;
`Idempotency-Key` en ajustes reutilizando el mecanismo de S3; decidir si RN-05 alcanza a ajustes de
productos controlados.

## 2026-10-07 — architect: design.md y refinamiento de tasks

Producido: `design.md` (D1–D10, contrato de 3 endpoints, 3 migraciones reversibles, 3 riesgos); `tasks.md`
reordenado (la carrera pasa a 5.4/5.5, después del endpoint, porque recorre el kernel HTTP real). Escenarios y specs
sin cambios. M1–M10 conservan sus ids.

Respuestas a las preguntas abiertas:
1. Aislamiento (D7): el trigger conserva `TRUNCATE`. `RefreshDatabase` no se afecta (`migrate:fresh` hace `DROP`;
   cada prueba termina en `ROLLBACK`). Carrera con `DatabaseMigrations` + filas dedicadas por iteración; el
   `rollback` de salida ejerce todos los `down()`. `DatabaseTruncation` prohibido. Rechazados:
   `session_replication_role` (además anulado por `ENABLE ALWAYS`), `DISABLE TRIGGER` en limpieza, filas sin limpiar.
2. Coherencia producto-lote (D3): S2 agrega `lots_id_product_id_unique` con migración propia y `down()`; no edita S1.
   FK compuesta en `stocks`. El kardex referencia la existencia por FK compuesta (D4).
3. Trigger diferido cantidad = saldo (D6): no. Sin escenario, no dispara bajo `RefreshDatabase`; defensa en
   `StockLedger` + M3/M5. Revisar si aparece otro escritor de `stocks`.
4. Mecanismo (D8): procesos con `Process::start()` + worker por kernel HTTP; barrera `LOCK TABLE kardex_movements
   IN SHARE MODE` liberada al ver N esperas en `pg_stat_activity`. M4 y M10 fallan de forma determinista, no
   probabilística. Rechazados: `pcntl_fork`, `Concurrency::run()`, barrera de bloqueo de asesoramiento.

Costura para S3/S4 (D2): `StockLedger::apply(list<StockChange>)`, clave global de bloqueo
`(lots.expires_on, lots.id, warehouse_id)` vía `Stock::scopeInLockOrder`; S3 bloquea candidatos FEFO con ese scope y
escribe por `apply()` dentro de su transacción. La regla de lote vencido vive en cada llamador, no en el libro.

Candidatos de deuda — decisión:
- Entrada de mercancía con alta de lotes: fuera, sin fila de deuda (ningún criterio calificado la pide).
- `Idempotency-Key` en ajustes: fuera, sin deuda; revisar si el mecanismo de S3 queda genérico.
- RN-05 en ajustes de controlados: fuera; RN-05 rige la dispensación y el ajuste ya exige regente.
- **Nuevo, señalado**: rol de base de la app = superusuario y dueño; puede deshabilitar el trigger por DDL
  (riesgo 3). Candidato de deuda menor para S8: rol de ejecución sin propiedad ni superusuario.

## 2026-10-07 — spec-engineer: hallazgos del spec-validator

- tasks 5.7, 6.2, 6.3 marcadas "Cimiento" con comando de verificación; orden del architect intacto.
- inventory "Existencias semilla": escenario negativo "Existencia previa no sembrada rechazada por la siembra"
  (la siembra no toma una bodega + lote ya existente; coherente con la idempotencia por bodega + lote del
  design), citado en 3.4. `openspec validate --strict`: válido.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-stock-and-kardex` tras correcciones de prosa (citas de tareas, escenario
  negativo de siembra). Ancla de transporte: 36 hits, 36 con ancla.
- Condición 1 (alcance exacto de S2): OK. Existencias bodega+producto+lote, kardex solo inserción,
  restricciones en DB, ajustes; consulta de inventario y kardex solo API (pantallas en S6).
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): A, coincide con ROADMAP.
- Condición 4 (ADR / RN): ninguna decisión congelada ni regla debilitada.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier A
- Riesgo del architect: el usuario de base de la app es superusuario y dueño de las tablas, puede
  deshabilitar el trigger del kardex. Se decide al auditar S2 si se fila como deuda (arreglo natural:
  rol de aplicación sin privilegios en la imagen de base de datos).

## 2026-10-08 — Orchestrator: /apply adelantado

- `openspec validate add-stock-and-kardex --strict`: válido. GATE 1 registrado.
- S1 tiene el bloque api cerrado (Pest 166 verdes, M1–M11) y solo le faltan web, devops y auditoría; S2 es
  solo backend sobre tablas de S1 ya construidas. Se adelanta el apply de S2 en paralelo (instrucción del
  usuario de usar hasta cuatro hilos). Riesgo: hallazgos de la auditoría de S1 que toquen modelos o
  migraciones; mitigación: el backend de S2 no modifica archivos de S1 (salvo la migración propia del único
  `lots_id_product_id_unique`, D3).
- Línea base: la del cierre backend de S1 (Pest 166 + 2 de proxy, verdes).

## 2026-10-08 — backend-implementer: grupos 1–5 y 6.2 (`software/api`)

Tareas 1.1–5.7 y 6.2 `[x]`. Pendientes: 6.1 (stack desde cero con `down -v`: destruye el stack compartido mientras
web/devops de S1 trabajan en paralelo; lo corre el Orchestrator al cerrar S1) y 6.3 (`verification.md`, registro de
cierre del Orchestrator; las tablas están aquí). Commits en `dev`: `855235e`, `880386a`, `dc44de1`, `3f9bd90`,
`5c0a0fb`, `83d0860`. Sin dependencias nuevas.

### Corridas

| # | Comando | Resultado |
|---|---|---|
| delta | `php artisan test tests/Feature/Inventory tests/Feature/Kardex` | 125 pasan / 501 aserciones |
| cierre 1 | `pint --test && phpstan analyse && php artisan test` | Pint pasa; Larastan abortó: worker paralelo llegó al límite de 128M de PHP (sin hallazgos; Pest no corrió) |
| cierre 2 | `pint --test && phpstan analyse --memory-limit=1G && php artisan test` | Pint pasa (161 archivos); Larastan 0 errores; Pest **293 pasan / 1201 aserciones** (línea base 168) |
| OpenAPI | `composer openapi` ×2 + `cmp`; `npm run openapi:lint` | idéntico entre corridas; Redocly válido |

Incidente: la primera prueba de migraciones corrió `migrate:fresh --seed` sobre la base de desarrollo `dispensart`
(el `api-tools` apunta a ella por defecto) y `run` recreó el contenedor `db` por cambios de compose sin commit de
devops. Mitigado: `migrate:rollback --step=3` sobre `dispensart` (esquema S1, datos semilla re-sembrados), stack
`healthy`. Desde ahí: `--no-deps` siempre y solo `dispensart_test`; el worker de carrera rechaza toda base que no
empiece por `dispensart_test`.

### Líneas (§ 0)

| Tipo | Líneas |
|---|---|
| Producto (`app`, `database`, `routes`, `bootstrap`, `lang`): 27 archivos nuevos + 50 líneas en archivos de S1 | 1247 |
| Pruebas (`tests`: 13 archivos de prueba, ayudas, `RaceRunner`, worker) | 1518 |
| Contrato generado (`openapi.json`) | 602 |

### Escenario → prueba → archivo:línea (rutas relativas a `software/api/tests/Feature/`)

| Capacidad | Escenario | Prueba | Archivo:línea |
|---|---|---|---|
| inventory | Existencia válida | guarda una existencia válida con el producto de su lote y cantidad 0 | Inventory/StockIntegrityTest.php:29 |
| inventory | Cantidad negativa rechazada por la base | rechaza en la base una cantidad negativa y conserva la anterior | Inventory/StockIntegrityTest.php:38 |
| inventory | Existencia duplicada | rechaza una segunda existencia para la misma bodega, producto y lote | Inventory/StockIntegrityTest.php:49 |
| inventory | Producto que no corresponde al lote | rechaza una existencia cuyo producto no es el de su lote | Inventory/StockIntegrityTest.php:61 |
| inventory | Borrar bodega o lote con existencias | rechaza borrar una bodega … · rechaza borrar un lote … | Inventory/StockIntegrityTest.php:74, :85 |
| inventory | Roles con lectura de inventario | devuelve a los roles con lectura las mismas existencias en el orden contractual (dataset 3) | Inventory/StockEndpointTest.php:28 |
| inventory | Filtros combinados | combina los filtros de bodega y producto con Y | Inventory/StockEndpointTest.php:53 |
| inventory | Lote vencido con existencia visible | muestra la existencia de un lote vencido con is_expired verdadero | Inventory/StockEndpointTest.php:69 |
| inventory | Existencia agotada omitida | omite una existencia que quedó en 0 tras un ajuste | Inventory/StockEndpointTest.php:79 |
| inventory | Filtro sin resultados | devuelve una lista vacía para un filtro sin resultados | Inventory/StockEndpointTest.php:89 |
| inventory | Filtro mal formado | rechaza un filtro mal formado | Inventory/StockEndpointTest.php:95 |
| inventory | Roles sin lectura de inventario (existencias) | rechaza con 403 a los roles sin lectura (dataset 2) · Policy por rol (dataset 5) | Inventory/StockEndpointTest.php:102; Inventory/InventoryPolicyTest.php:14 |
| inventory | Sin sesión (existencias) | responde 401 sin sesión | Inventory/StockEndpointTest.php:111 |
| inventory | Ajuste negativo exitoso | aplica un ajuste negativo y escribe exactamente un movimiento | Inventory/StockAdjustmentEndpointTest.php:33 |
| inventory | Ajuste positivo sobre existencia inexistente | crea la existencia con un ajuste positivo cuando no existía | Inventory/StockAdjustmentEndpointTest.php:52 |
| inventory | Campos del servidor ignorados | ignora usuario, saldo, fecha y tipo enviados por el cliente · (acción) fija tipo ajuste, el usuario autenticado… | Inventory/StockAdjustmentEndpointTest.php:65; Inventory/AdjustStockTest.php:54 |
| inventory | Datos inválidos o incompletos | rechaza datos inválidos o incompletos por campo (dataset 10) | Inventory/StockAdjustmentEndpointTest.php:178 |
| inventory | Otro rol intenta ajustar | rechaza con 403 a los demás roles (dataset 4) · Policy por rol | Inventory/StockAdjustmentEndpointTest.php:200; Inventory/InventoryPolicyTest.php:14 |
| inventory | Sin sesión (ajuste) | responde 401 sin sesión | Inventory/StockAdjustmentEndpointTest.php:216 |
| inventory | Sin token CSRF desde la SPA | rechaza con 419 un ajuste desde la SPA sin X-XSRF-TOKEN y lo acepta con él | Inventory/StockAdjustmentEndpointTest.php:226 |
| inventory | Reintento del mismo ajuste | aplica dos veces el mismo ajuste repetido (HTTP · acción) | Inventory/StockAdjustmentEndpointTest.php:107; Inventory/AdjustStockTest.php:66 |
| inventory | Ajuste mayor que la existencia | rechaza con 409 un ajuste mayor que la existencia, sin efecto · (libro) rechaza un cambio mayor… | Inventory/StockAdjustmentEndpointTest.php:120; Inventory/StockLedgerTest.php:75 |
| inventory | Ajuste negativo sin existencia previa | rechaza con 409 un ajuste negativo sin existencia previa y no la crea · (libro) | Inventory/StockAdjustmentEndpointTest.php:130; Inventory/StockLedgerTest.php:96 |
| inventory | Ajuste que deja exactamente cero | deja la existencia exactamente en cero (HTTP · libro) | Inventory/StockAdjustmentEndpointTest.php:81; Inventory/StockLedgerTest.php:66 |
| inventory | Carrera por la última unidad | serializa la carrera por la última unidad: un 201, un 409 y nunca negativo, 10 de 10 | Inventory/StockAdjustmentRaceTest.php:24 |
| inventory | Ajustes positivos simultáneos sobre existencia inexistente | acumula ajustes positivos simultáneos … en una sola fila, 10 de 10 | Inventory/StockAdjustmentRaceTest.php:51 |
| inventory | Baja de existencia vencida | permite dar de baja la existencia de un lote vencido (HTTP · acción) | Inventory/StockAdjustmentEndpointTest.php:148; Inventory/AdjustStockTest.php:37 |
| inventory | Ingreso a lote vencido rechazado | rechaza con 422 un ingreso a un lote que vence hoy en Bogotá (HTTP · acción) | Inventory/StockAdjustmentEndpointTest.php:158; Inventory/AdjustStockTest.php:27 |
| inventory | Ingreso a lote que vence mañana | acepta un ingreso a un lote que vence mañana (HTTP · acción) | Inventory/StockAdjustmentEndpointTest.php:168; Inventory/AdjustStockTest.php:46 |
| inventory | Siembra inicial | siembra existencias en las 3 bodegas con lote vencido, controlado vigente y 2 lotes vigentes en una bodega | Inventory/StockSeedTest.php:21 |
| inventory | Un movimiento de entrada por existencia sembrada | escribe un único movimiento entrada por existencia sembrada … | Inventory/StockSeedTest.php:37 |
| inventory | Siembra repetida | repite la siembra sin crear existencias ni movimientos ni cambiar cantidades | Inventory/StockSeedTest.php:52 |
| inventory | Ajustes sobreviven a la resiembra | conserva un ajuste del regente tras resembrar, sin segunda entrada (HTTP) | Inventory/StockSeedTest.php:63 |
| inventory | Existencia previa no sembrada rechazada por la siembra | no toma una existencia ya creada por un ajuste antes de la siembra | Inventory/StockSeedTest.php:75 |
| kardex | Inserción aceptada | acepta la inserción directa de un movimiento válido con fecha puesta por la base | Kardex/KardexIntegrityTest.php:36 |
| kardex | Edición rechazada por la base | rechaza en la base editar un movimiento y la fila no cambia (cantidad, motivo) · sigue rechazando con session_replication_role = replica | Kardex/KardexIntegrityTest.php:46, :84 |
| kardex | Borrado rechazado por la base | rechaza en la base borrar un movimiento y la fila permanece | Kardex/KardexIntegrityTest.php:61 |
| kardex | Vaciado rechazado por la base | rechaza en la base vaciar la tabla y las filas permanecen | Kardex/KardexIntegrityTest.php:72 |
| kardex | Sin ruta de edición ni borrado | no tiene ruta para editar ni borrar un movimiento (PATCH, DELETE) | Kardex/KardexEndpointTest.php:148 |
| kardex | Tipos futuros ya admitidos | admite ya los tipos de traslado con su signo · (dominio) declara los 5 tipos; signo por tipo | Kardex/KardexIntegrityTest.php:100; Kardex/MovementTypeTest.php:8, :13 |
| kardex | Tipo fuera del conjunto | rechaza un tipo fuera del conjunto | Kardex/KardexIntegrityTest.php:109 |
| kardex | Signo contrario al tipo | rechaza un signo contrario al tipo o una cantidad 0 (dataset 6) · (dominio) dataset 12 + StockChange | Kardex/KardexIntegrityTest.php:120; Kardex/MovementTypeTest.php:13, :30 |
| kardex | Saldo resultante negativo | rechaza un saldo resultante negativo | Kardex/KardexIntegrityTest.php:138 |
| kardex | Ajuste sin usuario o sin motivo | rechaza un ajuste sin usuario o sin motivo (dataset 3) | Kardex/KardexIntegrityTest.php:149 |
| kardex | (D4) movimiento sin existencia | rechaza un movimiento sin existencia que lo respalde | Kardex/KardexIntegrityTest.php:164 |
| kardex | Ajuste escribe un solo movimiento | aplica un ajuste negativo y escribe exactamente un movimiento · (libro) | Inventory/StockAdjustmentEndpointTest.php:33; Inventory/StockLedgerTest.php:26 |
| kardex | Cadena de saldos tras varios cambios | encadena los saldos tras +4, -2 y -1 (HTTP · libro) · varios cambios en una llamada | Inventory/StockAdjustmentEndpointTest.php:91; Inventory/StockLedgerTest.php:39, :54 |
| kardex | Ajuste rechazado no deja movimiento | `expectUntouched` en 403, 401, 419, 422 (validación, lote vencido) y 409 · (libro) valida todo antes de escribir | Inventory/StockAdjustmentEndpointTest.php:120, :158, :178, :200, :216, :226; Inventory/StockLedgerTest.php:84 |
| kardex | Fallo al escribir el movimiento revierte la existencia | revierte la existencia si falla la escritura del movimiento después de actualizarla | Inventory/StockLedgerTest.php:122 |
| kardex | Roles con lectura consultan el kardex | devuelve a los roles con lectura los movimientos … paginados (dataset 3) · ordena por fecha antes que por id | Kardex/KardexEndpointTest.php:28, :54 |
| kardex | Filtro por producto, lote y bodega | filtra por bodega, producto y lote combinados | Kardex/KardexEndpointTest.php:73 |
| kardex | Movimiento de ajuste con usuario y motivo | muestra en el ajuste el nombre del regente y su motivo | Kardex/KardexEndpointTest.php:92 |
| kardex | Movimiento de siembra sin usuario | muestra un único movimiento entrada sin usuario para una existencia semilla | Kardex/KardexEndpointTest.php:105 |
| kardex | Página fuera de rango o filtro sin resultados | devuelve data vacío (dataset 2) | Kardex/KardexEndpointTest.php:119 |
| kardex | Parámetros mal formados | rechaza parámetros mal formados (dataset 2) | Kardex/KardexEndpointTest.php:125 |
| kardex | Roles sin lectura de inventario | rechaza con 403 a los roles sin lectura (dataset 2) · Policy por rol | Kardex/KardexEndpointTest.php:135; Inventory/InventoryPolicyTest.php:14 |
| kardex | Sin sesión | responde 401 sin sesión | Kardex/KardexEndpointTest.php:144 |
| — (4.1, D10) | render 409/422 y CHECK como 500 | mapea cada rechazo de inventario … · trata una violación de restricción como defecto | Inventory/InventoryErrorTest.php:10, :21 |
| — (1.4) | Fábricas válidas | Factory de existencia (con entrada · en cero) | Inventory/StockFactoryTest.php:11, :22 |
| — (D2) | Clave global de bloqueo | ordena las existencias por la clave global de bloqueo | Inventory/StockLedgerTest.php:133 |

### [MUT] (corridas delta filtradas; script con respaldo y restauración, `git status` limpio al final)

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

### Anclas de transporte (cláusula → archivo:línea, `software/api/`)

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

### Barridos (`/usr/bin/grep`, control positivo plantado en el scratchpad)

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

### Desviaciones del design (sin cambio de contrato)

| Punto | Design | Aplicado | Motivo |
|---|---|---|---|
| D2 orden | `scopeInLockOrder` en el libro | el libro ordena las claves en PHP con la misma tupla `(expires_on, lot id, warehouse_id)` y bloquea clave por clave; `scopeInLockOrder` queda para S3 (probado en StockLedgerTest.php:133) | crear la fila faltante y bloquearla debe intercalarse por clave (D2 paso 2) |
| Data impact | `created_at` por la base | `KardexMovement::refresh()` tras insertar para devolver la fecha de la base | la respuesta 201 lleva la fecha del servidor |
| D9 reglas | `exists` en ids | `bail` antes de `integer` + `exists` | sin `bail`, `warehouse_id=abc` llegaba a la consulta `exists` y PostgreSQL rechazaba el texto en `bigint` (500) |
| Recurso | `type` string | `type` devuelto como enum `MovementType` | mismo JSON; el OpenAPI documenta los 5 literales (`components.schemas.MovementType`) |
| OpenAPI | — | transformador de S1 ampliado: 409 `insufficient_stock` y 422 con `lot_expired` solo en `POST /stock-adjustments` | Scramble no infiere excepciones propias |
| Migración | `CREATE FUNCTION` | `CREATE OR REPLACE FUNCTION` | `migrate:fresh` borra tablas, no funciones: la segunda migración fallaba |

### Para S3/S4

- `StockLedger::apply(list<StockChange>)` acepta varios cambios y varias existencias; dentro de una transacción del
  llamador queda como punto de guardado. `StockChange` rechaza un signo contrario al tipo (`InvalidArgumentException`).
- FEFO de S3: `Stock::query()->inLockOrder()` (alias `lock_lots`) + filtros + `lockForUpdate()`.
- `RaceRunner::postAdjustments` es específico de ajustes; S3 necesitará la misma barrera con su propio endpoint
  (generalizar la ruta del worker es trivial).

### Deuda (en prosa; el Orchestrator asigna id)

- Larastan con la configuración actual aborta por el límite de memoria de 128M de PHP en sus workers paralelos
  (`phpstan.neon` sin `memory_limit` ni imagen `dev` con más memoria); el comando de cierre de `tasks.md` y el de CI
  fallarían igual. Arreglo: `--memory-limit` en el comando o `memory_limit` en el php.ini de la imagen `dev` (devops).
- El servicio `api-tools` apunta por defecto a la base de desarrollo `dispensart`; un `artisan migrate:fresh` dentro
  de él la borra (pasó una vez en este apply). Sugerencia devops: `DB_DATABASE=dispensart_test` por defecto en
  `api-tools`, o documentarlo.
- Riesgo 3 del design sigue abierto (rol de base dueño y superusuario puede `DISABLE`/`DROP TRIGGER`), sin cambio.
