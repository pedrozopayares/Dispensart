# Verification — add-alerts (S5, tier A)

Fuente: sección backend-implementer de `journal.md`. Árbol medido: `dev` en `6bdf70f`. Prefijo `ALR` (capacidad
`inventory-alerts`). Rutas de prueba relativas a `software/api/tests/Feature/Alerts/` salvo indicación; de código, a
`software/api/`. Ayudas compartidas: `tests/Helpers/Alerts.php` (abreviado `Helpers`).

## 0. Reparto de líneas

Líneas añadidas por S5 desde GATE 1. Comando: `git diff --numstat f8b6e5f 6bdf70f -- <alcance> | awk -F'\t' '$1!="-"{a+=$1} END{print a}'`.

| Categoría | Alcance | Líneas |
|---|---|---|
| Producto — API | `software/api/{app,database,routes,bootstrap,lang}` | 429 |
| Producto — SPA | `software/web` salvo `api-schema.ts` | 0 |
| Producto — infraestructura | `software/compose.yaml`, `software/docker`, `.github` | 0 |
| Producto — infraestructura (6.1, posterior a `6bdf70f`) | `software/docker/smoke/alerts-smoke.sh` (`wc -l`) | 115 |
| Prueba — API | `software/api/tests` (incluye el arreglo de `Transfers/TransferRaceTest.php`) | 796 |
| Generado | `software/api/openapi.json` | 148 |
| Generado | `software/web/src/lib/api-schema.ts` | 91 |
| Registro | `openspec/changes/add-alerts/**/*.md` antes de este archivo (`wc -l`) | 643 |
| Registro — parches `[MUT]` | `openspec/changes/add-alerts/mutants/*.patch` (`wc -l`; 23 archivos) | 335 |

## 1. Matriz escenario → prueba → archivo:línea

| Capacidad | Escenarios en la spec | Escenarios con prueba |
|---|---|---|
| inventory-alerts | 30 | 30 |

Los escenarios de vencimiento y stock bajo tienen datos y afirmación en `Helpers` (una función por título). Los
recorren la prueba de consulta y una fila del dataset por la ruta, con el rol del WHEN.

