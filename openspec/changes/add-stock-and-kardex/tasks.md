# Tasks — add-stock-and-kardex (S2)

Tier A. `[MUT]` declarados: 10 (M1–M10). Refinado por el architect contra `design.md` (D1–D10).
Requisito previo: S1 archivado (migraciones de `lots`, capacidades `inventory.*`, render D5, `BusinessCalendar`,
`SpaClient`, Scramble). Todo es `software/api`: un solo bloque, sin trabajo de `web`, nada paralelizable entre
implementadores. Dentro del bloque, los grupos 2 y 4 no dependen del 3 y pueden adelantarse.
Pruebas del backend: Pest contra PostgreSQL, nunca SQLite. Autorización por rol con `actingAs` (D2 de S1); el caso
CSRF con `tests/Support/SpaClient`. `RefreshDatabase` por defecto; `DatabaseTruncation` prohibido (choca con el
trigger, D7); las pruebas de carrera usan `DatabaseMigrations` con filas dedicadas por iteración (D7, D8). Las
inserciones directas en el kardex crean antes su existencia (FK compuesta, D4).

## 1. Base de datos

- [ ] 1.1 Migración `lots_id_product_id_unique` (D3) y migración de existencias (Data impact fila 2): única por bodega + producto + lote, `stocks_quantity_non_negative`, FK bodega `RESTRICT`, FK compuesta `(lot_id, product_id) → lots (id, product_id)` `RESTRICT`; pruebas de sentencia directa. Cubre inventory "Existencia válida", "Cantidad negativa rechazada por la base", "Existencia duplicada", "Producto que no corresponde al lote", "Borrar bodega o lote con existencias". Verifica: prueba Pest verde.
- [ ] 1.2 Migración del kardex (Data impact fila 3): `CHECK` de tipo (5 literales de RN-06), signo por tipo con cantidad ≠ 0, `balance_after` ≥ 0, `ajuste` con usuario y motivo no vacío; FK compuesta a `stocks`, FK usuario; `created_at` por defecto de la base, sin `updated_at`; pruebas de inserción directa. Cubre kardex "Tipos futuros ya admitidos", "Tipo fuera del conjunto", "Signo contrario al tipo", "Saldo resultante negativo", "Ajuste sin usuario o sin motivo". Verifica: prueba Pest verde.
- [ ] 1.3 Trigger `kardex_movements_append_only` de sentencia sobre `UPDATE`, `DELETE` y `TRUNCATE`, `ENABLE ALWAYS`, mensaje que nombra la inmutabilidad; `down()` retira trigger y función (D5). Cubre kardex "Inserción aceptada", "Edición rechazada por la base", "Borrado rechazado por la base", "Vaciado rechazado por la base". Verifica: prueba Pest verde y `migrate:rollback --step=3` + `migrate` verdes en `api-tools`.
- [ ] 1.4 Modelos `Stock` (con `scopeInLockOrder`, D2) y `KardexMovement` (`$timestamps = false`) y factories (la de existencia con cantidad > 0 crea también su `entrada`, para no sembrar filas que violen la cadena de saldos). Cimiento. Verifica: `docker compose -f software/compose.yaml run --rm api-tools vendor/bin/pest --filter=Factory` verde.
- [ ] 1.5 [MUT] M1: retirar el trigger → "Edición rechazada…", "Borrado rechazado…" y "Vaciado rechazado…" FALLAN. M2: quitar `stocks_quantity_non_negative` → "Cantidad negativa rechazada por la base" FALLA. M9: quitar `stocks_lot_product_foreign` → "Producto que no corresponde al lote" FALLA. Restaurar cada uno → PASA. Verifica: filas M1, M2, M9 en `verification.md`.

## 2. Dominio

