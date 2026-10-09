# Verification — add-sensitive-operation-audit (S10, tier A)

Fuente: sección backend-implementer de `journal.md`. Árbol medido: `dev` en `7044892` (código y pruebas de S10).
Prefijo `SOA`. Rutas de prueba relativas a `software/api/tests/Feature/`; de código, a `software/api/`.

## 0. Reparto de líneas

Comando: `for c in 4a433a7 0ed7517 7044892; do git show --numstat --format= $c; done | awk '<alcance> {a+=$1; d+=$2}'`
(solo los tres commits de S10; los commits S9 intercalados en `dev` quedan fuera).

| Categoría | Alcance | Añadidas | Borradas |
|---|---|---|---|
| Producto — API | `software/api/{app,database}` | 145 | 12 |
| Producto — SPA | `software/web` | 0 | 0 |
| Producto — infraestructura | `software/compose.yaml`, `software/docker`, `.github` | 0 | 0 |
| Prueba — API | `software/api/tests` | 479 | 5 |
| Generado | `software/api/openapi.json` | 0 | 0 |
| Registro | `openspec/changes/add-sensitive-operation-audit/**/*.md` al cierre (`wc -l`) | ver § 7 | — |

## 1. Matriz escenario → prueba → archivo:línea

| Capacidad | Escenarios en la spec | Escenarios con prueba |
|---|---|---|
| audit-trail (delta S10) | 19 | 19 |

| Id | Requisito › Escenario | Prueba (título, prefijo = escenario) | Archivo:línea |
|---|---|---|---|
| SOA-01 | Alta › Alta registrada | Alta registrada: una fila user.created con el admin como actor… | Identity/UserCreationAuditTest.php:47 |
| SOA-02 | Alta › Sin datos personales ni secretos en la fila | Sin datos personales ni secretos en la fila: ni nombre, ni correo, ni contraseña, ni hash | Identity/UserCreationAuditTest.php:59 |
| SOA-03 | Alta › Fallo al registrar revierte el alta | Fallo al registrar revierte el alta: 500 server_error y ningún usuario nuevo | Identity/UserCreationAuditTest.php:74 |
| SOA-04 | Alta › Alta rechazada sin fila | Alta rechazada sin fila: 401, 422 correo duplicado, 422 rol inválido y 403 de otro rol | Identity/UserCreationAuditTest.php:92 |
| SOA-05 | Ajuste › Ajuste registrado | Ajuste registrado: una fila stock.adjusted con el regente como actor… | Inventory/StockAdjustmentAuditTest.php:51 |
| SOA-06 | Ajuste › Sin texto libre en la fila | Sin texto libre en la fila: el motivo y sus palabras distintivas no aparecen | Inventory/StockAdjustmentAuditTest.php:67 |
| SOA-07 | Ajuste › Fallo al registrar revierte el ajuste | Fallo al registrar revierte el ajuste: 500 server_error, existencia en 10… | Inventory/StockAdjustmentAuditTest.php:83 |
| SOA-08 | Ajuste › Ajuste rechazado sin fila | Ajuste rechazado sin fila: 401, 409, 422 lot_expired, 422 validation_failed, 403 y 419 | Inventory/StockAdjustmentAuditTest.php:102 |
| SOA-09 | Ajuste › Carrera por la última unidad con una sola fila | Carrera por la última unidad con una sola fila: serializa la carrera… 10 de 10 | Inventory/StockAdjustmentRaceTest.php:37 |
| SOA-10 | Ajuste › Ajustes simultáneos con una fila por movimiento | Ajustes simultáneos con una fila por movimiento: acumula ajustes positivos… 10 de 10 | Inventory/StockAdjustmentRaceTest.php:68 |
| SOA-11 | Ajuste › Reintento del mismo ajuste con dos filas | Reintento del mismo ajuste con dos filas: una por cada movimiento ajuste | Inventory/StockAdjustmentAuditTest.php:136 |
| SOA-12 | Ajuste › Resolución de discrepancia sin fila de ajuste | Resolución de discrepancia sin fila de ajuste: solo transfer.discrepancy_resolved | Inventory/StockAdjustmentAuditTest.php:148 |
| SOA-13 | Ajuste › Dispensación y su repetición idempotente sin fila de ajuste | Dispensación y su repetición idempotente sin fila de ajuste | Inventory/StockAdjustmentAuditTest.php:170 |
| SOA-14 | Integridad › Acciones nuevas admitidas | Acciones nuevas admitidas: user.created sobre un usuario y stock.adjusted… | Audit/SensitiveOperationIntegrityTest.php:41 |
| SOA-15 | Integridad › Acciones vigentes siguen admitidas | Acciones vigentes siguen admitidas: cada acción sobre su tipo de objeto (dataset 7) | Audit/SensitiveOperationIntegrityTest.php:52 |
| SOA-16 | Integridad › Emparejamiento cruzado rechazado por la base | Emparejamiento cruzado rechazado por la base… (dataset 2) | Audit/SensitiveOperationIntegrityTest.php:66 |
| SOA-17 | Integridad › Acción fuera del conjunto rechazada por la base | Acción fuera del conjunto rechazada por la base: user.role_changed | Audit/SensitiveOperationIntegrityTest.php:75 |
| SOA-18 | Integridad › Detalle con texto rechazado por la base | Detalle con texto rechazado por la base: correo en el detalle de user.created | Audit/SensitiveOperationIntegrityTest.php:81 |
| SOA-19 | Integridad › Filas nuevas inmutables | Filas nuevas inmutables: UPDATE, DELETE y TRUNCATE rechazados… (dataset 3) · Filas nuevas inmutables: sobreviven al retroceso de la migración y se revalidan | Audit/SensitiveOperationIntegrityTest.php:91; Audit/SensitiveOperationMigrationTest.php:30 |

