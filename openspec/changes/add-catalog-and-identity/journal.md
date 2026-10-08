# Journal — add-catalog-and-identity (S1)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv (reclamado en add-project-skeleton, misma sesión)
- Fase: /proposal, en paralelo con la propuesta de S0 (solo artefactos de spec; el apply espera el archivo de S0).
- Tier objetivo según ROADMAP: A.

## 2026-10-07 — spec-engineer: proposal, deltas, tasks borrador

Producido: `proposal.md`; deltas `specs/identity-access`, `specs/catalog`, `specs/seed-data`, `specs/app-shell`
(capacidades nuevas, `## Purpose` incluido); `tasks.md` borrador (8 grupos, `[MUT]` declarados M1–M11).
`openspec validate add-catalog-and-identity --strict`: válido. Ancla de transporte barrida con
`/usr/bin/grep`: todo hit lleva `[ancla: …]`; control positivo con una línea sin ancla plantada en el
scratchpad, detectada.

Supuestos (9, en `proposal.md` § Assumptions): códigos de rol literales de § 3; stock mínimo a S5; sin
escritura de lotes (S2); sin borrado, cambio de rol ni desactivación; lote vencido si `expires_on` ≤ hoy en
`America/Bogota`; mapa de capacidades completo de § 3 declarado ahora (auxiliar y regente con
`patients.view`); `SEED_USER_PASSWORD` con valor solo de desarrollo y sin usuarios demo en producción;
listados sin paginación; sin pantallas de administración.

Preguntas abiertas para el architect:
1. Dónde vive el login con sesión: `api` con estado (Sanctum `statefulApi`) o grupo `web` bajo `/api`; fija
   el estado observable del escenario "Origen ajeno a la SPA" (hoy sin código HTTP a propósito).
2. Cómo reactivar CSRF en pruebas (el framework la omite con `runningUnitTests`); sin eso 5.2 y M5 son
   falso verde.
3. Router de la SPA: si S0 no lo trae, es dependencia nueva (criterio de architect).
4. Herramienta gratuita de lint OpenAPI (5.16) y ubicación del archivo.
5. S0 dice "único valor por defecto de credencial = contraseña de BD"; `SEED_USER_PASSWORD` añade otro
   valor por defecto solo de desarrollo. Conciliar al aplicar S0/S1.

Candidatos de deuda (el Orchestrator asigna id): cambio de rol / desactivación de usuario con auditoría
(tras S3); RN-05 puede necesitar un segundo `regente_farmacia` semilla (decisión de S3).

## 2026-10-07 — architect: design.md + refinamiento de tasks

Producido: `design.md` (D1–D11, tabla API contract de 13 filas, Data impact, 3 riesgos). Respuestas a las preguntas abiertas:
1. Login en `routes/api.php` con `statefulApi()`. Origen ajeno o sin `Origin`/`Referer` → **403 `forbidden`** sin
   consultar base, sin limitador, sin `Set-Cookie` (evita 500 por sesión ausente y oráculo de contraseñas sin CSRF).
   Sin bearer: `getAccessTokenFromRequestUsing` nulo, sin tabla `personal_access_tokens`.
2. `App\Http\Middleware\ValidateCsrfToken` con `runningUnitTests()` = `false`, registrado en Sanctum y en `web`;
   `tests/Support/SpaClient` con tarro de cookies y `forgetGuards()` por petición (sin él M4 no prueba nada).
3. `react-router` v7 modo datos. Rechazadas: sin router, TanStack Router, wouter.
4. `dedoc/scramble` (require-dev) → `software/api/openapi.json` + `@redocly/cli` fijado en raíz + chequeo de deriva en CI.
   Rechazado YAML a mano (deriva silenciosa); queda como plan B acotado a 30 min.
5. Conflicto con S0: delta `MODIFIED` de `runtime-environment` (lista cerrada `DB_PASSWORD`, `SEED_USER_PASSWORD`;
   inerte en producción) → nueva tarea 0.1 para el spec-engineer, tras archivar S0. Valor por defecto único en
   `config/dispensart.php`; compose sin default.

Otras decisiones: `role varchar(32)` + `users_role_check` sin default (un default concedería permisos en silencio);
PG `ENUM` rechazado (no reversible limpio). `app.timezone` queda UTC (contrato de log de S0); reloj de negocio
`BusinessCalendar` en `America/Bogota` — corrige el borrador de 4.2. Sin `spatie/laravel-permission`, sin
`Gate::before`, sin `laravel-lang`, sin axios ni MSW. Respuestas con envoltura `data`.

Segundo `regente_farmacia`: **no se siembra en S1** (la spec fija exactamente 5 usuarios); S3 lo añade con un
`MODIFIED` de `seed-data` si su prueba de RN-05 lo exige. Recomendación: nota en la fila S3 del ROADMAP, no deuda.
Candidato de deuda confirmado: cambio de rol / desactivación con auditoría (tras S3).

Tasks: nueva 0.1 (precondición), 7.3 (CI de OpenAPI); 1.1, 2.3, 4.2, 5.1, 5.16, 6.1–6.3, 7.1 precisados con
referencias a design. Orden 0→5 api secuencial → 6 web ∥ 7.1 → 7.2–7.3 → 8. api→web obligatorio (contrato nuevo).

Riesgos: falso verde en sesión/CSRF (D2 + M4/M5 + humo 7.2); Scramble incompatible con Laravel 13 (plan B);
limitador por IP tras dos proxies (`TrustProxies` en rangos privados, revisar en S8).

## 2026-10-07 — spec-engineer: corrección de hallazgos del spec-validator (solo prosa)

- `tasks.md`: 1.4, 5.16, 7.3, 8.1, 8.2 marcadas "Cimiento" con comando de verificación; 3.2, 3.3 y 7.2 citan
  escenarios por nombre; definición de "Cimiento" en la cabecera. Orden del architect y tarea 0.1 intactos.