| Id | Escenario | Prueba | Archivo:línea |
|---|---|---|---|
| ALR-01 | Mínimo válido | Mínimo válido | StockMinimumIntegrityTest.php:27 |
| ALR-02 | Mínimo cero o negativo rechazado por la base | Mínimo cero o negativo rechazado por la base (dataset `cero`, `negativo`) | StockMinimumIntegrityTest.php:37 |
| ALR-03 | Mínimo duplicado | Mínimo duplicado | StockMinimumIntegrityTest.php:49 |
| ALR-04 | Bodega o producto inexistente | Bodega o producto inexistente (dataset `bodega`, `producto`) | StockMinimumIntegrityTest.php:60 |
| ALR-05 | Borrar bodega o producto con mínimo | Borrar bodega o producto con mínimo (dataset `bodega`, `producto`) | StockMinimumIntegrityTest.php:72 |
| ALR-06 | Siembra inicial de mínimos | Siembra inicial de mínimos | StockMinimumSeedTest.php:26 |
| ALR-07 | Siembra repetida | Siembra repetida | StockMinimumSeedTest.php:53 |
| ALR-08 | Mínimo cambiado a mano sobrevive a la resiembra | Mínimo cambiado a mano sobrevive a la resiembra | StockMinimumSeedTest.php:63 |
| ALR-09 | Roles con lectura de inventario | Roles con lectura de inventario (3 roles, mismo cuerpo, forma completa del contrato) | AlertEndpointTest.php:17 |
| ALR-10 | Filtro por bodega | Filtro por bodega | AlertEndpointTest.php:63 |
| ALR-11 | Sin alertas | Sin alertas | AlertEndpointTest.php:85 |
| ALR-12 | Bodega inexistente | Bodega inexistente | AlertEndpointTest.php:96 |
| ALR-13 | Filtro mal formado | Filtro mal formado (dataset `abc`, `cero`) | AlertEndpointTest.php:109 |
| ALR-14 | Roles sin lectura de inventario | Roles sin lectura de inventario (dataset `medico`, `admin`) | AlertEndpointTest.php:116 |
| ALR-15 | Sin sesión | Sin sesión | AlertEndpointTest.php:125 |
| ALR-16 | Lote que vence en 90 días incluido | consulta · ruta (regente) | ExpiringLotsQueryTest.php:24; AlertEndpointTest.php:137; Helpers:172 |
| ALR-17 | Lote que vence en 91 días excluido | consulta · ruta (regente) | ExpiringLotsQueryTest.php:26; AlertEndpointTest.php:138; Helpers:179 |
| ALR-18 | Lote ya vencido con existencia | consulta · ruta (auditor) | ExpiringLotsQueryTest.php:28; AlertEndpointTest.php:139; Helpers:186 |
| ALR-19 | Lote próximo a vencer sin existencia | consulta · ruta (auxiliar); existencia llevada a 0 por `AdjustStock` | ExpiringLotsQueryTest.php:30; AlertEndpointTest.php:140; Helpers:197 |
| ALR-20 | Mismo lote en dos bodegas | consulta · ruta (auditor) | ExpiringLotsQueryTest.php:32; AlertEndpointTest.php:141; Helpers:211 |
| ALR-21 | Orden por vencimiento | consulta · ruta (regente); lotes insertados 60, 5, 30 | ExpiringLotsQueryTest.php:34; AlertEndpointTest.php:142; Helpers:223 |
| ALR-22 | Frontera del día en hora de Bogotá | consulta · ruta (regente); reloj 2027-03-15 04:30 UTC | ExpiringLotsQueryTest.php:36; AlertEndpointTest.php:143; Helpers:234 |
| ALR-23 | Producto bajo su mínimo | consulta · ruta (regente) | LowStockQueryTest.php:25; AlertEndpointTest.php:153; Helpers:260 |
| ALR-24 | Existencia igual al mínimo | consulta · ruta (regente) | LowStockQueryTest.php:27; AlertEndpointTest.php:154; Helpers:268 |
| ALR-25 | Suma de varios lotes cubre el mínimo | consulta · ruta (auditor) | LowStockQueryTest.php:29; AlertEndpointTest.php:155; Helpers:282 |
| ALR-26 | Sin existencias con mínimo definido | consulta · ruta (auxiliar) | LowStockQueryTest.php:31; AlertEndpointTest.php:156; Helpers:297 |
| ALR-27 | Existencia vencida no cuenta | consulta · ruta (regente) | LowStockQueryTest.php:33; AlertEndpointTest.php:157; Helpers:305 |
| ALR-28 | Mínimo propio de cada bodega | consulta · ruta (auditor) | LowStockQueryTest.php:35; AlertEndpointTest.php:158; Helpers:314 |
| ALR-29 | Producto sin mínimo | consulta · ruta (regente) | LowStockQueryTest.php:37; AlertEndpointTest.php:159; Helpers:327 |
| ALR-30 | Unidades en tránsito no cuentan hasta la recepción | consulta · ruta (regente); despacho y recepción por HTTP real de S4 | LowStockQueryTest.php:39; AlertEndpointTest.php:160; Helpers:338 |

| Prueba de apoyo (no es escenario) | Archivo:línea |
|---|---|
| control 89 días (control positivo de M1 y M9) | ExpiringLotsQueryTest.php:39 |
| el filtro de bodega deja solo sus existencias | ExpiringLotsQueryTest.php:48 |
| el filtro de bodega deja solo sus pares | LowStockQueryTest.php:41 |

## 2. Ancla de transporte: cláusula → ruta archivo:línea

| Ancla de la spec | Código archivo:línea |
|---|---|
| ruta `GET /api/alerts` | routes/api.php:58; app/Http/Controllers/Inventory/AlertController.php:16 |
| FormRequest de consulta de alertas (422) | app/Http/Requests/Inventory/ListAlertsRequest.php:26 (`sometimes`, `integer`, `min:1`); app/Exceptions/ApiExceptionRenderer.php:24 |
| Policy de alertas (403) | app/Policies/StockPolicy.php:14 (`viewAny`, inventory.view), invocada desde app/Http/Requests/Inventory/ListAlertsRequest.php:17 (design D5); app/Exceptions/ApiExceptionRenderer.php:57 |
| middleware `auth:sanctum` (401) | routes/api.php:36; app/Exceptions/ApiExceptionRenderer.php:23 |
| consulta de vencimiento | app/Queries/AlertQuery.php:33 (`expiringLots`) |
| consulta de stock bajo | app/Queries/AlertQuery.php:60 (`lowStock`) |
| restricciones de `stock_minimums` | database/migrations/2026_10_11_000001_create_stock_minimums_table.php:21, :24 (RESTRICT), :28 (único), :33 (CHECK) |
| siembra de mínimos | database/seeders/StockMinimumSeeder.php:40 (`firstOrCreate`); database/seeders/DatabaseSeeder.php:23 |

