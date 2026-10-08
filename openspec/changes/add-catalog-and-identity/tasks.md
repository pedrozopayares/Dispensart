# Tasks — add-catalog-and-identity (S1)

Tier A. `[MUT]` declarados: 11 (M1–M11). "Cimiento" marca andamiaje o registro sin escenario propio: se verifica con el comando que la tarea nombra. Refinado por el architect con `design.md` (D1–D11).
Orden: 0 → 1 → 2 → 3 → 4 → 5 (api, secuencial) → 6 (web) ∥ 7.1; 7.2–7.3 tras 5 y 7.1; 8 al final.
api → web obligatorio: S1 crea el contrato que la SPA consume (tabla API contract de `design.md`).
Pruebas del backend: Pest contra PostgreSQL, nunca SQLite. Pruebas de flujo de sesión: petición HTTP real
con el `Origin` de la SPA y la verificación CSRF reactivada; `actingAs` solo en pruebas de autorización, nunca
en las de login, logout o CSRF (falso verde: el framework omite CSRF en pruebas y `actingAs` salta la sesión).

## 0. Precondiciones (antes del apply)

- [x] 0.1 spec-engineer: delta `MODIFIED` de `runtime-environment` "Secretos fuera del repositorio" con la lista cerrada de valores por defecto de credencial de desarrollo (`DB_PASSWORD`, `SEED_USER_PASSWORD`) e inertes en producción (design D10); requiere S0 archivado. Cubre runtime-environment "Credencial reemplazable" (modificado) y seed-data "Producción sin contraseña explícita". Verifica: `openspec validate add-catalog-and-identity --strict` válido.

## 1. Base de datos

- [x] 1.1 Migración: columna `role varchar(32)` en `users`, NOT NULL sin default, con `users_role_check` sobre los 5 literales (no desde el enum; design Data impact); prueba de inserción directa en la base. Cubre identity-access "Usuario con rol válido", "Rol fuera del conjunto rechazado por la base", "Rol nulo rechazado por la base". Verifica: prueba Pest verde.
- [x] 1.2 Migraciones `warehouses` (`code` y `name` únicos) y `products` (`code` único, `presentation` nullable, `is_controlled` NOT NULL por defecto `false`); pruebas de inserción directa. Cubre catalog "Código de bodega o de producto duplicado en la base". Verifica: prueba Pest verde.
- [x] 1.3 Migración `lots`: FK a `products` que impide borrar con lotes, `expires_on` NOT NULL, único (`product_id`, `lot_code`); pruebas de inserción y borrado directos. Cubre catalog "Lote duplicado para el mismo producto", "Mismo código de lote en otro producto", "Lote huérfano o sin vencimiento", "Borrar producto con lotes". Verifica: prueba Pest verde.
- [x] 1.4 Modelos Eloquent y factories (usuario con un estado por rol, bodega, producto, lote). Verifica: `docker compose -f software/compose.yaml run --rm api-tools php artisan test --filter=Factory` verde. Cimiento.
- [x] 1.5 [MUT] M8: quitar el CHECK de `role` → "Rol fuera del conjunto…" FALLA; restaurar → PASA. M9: quitar el único (`product_id`, `lot_code`) → "Lote duplicado…" FALLA; restaurar → PASA. Verifica: filas M8 y M9 en la tabla `[MUT]` de `verification.md`.

## 2. Dominio

- [x] 2.1 Enum de roles y mapa rol → capacidades con denegación por defecto; prueba con dataset de las 5 filas exactas y de una capacidad desconocida. Cubre todos los escenarios de identity-access "Mapa de capacidades por rol". Verifica: prueba Pest verde.
- [x] 2.2 [MUT] M1: conceder `catalog.manage` a `regente_farmacia` → "Capacidades de regente_farmacia" FALLA; restaurar → PASA. Verifica: fila M1 en `verification.md`.
- [x] 2.3 `BusinessCalendar::today()` (`config('dispensart.business_timezone')`, `app.timezone` sigue UTC; design D6) y `Lot::isExpiredOn()` (`expires_on` ≤ hoy) con `travelTo`, incluida la frontera 23:30 Bogotá. Cubre catalog "Lote que venció ayer", "Lote que vence hoy", "Lote que vence mañana", "Cambio de día sin escritura". [MUT] M10: `<=` → `<` → "Lote que vence hoy" FALLA; restaurar → PASA. Verifica: prueba verde y fila M10.