- [ ] 2.1 Enum `MovementType` con el signo permitido por tipo; prueba con dataset de los 5 tipos y los signos prohibidos. Cubre kardex "Tipos futuros ya admitidos", "Signo contrario al tipo" a nivel de dominio. Verifica: prueba Pest verde.
- [ ] 2.2 Regla de ajuste sobre lote vencido (positivo rechazado con `LotExpired`, negativo permitido) usando `Lot::isExpiredOn` y `BusinessCalendar::today()` de S1, con reloj fijado. Cubre inventory "Baja de existencia vencida", "Ingreso a lote vencido rechazado", "Ingreso a lote que vence mañana" a nivel de dominio. Verifica: prueba Pest verde.

## 3. Aplicación (depende de 1)

- [ ] 3.1 `StockLedger::apply()` (D2): clave global de bloqueo, `insertOrIgnore` + `FOR UPDATE` por clave en orden, validación de saldos antes de escribir (`InsufficientStock`), `UPDATE … RETURNING` y un movimiento por cambio con `balance_after`, todo en una transacción (savepoint si el llamador ya tiene una). Cubre kardex "Ajuste escribe un solo movimiento", "Cadena de saldos tras varios cambios", "Ajuste rechazado no deja movimiento", "Fallo al escribir el movimiento revierte la existencia"; inventory "Ajuste que deja exactamente cero", "Ajuste mayor que la existencia", "Ajuste negativo sin existencia previa". Verifica: prueba Pest verde.
- [ ] 3.2 [MUT] M3: omitir la escritura del movimiento, y luego escribirlo dos veces → "Ajuste escribe un solo movimiento" FALLA en ambos. M5: sacar la escritura de la transacción → "Fallo al escribir el movimiento revierte la existencia" FALLA. Restaurar → PASA. Verifica: filas M3, M5.
- [ ] 3.3 Acción `AdjustStock` fuera del controlador (D9): carga el lote, aplica 2.2 solo a `delta > 0`, usa el usuario autenticado, recibe solo campos validados y delega en 3.1 con `type = ajuste`. Cubre inventory "Campos del servidor ignorados", "Reintento del mismo ajuste" a nivel de acción. Verifica: prueba Pest verde y la prueba `arch()` de controladores delgados de S0 sigue verde.
- [ ] 3.4 Seeder de existencias tras `LotSeeder`: crea solo existencias ausentes por bodega + lote, vía 3.1 con `entrada` y usuario nulo; cumple la distribución exigida (incluye el lote vencido: la regla de 2.2 no vive en el libro, D2). Cubre inventory "Siembra inicial", "Un movimiento de entrada por existencia sembrada", "Siembra repetida", "Ajustes sobreviven a la resiembra", "Existencia previa no sembrada rechazada por la siembra". [MUT] M8: sembrar sin comprobar existencia previa → "Siembra repetida" FALLA; restaurar → PASA. Verifica: prueba verde y fila M8.

## 4. Infraestructura (independiente de 2 y 3)

- [ ] 4.1 Ampliar el render de D5 (S1) según D10: `InsufficientStock` → 409 `insufficient_stock`, `LotExpired` → 422 `lot_expired`, mensajes en `lang/es/errors.php`, sin traza, SQL ni cantidades. Cubre el cuerpo de inventory "Ajuste mayor que la existencia" e "Ingreso a lote vencido rechazado". Verifica: prueba Pest por código verde.
- [ ] 4.2 `StockPolicy` (`viewAny`, `adjust`) y `KardexMovementPolicy` (`viewAny`) que delegan en `inventory.view` / `inventory.adjust` del mapa de S1 (sin tocar el mapa). Cubre inventory "Roles sin lectura de inventario", "Otro rol intenta ajustar"; kardex "Roles sin lectura de inventario", a nivel unitario. Verifica: prueba por rol verde.

## 5. API (depende de 3 y 4; una prueba HTTP real por endpoint)