## 3. [MUT]

Arnés de la cabecera de `tasks.md`, desde la raíz del repo. `pest()` invoca
`docker compose -f software/compose.yaml --profile tools run --rm --no-deps api-tools vendor/bin/pest … --fail-on-empty-test-suite`
(forma de invocación indicada por el Orchestrator). Cada fila corrió como `mut <Mn> <archivo> '<filtro>'`: aplica,
exige FALLA, revierte, exige PASA y `git diff --quiet -- software/api`. Salida de cada cadena y autocontrol del
arnés (`! pest AlertEndpointTest.php 'no-existe-zzz'`, «No tests found»): tabla de corridas `[MUT]` al final de esta
sección.

| n | Mutación (parche `mutants/M<n>.patch`) | Aplicada → FALLA m/k: prueba | Restaurada → PASA k/k |
|---|---|---|---|
| M1 | AlertQuery.php:44 `<=` → `<` contra el horizonte | 1/1: Lote que vence en 90 días incluido (lista vacía) | 1/1 |
| M2 | AlertQuery.php:35 `BusinessCalendar::today()` → `now('UTC')->toImmutable()->startOfDay()` | 1/1: Frontera del día en hora de Bogotá (el día 91 de Bogotá entra) | 1/1 |
| M3 | AlertQuery.php:82 `<` → `<=` contra el mínimo | 1/1: Existencia igual al mínimo (el par de 10/10 aparece) | 1/1 |
| M4 | AlertQuery.php:70 sin el predicado de no vencido en la subconsulta | 1/1: Existencia vencida no cuenta (disponible 24, no alerta) | 1/1 |
| M5 | AlertQuery.php:78 `leftJoinSub` → `joinSub` | 1/1: Sin existencias con mínimo definido (par ausente) | 1/1 |
| M6 | migración :33 sin `stock_minimums_minimum_quantity_positive` | 2/2: Mínimo cero o negativo rechazado por la base (`cero`, `negativo` aceptados) | 2/2 |
| M7 | AlertQuery.php:66–79 subconsulta agrupada y unida solo por producto | 1/1: Mínimo propio de cada bodega (Central suma 53, sale) | 1/1 |
| M8 | migración :28 sin `stock_minimums_warehouse_product_unique` | 1/1: Mínimo duplicado (segunda fila aceptada) | 1/1 |
| M9 | AlertQuery.php:36 horizonte `+ EXPIRY_WINDOW_DAYS + 1` | 1/1: Lote que vence en 91 días excluido (aparece) | 1/1 |
| M10 | AlertQuery.php:44 límite inferior `lots.expires_on > :today` | 1/1: Lote ya vencido con existencia (ambos ausentes) | 1/1 |
| M11 | AlertQuery.php:70 `>` → `>=` (el lote que vence hoy cuenta) | 1/1: Existencia vencida no cuenta (disponible 24) | 1/1 |
| M12 | AlertQuery.php:25 disponible + líneas `EN_TRANSITO` del destino | 1/1: Unidades en tránsito no cuentan hasta la recepción (Urgencias 11, ausente en la 1.ª consulta) | 1/1 |
| M13 | AlertQuery.php:25 disponible − líneas `EN_TRANSITO` del origen | 1/1: Unidades en tránsito no cuentan hasta la recepción (Central 2, no 7) | 1/1 |
| M14 | AlertQuery.php:43 sin `stocks.quantity > 0` | 1/1: Lote próximo a vencer sin existencia (fila de cantidad 0 listada) | 1/1 |
| M15 | migración :21, :24 `restrictOnDelete()` → `cascadeOnDelete()` | 2/2: Borrar bodega o producto con mínimo (`bodega`, `producto` borrados) | 2/2 |
| M16 | AlertQuery.php:44 horizonte `CURRENT_DATE + 90` de la base | 1/1: Lote que vence en 90 días incluido (reloj en 2027, ausente) | 1/1 |
| M17 | StockMinimumSeeder.php:40 `firstOrCreate` → `updateOrCreate` | 1/1: Mínimo cambiado a mano sobrevive a la resiembra (20, no 7) | 1/1 |
| M18 | StockMinimumSeeder.php:40 `create` sin búsqueda previa | 1/1: Siembra repetida (`UniqueConstraintViolationException`) | 1/1 |
| M19 | ListAlertsRequest.php:17 `authorize()` devuelve `true` | 2/2: Roles sin lectura de inventario (`medico`, `admin` → 200) | 2/2 |
| M20 | AlertQuery.php:83 `lowStock` ignora `warehouse_id` | 1/1: Filtro por bodega (`low_stock` con 2 filas, no 1) | 1/1 |
| M21 | AlertQuery.php:47 sin `ORDER BY lots.expires_on` | 1/1: Orden por vencimiento (60, 5, 30) | 1/1 |
| M22 | ListAlertsRequest.php:26 sin `min:1` | 1/2: Filtro mal formado (`cero` → 200; `abc` sigue 422) | 2/2 |
| M23 | AlertQuery.php:74–83 `warehouses CROSS JOIN products` + `LEFT JOIN stock_minimums` + `COALESCE(minimum_quantity, 1)` | 1/1: Producto sin mínimo (par sin mínimo listado con mínimo 1) | 1/1 |