## 2. Ancla de transporte: cláusula → ruta archivo:línea

| Escenario | Cláusula | Ruta / productor archivo:línea | Prueba que lo ejerce |
|---|---|---|---|
| Alta registrada | HTTP 201 + fila | `routes/api.php:42` → `app/Http/Controllers/Users/UserController.php:33`; fila en `app/Actions/Identity/CreateUser.php:35` dentro de `:26` | SOA-01 |
| Fallo al registrar revierte el alta | HTTP 500 `server_error` | `app/Exceptions/ApiExceptionRenderer.php:54` | SOA-03 |
| Alta rechazada sin fila | 422 / 403 / 401 | identity-access › escenarios vivos citados en el delta | SOA-04 |
| Ajuste registrado | HTTP 201 + fila | `routes/api.php:57` → `app/Http/Controllers/Inventory/StockAdjustmentController.php:21`; fila en `app/Actions/Inventory/AdjustStockManually.php:35` dentro de `:32` | SOA-05 |
| Fallo al registrar revierte el ajuste | HTTP 500 `server_error` | `app/Exceptions/ApiExceptionRenderer.php:54` | SOA-07 |
| Ajuste rechazado sin fila | 409 / 422 / 403 / 401 / 419 | inventory › escenarios vivos citados en el delta | SOA-08 |
| Carrera por la última unidad con una sola fila | 201 + 409 | inventory › «Carrera por la última unidad» | SOA-09 |
| Resolución de discrepancia sin fila de ajuste | HTTP 200 | `routes/api.php:92` | SOA-12 |
| Emparejamiento cruzado rechazado por la base | 23514 `audit_events_action_subject_check` | `database/migrations/2026_10_12_000001_extend_audit_events_for_sensitive_operations.php:39-40` | SOA-16 |
| Acción fuera del conjunto rechazada por la base | 23514 `audit_events_action_check` | ídem `:27-31` | SOA-17 |
| Detalle con texto rechazado por la base | 23514 `audit_events_details_ids_only` | `database/migrations/2026_10_09_000007_create_audit_tables.php:65` | SOA-18 |

## 3. `[MUT]` — declarados 11, entregados 11; controles declarados 4, entregados 4

Comando por fila: el de `tasks.md` (1.5, 2.4, 3.6) con la precondición de árbol limpio acotada a los archivos del
parche (`git apply --numstat`), ver § 6 desvío 2. Todos los comandos salieron 0.

