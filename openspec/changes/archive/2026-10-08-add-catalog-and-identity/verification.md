# Verification — add-catalog-and-identity (S1, tier A)

Fuente: secciones de `journal.md` (backend-implementer grupos 1–5 y delta de proxy, frontend-implementer grupo 6,
devops-implementer grupo 7). Árbol medido: `dev` en `9870634`. Prefijos: `IA` identity-access, `CAT` catalog,
`SD` seed-data, `AS` app-shell, `RE` runtime-environment (delta MODIFIED). Rutas de prueba de API relativas a
`software/api/tests/Feature/`; de SPA, a `software/web/src/`.

## 0. Reparto de líneas

Líneas añadidas por S1. Comando base: `git diff --numstat e96abd9^ 9870634 -- <alcance> | awk -F'\t' '$1!="-"{a+=$1} END{print a}'`.

| Categoría | Alcance | Líneas |
|---|---|---|
| Producto — API | `software/api/{app,config,database,routes,bootstrap,lang}` | 2120 |
| Producto — SPA | `software/web/src` sin `*.test.*`, `src/test`, `components/ui`, `lib/api-schema.ts` | 654 |
| Producto — infraestructura | `software/docker` (sin `smoke`), `compose.yaml`, `.env.example`, `.github/workflows`, `package.json` raíz | 79 |
| Prueba — API | `software/api/tests`, `phpunit.xml` | 1526 |
| Prueba — SPA | `software/web/src/**/*.test.*`, `src/test` | 586 |
| Prueba — humo del stack | `software/docker/smoke` | 107 |
| Generado | `openapi.json`, `lib/api-schema.ts`, `components/ui` (CLI shadcn) | 2745 |
| Registro | `openspec/changes/add-catalog-and-identity/**` (antes de este archivo) | 521 |

## 1. Matriz escenario → prueba o evidencia

### 1.1 identity-access