Declarados: 23 (M1–M23); entregados: 23.

| Control positivo (`ctl`) | Filtro | Resultado con el mutante aplicado |
|---|---|---|
| ctl M6, M8, M15 | `Mínimo válido` | PASA 1/1 en cada uno |
| ctl M1, M9 | `control 89 días` | PASA 1/1 en cada uno |
| ctl M3, M4, M5, M7, M11, M12, M13, M23 | `Producto bajo su mínimo` | PASA 1/1 en cada uno |
| ctl M22 | `Filtro mal formado.*abc` | PASA 1/1 |

| Corrida `[MUT]` | Árbol | Cadena | Salida |
|---|---|---|---|
| 1.3 | `ff4c137` | `mut M6 … && mut M8 … && mut M15 … && ctl M6/M8/M15 'Mínimo válido'` | 0 |
| 2.2 | `c208a5b` | `mut M17 … && mut M18 …` | 0 |
| 3.2 | `c208a5b` | `mut M1, M9, M2, M16, M10, M14, M21 && ctl M1, M9 'control 89 días'` | 0 |
| 3.4, 1.ª | `c208a5b` | parches M12 y M13 con error de sintaxis PHP (comilla de cierre): «FALLA» por `ParseError`, no por la propiedad. `ctl M12` lo destapó (PASA exigido, FALLA obtenido) | 1 (descartada) |
| 3.4, 2.ª | `c208a5b` | parches M12 y M13 rehechos; `php -l` sin errores sobre los 15 parches de `AlertQuery.php`; cadena completa de 3.4 | 0 |
| 4.3 | `da49eb6` y otra vez en `db01342` (controlador con `InventoryAlerts`) | autocontrol `no-existe-zzz` && `mut M19, M20, M22 && ctl M22 'Filtro mal formado.*abc'` | 0 y 0 |

## 4. Barridos (`/usr/bin/grep`; 6.3 desde la raíz, el resto desde `software/api`)