- `proposal.md` recortado a una página; Assumptions conservados (7 alineado con design D10). Specs sin cambios.
- `openspec validate add-catalog-and-identity --strict`: válido.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-catalog-and-identity` tras corregir citas de tareas y largo de la propuesta
  (hallazgos solo de prosa, re-verificados por spec-validator). Ancla de transporte: 68 hits, 68 con ancla.
- Condición 1 (alcance exacto de S1): OK. Usuarios y 5 roles, Sanctum SPA, Policies, bodegas, productos,
  lotes, siembra. Login + shell de SPA: mínimo para que la autenticación sea alcanzable. OpenAPI de los
  endpoints nuevos: exigencia de la parte A sobre lo que esta tajada expone.
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): A, coincide con ROADMAP.
- Condición 4 (ADR / RN): `react-router` y Scramble son dependencias nuevas, no reabren ADR; ninguna RN se debilita.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier A
- Candidato de deuda (cambio de rol con auditoría) queda fuera de alcance; se filará si una tajada lo
  descubre como necesario. Apply espera el archivo de S0.

## 2026-10-07 — spec-engineer: tarea 0.1

- Delta `MODIFIED` `specs/runtime-environment/spec.md`, "Secretos fuera del repositorio" (encabezado idéntico al
  de la spec viva): lista cerrada `DB_PASSWORD` + `SEED_USER_PASSWORD`, inerte en producción (design D10). Bloque
  completo copiado; "Credencial reemplazable" ampliado; dos escenarios nuevos. 0.1 `[x]`. Validación estricta: válida.

## 2026-10-07 — Orchestrator: /apply

- S0 archivado; specs vivas `runtime-environment`, `service-health`, `ci-pipeline`.
- Preflight: stack compose sano (db, api, web `healthy`). Línea base de suites (corrida 1 de 3):
  backend Pest 26 pasan / 143 aserciones; frontend Vitest 8 pasan.
- Insumo heredado de S0: las pruebas fuerzan `APP_KEY` vacía; Sanctum con sesión por cookie necesita una
  clave de prueba generada en tiempo de ejecución.
- Tarea 0.1 (delta MODIFIED sobre `runtime-environment`) delegada al spec-engineer.

## 2026-10-08 — backend-implementer: grupos 1–5 (`software/api`)

Tareas 1.1–5.16 `[x]`. Commits en `dev`: `09ea4dc`, `bdbb36a`, `e2ecaa3`, `044352e`, `799df06`, `261ee7a`.
Dependencias nuevas: `laravel/sanctum` 4.3.3 (require), `dedoc/scramble` 0.13.47 (require-dev), `@redocly/cli` 2.60.0 (raíz, dev).

### Corrida de cierre (1 de 1 del implementador)

| Comando | Resultado |
|---|---|
| `vendor/bin/pint --test` | pasa |
| `vendor/bin/phpstan analyse` (nivel 6) | 0 errores |
| `php artisan test` (PostgreSQL `dispensart_test`) | 166 pasan / 686 aserciones (línea base 26) |
| `npm run openapi:lint` (Redocly) | válido, 0 avisos |
| deriva OpenAPI (`composer openapi` + `git diff --exit-code` en el host) | exit 0; control positivo: `/lots` quitado del archivo → exit 1; re-exportado → exit 0 |

### Líneas

| Tipo | Líneas añadidas |
|---|---|
| Producto (`app`, `config`, `database`, `routes`, `bootstrap`, `lang`) | 2120 |
| Pruebas (`tests`, `phpunit.xml`) | 1473 |
| Contrato generado (`openapi.json`) | 1266 |

### Escenario → prueba → archivo:línea (rutas relativas a `software/api/tests/Feature/`)

| Capacidad | Escenario | Prueba | Archivo:línea |
|---|---|---|---|
| identity-access | Usuario con rol válido | guarda un usuario con rol válido insertado directamente | Identity/UserRoleConstraintTest.php:22 |
| identity-access | Rol fuera del conjunto rechazado por la base | rechaza en la base un rol fuera del conjunto | Identity/UserRoleConstraintTest.php:29 |
| identity-access | Rol nulo rechazado por la base | rechaza en la base un usuario sin rol | Identity/UserRoleConstraintTest.php:39 |
| identity-access | Capacidades de auxiliar / regente / medico / auditor / admin | resuelve exactamente las capacidades de cada rol (dataset 5) | Identity/RoleAbilitiesTest.php:12 |
| identity-access | Capacidades de auditor, solo lectura | niega al auditor toda capacidad de escritura | Identity/RoleAbilitiesTest.php:30 |
| identity-access | Capacidades de admin, sin datos clínicos ni inventario | niega al admin datos clínicos e inventario | Identity/RoleAbilitiesTest.php:42 |
| identity-access | Capacidad desconocida denegada a todo rol | niega una capacidad desconocida a los cinco roles | Identity/RoleAbilitiesTest.php:48 |
| identity-access | Petición sin sesión | GET /api/warehouses › responde 401 sin sesión | Catalog/WarehouseEndpointTest.php:34 |
| identity-access | Petición sin sesión (Bearer falso → 401, no 500) | responde 401, no 500, ante una cabecera Bearer falsa | Identity/LoginTest.php:133 |
| identity-access | 401 en JSON aunque falte Accept | responde 401 en JSON aunque falte Accept, sin redirección | Identity/MeTest.php:32 |
| identity-access | Rol falsificado por el cliente ignorado | ignora un rol falsificado en el cuerpo o en cabeceras | Catalog/ProductEndpointTest.php:100 |
| identity-access | Cambio de rol en la base vigente en la petición siguiente | aplica en la petición siguiente un cambio de rol hecho en la base | Catalog/WarehouseEndpointTest.php:94 |
| identity-access | Rechazo por permisos (cuerpo exacto) | rechaza con 403 a los demás roles con el cuerpo exacto y sin crear | Catalog/WarehouseEndpointTest.php:77 |
| identity-access | Recurso inexistente | responde 404 en español sin nombre de clase a un producto inexistente | Catalog/ProductEndpointTest.php:150 |
| identity-access | Validación con errores por campo | rechaza sin code con errors.code en español | Catalog/ProductEndpointTest.php:83 |
| identity-access | Emisión de la cookie CSRF | emite la cookie XSRF-TOKEN legible por la SPA con un 204 | Identity/CsrfTest.php:11 |
| identity-access | Login sin token CSRF | rechaza con 419 el login sin X-XSRF-TOKEN y no abre sesión | Identity/CsrfTest.php:23 |
| identity-access | Escritura con token CSRF caducado | rechaza con 419 una escritura con token de otra sesión | Identity/CsrfTest.php:40 |
| identity-access | Lectura sin token CSRF | permite lecturas con sesión sin X-XSRF-TOKEN | Identity/CsrfTest.php:57 |
| identity-access | Credenciales válidas | abre sesión con credenciales válidas, regenera la sesión y no devuelve token | Identity/LoginTest.php:27 |
| identity-access | Correo con mayúsculas | compara el correo sin distinguir mayúsculas | Identity/LoginTest.php:50 |
| identity-access | Contraseña incorrecta | rechaza una contraseña incorrecta sin abrir sesión | Identity/LoginTest.php:59 |
| identity-access | Correo inexistente indistinguible | responde igual para un correo inexistente que para una contraseña incorrecta | Identity/LoginTest.php:67 |
| identity-access | Datos incompletos o mal formados | rechaza datos incompletos o mal formados por campo (dataset 3) | Identity/LoginTest.php:75 |
| identity-access | Demasiados intentos fallidos | bloquea con 429 el sexto intento tras cinco fallos | Identity/LoginTest.php:90 |
| identity-access | Éxito reinicia el contador | reinicia el contador de fallos tras un inicio de sesión exitoso | Identity/LoginTest.php:102 |
| identity-access | Origen ajeno a la SPA | rechaza con 403 y sin cookie el login desde un origen ajeno (dataset 2) | Identity/LoginTest.php:117 |
| identity-access | Cierre de sesión exitoso | cierra la sesión: la cookie anterior deja de autenticar | Identity/LogoutTest.php:11 |
| identity-access | Cierre sin sesión | responde 401 al cerrar sin sesión | Identity/LogoutTest.php:25 |
| identity-access | Cierre repetido | responde 401, no 500, al repetir el cierre | Identity/LogoutTest.php:32 |
| identity-access | Usuario autenticado | devuelve el usuario de la sesión con las capacidades de su rol | Identity/MeTest.php:12 |
| identity-access | Usuario actual › Sin sesión | responde 401 sin sesión | Identity/MeTest.php:26 |
| identity-access | Admin lista usuarios | lista a un admin los 5 usuarios semilla por nombre | Identity/UsersEndpointTest.php:25 |
| identity-access | Otro rol intenta listar usuarios | rechaza con 403 a los demás roles (dataset 4) | Identity/UsersEndpointTest.php:39 |
| identity-access | Listado › Sin sesión | responde 401 sin sesión | Identity/UsersEndpointTest.php:46 |
| identity-access | Alta exitosa (usuario) | crea un usuario con contraseña con hash que luego inicia sesión | Identity/UsersEndpointTest.php:52 |
| identity-access | Correo duplicado sin distinguir mayúsculas | rechaza un correo duplicado sin distinguir mayúsculas | Identity/UsersEndpointTest.php:78 |
| identity-access | Rol inválido o datos incompletos | rechaza rol inválido o datos incompletos (dataset 3) | Identity/UsersEndpointTest.php:91 |
| identity-access | Otro rol intenta crear usuarios | rechaza con 403 a los demás roles y no crea usuario (dataset 4) | Identity/UsersEndpointTest.php:111 |
| identity-access | Alta › Sin sesión | responde 401 sin sesión y no crea usuario | Identity/UsersEndpointTest.php:121 |
| catalog | Cualquier rol consulta bodegas | devuelve a cualquier rol las 3 bodegas semilla (dataset 5) | Catalog/WarehouseEndpointTest.php:17 |
| catalog | Sin bodegas | devuelve una lista vacía sin bodegas | Catalog/WarehouseEndpointTest.php:28 |
| catalog | Alta exitosa (bodega) | crea una bodega como admin | Catalog/WarehouseEndpointTest.php:42 |
| catalog | Código o nombre duplicado | rechaza código o nombre duplicado (dataset 2) | Catalog/WarehouseEndpointTest.php:51 |
| catalog | Datos incompletos o demasiado largos | rechaza datos incompletos o demasiado largos (dataset 2) | Catalog/WarehouseEndpointTest.php:65 |
| catalog | Otro rol intenta crear (bodega) | rechaza con 403 a los demás roles (dataset 4) | Catalog/WarehouseEndpointTest.php:77 |
| catalog | Alta de bodegas › Sin sesión | responde 401 sin sesión y no crea bodega | Catalog/WarehouseEndpointTest.php:86 |
| catalog | Cambio parcial | cambia solo el nombre y conserva el código | Catalog/WarehouseEndpointTest.php:117 |
| catalog | Conservar su propio código | permite conservar su propio código | Catalog/WarehouseEndpointTest.php:124 |
| catalog | Cuerpo vacío | devuelve la bodega intacta con un cuerpo vacío | Catalog/WarehouseEndpointTest.php:131 |
| catalog | Código de otra bodega | rechaza el código de otra bodega sin cambiarla | Catalog/WarehouseEndpointTest.php:138 |
| catalog | Bodega inexistente | responde 404 a una bodega inexistente | Catalog/WarehouseEndpointTest.php:149 |
| catalog | Otro rol intenta modificar (bodega) | rechaza con 403 a un regente sin cambiar la bodega | Catalog/WarehouseEndpointTest.php:156 |
| catalog | Cualquier rol consulta productos | devuelve a cualquier rol los 6 productos semilla (dataset 5) | Catalog/ProductEndpointTest.php:15 |
| catalog | Sin productos | devuelve una lista vacía sin productos | Catalog/ProductEndpointTest.php:29 |
| catalog | Consulta de productos › Sin sesión | responde 401 sin sesión | Catalog/ProductEndpointTest.php:33 |
| catalog | Alta exitosa de control especial | crea un producto de control especial | Catalog/ProductEndpointTest.php:39 |
| catalog | Campos opcionales omitidos | usa presentation null e is_controlled false si se omiten | Catalog/ProductEndpointTest.php:56 |
| catalog | Datos inválidos (producto) | rechaza datos inválidos por campo (dataset 3) | Catalog/ProductEndpointTest.php:66 |
| catalog | Otro rol intenta crear (producto) | rechaza con 403 a los demás roles (dataset 4) | Catalog/ProductEndpointTest.php:91 |
| catalog | Alta de productos › Sin sesión | responde 401 sin sesión y no crea producto | Catalog/ProductEndpointTest.php:110 |
| catalog | Marcar como control especial | marca como control especial sin cambiar los demás campos | Catalog/ProductEndpointTest.php:127 |
| catalog | Código de otro producto | rechaza el código de otro producto sin cambiarlo | Catalog/ProductEndpointTest.php:140 |
| catalog | Producto inexistente | responde 404 en español sin nombre de clase | Catalog/ProductEndpointTest.php:150 |
| catalog | Otro rol intenta modificar (producto) | rechaza con 403 a un auditor sin cambiar el producto | Catalog/ProductEndpointTest.php:158 |
| catalog | Todos los lotes en orden de vencimiento | devuelve todos los lotes por vencimiento y luego id | Catalog/LotEndpointTest.php:13 |
| catalog | Filtro por producto | filtra por producto | Catalog/LotEndpointTest.php:27 |
| catalog | Producto sin lotes o inexistente | devuelve una lista vacía para un producto inexistente | Catalog/LotEndpointTest.php:38 |
| catalog | Filtro mal formado | rechaza un filtro mal formado | Catalog/LotEndpointTest.php:46 |
| catalog | Consulta de lotes › Sin sesión | responde 401 sin sesión | Catalog/LotEndpointTest.php:53 |
| catalog | Lote que venció ayer / hoy / mañana (HTTP) | calcula is_expired con el día de Bogotá en cada respuesta | Catalog/LotEndpointTest.php:57 |
| catalog | Lote que venció ayer | marca vencido un lote que venció ayer | Catalog/LotExpiryTest.php:22 |
| catalog | Lote que vence hoy | marca vencido un lote que vence hoy | Catalog/LotExpiryTest.php:28 |
| catalog | Lote que vence mañana | no marca vencido un lote que vence mañana | Catalog/LotExpiryTest.php:34 |
| catalog | Frontera 23:30 Bogotá (design D6) | en la frontera de 23:30 Bogotá usa el día de Bogotá | Catalog/LotExpiryTest.php:40 |
| catalog | Cambio de día sin escritura | vence al cambiar de día sin escritura alguna | Catalog/LotExpiryTest.php:48 |
| catalog | Lote duplicado para el mismo producto | rechaza un lote duplicado para el mismo producto | Catalog/CatalogIntegrityTest.php:64 |
| catalog | Mismo código de lote en otro producto | acepta el mismo código de lote en otro producto | Catalog/CatalogIntegrityTest.php:76 |
| catalog | Lote huérfano o sin vencimiento | rechaza un lote huérfano · rechaza un lote sin vencimiento | Catalog/CatalogIntegrityTest.php:85, :94 |
| catalog | Código de bodega o de producto duplicado en la base | rechaza bodega con código / nombre existente · producto con código existente | Catalog/CatalogIntegrityTest.php:24, :35, :46 |
| catalog | Borrar producto con lotes | rechaza borrar un producto con lotes y conserva ambos | Catalog/CatalogIntegrityTest.php:104 |
| seed-data | Bodegas y productos sembrados | siembra exactamente las 3 bodegas y 6 productos | Seed/SeedTest.php:34 |
| seed-data | Lotes por producto | siembra 2 o 3 lotes por producto y al menos uno no vencido del controlado | Seed/SeedTest.php:44 |
| seed-data | Distribución de vencimientos | distribuye vencimientos: vencido, 1–29, 31–90 y más de 90 días | Seed/SeedTest.php:55 |
| seed-data | Sin datos reales (correos) | no contiene correos fuera de dispensart.test en el código de siembra | Seed/SeedTest.php:66 |
| seed-data | Un usuario por rol | crea exactamente un usuario por rol con los correos indicados | Seed/SeedTest.php:78 |
| seed-data | Inicio de sesión con la contraseña documentada | permite iniciar sesión con la contraseña documentada | Seed/SeedTest.php:90 |
| seed-data | Contraseña reemplazada por entorno | usa SEED_USER_PASSWORD si está definida | Seed/SeedTest.php:99 |
| seed-data | Contraseña guardada con hash | guarda las contraseñas con hash | Seed/SeedTest.php:109 |
| seed-data · runtime-environment | Producción sin contraseña explícita · Valor por defecto inerte en producción | en producción sin SEED_USER_PASSWORD siembra el catálogo, ningún usuario | Seed/SeedTest.php:117 |
| seed-data | Siembra repetida | repite la siembra sin error y con los mismos conteos | Seed/SeedTest.php:135 |
| seed-data | Cambios del admin sobreviven al reinicio (nivel seeder) | conserva el nombre que el admin dio a una bodega semilla | Seed/SeedTest.php:145 |
| seed-data | Filas no semilla intactas | deja intactas las filas que no son semilla | Seed/SeedTest.php:156 |
| — (cimiento 1.4) | Fábricas válidas | Factory de usuario / de bodega, producto y lote | FactoryTest.php:14, :20 |

Fuera de este bloque: seed-data "Arranque desde cero" y "Cambios del admin sobreviven al reinicio" en el stack (7.1), humo con cookies reales (7.2), app-shell (6.x).

### [MUT] (corridas delta filtradas)

| n | mutation | Applied → FAILS m/k: test | Restored → PASSES k/k |
|---|---|---|---|
| M1 | `Role::RegenteFarmacia` gana `Ability::CatalogManage` | FAILS 1/12: resuelve exactamente las capacidades de cada rol › regente_farmacia | PASSES 12/12 |
| M2 | `ProductPolicy::create` devuelve `true` | FAILS 5/23: rechaza con 403 a los demás roles (4 del dataset) + ignora un rol falsificado | PASSES 23/23 |
| M3 | grupo `/api` sin `auth:sanctum` (`Route::middleware([])`) | FAILS 2/24: GET /api/warehouses › responde 401 sin sesión; POST › responde 401 sin sesión | PASSES 24/24 |
| M4 | login copia `role` a la sesión y `WarehousePolicy::create` autoriza con ese valor | FAILS 1/24: aplica en la petición siguiente un cambio de rol hecho en la base | PASSES 24/24 |
| M5 | `ValidateCsrfToken::$except = ['api/auth/login']` | FAILS 1/4: rechaza con 419 el login sin X-XSRF-TOKEN | PASSES 4/4 |
| M6 | correo inexistente → `ValidationException` "El correo no está registrado." | FAILS 1/12: responde igual para un correo inexistente que para una contraseña incorrecta | PASSES 12/12 |
| M7 | sin `RateLimiter::hit` en el fallo | FAILS 1/12: bloquea con 429 el sexto intento tras cinco fallos | PASSES 12/12 |
| M8 | migración sin `users_role_check` | FAILS 1/3: rechaza en la base un rol fuera del conjunto | PASSES 3/3 |
| M9 | migración sin `lots_product_id_lot_code_unique` | FAILS 1/9: rechaza un lote duplicado para el mismo producto | PASSES 9/9 |
| M10 | `Lot::isExpiredOn` con `<` en lugar de `<=` | FAILS 3/6: vence hoy; frontera 23:30 Bogotá; cambio de día sin escritura | PASSES 6/6 |
| M11 | `WarehouseSeeder` con `create()` en lugar de `firstOrCreate` por código | FAILS 3/12: repite la siembra; conserva el nombre del admin; filas no semilla intactas | PASSES 12/12 |

### Anclas de transporte (cláusula → archivo:línea, `software/api/`)

| Cláusula del ancla | Archivo:línea |
|---|---|
| middleware `auth:sanctum` | routes/api.php:23 |
| ruta `POST /api/auth/login` | routes/api.php:21 |
| ruta `POST /api/auth/logout` | routes/api.php:24 |
| ruta `GET /api/auth/me` | routes/api.php:25 |
| ruta `GET /api/users` · `POST /api/users` | routes/api.php:27 · :28 |
| ruta `GET /api/warehouses` · `POST` · `PATCH /{id}` | routes/api.php:30 · :31 · :32 |
| ruta `GET /api/products` · `POST` · `PATCH /{id}` | routes/api.php:34 · :35 · :36 |
| ruta `GET /api/lots` | routes/api.php:38 |
| ruta de Sanctum `sanctum/csrf-cookie` | vendor/laravel/sanctum/src/SanctumServiceProvider.php:73 (grupo `web`, :76) |
| sesión solo para orígenes de la SPA (`statefulApi`) | bootstrap/app.php:33 |
| render JSON forzado para `api/*` | bootstrap/app.php:52, :58 |
| invitado → 401 sin redirección | bootstrap/app.php:37 |
| render de AuthenticationException | app/Exceptions/ApiExceptionRenderer.php:23 |
| render de ValidationException | app/Exceptions/ApiExceptionRenderer.php:24 |
| render de AuthorizationException (403 `forbidden`) | app/Exceptions/ApiExceptionRenderer.php:29, :39 |
| render de ModelNotFoundException (404 `not_found`) | app/Exceptions/ApiExceptionRenderer.php:29, :40 |
| middleware CSRF + render de TokenMismatchException | bootstrap/app.php:40; app/Http/Middleware/ValidateCsrfToken.php:15, :20; app/Exceptions/ApiExceptionRenderer.php:42 |
| acción de login (respuesta idéntica) | app/Actions/Identity/LoginAction.php:43, :46, :49 |
| limitador de login | app/Actions/Identity/LoginAction.php:35, :47, :52 |
| FormRequest de login (incluye 403 por origen ajeno) | app/Http/Requests/Auth/LoginRequest.php:14, :29 |
| FormRequest de alta de usuario | app/Http/Requests/Users/StoreUserRequest.php:32 |
| FormRequest de bodega | app/Http/Requests/Catalog/StoreWarehouseRequest.php:22; UpdateWarehouseRequest.php:23 |
| FormRequest de producto | app/Http/Requests/Catalog/StoreProductRequest.php:22; UpdateProductRequest.php:22 |
| FormRequest de consulta de lotes | app/Http/Requests/Catalog/ListLotsRequest.php:21 |
| Policy de usuarios | app/Policies/UserPolicy.php:15, :20 |
| Policy de bodegas | app/Policies/WarehousePolicy.php:21, :26 |
| Policy de productos | app/Policies/ProductPolicy.php:21, :26 |
| recurso de lote (`is_expired`) | app/Http/Resources/LotResource.php:27 |
| sin bearer | app/Providers/AppServiceProvider.php:32 |

### Barridos (`/usr/bin/grep`, con control positivo plantado en el scratchpad)

| # | Patrón buscado | Alcance | Hits | Control positivo |
|---|---|---|---|---|
| 1 | `Log::*(` con `$email`, `$password`, `password`, `->email`, `->name` | app database routes bootstrap | 0 | archivo plantado con `Log::warning(..., ['email' => $email, ...])` → 1 |
| 2 | `dd(`, `dump(`, `var_dump(`, `ray(` | app database routes bootstrap tests config | 0 | `dd($x);` plantado → 1 |
| 3 | `HasApiTokens`, `personal_access_tokens`, `createToken` | app database config routes | 0 | plantado → 2 |
| 4 | `actingAs`, `withoutMiddleware` | LoginTest, LogoutTest, CsrfTest, SpaClient | 1 (comentario "sin actingAs", LoginTest.php:10) | UsersEndpointTest.php → 8 |
| 5 | `protected $except`, `validateCsrfTokens(except`, `withoutMiddleware(...Csrf` | app bootstrap tests | 0 | `protected $except = [...]` plantado → 1 |
| 6 | `Facades\DB` | app/Http/Controllers | 0 | database/migrations → 1 archivo |
| 7 | `base64:` + 20 caracteres (APP_KEY) | `git grep` en software | 0 | `APP_KEY=base64:AAAA…` plantado → 1 |
| 8 | `sqlite` | phpunit.xml tests | 2 (comentarios "nunca SQLite", phpunit.xml:28, DatabaseConnectionTest.php:5) | config/database.php → 3 |
| 9 | correos fuera de `@dispensart.test` | database/seeders database/factories | 0 | `real@gmail.com` plantado → 1 |

### Desviaciones del design (sin cambio de contrato salvo nota)

| Punto | Design | Aplicado | Motivo |
|---|---|---|---|
| D1 origen ajeno | corte en `LoginController` | `LoginRequest::authorize()` → `hasSession()` | mismo 403, antes de validar; controlador sin `if` |
| D2 CSRF | `ValidateCsrfToken` sin atajo de pruebas | además `hasValidOrigin()` = `false`; extiende `PreventRequestForgery` (base no obsoleta de Laravel 13) | Laravel 13 acepta `Sec-Fetch-Site: same-origin` sin token; la spec exige `X-XSRF-TOKEN` en toda escritura. **La SPA debe enviar siempre `X-XSRF-TOKEN`** (ya lo pide D8) |
| D3 clave del limitador | `sha1($email)` | `hash('sha256', $email)` | el preset `arch()->security()` de S0 prohíbe `sha1` |
| D3 hash ficticio | literal fijo | `Hash::make` aleatorio una vez por proceso | mismo costo que los hashes reales, sin literal versionado |
| D5 | — | `redirectGuestsTo(null)` | sin él, 401 sin `Accept` daba 500 (`Route [login] not defined`) |
| D9 | — | `config/session.php` cookie por defecto `dispensart-session` (antes slug de `APP_NAME`); servidor OpenAPI relativo `/api`; sin UI `/docs/api` | OpenAPI determinista; `SESSION_COOKIE` sigue reemplazándola |
| D11 usuarios semilla | `firstOrCreate` | `firstOrNew` + `forceFill` | `role` no es asignable en masa; misma semántica (solo crea si falta) |
| Altas | 201 implícito (`wasRecentlyCreated`) | `->response()->setStatusCode(201)` explícito | el OpenAPI inferido documenta 201 |

### Para devops (7.1–7.3) y frontend (6.x)

- **Conflicto a decidir antes de 7.1:** compose arranca `api` con `APP_ENV=production` por defecto; por D10 la siembra no crea usuarios sin `SEED_USER_PASSWORD` en producción, y compose no le da valor → "Arranque desde cero" (login con cada usuario semilla) no se cumple tal como está. Opciones: `APP_ENV` local por defecto en el stack de desarrollo, o documentar `SEED_USER_PASSWORD` obligatoria.
- Imagen `api` debe reconstruirse: `laravel/sanctum` es dependencia de producción nueva. Entrypoint: `db:seed --force` tras `migrate --force`.
- Variables nuevas para `.env.example`: `SANCTUM_STATEFUL_DOMAINS` (defecto `localhost:8090,127.0.0.1:8090`; cambiar si `WEB_PORT` ≠ 8090), `TRUSTED_PROXIES` (defecto rangos privados), `SESSION_COOKIE`, `SESSION_SECURE_COOKIE` (vacía = Secure cuando la petición llega por HTTPS vía proxy de confianza), `SEED_USER_PASSWORD` (defecto solo desarrollo `dispensart-dev-only`, en `config/dispensart.php`).
- CI 7.3: `composer openapi` necesita PostgreSQL (migra `dispensart_test` para leer columnas); `composer openapi:check` usa `git diff` → correr en el runner con el repo, no dentro de `api-tools` (el bind mount no es repositorio git). Lint: `npm run openapi:lint` en la raíz.
- Frontend: respuestas exitosas con envoltura `data`; cookie de sesión `dispensart-session`.

### Deuda (en prosa; el Orchestrator asigna id)

- Falta una prueba de `TrustProxies`: que `X-Forwarded-For` desde un proxy privado separe los contadores del limitador y que el mismo encabezado desde una IP pública se ignore (riesgo 3 del design). Las pruebas actuales fijan `REMOTE_ADDR` 127.0.0.1, fuera de los rangos de confianza.
- Si el admin cambia el código de un producto semilla, la siguiente siembra recrea el producto original con su código (la clave natural es el código). Ningún escenario lo cubre; impacto bajo.
- `npm audit` en la raíz: 4 vulnerabilidades altas transitivas de `@fission-ai/openspec` (braces/micromatch), previas a este cambio; `@redocly/cli` no añade ninguna. Herramienta de desarrollo, no se despliega.

## 2026-10-08 — Orchestrator: bloque api cerrado, web ∥ devops

- Backend 1.1–5.16 `[x]`. Corrida de cierre backend (corrida 2 de 3): Pint limpio, Larastan 0, Pest 166 / 686.
  M1–M11 aplicados → FALLA, restaurados → PASA (tabla en la sección del backend).
- Decisión del Orchestrator (pregunta abierta del backend): el stack de compose es el de evaluación local;
  `APP_ENV=local` por defecto y `SEED_USER_PASSWORD` con valor por defecto solo de desarrollo, ambos
  sobrescribibles por `.env` (dentro del delta MODIFIED de `runtime-environment`). Así "Arranque desde
  cero" siembra los 5 usuarios.
- Deuda del backend: prueba de proxy de confianza → se salda dentro de S1 (delta). Resiembra por clave
  natural tras cambiar el código de un producto semilla → comportamiento esperado de la siembra
  idempotente; va a supuestos del README (S8), sin fila. `npm audit` en dependencias de la CLI de
  OpenSpec (herramienta de desarrollo en la raíz, no producto) → sin fila.
- Delegación: frontend-implementer (grupo 6) ∥ devops-implementer (grupo 7). Sin contrato nuevo entre ellos.

## 2026-10-08 — backend-implementer: deuda de proxy saldada (delta)

| Escenario (design D3, riesgo 3) | Prueba | Archivo:línea | Mutación → resultado |
|---|---|---|---|
| Tras proxy privado de confianza el limitador usa la IP reenviada | tras un proxy privado de confianza, cuenta los fallos por la IP reenviada del cliente | software/api/tests/Feature/Identity/LoginProxyTest.php:26 | `trustedproxy.proxies` = `127.0.0.2` → FALLA 1/2; restaurado → PASA 2/2 |
| `X-Forwarded-For` desde fuente no confiable se ignora | desde una fuente no confiable ignora X-Forwarded-For y cuenta por la IP de conexión | software/api/tests/Feature/Identity/LoginProxyTest.php:42 | `trustedproxy.proxies` = `*` → FALLA 1/2; restaurado → PASA 2/2 |

Corrida delta `--filter=LoginProxyTest`: 2 pasan / 14 aserciones. Pint `--test` del archivo: pasa. Larastan: 0 errores. Sin cambios de código de aplicación. La primera fila de deuda de la sección anterior queda saldada.

## 2026-10-08 — devops-implementer: grupo 7 (stack, humo, CI)

Tareas 7.1–7.3 `[x]`. Decisión del Orchestrator aplicada: `APP_ENV=${APP_ENV:-local}` en compose;
`SEED_USER_PASSWORD: ${SEED_USER_PASSWORD:-}` sin valor por defecto en compose (D10, escenario "Lista
cerrada"): el valor solo de desarrollo vive en `config/dispensart.php` y se documenta en `software/.env.example`.

### Cambios

| Archivo | Cambio |
|---|---|
| `software/docker/api/entrypoint.sh` | `db:seed --force` tras `migrate`; stdout de Artisan capturado, stderr (avisos JSON del seeder) pasa |
| `software/docker/web/api-proxy.conf` | `Origin` y `Referer` explícitos; `X-Forwarded-For $remote_addr` (web es el borde: el cliente no inyecta IP al limitador; revisar en S8 si hay balanceador) |
| `software/compose.yaml` | `APP_ENV` local; `SEED_USER_PASSWORD`, `SANCTUM_STATEFUL_DOMAINS` (sigue a `WEB_PORT`), `TRUSTED_PROXIES` |
| `software/.env.example` | las 4 variables nuevas, contraseña semilla marcada solo desarrollo; vacías = valor por defecto |
| `software/docker/api/Dockerfile` | `mkdir -p resources/views` (ver hallazgo) |
| `software/docker/smoke/auth-smoke.sh` | humo versionado (bash + curl, tarro de cookies) |
| `.github/workflows/ci.yml` | trabajo `backend`: `composer openapi:check` en el runner + `npm ci` raíz + `npm run openapi:lint`; `paths` suma `package.json`/`package-lock.json` raíz |

### Hallazgo: S0 no arrancaba desde un clon limpio

`software/api/resources/views` es un directorio vacío: git no lo versiona. En el árbol de trabajo existe; en un
clon nuevo falta y `artisan optimize` (caché de vistas) sale con error → `api` reinicia en bucle, nunca sana.
La verificación en frío de S0 corrió sobre el árbol de trabajo y no lo vio. Reproducido con `git worktree` de
HEAD; corregido en la imagen. CI aún no construye imágenes (S8): la construcción desde checkout limpio lo cubrirá.

### Verificación en frío (desde `git worktree` de HEAD + estos cambios)

El árbol de trabajo principal tiene trabajo en curso del frontend (`software/web/src`, `App.test.tsx` no compila
con `tsc`) y de otra sesión (`software/api`); la construcción de `web` falla ahí. Se verificó desde un worktree
limpio de HEAD con solo los archivos devops superpuestos; sin tocar el trabajo ajeno.

| Comprobación | Resultado |
|---|---|
| `docker compose config -q` | exit 0 |
| `down -v` + `up --build --wait` | db, api, web `healthy` |
| `/health` · `/ready` por `localhost:8090` | 200 · 200 |
| `auth-smoke.sh` | 38 comprobaciones, 0 fallas, exit 0 |
| Control positivo: `SEED_USER_PASSWORD=wrong-on-purpose auth-smoke.sh` | 15 fallas, exit 1 |
| Conteos bodegas/productos/lotes/usuarios tras arranque desde cero | 3 / 6 / 14 / 5 |
| Tras `up --force-recreate api` (siembra repetida) | 3 / 6 / 14 / 5 |
| Admin renombra `FC` por PATCH vía proxy, luego recrea `api` | nombre nuevo conservado, 3 bodegas (sin duplicado) |
| `APP_ENV=production` sin `SEED_USER_PASSWORD`, volúmenes vacíos | 3 / 6 / 14 / **0**; log `warning` "Usuarios semilla omitidos…" sin valor |
| `id -u` en api · web | 1000 · 101 (db: upstream, `postgres` en los procesos del servidor) |
| `git check-ignore` software/.env, api/.env, web/.env | 3 ignorados |
| Plantilla completa: cada `${VAR}` de compose en `.env.example` | 12 de 12 (`POSTGRES_*` son `$${}` del contenedor) |

### Escenario → evidencia en el stack

| Capacidad | Escenario | Evidencia |
|---|---|---|
| seed-data | Arranque desde cero | `up --build --wait` + humo: login 200 de los 5 usuarios |
| seed-data | Siembra repetida | conteos iguales tras recrear `api` |
| seed-data | Cambios del admin sobreviven al reinicio | PATCH + recreación: nombre conservado, sin duplicado |
| runtime-environment | Valor por defecto de la siembra inerte en producción | `APP_ENV=production`: 0 usuarios, aviso sin contraseña |
| identity-access | Credenciales válidas | humo: `POST /api/auth/login` 200 con `role` esperado ×5 |
| identity-access | Escritura con token CSRF caducado (sin token) | humo: `POST /api/warehouses` sin `X-XSRF-TOKEN` 419 ×5, con sesión válida |
| identity-access | Cierre de sesión exitoso | humo: logout 204, luego `me` 401 ×5 |
| identity-access | Origen ajeno a la SPA | humo: login sin `Origin` 403 `forbidden` |

### CI (7.3)

| Comprobación | Resultado |
|---|---|
| `actionlint` 1.7.12 (imagen local) sobre `ci.yml` | exit 0; control: workflow con `${{ github.nope }}` → exit 1 |
| Deriva: `composer openapi` (api-tools) + `git diff --exit-code` | exit 0 |
| Control: índice con `openapi.json` sin `/lots`, re-exportado | `git diff` exit 1; índice restaurado → exit 0 |
| `npm run openapi:lint` (raíz) | válido |

### Barrido de secretos (`/usr/bin/grep -rnE`, compose, `.env.example`, `docker/`, workflows)

| Patrón | Hits | Control positivo |
|---|---|---|
| `base64:` ≥20 · `sk-`/`ghp_`/`AKIA` · `PASSWORD[=:]` literal | 6, todos de la lista cerrada: `DB_PASSWORD` desarrollo (3), CI efímera (2), valor solo desarrollo de semilla en el humo (1) | archivo plantado con `APP_KEY=base64:…` y `SEED_USER_PASSWORD=…` → 2 |

### Deuda (prosa; el Orchestrator asigna id)

- El humo repite el literal del valor solo de desarrollo de `SEED_USER_PASSWORD` (segunda fuente además de
  `config/dispensart.php`); si cambia uno sin el otro el humo falla en rojo, no en falso verde.
- CI no ejecuta el humo ni construye imágenes; ambos quedan para el trabajo de imágenes de S8.

## 2026-10-08 — frontend-implementer: grupo 6 (`software/web`)

Tareas 6.1–6.5 `[x]`. Dependencia nueva: `react-router` 7.18.4 (MIT, design D7). Tipos de la API generados
desde `software/api/openapi.json` con `openapi-typescript@7.13.0` vía `npx` fijado (sin dependencia: su peer exige
TypeScript 5 y la SPA usa 6) → `src/lib/api-schema.ts`; scripts `api:types` y `api:types:check` (deriva). Componentes
shadcn/ui añadidos con la CLI: `input`, `field`, `label`, `separator`, `alert`, `empty`, `spinner`, `badge`.

### Corridas

| Corrida | Comando | Resultado |
|---|---|---|
| Cierre (1 de 1) | `npm run lint && npm run typecheck && npm test -- --run && npm run build` | lint 0, tsc 0, 39 pruebas / 7 archivos (línea base 8 / 3), build OK |
| Delta | `vitest --run` tras etiqueta del spinner al módulo de textos | 39 / 39 |
| Deriva de tipos | `npm run api:types:check` | exit 0; control positivo (línea plantada en `api-schema.ts`) → exit 1; restaurado → exit 0 |

### Líneas (añadidas, `software/web`)

| Tipo | Líneas |
|---|---|
| Producto (`src` sin pruebas, sin `ui`) | 600 |
| Componentes shadcn/ui generados | 545 |
| Pruebas (`*.test.*`, `src/test`) | 647 |
| Tipos generados del OpenAPI | 934 |

### Escenario → prueba → archivo:línea (rutas relativas a `software/web/src/`)

| Capacidad | Escenario | Prueba | Archivo:línea |
|---|---|---|---|
| app-shell | Ingreso exitoso | navega a / y el encabezado muestra nombre y rol | features/session/login-page.test.tsx:36 |
| app-shell | Doble clic (login) | una sola petición y botón "Ingresando…" deshabilitado · doble envío por teclado | features/session/login-page.test.tsx:51, :69 |
| app-shell | Credenciales inválidas | mensaje, conserva correo, vacía contraseña, rehabilita | features/session/login-page.test.tsx:87 |
| app-shell | Campos vacíos | "Este campo es obligatorio." y ninguna petición | features/session/login-page.test.tsx:98 |
| app-shell | Demasiados intentos | mensaje de espera | features/session/login-page.test.tsx:110 |
| app-shell | Servidor inalcanzable | red y 500 (dataset 2) | features/session/login-page.test.tsx:120 |
| app-shell | Usuario ya autenticado | /login lleva a / sin formulario | features/session/login-page.test.tsx:141 |
| app-shell | Pantalla de inicio de sesión (cookie CSRF antes de enviar) | pide la cookie CSRF antes del login y envía X-XSRF-TOKEN | lib/api.test.ts:16 |
| app-shell | Carga con sesión vigente | "Cargando sesión…" y luego el inicio | app/protected-layout.test.tsx:9 |
| app-shell | Ruta protegida sin sesión | `/` y `/inventario` (dataset 2) → /login sin shell | app/protected-layout.test.tsx:22 |
| app-shell | Fallo al consultar la sesión | error con "Reintentar" que repite la consulta | app/protected-layout.test.tsx:35 |
| app-shell | Sesión expirada durante el uso | 401 → /login con aviso y caché descartada | app/protected-layout.test.tsx:52 |
| app-shell | Token CSRF vencido y reintento exitoso | cliente: dos peticiones, token renovado · UI: cierre sin error | lib/api.test.ts:58; app/shell-header.test.tsx:89 |
| app-shell | Token CSRF vencido dos veces | cliente: sin tercer intento · UI login: mensaje de recargar | lib/api.test.ts:77; features/session/login-page.test.tsx:132 |
| app-shell | Etiqueta de rol en español | "Regente de farmacia", nunca el código | app/shell-header.test.tsx:30 |
| app-shell | Página de inicio sin pantallas aún | saludo y estado vacío | app/shell-header.test.tsx:39 |
| app-shell | Cierre exitoso | descarta la caché y navega a /login | app/shell-header.test.tsx:46 |
| app-shell | Doble clic en cerrar sesión | una sola petición, botón deshabilitado | app/shell-header.test.tsx:59 |
| app-shell | Cierre fallido | red y 500 (dataset 2): mensaje, sigue en el shell | app/shell-header.test.tsx:76 |
| app-shell | Otro usuario no ve datos del anterior | admin cierra, auditor entra: solo datos del auditor | app/shell-header.test.tsx:103 |
| app-shell | Textos desde el módulo central (6.2) | /login con errores · carga, fallo y shell · 2 controles positivos | App.test.tsx:40, :59, :81, :90 |
| runtime-environment | Shell en la raíz (sin sesión: nombre y bienvenida en /login) | muestra el nombre del producto y el mensaje de bienvenida | features/session/login-page.test.tsx:152 |
| — (cimiento 6.1) | Escrituras con X-XSRF-TOKEN · lecturas sin él · 401 = null · red/502 | 4 pruebas | lib/api.test.ts:36, :49, :92, :97 |

Frontera HTTP: `fetch` sustituido (`src/test/fake-api.ts`); la emisión de la cookie CSRF escribe `document.cookie`
como Sanctum. Toda ruta pedida sin manejador hace fallar la prueba en `src/test/setup.ts` (evita un falso "error de
red"); ese guardia detectó un manejador faltante durante la autoría.

### [MUT] web (corridas delta filtradas; aplicada → falla, restaurada → pasa)

| n | Mutación | Aplicada → FALLA | Restaurada |
|---|---|---|---|
| W1 | login sin guardia `inFlight` | 1/11: doble envío por teclado | 11/11 |
| W2 | login sin `disabled` en el botón | 1/10: doble clic | 10/10 |
| W3 | sin reintento ante `csrf_token_mismatch` | 3/15: reintento exitoso (cliente y UI), vencido dos veces | 15/15 |
| W4 | escrituras sin `X-XSRF-TOKEN` | 4/15: login, escritura, reintento, cierre | 15/15 |
| W5 | login sin pedir la cookie CSRF | 1/7: cookie CSRF antes del login | 7/7 |
| W6 | cierre sin `queryClient.clear()` | 2/8: cierre exitoso, otro usuario | 8/8 |
| W7 | 401 de la sesión expirada sin `clear()` | 1/5: sesión expirada durante el uso | 5/5 |
| W8 | 401 de `me` tratado como error, no `null` | 3/5: rutas sin sesión (2), sesión expirada | 5/5 |
| W9 | encabezado muestra el código de rol | 2/8: etiqueta de rol, otro usuario | 8/8 |
| W10 | cierre sin guardia `inFlight` | 1/8: doble clic en cerrar sesión | 8/8 |
| W11 | credenciales inválidas no vacían la contraseña | 1/11 | 11/11 |
| W12 | envío sin validar campos vacíos | 1/11: campos vacíos | 11/11 |

### Barridos (`/usr/bin/grep`, `software/web/src`, sin `api-schema.ts`; control positivo plantado en el scratchpad)

| # | Patrón | Alcance | Hits | Control |
|---|---|---|---|---|
| 1 | `console.(log\|info\|debug)(` | src | 0 | 1 |
| 2 | `console.(warn\|error)(` fuera de pruebas | src | 0 | — (1 cubre la forma) |
| 3 | `localStorage`, `sessionStorage` | src | 0 | 1 |
| 4 | `fetch(` fuera de `lib/api.ts` y pruebas | src | 0 | 1 |
| 5 | texto literal en JSX (`>Texto<`) | src sin `ui` ni pruebas | 0 | 1 |
| 6 | `Authorization`, `Bearer` en código | src sin pruebas | 0 | 1 |
| 7 | `dangerouslySetInnerHTML` | src | 0 | 1 |
| 8 | `catch` vacío | src | 0 | 1 |

### Verificación renderizada (stack en marcha, `localhost:8090`, web reconstruida con este código)

Chromium sin cabeza (playwright-core en el scratchpad). Secuencia observada: `GET /inventario` → `me` 401 → `/login`;
envío vacío → 2 mensajes; login con contraseña errónea → CSRF 204 + login 422 + mensaje; login regente → CSRF 204 +
login 200 → `/`; recarga → `me` 200 con sesión; cerrar sesión → logout 204 → `/login`; `/` → `me` 401 → `/login`.
Cookies: `XSRF-TOKEN` (no HttpOnly), `dispensart-session` (HttpOnly); `localStorage` vacío.

| Captura | Archivo |
|---|---|
| Login con campos vacíos | captures/s1-login-campos-vacios.png |
| Login con credenciales inválidas | captures/s1-login-credenciales-invalidas.png |
| Shell del regente con inicio vacío | captures/s1-shell-regente.png |

### Decisiones y desviaciones

| Punto | Design | Aplicado | Motivo |
|---|---|---|---|
| D8 módulo de textos | `src/strings.ts` | `src/lib/strings.ts` (módulo único existente de S0) | un solo módulo central |
| D8 tipos | — | generados del OpenAPI (`openapi-typescript` por `npx` fijado) | ley del agente: tipos derivados del contrato, nunca duplicados |
| D8 manejador global | `router.navigate` | `createAppQueryClient(router)` en `app/app-query-client.ts` | el cliente se crea con el router; pruebas usan el mismo cableado con router en memoria |
| Sesión | `useQuery(['session','me'])` | además `staleTime: Infinity`, `retry: false` | solo cambia por login/logout/401; "Reintentar" es manual |
| Login exitoso | — | descarta toda consulta salvo la sesión antes de fijar el usuario | "Otro usuario no ve datos del anterior" también si no hubo cierre |
| Runtime "Shell en la raíz" | página shell sin sesión | `/` sin sesión → `/login`, que muestra nombre del producto y bienvenida | la escena S0 sigue cumpliéndose bajo el shell protegido |

### Deuda (prosa; el Orchestrator asigna id)

- `api:types:check` no corre en CI: el workflow `frontend` debería ejecutarlo para que un cambio del OpenAPI sin
  regenerar los tipos falle (es archivo de CI, fuera del alcance de este agente).
- `npm audit` reporta 7 altas preexistentes vía `shadcn` → `fast-glob` → `micromatch` → `braces` (solo desarrollo, no
  llega al bundle); ninguna introducida por este bloque.