| Escenario | Prueba | Archivo:línea |
|---|---|---|
| IA › Usuario con rol válido | guarda un usuario con rol válido insertado directamente | `Identity/UserRoleConstraintTest.php:22` |
| IA › Rol fuera del conjunto rechazado por la base | rechaza en la base un rol fuera del conjunto | `Identity/UserRoleConstraintTest.php:29` |
| IA › Rol nulo rechazado por la base | rechaza en la base un usuario sin rol | `Identity/UserRoleConstraintTest.php:39` |
| IA › Capacidades de auxiliar_farmacia | resuelve exactamente las capacidades de cada rol (dataset, fila auxiliar) | `Identity/RoleAbilitiesTest.php:12` |
| IA › Capacidades de regente_farmacia | idem, fila regente | `Identity/RoleAbilitiesTest.php:12` |
| IA › Capacidades de medico | idem, fila medico | `Identity/RoleAbilitiesTest.php:12` |
| IA › Capacidades de auditor, solo lectura | idem, fila auditor · niega al auditor toda capacidad de escritura | `Identity/RoleAbilitiesTest.php:12`, `:30` |
| IA › Capacidades de admin, sin datos clínicos ni inventario | idem, fila admin · niega al admin datos clínicos e inventario | `Identity/RoleAbilitiesTest.php:12`, `:42` |
| IA › Capacidad desconocida denegada a todo rol | niega una capacidad desconocida a los cinco roles | `Identity/RoleAbilitiesTest.php:48` |
| IA › Petición sin sesión | GET /api/warehouses responde 401 sin sesión · 401, no 500, ante Bearer falso | `Catalog/WarehouseEndpointTest.php:34`; `Identity/LoginTest.php:133` |
| IA › 401 en JSON aunque falte Accept | responde 401 en JSON aunque falte Accept, sin redirección | `Identity/MeTest.php:32` |
| IA › Rol falsificado por el cliente ignorado | ignora un rol falsificado en el cuerpo o en cabeceras | `Catalog/ProductEndpointTest.php:100` |
| IA › Cambio de rol en la base vigente en la petición siguiente | aplica en la petición siguiente un cambio de rol hecho en la base | `Catalog/WarehouseEndpointTest.php:94` |
| IA › Rechazo por permisos | rechaza con 403 a los demás roles con el cuerpo exacto y sin crear | `Catalog/WarehouseEndpointTest.php:77` |
| IA › Recurso inexistente | responde 404 en español sin nombre de clase a un producto inexistente | `Catalog/ProductEndpointTest.php:150` |
| IA › Validación con errores por campo | rechaza sin code con errors.code en español | `Catalog/ProductEndpointTest.php:83` |
| IA › Emisión de la cookie CSRF | emite la cookie XSRF-TOKEN legible por la SPA con un 204 · humo: `/sanctum/csrf-cookie` 204 por el proxy | `Identity/CsrfTest.php:11`; `software/docker/smoke/auth-smoke.sh:85` |
| IA › Login sin token CSRF | rechaza con 419 el login sin X-XSRF-TOKEN y no abre sesión | `Identity/CsrfTest.php:23` |
| IA › Escritura con token CSRF caducado | rechaza con 419 una escritura con token de otra sesión · humo: escritura sin token con sesión válida 419 | `Identity/CsrfTest.php:40`; `software/docker/smoke/auth-smoke.sh:96` |
| IA › Lectura sin token CSRF | permite lecturas con sesión sin X-XSRF-TOKEN | `Identity/CsrfTest.php:57` |
| IA › Credenciales válidas | abre sesión, regenera la sesión y no devuelve token · humo: login 200 con rol, cada usuario semilla | `Identity/LoginTest.php:27`; `software/docker/smoke/auth-smoke.sh:90` |
| IA › Correo con mayúsculas | compara el correo sin distinguir mayúsculas | `Identity/LoginTest.php:50` |
| IA › Contraseña incorrecta | rechaza una contraseña incorrecta sin abrir sesión | `Identity/LoginTest.php:59` |
| IA › Correo inexistente indistinguible | responde igual para un correo inexistente que para una contraseña incorrecta | `Identity/LoginTest.php:67` |
| IA › Datos incompletos o mal formados | rechaza datos incompletos o mal formados por campo (dataset) | `Identity/LoginTest.php:75` |
| IA › Demasiados intentos fallidos | bloquea con 429 el sexto intento tras cinco fallos | `Identity/LoginTest.php:90` |
| IA › Éxito reinicia el contador | reinicia el contador de fallos tras un inicio de sesión exitoso | `Identity/LoginTest.php:102` |
| IA › Origen ajeno a la SPA | rechaza con 403 y sin cookie el login desde un origen ajeno (dataset) · humo: login sin Origin 403 `forbidden` | `Identity/LoginTest.php:117`; `software/docker/smoke/auth-smoke.sh:69` |
| IA › Cierre de sesión exitoso | la cookie anterior deja de autenticar · humo: logout 204, luego `me` 401 | `Identity/LogoutTest.php:11`; `software/docker/smoke/auth-smoke.sh:100`, `:103` |
| IA › Cierre sin sesión | responde 401 al cerrar sin sesión | `Identity/LogoutTest.php:25` |
| IA › Cierre repetido | responde 401, no 500, al repetir el cierre | `Identity/LogoutTest.php:32` |
| IA › Usuario actual › Usuario autenticado | devuelve el usuario de la sesión con las capacidades de su rol · humo: `me` 200 | `Identity/MeTest.php:12`; `software/docker/smoke/auth-smoke.sh:93` |
| IA › Usuario actual › Sin sesión | responde 401 sin sesión | `Identity/MeTest.php:26` |
| IA › Admin lista usuarios | lista a un admin los 5 usuarios semilla por nombre | `Identity/UsersEndpointTest.php:25` |
| IA › Otro rol intenta listar usuarios | rechaza con 403 a los demás roles (dataset) | `Identity/UsersEndpointTest.php:39` |
| IA › Listado › Sin sesión | responde 401 sin sesión | `Identity/UsersEndpointTest.php:46` |
| IA › Alta exitosa | crea un usuario con contraseña con hash que luego inicia sesión | `Identity/UsersEndpointTest.php:52` |
| IA › Correo duplicado sin distinguir mayúsculas | rechaza un correo duplicado sin distinguir mayúsculas | `Identity/UsersEndpointTest.php:78` |
| IA › Rol inválido o datos incompletos | rechaza rol inválido o datos incompletos (dataset) | `Identity/UsersEndpointTest.php:91` |
| IA › Otro rol intenta crear usuarios | rechaza con 403 a los demás roles y no crea usuario (dataset) | `Identity/UsersEndpointTest.php:111` |
| IA › Alta › Sin sesión | responde 401 sin sesión y no crea usuario | `Identity/UsersEndpointTest.php:121` |

### 1.2 catalog