## 3. Aplicación

- [x] 3.1 Policies/Gates de bodegas, productos y usuarios que consultan solo el mapa con el rol leído de la base en cada petición. Cubre identity-access "Rol falsificado por el cliente ignorado", "Cambio de rol en la base vigente en la petición siguiente" (a nivel unitario). Verifica: prueba por rol verde.
- [x] 3.2 Acciones fuera de los controladores: alta de usuario (correo en minúsculas, contraseña con hash), alta y modificación parcial de bodega y de producto (campo ausente = sin cambio). Cubre identity-access "Alta exitosa", "Correo duplicado sin distinguir mayúsculas"; catalog "Alta exitosa" (bodegas), "Cambio parcial", "Cuerpo vacío", "Campos opcionales omitidos", "Marcar como control especial" (a nivel de acción). Verifica: la prueba `arch()` de controladores delgados de S0 sigue verde.
- [x] 3.3 Acción de login: correo normalizado, respuesta idéntica para correo inexistente y contraseña incorrecta, limitador de 5 fallos por correo + IP en 60 s que se reinicia con un éxito, regeneración de la sesión. Cubre identity-access "Credenciales válidas", "Correo con mayúsculas", "Contraseña incorrecta", "Correo inexistente indistinguible", "Demasiados intentos fallidos", "Éxito reinicia el contador" (a nivel de acción). Verifica: pruebas unitarias del limitador y de la respuesta idéntica verdes.

## 4. Infraestructura

- [x] 4.1 Render JSON uniforme de rechazos para `api/*` (401 `unauthenticated`, 403 `forbidden`, 404 `not_found`, 419 `csrf_token_mismatch`, 422 `validation_failed`/`invalid_credentials`, 429 `too_many_attempts`) con mensajes en `lang/es`, sin traza, SQL ni nombre de modelo. Cubre identity-access "Forma JSON de los rechazos" y "401 en JSON aunque falte Accept". Verifica: prueba por código verde.
- [x] 4.2 Configuración Sanctum SPA (design D1–D3): `statefulApi()`, dominios con estado desde entorno (incluye `localhost:8090`), cookie de sesión HttpOnly + SameSite Lax (Secure en producción), sin bearer (`getAccessTokenFromRequestUsing` nulo, sin tabla de tokens), `TrustProxies` en rangos privados; `App\Http\Middleware\ValidateCsrfToken` sin atajo de pruebas; `tests/Support/SpaClient` (Origin, tarro de cookies, `X-XSRF-TOKEN`, `forgetGuards` por petición). Cubre identity-access "Emisión de la cookie CSRF" y "Petición sin sesión" (con `Authorization: Bearer` falso → 401, no 500). Verifica: pruebas verdes.
- [x] 4.3 Seeders idempotentes por clave natural: bodegas, productos, lotes con vencimientos relativos a hoy, usuarios con `SEED_USER_PASSWORD` o el valor por defecto de desarrollo, y sin usuarios en producción sin la variable. Cubre seed-data "Catálogo semilla", "Usuarios semilla" (salvo login) y "Siembra repetida", "Cambios del admin sobreviven al reinicio", "Filas no semilla intactas" a nivel de seeder. Verifica: prueba de siembra doble verde.
- [x] 4.4 [MUT] M11: cambiar la búsqueda por clave natural por una inserción simple → "Siembra repetida" FALLA; restaurar → PASA. Verifica: fila M11 en `verification.md`.

## 5. API (una prueba HTTP real por endpoint)

