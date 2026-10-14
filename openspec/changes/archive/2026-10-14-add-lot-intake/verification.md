# Verification — add-lot-intake (S18, tier A)

Árbol medido: `feat/add-lot-intake` en `d45abfb` (código y pruebas de S18). Prefijo `LIN`. Rutas de prueba de
API relativas a `software/api/tests/Feature/`; de SPA, a `software/web/src/`.

## 0. Reparto de líneas

Comando: `for c in 783781f d45abfb; do git show --numstat --format= $c; done | awk '<alcance> {a+=$1; d+=$2}'`.

| Categoría | Alcance | Añadidas | Borradas |
|---|---|---|---|
| Producto — API | `software/api/{app,database,routes,lang}` | 250 | 1 |
| Producto — SPA | `software/web/src` sin pruebas ni `api-schema.ts` | 270 | 6 |
| Prueba — API | `software/api/tests` | 296 | 1 |
| Prueba — SPA | `software/web/src/**/*.test.tsx` | 148 | 0 |
| Generado | `software/api/openapi.json`, `software/web/src/lib/api-schema.ts` | 219 | 0 |
| Colección Postman | `software/docs/postman/dispensart.postman_collection.json` | 134 | 1 |
| Registro | `openspec/changes/add-lot-intake/**/*.md` al cierre (`wc -l`) | ver § 5 | — |

## 1. Matriz escenario → prueba → archivo:línea → ancla

| Capacidad | Escenarios en la spec | Escenarios con prueba |
|---|---|---|
| inventory (delta) | 8 | 8 |
| audit-trail (delta) | 4 | 4 |
| inventory-screen (delta) | 5 | 5 |

| Id | Escenario | Prueba (título) | Archivo:línea | Cláusula → ruta archivo:línea |
|---|---|---|---|---|
| LIN-01 | Ingreso de lote nuevo | Ingreso de lote nuevo: crea el lote, la existencia y un movimiento entrada | Inventory/StockEntryEndpointTest.php:57 | 201 → `routes/api.php:61`, `StockEntryController.php` `setStatusCode(201)` |
| LIN-02 | Ingreso sobre lote existente con el mismo vencimiento | Ingreso sobre lote existente con el mismo vencimiento… | Inventory/StockEntryEndpointTest.php:75 | 201 → `routes/api.php:61` |
| LIN-03 | Vencimiento distinto del lote existente | Vencimiento distinto del lote existente: 422 en expires_on… | Inventory/StockEntryEndpointTest.php:109 | 422 → `ReceiveLot.php:86` + `ApiExceptionRenderer.php:25` |
| LIN-04 | Lote vencido rechazado | Lote vencido rechazado: vence hoy en America/Bogota… (+ sobre lote existente, :132) | Inventory/StockEntryEndpointTest.php:123 | 422 → `ReceiveLot.php:42` + `ApiExceptionRenderer.php:33` |
| LIN-05 | Datos inválidos o incompletos | Datos inválidos o incompletos: 422 validation_failed… (11 casos) | Inventory/StockEntryEndpointTest.php:143 | 422 → `StoreStockEntryRequest.php:27` + `ApiExceptionRenderer.php:25` |
| LIN-06 | Otro rol intenta registrar un ingreso | Otro rol intenta registrar un ingreso: 403 forbidden (4 roles) | Inventory/StockEntryEndpointTest.php:165 | 403 → `StockPolicy.php:27` + `ApiExceptionRenderer.php:61` |
| LIN-07 | Sin sesión | Sin sesión: 401 unauthenticated | Inventory/StockEntryEndpointTest.php:179 | 401 → `routes/api.php:39` + `ApiExceptionRenderer.php:24` |
| LIN-08 | Ingresos simultáneos del mismo lote nuevo | Ingresos simultáneos del mismo lote nuevo… 10 de 10 | Inventory/StockEntryRaceTest.php:19 | 201 → `ReceiveLot.php:76` (`insertOrIgnore`) |
| LIN-09 | Ingreso registrado | Ingreso registrado: una fila stock.received… | Inventory/StockEntryEndpointTest.php:189 | 201 → `routes/api.php:61` |
| LIN-10 | Sin texto libre en la fila | Sin texto libre en la fila: ni el motivo ni el código de lote | Inventory/StockEntryEndpointTest.php:199 | — |
| LIN-11 | Ingreso rechazado sin fila | `expectNoIntake` (cuenta `stock.received` = 0) en LIN-03..LIN-07 | Inventory/StockEntryEndpointTest.php:48 | 422/403 → anclas de LIN-03..LIN-06 |
| LIN-12 | Acción con objeto equivocado rechazada por la base | Acción con objeto equivocado rechazada por la base… | Inventory/StockEntryEndpointTest.php:208 | — |
| LIN-13 | Regente ve el formulario | Regente ve el formulario con sus seis campos | features/inventory/stock-entry-form.test.tsx:56 | — |
| LIN-14 | Otros roles sin formulario | Otros roles sin formulario: auxiliar_farmacia, auditor | features/inventory/stock-entry-form.test.tsx:66 | — |
| LIN-15 | Ingreso exitoso | Ingreso exitoso: envía los seis campos, confirma, limpia y vuelve a pedir… | features/inventory/stock-entry-form.test.tsx:74 | — |
| LIN-16 | Campos obligatorios vacíos | Campos obligatorios vacíos: cada campo muestra su mensaje… | features/inventory/stock-entry-form.test.tsx:106 | — |
| LIN-17 | Rechazo de la API | lot_expired (:116) y validation_failed en expires_on (:131) | features/inventory/stock-entry-form.test.tsx:116 | escenarios LIN-03, LIN-04 |