| Escenario | Prueba | Archivo:línea |
|---|---|---|
| CAT › Cualquier rol consulta bodegas | devuelve a cualquier rol las 3 bodegas semilla (dataset) | `Catalog/WarehouseEndpointTest.php:17` |
| CAT › Sin bodegas | devuelve una lista vacía sin bodegas | `Catalog/WarehouseEndpointTest.php:28` |
| CAT › Consulta de bodegas › Sin sesión | responde 401 sin sesión | `Catalog/WarehouseEndpointTest.php:34` |
| CAT › Alta exitosa (bodega) | crea una bodega como admin | `Catalog/WarehouseEndpointTest.php:42` |
| CAT › Código o nombre duplicado | rechaza código o nombre duplicado (dataset) | `Catalog/WarehouseEndpointTest.php:51` |
| CAT › Datos incompletos o demasiado largos | rechaza datos incompletos o demasiado largos (dataset) | `Catalog/WarehouseEndpointTest.php:65` |
| CAT › Otro rol intenta crear (bodega) | rechaza con 403 a los demás roles (dataset) | `Catalog/WarehouseEndpointTest.php:77` |
| CAT › Alta de bodegas › Sin sesión | responde 401 sin sesión y no crea bodega | `Catalog/WarehouseEndpointTest.php:86` |
| CAT › Cambio parcial | cambia solo el nombre y conserva el código | `Catalog/WarehouseEndpointTest.php:117` |
| CAT › Conservar su propio código | permite conservar su propio código | `Catalog/WarehouseEndpointTest.php:124` |
| CAT › Cuerpo vacío | devuelve la bodega intacta con un cuerpo vacío | `Catalog/WarehouseEndpointTest.php:131` |
| CAT › Código de otra bodega | rechaza el código de otra bodega sin cambiarla | `Catalog/WarehouseEndpointTest.php:138` |
| CAT › Bodega inexistente | responde 404 a una bodega inexistente | `Catalog/WarehouseEndpointTest.php:149` |
| CAT › Otro rol intenta modificar (bodega) | rechaza con 403 a un regente sin cambiar la bodega | `Catalog/WarehouseEndpointTest.php:156` |
| CAT › Cualquier rol consulta productos | devuelve a cualquier rol los 6 productos semilla (dataset) | `Catalog/ProductEndpointTest.php:15` |
| CAT › Sin productos | devuelve una lista vacía sin productos | `Catalog/ProductEndpointTest.php:29` |
| CAT › Consulta de productos › Sin sesión | responde 401 sin sesión | `Catalog/ProductEndpointTest.php:33` |
| CAT › Alta exitosa de control especial | crea un producto de control especial | `Catalog/ProductEndpointTest.php:39` |
| CAT › Campos opcionales omitidos | usa presentation null e is_controlled false si se omiten | `Catalog/ProductEndpointTest.php:56` |
| CAT › Datos inválidos | rechaza datos inválidos por campo (dataset) | `Catalog/ProductEndpointTest.php:66` |
| CAT › Otro rol intenta crear (producto) | rechaza con 403 a los demás roles (dataset) | `Catalog/ProductEndpointTest.php:91` |
| CAT › Alta de productos › Sin sesión | responde 401 sin sesión y no crea producto | `Catalog/ProductEndpointTest.php:110` |
| CAT › Marcar como control especial | marca como control especial sin cambiar los demás campos | `Catalog/ProductEndpointTest.php:127` |
| CAT › Código de otro producto | rechaza el código de otro producto sin cambiarlo | `Catalog/ProductEndpointTest.php:140` |
| CAT › Producto inexistente | responde 404 en español sin nombre de clase | `Catalog/ProductEndpointTest.php:150` |
| CAT › Otro rol intenta modificar (producto) | rechaza con 403 a un auditor sin cambiar el producto | `Catalog/ProductEndpointTest.php:158` |
| CAT › Todos los lotes en orden de vencimiento | devuelve todos los lotes por vencimiento y luego id | `Catalog/LotEndpointTest.php:13` |
| CAT › Filtro por producto | filtra por producto | `Catalog/LotEndpointTest.php:27` |
| CAT › Producto sin lotes o inexistente | devuelve una lista vacía para un producto inexistente | `Catalog/LotEndpointTest.php:38` |
| CAT › Filtro mal formado | rechaza un filtro mal formado | `Catalog/LotEndpointTest.php:46` |
| CAT › Consulta de lotes › Sin sesión | responde 401 sin sesión | `Catalog/LotEndpointTest.php:53` |
| CAT › Lote que venció ayer | marca vencido un lote que venció ayer · HTTP: is_expired con el día de Bogotá | `Catalog/LotExpiryTest.php:22`; `Catalog/LotEndpointTest.php:57` |
| CAT › Lote que vence hoy | marca vencido un lote que vence hoy · frontera 23:30 Bogotá (design D6) | `Catalog/LotExpiryTest.php:28`, `:40` |
| CAT › Lote que vence mañana | no marca vencido un lote que vence mañana | `Catalog/LotExpiryTest.php:34` |
| CAT › Cambio de día sin escritura | vence al cambiar de día sin escritura alguna | `Catalog/LotExpiryTest.php:48` |
| CAT › Lote duplicado para el mismo producto | rechaza un lote duplicado para el mismo producto | `Catalog/CatalogIntegrityTest.php:64` |
| CAT › Mismo código de lote en otro producto | acepta el mismo código de lote en otro producto | `Catalog/CatalogIntegrityTest.php:76` |
| CAT › Lote huérfano o sin vencimiento | rechaza un lote huérfano · rechaza un lote sin vencimiento | `Catalog/CatalogIntegrityTest.php:85`, `:94` |
| CAT › Código de bodega o de producto duplicado en la base | bodega con código / nombre existente · producto con código existente | `Catalog/CatalogIntegrityTest.php:24`, `:35`, `:46` |
| CAT › Borrar producto con lotes | rechaza borrar un producto con lotes y conserva ambos | `Catalog/CatalogIntegrityTest.php:104` |