- [ ] 5.1 `GET /api/stock` con FormRequest de filtros (`integer|min:1`, sin `exists`), scope `filter()`, orden de D9 y API Resource + prueba HTTP real con dataset de los 5 roles. Cubre inventory "Consulta de existencias" (todos). Verifica: prueba verde.
- [ ] 5.2 `GET /api/kardex` paginado con FormRequest y API Resource + prueba HTTP real con dataset de los 5 roles. Cubre kardex "Consulta del kardex" (todos) y "Sin ruta de edición ni borrado". Verifica: prueba verde.
- [ ] 5.3 `POST /api/stock-adjustments` con FormRequest + prueba HTTP real (`actingAs` por rol; `SpaClient` para 419). Cubre inventory "Ajuste de inventario" (todos), "Ajuste mayor que la existencia", "Ajuste negativo sin existencia previa", "Ajuste que deja exactamente cero", "Ajuste sobre lote vencido" (todos), "Ajustes sobreviven a la resiembra"; kardex "Ajuste escribe un solo movimiento", "Cadena de saldos tras varios cambios", "Ajuste rechazado no deja movimiento". Verifica: prueba verde.
- [ ] 5.4 Prueba de carrera real (D8): `tests/Support/RaceRunner` + `tests/Support/race-worker.php` (procesos con conexión propia, `DB_DATABASE` del padre, kernel HTTP real), barrera `LOCK TABLE kardex_movements IN SHARE MODE` liberada al ver N workers en espera de bloqueo; `DatabaseMigrations`, 10 iteraciones con filas dedicadas en una prueba. Afirma estados HTTP (201 + 409 `insufficient_stock`, nunca 500), cantidad final y conteo de movimientos. Cubre inventory "Carrera por la última unidad", "Ajustes positivos simultáneos sobre existencia inexistente". Verifica: prueba Pest verde con 10/10 iteraciones.
- [ ] 5.5 [MUT] M4: quitar el `FOR UPDATE` de 3.1 → "Carrera por la última unidad" FALLA 10/10. M10: reemplazar `insertOrIgnore` por inserción simple → "Ajustes positivos simultáneos…" FALLA 10/10. Restaurar → PASA 10/10. Verifica: filas M4, M10.
- [ ] 5.6 [MUT] M6: quitar la regla de 2.2 de la acción → "Ingreso a lote vencido rechazado" FALLA. M7: `StockPolicy::adjust` autoriza siempre → dataset 403 de 5.3 FALLA. Restaurar → PASA. Verifica: filas M6, M7.
- [ ] 5.7 Exportar OpenAPI (D9 de S1) con los 3 endpoints, códigos 409 `insufficient_stock` y 422 `lot_expired`. Cimiento. Verifica: `npx @redocly/cli lint software/api/openapi.json` verde y `git diff --exit-code software/api/openapi.json` tras `scramble:export` sin diferencias.

## 6. Integración y cierre

- [ ] 6.1 Stack desde cero: `down -v` + `up --build --wait`; login como regente y `GET /api/stock` con existencias semilla; segundo `up` con conteos de existencias y movimientos iguales. Cubre inventory "Siembra inicial", "Siembra repetida" en el stack. Verifica: comandos y conteos registrados en `journal.md`.
- [ ] 6.2 Corrida completa de cierre: Pint, Larastan, Pest. Cimiento. Verifica: `docker compose -f software/compose.yaml run --rm api-tools sh -c 'vendor/bin/pint --test && vendor/bin/phpstan analyse && vendor/bin/pest'` verde, registradas en `journal.md` dentro del presupuesto de 3 corridas.
- [ ] 6.3 `verification.md` en tablas: escenario → prueba → archivo:línea; `[MUT]` M1–M10 aplicado/restaurado; columna cláusula → ruta archivo:línea por cada hit del ancla de transporte; § 0 con líneas de producto, de prueba y de registro. Cimiento. Verifica: `openspec validate add-stock-and-kardex --strict` válido y spec-validator sin hallazgos.

## Workflow follow-up

- GATE 2 lo emite solo `final-auditor`.
- Archivar con `openspec archive add-stock-and-kardex -y` y validar `openspec validate --all --strict`.