Adicionales sin escenario propio: lote que vence mañana aceptado (`StockEntryEndpointTest.php:90`); campos del
servidor ignorados (`:96`); retroceso de la migración con filas `stock.received`
(`Audit/LotIntakeMigrationTest.php:12`); `SensitiveOperationMigrationTest.php:42` pasa a `--step 2` porque la
migración de S18 es la última.

## 2. `[MUT]` — declarados 6, entregados 6

Comando por pin: mutación aplicada → Pest/Vitest filtrado al título → restauración → misma corrida.

| n | Mutación | Aplicada → FALLA | Restaurada → PASA |
|---|---|---|---|
| M1 | `ReceiveLot.php:41` condición de vencido → `false` | 2 fallan de 2: «Lote vencido rechazado» (:123, :132) | 2 de 2 |
| M2 | `ReceiveLot.php:85` comparación de vencimiento → `false` | 1 falla de 1: «Vencimiento distinto» | 1 de 1 |
| M3 | `insertOrIgnore` → `insert` en `lots` | 1 falla de 1: «Ingresos simultáneos» | 1 de 1 |
| M4 | fila `stock.received` omitida (`if (false)`) | 1 falla de 1: «Ingreso registrado» | 1 de 1 |
| M5 | `StockPolicy::receive()` → `true` | 4 fallan de 4: «Otro rol» | 4 de 4 |
| M6 | `inventory-page.tsx` `can(user, 'inventory.adjust')` → `user !== null` | 2 fallan de 2: «Otros roles sin formulario» | 2 de 2 |

## 3. Corridas de suite (presupuesto 3)

| # | Corrida | Comando | Resultado |
|---|---|---|---|
| 1 | Línea base API | `api-tools vendor/bin/pest --parallel` | 1074 pasan, 1 falla (`EvaluationSetTest`, previa → D-6b6-1) |
| 1 | Línea base SPA | `npx vitest run` | 360 pasan de 360 |
| 2 | Cierre API | `api-tools -e AI_PROVIDER=mock vendor/bin/pest` | 1099 pasan, 0 fallan; el proceso aborta al final por `memory_limit` 128M del contenedor `api-tools` (`Allowed memory size of 134217728 bytes exhausted`, en `Tests\Arch\AssistantArchTest`) → D-6b6-2. `tests/Arch` aislado: pasa |
| 2 | Cierre SPA | `npx vitest run` | 34 archivos, 367 pasan de 367 |

## 4. Herramientas y contrato

| Verificación | Comando | Resultado |
|---|---|---|
| Pint | `api-tools vendor/bin/pint` | 421 archivos, PASS |
| Larastan | `api-tools vendor/bin/phpstan analyse` | 0 errores |
| ESLint | `npm run lint` | 0 avisos (`--max-warnings 0`) |
| Typecheck | `npm run typecheck` | 0 errores |
| Deriva del contrato | `api-tools composer openapi:check` | `composer openapi` en contenedor + `git diff --exit-code -- software/api/openapi.json` en host: sale 0 (en contenedor `git` no ve el repo: exit 129) |
| Cobertura Postman | `bash software/docs/postman/check-coverage.sh …collection.json` | 35 de 35 operaciones; control positivo: `POST /api/stock-entries` listada |
| newman sobre el stack reconstruido | `npx newman@6.2.3 run … -e local…` | 126 peticiones, 241 aserciones, 0 fallan |
| Ancla de transporte | `/usr/bin/grep -rnE '<patrón CYCLE-TIERS>' specs/` | 9 hits, 0 sin ancla; control positivo: los 9 hits |