### 1.3 seed-data

| Escenario | Prueba o comando de evidencia → resultado | Archivo:línea |
|---|---|---|
| SD › Bodegas y productos sembrados | siembra exactamente las 3 bodegas y 6 productos | `Seed/SeedTest.php:34` |
| SD › Lotes por producto | 2 o 3 lotes por producto y al menos uno no vencido del controlado | `Seed/SeedTest.php:44` |
| SD › Distribución de vencimientos | vencido, 1–29, 31–90 y más de 90 días | `Seed/SeedTest.php:55` |
| SD › Sin datos reales | sin correos fuera de dispensart.test en el código de siembra | `Seed/SeedTest.php:66` |
| SD › Un usuario por rol | crea exactamente un usuario por rol con los correos indicados | `Seed/SeedTest.php:78` |
| SD › Inicio de sesión con la contraseña documentada | permite iniciar sesión con la contraseña documentada · stack: humo con el valor por defecto → login 200 ×5 | `Seed/SeedTest.php:90`; `software/docker/smoke/auth-smoke.sh:90` |
| SD › Contraseña reemplazada por entorno | usa SEED_USER_PASSWORD si está definida · stack: ver RE › Credencial reemplazable | `Seed/SeedTest.php:99` |
| SD › Contraseña guardada con hash | guarda las contraseñas con hash | `Seed/SeedTest.php:109` |
| SD › Producción sin contraseña explícita | catálogo sí, ningún usuario · stack `APP_ENV=production`, volúmenes vacíos → conteos 3/6/14/0, log `warning` sin valor | `Seed/SeedTest.php:117`; `software/docker/api/entrypoint.sh:45` |
| SD › Arranque desde cero | `down -v` → `up --build --wait` (worktree limpio de HEAD) → 3 servicios `healthy`, conteos 3/6/14/5, humo 38 de 38 | `software/docker/api/entrypoint.sh:45`; `software/compose.yaml:49` |
| SD › Siembra repetida | prueba: misma base dos veces · stack: `up --force-recreate api` → conteos 3/6/14/5 sin cambio | `Seed/SeedTest.php:135`; `software/docker/api/entrypoint.sh:45` |
| SD › Cambios del admin sobreviven al reinicio | prueba: nombre del admin conservado · stack: PATCH `FC` vía proxy + recrear `api` → nombre nuevo, 3 bodegas | `Seed/SeedTest.php:145` |
| SD › Filas no semilla intactas | deja intactas las filas que no son semilla | `Seed/SeedTest.php:156` |

### 1.4 app-shell