| # | Comando | Resultado | Control positivo |
|---|---|---|---|
| 6.3a | `/usr/bin/grep -cE 'lockForUpdate\|sharedLock\|FOR (UPDATE\|SHARE)' software/api/app/Queries/AlertQuery.php` | 0 | mismo patrón en `software/api/app/Services/Inventory/StockLedger.php` → 1 |
| 6.3b | `/usr/bin/grep -ciE 'transfer' software/api/app/Queries/AlertQuery.php` | 0 | mismo patrón en `software/api/app/Actions/Transfers/DispatchTransfer.php` → 18 |
| 6.3 | comando compuesto de la tarea 6.3 | sale 0 | — |
| S1 | `/usr/bin/grep -nE 'CURRENT_DATE\|now\(' app/Queries/AlertQuery.php` | 1 hit: :14, el docblock («nunca CURRENT_DATE»); 0 en código | `/usr/bin/grep -cE 'CURRENT_DATE' openspec/changes/add-alerts/mutants/M16.patch` → 1 |
| S2 | `/usr/bin/grep -ciE '\->(insert\|update\|delete\|decrement\|increment\|save)\(' app/Queries/AlertQuery.php` | 0 | mismo patrón en `app/Services/Inventory/StockLedger.php` → 1 |
| S3 | `/usr/bin/grep -c 'Facades\\DB' app/Http/Controllers/Inventory/AlertController.php` | 0 | `app/Actions/Transfers/DispatchTransfer.php` → 1 |
| S4 | `Log::` sobre los 8 archivos nuevos de `app/` y el seeder (`cat … \| /usr/bin/grep -c 'Log::'`) | 0 | `app/Http/Middleware/AssignCorrelationId.php` → 1 |
| S5 | `/usr/bin/grep -rnE '\b(dd\|dump\|ray\|var_dump\|print_r)\(' app tests/Feature/Alerts tests/Helpers/Alerts.php \| /usr/bin/grep -c .` | 0 | `printf 'dd($x);'` → 1 |
| S6 | mensajes literales (`'message'\|abort\(\|__\(`) en controlador, request y 3 recursos nuevos | 0: el endpoint no agrega textos; reutiliza `errors.forbidden`, `errors.unauthenticated`, `errors.validation_failed` y el atributo `warehouse_id` de `lang/es/validation.php` | `tests/Feature/Inventory/StockEndpointTest.php` → 1 |
| S7 | `/usr/bin/grep -rn "'--step'" tests` (reversiones con conteo fijo) | 1 hit: `Transfers/TransferRaceTest.php:198`, ya con `$steps` calculado | `printf "['--step' => 4]"` → 1 |

## 5. Corridas

| # | Comando | Árbol | Resultado |
|---|---|---|---|
| línea base | CI 37769634376 | `d350c4c` | verde (backend y frontend) |
| delta 1.1 | `pest tests/Feature/Alerts/StockMinimumIntegrityTest.php`; `migrate:rollback --step=1 && migrate` | sin commit / `ff4c137` | 8 pasan / 24 aserciones; reversión y migración salen 0 |
| delta 1.2 | `pest --filter=StockMinimum --fail-on-empty-test-suite` | `ff4c137` | 8 pasan |
| delta 3.1, 3.3 | `pest ExpiringLotsQueryTest.php LowStockQueryTest.php` | sin commit / `9bed223` | 18 pasan / 36 aserciones |
| delta 2.1, 3.5 | `pest StockMinimumSeedTest.php` | sin commit / `c208a5b` | 3 pasan / 10 aserciones |
| delta 4.1, 4.2 | `pest AlertEndpointTest.php tests/Arch` (+ Pint y Larastan sobre los archivos tocados) | `da49eb6`, `db01342` | 28 pasan / 187 aserciones; Pint pasa; Larastan 0 errores |
| OpenAPI 5.1 | `composer openapi` (contenedor); `npx @redocly/cli lint software/api/openapi.json`; re-export + `git diff --exit-code -- software/api/openapi.json` (host) | `db01342` | `/alerts` con 200 (`AlertsResource` → listas de `ExpiringLotResource` y `LowStockResource`), 401, 403, 422; Redocly válido; deriva 0. `composer openapi:check` dentro del contenedor sale 129: `git` no ve repositorio en el montaje de `api/`; en CI corre en el runner |
| tipos web | `npm run api:types` (software/web) | `db01342` | `api-schema.ts` regenerado y versionado |
| **cierre 6.2** | `pint --test && phpstan analyse --memory-limit=1G && pest` | `db01342` | Pint pasa; Larastan 0 errores; Pest **795 pasan, 1 falla / 3174 aserciones**. La falla: `Transfers/TransferRaceTest.php` «revierte las 4 migraciones de traslados…», con `--step=4` fijo; la tabla de S5 es ahora la última migración |
| delta de saneo | `pint --test` + `pest tests/Feature/Transfers/TransferRaceTest.php` tras contar los pasos desde la primera migración de S4 | `6bdf70f` | Pint pasa; 5 pasan / 14 aserciones. La reversión baja también `stock_minimums` y vuelve a migrar |

| Presupuesto de suite | Corridas |
|---|---|
| línea base (CI) | 1 |
| cierre del implementer (6.2, local) | 1 |
| confirmación del auditor (pendiente) | 1 |
| total frente al tope de 3 | 3 |