| n | Mutación (`mutants/M<n>.patch`) | Aplicado → resultado | Restaurado → resultado |
|---|---|---|---|
| M1 | emparejamiento sin la rama `stock.adjusted`/`kardex_movement` | FALLA 1/1: «Acciones nuevas admitidas» (23514 `audit_events_action_subject_check`) | PASA 1/1 |
| M2 | ramas nuevas sin tipo: `action IN ('user.created', 'stock.adjusted')` | FALLA 2/2: «Emparejamiento cruzado rechazado por la base» | PASA 2/2 |
| Control M2 | mismo parche | PASA 1/1: «Acciones nuevas admitidas» | — |
| M3 | `CreateUser`: `record()` después de `DB::transaction` | FALLA 1/1: «Fallo al registrar revierte el alta» (usuario existe: `true is false`) | PASA 1/1 |
| M4 | `CreateUser`: actor = `$user->id` | FALLA 1/1: «Alta registrada» (arreglos distintos) | PASA 1/1 |
| M5 | `AdjustStockManually`: `record()` después de `DB::transaction` | FALLA 1/1: «Fallo al registrar revierte el ajuste» (`7 is identical to 10`) | PASA 1/1 |
| M6 | `AdjustStockManually`: `record()` antes de `DB::transaction`, objeto `$data['lot_id']` | FALLA 1/1: «Ajuste rechazado sin fila» (`1 is identical to 0`) · FALLA 1/1: «Carrera por la última unidad con una sola fila» (10/10 iteraciones fuera) | PASA 1/1 · PASA 1/1 |
| Control M6 | mismo parche | PASA 1/1: «Fallo al registrar revierte el ajuste» | — |
| M7 | `record()` movido de `AdjustStockManually` a `AdjustStock::handle()` tras el libro | FALLA 1/1: «Resolución de discrepancia sin fila de ajuste» (`2 is identical to 1`) | PASA 1/1 |
| Control M7 | mismo parche | PASA 1/1: «Ajuste registrado» | — |
| M8 | `AdjustStockManually`: objeto = `$data['lot_id']` | FALLA 1/1: «Ajuste registrado» (arreglos distintos) | PASA 1/1 |
| M9 | `audit_events_action_check` sin `'user.created'` | FALLA 1/1: «Acciones nuevas admitidas» (23514 `audit_events_action_check`) | PASA 1/1 |
| M10 | `audit_events_subject_type_check` sin `'kardex_movement'` | FALLA 1/1: «Acciones nuevas admitidas» (23514 `audit_events_subject_type_check`) | PASA 1/1 |
| M11 | `down()` con `validate: true` (sin `NOT VALID`) | FALLA 1/1: «Filas nuevas inmutables» en `SensitiveOperationMigrationTest` (23514: `audit_events_action_check` violada por alguna fila al retroceder) | PASA 1/1 |
| Control M11 | mismo parche | PASA 3/3: «Filas nuevas inmutables» en `SensitiveOperationIntegrityTest` | — |

## 4. Barridos (todo cero con su control positivo en la misma fila)

| Barrido | Comando | Resultado | Control positivo |
|---|---|---|---|
| PII en las llamadas a `record()` | `/usr/bin/grep -nE -A3 'audit->record\(' app/Actions/Identity/CreateUser.php app/Actions/Inventory/AdjustStockManually.php \| /usr/bin/grep -cE 'password\|email\|reason\|name'` | 0 | llamadas presentes: mismo primer `grep` con `/usr/bin/grep -cE 'audit->record\('` = 2; `/usr/bin/grep -cE 'password\|email\|reason\|name'` = 6 en `StoreUserRequest.php` y 6 en `StoreStockAdjustmentRequest.php`; línea plantada `$this->audit->record(…, ['reason' => 1]);` por la misma tubería = 1 |
| Motivo del ajuste en la fila (prueba) | SOA-06: `json_encode` sin escapar de la fila `stock.adjusted` contiene `SENSITIVE_REASON`, `Ana`, `Sintética`, `9999010001` | 0 de 4 | los 4 presentes en el movimiento del kardex de la misma petición |
| Datos del usuario en la fila (prueba) | SOA-02: fila `user.created` contiene nombre, correo, contraseña, hash | 0 de 4 | nombre, correo y hash presentes en la fila de `users` |
| Motivo y datos en la base de desarrollo (en vivo) | `SELECT count(*) FROM audit_events WHERE id > 85 AND (details::text \|\| coalesce(correlation_id,'')) ~* 'erificacion\|dispensart.test\|Clave'` | 0 | `SELECT count(*) FROM users WHERE id = 7 AND (name \|\| email) ~* 'erificacion\|dispensart.test'` = 1; `kardex_movements` id 83 con el motivo = 1 |
| Cambio de contrato | `php artisan scramble:export --path=openapi.json` + `git diff --exit-code software/api/openapi.json` | sin diferencias (salida 0) | — (sin tipos SPA que regenerar) |