| Escenario | Prueba | Archivo:línea |
|---|---|---|
| AS › Ingreso exitoso | navega a / y el encabezado muestra nombre y rol | `features/session/login-page.test.tsx:36` |
| AS › Doble clic (login) | una sola petición y botón deshabilitado · doble envío por teclado | `features/session/login-page.test.tsx:51`, `:69` |
| AS › Credenciales inválidas | mensaje, conserva correo, vacía contraseña, rehabilita | `features/session/login-page.test.tsx:87` |
| AS › Campos vacíos | "Este campo es obligatorio." y ninguna petición | `features/session/login-page.test.tsx:98` |
| AS › Demasiados intentos | mensaje de espera | `features/session/login-page.test.tsx:110` |
| AS › Servidor inalcanzable | red y 500 (dataset) | `features/session/login-page.test.tsx:120` |
| AS › Usuario ya autenticado | /login lleva a / sin formulario | `features/session/login-page.test.tsx:141` |
| AS › Pantalla de inicio de sesión (cookie CSRF antes de enviar) | pide la cookie CSRF antes del login y envía X-XSRF-TOKEN | `lib/api.test.ts:16` |
| AS › Carga con sesión vigente | "Cargando sesión…" y luego el inicio | `app/protected-layout.test.tsx:9` |
| AS › Ruta protegida sin sesión | `/` y `/inventario` (dataset) → /login sin shell | `app/protected-layout.test.tsx:22` |
| AS › Fallo al consultar la sesión | error con "Reintentar" que repite la consulta | `app/protected-layout.test.tsx:35` |
| AS › Etiqueta de rol en español | "Regente de farmacia", nunca el código | `app/shell-header.test.tsx:30` |
| AS › Página de inicio sin pantallas aún | saludo y estado vacío | `app/shell-header.test.tsx:39` |
| AS › Cierre exitoso | descarta la caché y navega a /login | `app/shell-header.test.tsx:46` |
| AS › Doble clic en cerrar sesión | una sola petición, botón deshabilitado | `app/shell-header.test.tsx:59` |
| AS › Cierre fallido | red y 500 (dataset): mensaje, sigue en el shell | `app/shell-header.test.tsx:76` |
| AS › Otro usuario no ve datos del anterior | admin cierra, auditor entra: solo datos del auditor | `app/shell-header.test.tsx:103` |
| AS › Sesión expirada durante el uso | 401 → /login con aviso y caché descartada | `app/protected-layout.test.tsx:52` |
| AS › Token CSRF vencido y reintento exitoso | cliente: dos peticiones, token renovado · UI: cierre sin error | `lib/api.test.ts:58`; `app/shell-header.test.tsx:89` |
| AS › Token CSRF vencido dos veces | cliente: sin tercer intento · UI login: mensaje de recargar | `lib/api.test.ts:77`; `features/session/login-page.test.tsx:132` |
| AS › Textos desde el módulo central (6.2) | /login con errores · carga, fallo y shell · controles positivos | `App.test.tsx:40`, `:59`, `:81`, `:90` |
| RE (S0) › Shell en la raíz, bajo sesión | sin sesión, /login muestra el nombre del producto y la bienvenida | `features/session/login-page.test.tsx:152` |

### 1.5 runtime-environment (MODIFIED: Secretos fuera del repositorio)

| Escenario | Comando de evidencia → resultado | Archivo:línea |
|---|---|---|
| RE › Plantilla completa | por cada `${VAR}` de `compose.yaml`, `/usr/bin/grep -q "^VAR=" .env.example` → 12 de 12; control: `POSTGRES_DB`, `POSTGRES_USER` (`$${}` del contenedor) salen como ausentes → el bucle detecta faltantes; `APP_KEY=` vacía | `software/.env.example:16` |
| RE › .env ignorado por git | `git check-ignore -v software/.env software/api/.env software/web/.env` → 3 de 3 | `.gitignore:6`; `software/api/.gitignore:3` |
| RE › Credencial reemplazable | `down -v`; `DB_PASSWORD=<alt> SEED_USER_PASSWORD=<alt> up -d --wait` → 3 `healthy` (`/ready` exige base); `printenv DB_PASSWORD` en api = largo del valor alterno; humo con `<alt>` → exit 0; humo con el valor por defecto → 15 fallas, exit 1 | `software/compose.yaml:13`, `:58` |
| RE › Lista cerrada de valores por defecto | barrido § 5 fila D1 → solo `DB_PASSWORD` y el valor de semilla; compose `SEED_USER_PASSWORD: ${SEED_USER_PASSWORD:-}` sin valor; API: `env('…PASSWORD…', '<literal>')` en `config` → 2 hits, ninguno credencial (`AUTH_PASSWORD_BROKER`, tabla de reset); valor de semilla solo en `config/dispensart.php:17` | `software/compose.yaml:58`; `software/api/config/dispensart.php:17` |
| RE › Valor por defecto de la siembra inerte en producción | prueba: producción sin variable → 0 usuarios · stack `APP_ENV=production` → conteos 3/6/14/0 | `Seed/SeedTest.php:117`; `software/compose.yaml:49` |

## 2. Mutaciones `[MUT]`