## 6. Humo en el stack (6.1)

Script `software/docker/smoke/alerts-smoke.sh` (bash + curl + jq; solo lectura). Árbol: `c24c749` + el script.
Stack de desarrollo `dispensart` (8090, 127.0.0.1:5434), imágenes reconstruidas por el propio comando.

| Desvío del comando de 6.1 | Motivo | Efecto |
|---|---|---|
| sin `down -v` inicial | orden del Orchestrator: no borrar el volumen del stack de desarrollo, en uso por otros agentes | la base no estaba recién creada; `stock_minimums` no existía (migración de S5 sin aplicar) y nació en el `up --build`. El resto del comando, literal |

| Corrida | Comando | Comprobaciones | Fallas | Salida | `stock_minimums` |
|---|---|---|---|---|---|
| 1 | `up --build --wait` → `alerts-smoke.sh` | 18 | 0 | 0 | n1 = 4 |
| 2 | `up --wait --force-recreate api` (entrypoint: `migrate` + `db:seed`, log `siembra al dia`) → `alerts-smoke.sh` | 18 | 0 | 0 | n2 = 4 |
| 3 | `alerts-smoke.sh` | 18 | 0 | 0 | — |
| comando compuesto | `… && [ "$n1" -eq 4 ] && [ "$n1" -eq "$n2" ]` | — | — | 0 | 4 = 4 |

| Comprobación (18 por corrida) | Rol | Esperado | 1 | 2 | 3 |
|---|---|---|---|---|---|
| csrf-cookie + login | regente, auditor, médico | 204 + 200 (6 comprobaciones) | PASA | PASA | PASA |
| `GET /api/alerts` | regente | 200, ambas listas son arreglos | PASA | PASA | PASA |
| `expiring_lots` no vacía; `low_stock` no vacía | regente | 2 comprobaciones | PASA | PASA | PASA |
| `L-ACE-2401` con `is_expired` true, `days_to_expiry` < 0, `quantity` > 0 | regente | presente | PASA | PASA | PASA |
| FC/MED-006, BH/MED-004, BH/MED-006 en `low_stock` | regente | presentes (3 comprobaciones) | PASA | PASA | PASA |
| FC/MED-001 en `low_stock` | regente | ausente | PASA | PASA | PASA |
| toda fila de `low_stock` con `available_quantity < minimum_quantity` | regente | verdadero | PASA | PASA | PASA |
| `GET /api/alerts` | auditor | 200 | PASA | PASA | PASA |
| cuerpo del auditor = cuerpo del regente (`jq -S`) | auditor | idéntico | PASA | PASA | PASA |
| `GET /api/alerts` | médico | 403 `code == "forbidden"` | PASA | PASA | PASA |

| Control negativo (debe salir 1) | Comando | Salida | Fallas |
|---|---|---|---|
| contraseña errónea | `SEED_USER_PASSWORD=<errónea> bash alerts-smoke.sh` | 1 | 1 de 2 (login 422; aborta) |
| aserción invertida | copia en el scratchpad con `low FC MED-001` `== 0` → `> 0` (`diff`: 1 línea) | 1 | 1 de 18 (`low_stock no contiene FC/MED-001`) |

| Barrido / regresión | Comando | Resultado | Control positivo |
|---|---|---|---|
| contraseña nunca impresa | `cat <logs de las 3 corridas, 2 controles y 4 humos> \| /usr/bin/grep -c 'dispensart-dev-only\|<errónea>'` | 0 | `/usr/bin/grep -c 'dispensart-dev-only' software/docker/smoke/alerts-smoke.sh` → 1 |
| no root | `docker compose exec -T <svc> id -u` | api 1000, web 101 | `db` (imagen oficial de postgres, fuera de este cambio) → 0 |
| salud | `curl localhost:8090/health`, `/ready` | 200, 200 | — |
| `auth-smoke.sh` | una corrida tras las de alertas | 38 comprobaciones, 0 fallas, sale 0 | — |
| `stock-smoke.sh` | ídem | 15, 0, sale 0 | — |
| `dispensation-smoke.sh` | ídem | 23, 0, sale 0 | — |
| `transfer-smoke.sh` | ídem | 28, 0, sale 0 | — |