- [x] 5.1 `POST /api/auth/login` + prueba HTTP real desde `GET /sanctum/csrf-cookie`. Cubre identity-access "Inicio de sesión" (todos; "Origen ajeno a la SPA" fija 403 `forbidden` sin `Set-Cookie`, design D1) y seed-data "Inicio de sesión con la contraseña documentada", "Contraseña reemplazada por entorno". Verifica: prueba verde.
- [x] 5.2 Prueba HTTP real de CSRF. Cubre identity-access "Login sin token CSRF", "Escritura con token CSRF caducado", "Lectura sin token CSRF". Verifica: prueba verde.
- [x] 5.3 [MUT] M5: excluir `api/auth/login` de la verificación CSRF → "Login sin token CSRF" FALLA. M6: mensaje distinto para correo inexistente → "Correo inexistente indistinguible" FALLA. M7: no registrar el fallo en el limitador → "Demasiados intentos fallidos" FALLA. Restaurar cada uno → PASA. Verifica: filas M5–M7.
- [x] 5.4 `POST /api/auth/logout` + prueba HTTP real. Cubre identity-access "Cierre de sesión" (todos). Verifica: prueba verde.
- [x] 5.5 `GET /api/auth/me` + prueba HTTP real. Cubre identity-access "Usuario actual" (todos) y "401 en JSON aunque falte Accept". Verifica: prueba verde.
- [x] 5.6 `GET /api/users` + prueba HTTP real con dataset de los 4 roles denegados. Cubre identity-access "Listado de usuarios" (todos). Verifica: prueba verde.
- [x] 5.7 `POST /api/users` + prueba HTTP real. Cubre identity-access "Alta de usuarios" (todos). Verifica: prueba verde.
- [x] 5.8 `GET /api/warehouses` + prueba HTTP real con dataset de los 5 roles. Cubre catalog "Consulta de bodegas" e identity-access "Petición sin sesión". Verifica: prueba verde.
- [x] 5.9 `POST /api/warehouses` + prueba HTTP real. Cubre catalog "Alta de bodegas" (todos), identity-access "Rechazo por permisos" y "Cambio de rol en la base vigente en la petición siguiente". Verifica: prueba verde.
- [x] 5.10 `PATCH /api/warehouses/{id}` + prueba HTTP real. Cubre catalog "Modificación de bodegas" (todos). Verifica: prueba verde.
- [x] 5.11 `GET /api/products` + prueba HTTP real con dataset de los 5 roles. Cubre catalog "Consulta de productos" (todos). Verifica: prueba verde.
- [x] 5.12 `POST /api/products` + prueba HTTP real. Cubre catalog "Alta de productos" (todos), identity-access "Rol falsificado por el cliente ignorado" y "Validación con errores por campo". Verifica: prueba verde.
- [x] 5.13 `PATCH /api/products/{id}` + prueba HTTP real. Cubre catalog "Modificación de productos" (todos) e identity-access "Recurso inexistente". Verifica: prueba verde.
- [x] 5.14 `GET /api/lots` + prueba HTTP real. Cubre catalog "Consulta de lotes" (todos) y el estado de vencimiento en la respuesta. Verifica: prueba verde.
- [x] 5.15 [MUT] M2: la Policy de productos autoriza siempre → dataset 403 de 5.12 FALLA. M3: quitar `auth:sanctum` del grupo `/api` → "Sin sesión" de 5.8 FALLA. M4: autorizar con el rol copiado a la sesión al hacer login → "Cambio de rol en la base…" FALLA. Restaurar cada uno → PASA. Verifica: filas M2–M4.
- [x] 5.16 OpenAPI 3.1 de los endpoints de esta tajada (design D9): `dedoc/scramble` en `require-dev` + extensión de la forma de rechazo, exportado a `software/api/openapi.json`; `@redocly/cli` fijado en el `package.json` raíz; script de deriva (exportar + `git diff --exit-code`). Plan B a 30 min: YAML a mano, anotado en el journal. Sin escenario propio (parte A: API documentada); refleja la tabla API contract de `design.md`. Verifica: `npx @redocly/cli lint software/api/openapi.json` sin errores y deriva vacía. Cimiento.

## 6. Web