| n | mutation | Applied → FAILS m/k: test | Restored → PASSES k/k |
|---|---|---|---|
| M1 | `Role::RegenteFarmacia` gana `Ability::CatalogManage` | FAILS 1/12: resuelve exactamente las capacidades de cada rol › regente_farmacia | PASSES 12/12 |
| M2 | `ProductPolicy::create` devuelve `true` | FAILS 5/23: rechaza con 403 a los demás roles (4 del dataset) + ignora un rol falsificado | PASSES 23/23 |
| M3 | grupo `/api` sin `auth:sanctum` | FAILS 2/24: GET y POST /api/warehouses › 401 sin sesión | PASSES 24/24 |
| M4 | login copia `role` a la sesión y `WarehousePolicy::create` autoriza con ese valor | FAILS 1/24: cambio de rol en la base vigente en la petición siguiente | PASSES 24/24 |
| M5 | `ValidateCsrfToken::$except = ['api/auth/login']` | FAILS 1/4: rechaza con 419 el login sin X-XSRF-TOKEN | PASSES 4/4 |
| M6 | correo inexistente → mensaje "El correo no está registrado." | FAILS 1/12: correo inexistente indistinguible | PASSES 12/12 |
| M7 | sin `RateLimiter::hit` en el fallo | FAILS 1/12: bloquea con 429 el sexto intento | PASSES 12/12 |
| M8 | migración sin `users_role_check` | FAILS 1/3: rol fuera del conjunto rechazado por la base | PASSES 3/3 |
| M9 | migración sin `lots_product_id_lot_code_unique` | FAILS 1/9: lote duplicado para el mismo producto | PASSES 9/9 |
| M10 | `Lot::isExpiredOn` con `<` en lugar de `<=` | FAILS 3/6: vence hoy; frontera 23:30 Bogotá; cambio de día sin escritura | PASSES 6/6 |
| M11 | `WarehouseSeeder` con `create()` en lugar de `firstOrCreate` | FAILS 3/12: siembra repetida; nombre del admin; filas no semilla | PASSES 12/12 |
| P1 | `trustedproxy.proxies` = `127.0.0.2` | FAILS 1/2: tras proxy privado de confianza cuenta por la IP reenviada (`Identity/LoginProxyTest.php:26`) | PASSES 2/2 |
| P2 | `trustedproxy.proxies` = `*` | FAILS 1/2: desde fuente no confiable ignora X-Forwarded-For (`Identity/LoginProxyTest.php:42`) | PASSES 2/2 |
| W1 | login sin guardia `inFlight` | FAILS 1/11: doble envío por teclado | PASSES 11/11 |
| W2 | login sin `disabled` en el botón | FAILS 1/10: doble clic | PASSES 10/10 |
| W3 | sin reintento ante `csrf_token_mismatch` | FAILS 3/15: reintento exitoso (cliente y UI), vencido dos veces | PASSES 15/15 |
| W4 | escrituras sin `X-XSRF-TOKEN` | FAILS 4/15: login, escritura, reintento, cierre | PASSES 15/15 |
| W5 | login sin pedir la cookie CSRF | FAILS 1/7: cookie CSRF antes del login | PASSES 7/7 |
| W6 | cierre sin `queryClient.clear()` | FAILS 2/8: cierre exitoso, otro usuario | PASSES 8/8 |
| W7 | 401 de sesión expirada sin `clear()` | FAILS 1/5: sesión expirada durante el uso | PASSES 5/5 |
| W8 | 401 de `me` tratado como error, no `null` | FAILS 3/5: rutas sin sesión (2), sesión expirada | PASSES 5/5 |
| W9 | encabezado muestra el código de rol | FAILS 2/8: etiqueta de rol, otro usuario | PASSES 8/8 |
| W10 | cierre sin guardia `inFlight` | FAILS 1/8: doble clic en cerrar sesión | PASSES 8/8 |
| W11 | credenciales inválidas no vacían la contraseña | FAILS 1/11: credenciales inválidas | PASSES 11/11 |
| W12 | envío sin validar campos vacíos | FAILS 1/11: campos vacíos | PASSES 11/11 |

## 3. Anclas de transporte (cláusula → archivo:línea)