## 5. Corridas

| Corrida | Comando (`docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools …`) | Árbol | Resultado |
|---|---|---|---|
| Base (1 de 3) | `vendor/bin/pest` | `4d06b6b` | 1012 pasan (4145 aserciones), 160.98 s |
| Cierre (2 de 3) | `sh -c 'vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G --no-progress && vendor/bin/pest'` | `7044892` + S9 `8727438` | Pint PASS; PHPStan sin errores; 1039 pasan (4281 aserciones), 151.77 s |
| Delta de cierre | nuevas: Integridad 15 + Migración 1 + Alta 4 + Ajuste 7 | — | 27 = 1039 − 1012 |
| Migración en desarrollo | `docker compose -f software/compose.yaml up -d --build api` (el entrypoint migra) | `7044892` | `migrations`: `2026_10_12_000001_…` lote 5; `api` healthy; `GET :8090/ready` 200 |

## 6. Verificación en vivo (base `dispensart`, por Nginx `:8090`, sesión SPA con CSRF)

| Paso | Resultado |
|---|---|
| `audit_events` por acción, antes | 7 acciones, 85 filas; sin `user.created` ni `stock.adjusted` |
| Login `regente@dispensart.test` (contraseña semilla por configuración, nunca impresa) → `POST /api/stock-adjustments` `{warehouse_id 1, lot_id 3, quantity 1}` | csrf 204, login 200, ajuste 201, movimiento 83, saldo 96 |
| Fila nueva | id 86 · actor 2 · `stock.adjusted` · `kardex_movement` · 83 · `{"lot_id": 3, "warehouse_id": 1}` · `correlation_id` = cabecera `X-Correlation-Id` · `created_at` no nulo |
| Login `admin@dispensart.test` → `POST /api/users` (usuario sintético `verificacion.s10@dispensart.test`) | 201, usuario 7 |
| Fila nueva | id 87 · actor 5 (admin) · `user.created` · `user` · 7 · `{}` · `correlation_id` = cabecera |
| `audit_events` por acción, después | `stock.adjusted` 1, `user.created` 1; las 7 acciones previas sin cambio |

## 7. Desvíos

| n | Desvío | Motivo (medido) |
|---|---|---|
| 1 | `UserCreationAuditTest` y `StockAdjustmentAuditTest` usan `DatabaseMigrations`, no `RefreshDatabase` (D7) | dentro de la transacción de `RefreshDatabase`, M3/M5/M6 escriben la fila fuera del punto de guardado: el fallo forzado aborta la transacción de la prueba (25P02) y ninguna aserción de estado se puede leer; el control M6 sería imposible. Con confirmaciones reales M3 falla por «usuario existe» y M5 por «7 ≠ 10». El disparador de prueba cae con la tabla en el rollback; la función se borra en `afterEach` |
| 2 | Precondición `git status --porcelain` acotada a los archivos del parche, no a `software/api` | trabajo S9 concurrente dejó archivos sin confirmar en `software/api` durante los `[MUT]`; ninguno tocaba archivos de S10 |
| 3 | Sin corrida roja previa por prueba (1.1, 1.2, 2.1, 3.1, 3.3) | ley del implementador: prueba e implementación juntas; los `[MUT]` prueban que cada prueba muerde |

| Registro | `wc -l` |
|---|---|
| `openspec/changes/add-sensitive-operation-audit/**/*.md` al cierre | 653 |