- [ ] 6.1 Cliente HTTP de la SPA sobre `fetch` (design D8): mismo origen con cookies, `X-XSRF-TOKEN` desde la cookie, cookie CSRF antes del login, un único reintento ante `csrf_token_mismatch`, señal de sesión expirada ante `unauthenticated`. Cubre app-shell "Sesión expirada y token CSRF vencido" (todos). Verifica: Vitest verde.
- [ ] 6.2 Textos en el módulo central: login, shell, etiquetas de rol, mensajes de error y estados. Cubre app-shell "Etiqueta de rol en español" y el requisito de textos de "Pantalla de inicio de sesión". Verifica: Vitest que cada texto visible de 6.3–6.5 sale del módulo.
- [ ] 6.3 Dependencia nueva `react-router` v7 en modo datos (design D7); sesión con TanStack Query y ruta de diseño protegida: carga, sin sesión → `/login`, fallo con "Reintentar", usuario autenticado en `/login` → `/`. Cubre app-shell "Rutas protegidas por sesión" (todos) y "Usuario ya autenticado". Verifica: Vitest verde.
- [ ] 6.4 Pantalla `/login` con componentes de shadcn/ui existentes: campos obligatorios, estado pendiente sin doble envío, errores por `code`. Cubre app-shell "Pantalla de inicio de sesión" (todos). Verifica: Vitest verde.
- [ ] 6.5 Encabezado del shell (nombre, etiqueta de rol, "Cerrar sesión") y página de inicio con estado vacío; cierre con estado pendiente, fallo y limpieza de caché. Cubre app-shell "Encabezado con sesión y cierre" (todos). Verifica: Vitest verde.

## 7. DevOps

- [ ] 7.1 (∥ bloque 6) Arranque de `api`: sembrar después de migrar; Nginx de `web` también proxifica `/sanctum/` y conserva `Origin`/`X-Forwarded-For`; compose pasa `SEED_USER_PASSWORD: ${SEED_USER_PASSWORD:-}` sin valor por defecto (design D10); `software/.env.example` con `SEED_USER_PASSWORD` marcada solo desarrollo, dominios con estado y variables de sesión. Cubre seed-data "Arranque desde cero", "Siembra repetida", "Cambios del admin sobreviven al reinicio" en el stack. Verifica: `down -v` + `up --build --wait`, login con cada usuario semilla; segundo `up` con conteos iguales.
- [ ] 7.2 Script de humo versionado contra `localhost:${WEB_PORT}` con cookies: cookie CSRF → login → `me` → escritura sin `X-XSRF-TOKEN` (419 real, sin el atajo de pruebas) → logout → `me` 401. Cubre identity-access "Credenciales válidas", "Escritura con token CSRF caducado", "Cierre de sesión exitoso" y seed-data "Arranque desde cero" sobre el stack real. Verifica: el script termina con código 0 sobre el stack en marcha.

- [ ] 7.3 CI: lint de OpenAPI y comprobación de deriva de 5.16 en el trabajo `backend`. Sin escenario propio; protege la tabla API contract de `design.md`. Verifica: workflow verde y control positivo (un endpoint quitado del archivo hace fallar la deriva). Cimiento.

## 8. Integración y cierre

- [ ] 8.1 Corrida completa de cierre: Pint, Larastan, Pest, ESLint, Vitest. Verifica: todas verdes, registradas en `journal.md` dentro del presupuesto de 3 corridas. Integración: ejerce todos los escenarios ya citados en 1–7. Cimiento.
- [ ] 8.2 `verification.md` en tablas: escenario → prueba → archivo:línea; `[MUT]` M1–M11 aplicado/restaurado; columna cláusula → ruta archivo:línea por cada hit del ancla de transporte; § 0 con líneas de producto, de prueba y de registro. Verifica: `openspec validate add-catalog-and-identity --strict` válido y spec-validator sin hallazgos. Registro: sin escenario propio. Cimiento.

## Workflow follow-up

- GATE 2 lo emite solo `final-auditor`.
- Archivar con `openspec archive add-catalog-and-identity -y` y validar `openspec validate --all --strict`.