| Cláusula del ancla | Archivo:línea (`software/api/` salvo indicación) |
|---|---|
| ruta `POST /api/auth/login` | `routes/api.php:21` |
| middleware `auth:sanctum` | `routes/api.php:23` |
| ruta `POST /api/auth/logout` · `GET /api/auth/me` | `routes/api.php:24` · `:25` |
| ruta `GET /api/users` · `POST /api/users` | `routes/api.php:27` · `:28` |
| ruta `GET /api/warehouses` · `POST` · `PATCH /{id}` | `routes/api.php:30` · `:31` · `:32` |
| ruta `GET /api/products` · `POST` · `PATCH /{id}` | `routes/api.php:34` · `:35` · `:36` |
| ruta `GET /api/lots` | `routes/api.php:38` |
| ruta de Sanctum `sanctum/csrf-cookie` (grupo `web`) | `vendor/laravel/sanctum/src/SanctumServiceProvider.php:73`, `:76` |
| `/sanctum` y `/api` reenviados por el Nginx de web | `software/docker/web/default.conf.template:10` |
| `Origin`/`Referer` y IP de cliente reenviados a la API | `software/docker/web/api-proxy.conf:6`, `:7`, `:11` |
| sesión solo para orígenes de la SPA (`statefulApi`) | `bootstrap/app.php:33` |
| invitado → 401 sin redirección | `bootstrap/app.php:37` |
| middleware CSRF sin atajo de pruebas | `bootstrap/app.php:40`; `app/Http/Middleware/ValidateCsrfToken.php:15`, `:20` |
| render JSON forzado para `api/*` | `bootstrap/app.php:52`, `:58` |
| render de AuthenticationException · ValidationException | `app/Exceptions/ApiExceptionRenderer.php:23` · `:24` |
| render 403 `forbidden` · 404 `not_found` · 419 `csrf_token_mismatch` | `app/Exceptions/ApiExceptionRenderer.php:29`, `:39` · `:40` · `:42` |
| acción de login (respuesta idéntica) | `app/Actions/Identity/LoginAction.php:43`, `:46`, `:49` |
| limitador de login | `app/Actions/Identity/LoginAction.php:35`, `:47`, `:52` |
| FormRequest de login (403 por origen ajeno) | `app/Http/Requests/Auth/LoginRequest.php:14`, `:29` |
| FormRequest de alta de usuario | `app/Http/Requests/Users/StoreUserRequest.php:32` |
| FormRequest de bodega (alta · cambio) | `app/Http/Requests/Catalog/StoreWarehouseRequest.php:22` · `UpdateWarehouseRequest.php:23` |
| FormRequest de producto (alta · cambio) | `app/Http/Requests/Catalog/StoreProductRequest.php:22` · `UpdateProductRequest.php:22` |
| FormRequest de consulta de lotes | `app/Http/Requests/Catalog/ListLotsRequest.php:21` |
| Policy de usuarios · bodegas · productos | `app/Policies/UserPolicy.php:15`, `:20` · `WarehousePolicy.php:21`, `:26` · `ProductPolicy.php:21`, `:26` |
| recurso de lote (`is_expired`) | `app/Http/Resources/LotResource.php:27` |
| sin bearer | `app/Providers/AppServiceProvider.php:32` |

## 4. Corridas de suite

| # | Corrida | Árbol | Comando | Resultado | Presupuesto |
|---|---|---|---|---|---|
| 1 | Línea base (Orchestrator) | `dev` antes del apply | Pest · Vitest | Pest 26 / 143 aserciones; Vitest 8 | corrida 1 de 3 |
| 2 | Cierre backend | tras `261ee7a` | Pint `--test` · Larastan · `php artisan test` · Redocly · deriva OpenAPI | Pint limpio; Larastan 0; Pest 166 / 686; Redocly válido; deriva exit 0 | corrida 2 de 3 |
| 3 | Delta backend (deuda de proxy) | `abb47ae` | `--filter=LoginProxyTest` · Pint del archivo · Larastan | 2 / 14 aserciones; Pint pasa; Larastan 0 | delta, fuera del tope |
| 4 | Cierre frontend | `ea2c38b` | `npm run lint && typecheck && test -- --run && build` | ESLint 0; tsc 0; Vitest 39 / 7 archivos; build OK | corrida 3 de 3 |
| 5 | Delta frontend (solo pruebas) | `ea2c38b` | `vitest --run` tras mover etiqueta del spinner al módulo de textos | 39 / 39 | delta, fuera del tope |
| 6 | Humo del stack (devops) | worktree de `8f17e88` | `down -v` → `up --build --wait` → `auth-smoke.sh` | 3 `healthy`; `/health` 200, `/ready` 200; 38 de 38; control (contraseña errónea) exit 1 | stack, no suite |
| 7 | CI | `8f17e88` | run 37731044114 | success: Pint, Larastan, Pest 168 / 700, deriva y lint OpenAPI; Vitest 8 (SPA aún sin commit) | runner |
| 8 | CI de cierre — evidencia de 8.1 | `9870634` | run 37731311110 | success: Pint, Larastan, Pest 168 / 700, deriva OpenAPI, Redocly, ESLint, tsc, deriva de tipos, Vitest 39 | runner |

## 5. Barridos (`/usr/bin/grep`, control positivo plantado en el scratchpad)

| # | Patrón | Alcance | Hits | Control positivo |
|---|---|---|---|---|
| B1 | `Log::*(` con `$email`, `$password`, `password`, `->email`, `->name` | api `app database routes bootstrap` | 0 | `Log::warning(..., ['email' => $email])` plantado → 1 |
| B2 | `dd(`, `dump(`, `var_dump(`, `ray(` | api `app database routes bootstrap tests config` | 0 | `dd($x);` plantado → 1 |
| B3 | `HasApiTokens`, `personal_access_tokens`, `createToken` | api `app database config routes` | 0 | plantado → 2 |
| B4 | `actingAs`, `withoutMiddleware` | `LoginTest`, `LogoutTest`, `CsrfTest`, `SpaClient` | 1 (comentario "sin actingAs", `Identity/LoginTest.php:10`) | `UsersEndpointTest.php` → 8 |
| B5 | `protected $except`, `validateCsrfTokens(except`, `withoutMiddleware(...Csrf` | api `app bootstrap tests` | 0 | `protected $except = [...]` plantado → 1 |
| B6 | `Facades\DB` | `app/Http/Controllers` | 0 | `database/migrations` → 1 archivo |
| B7 | `base64:` + 20 caracteres | `git grep` en `software` | 0 | `APP_KEY=base64:AAAA…` plantado → 1 |
| B8 | `sqlite` | `phpunit.xml`, `tests` | 2 (comentarios "nunca SQLite") | `config/database.php` → 3 |
| B9 | correos fuera de `@dispensart.test` | `database/seeders`, `database/factories` | 0 | `real@gmail.com` plantado → 1 |
| F1 | `console.(log\|info\|debug)(` | `software/web/src` sin `api-schema.ts` | 0 | plantado → 1 |
| F2 | `console.(warn\|error)(` fuera de pruebas | `git ls-files software/web/src` sin pruebas ni `api-schema.ts` (25 archivos) | 0 | `console.error("x")` plantado → 1 |
| F3 | `localStorage`, `sessionStorage` | `software/web/src` | 0 | plantado → 1 |
| F4 | `fetch(` fuera de `lib/api.ts` y pruebas | `software/web/src` | 0 | plantado → 1 |
| F5 | texto literal en JSX (`>Texto<`) | `src` sin `ui` ni pruebas | 0 | plantado → 1 |
| F6 | `Authorization`, `Bearer` | `src` sin pruebas | 0 | plantado → 1 |
| F7 | `dangerouslySetInnerHTML` | `software/web/src` | 0 | plantado → 1 |
| F8 | `catch` vacío | `software/web/src` | 0 | plantado → 1 |
| D1 | `base64:[A-Za-z0-9+/]{20,}` · `(sk-\|ghp_\|AKIA)…` · `PASSWORD[=:]` con literal | `software/compose.yaml`, `software/.env.example`, `software/docker`, `.github/workflows` | 6: `DB_PASSWORD` de desarrollo (3), CI efímera (2), valor de semilla de desarrollo en el humo (1) | `APP_KEY=base64:…` y `SEED_USER_PASSWORD=…` plantados → 2 |
| D2 | `env('[A-Z_]*PASSWORD[A-Z_]*', '<literal>')` | `software/api/config` | 2, ninguno credencial (`auth.php:20`, `:98`) | — (los 2 hits son el control: el patrón encuentra literales) |
| D3 | directorios vacíos que el build necesita | `git worktree` limpio de HEAD | `resources/views` ausente → `artisan optimize` falla | árbol de trabajo con el directorio → arranca |

## 6. Deuda señalada (prosa; el Orchestrator asigna ids)

- Saldada en S1: la prueba de proxy de confianza (P1, P2) y la deriva de tipos de la SPA en CI (paso
  `npm run api:types:check` del trabajo `frontend`, `.github/workflows/ci.yml:147`).
- Saldada en S1: la API no arrancaba desde un clon limpio porque git no versiona el directorio vacío de vistas;
  corregido en la imagen (`software/docker/api/Dockerfile:96`). La verificación en frío de S0 corrió sobre el
  árbol de trabajo y no lo detectó.
- CI no construye imágenes ni ejecuta el humo del stack; queda para el trabajo de imágenes de S8.
- El humo repite el literal del valor de semilla de desarrollo además de `config/dispensart.php`; si uno cambia sin
  el otro, el humo falla en rojo, no en falso verde.
- `npm audit` reporta vulnerabilidades altas preexistentes en herramientas de desarrollo (CLI de OpenSpec en la
  raíz; cadena `shadcn` → `micromatch` → `braces` en la SPA); ninguna llega al bundle ni a la imagen.
- Resiembra por clave natural tras cambiar el código de un producto semilla: decisión del Orchestrator, supuesto
  del README en S8, sin fila.
