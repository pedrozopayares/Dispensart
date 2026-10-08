# Design — add-catalog-and-identity (S1, tier A)

## Context

Motivación y alcance en `proposal.md`; requisitos en `specs/`. Se construye sobre S0 (`add-project-skeleton/design.md`):
imagen `api` Nginx + PHP-FPM tras el Nginx de `web` (mismo origen `localhost:8090`), Pest en `api-tools` contra
`dispensart_test` en PostgreSQL 16, log JSON en UTC, `shouldRenderJsonWhen` para `api/*`, tablas `users` y `sessions`.

## Goals / Non-Goals

**Goals**
- Fijar las costuras de identidad que S2–S7 heredan: rol leído de la base en cada petición, mapa de capacidades
  único, forma de rechazo única, reloj de negocio único (`America/Bogota`).
- Que cada prueba de sesión pueda fallar: CSRF activa en Pest, guardias frescos por petición, mutaciones M1–M11.
- Integridad del catálogo en la base, con migraciones reversibles.

**Non-Goals**
- Tokens bearer, `personal_access_tokens`, OAuth, recordarme, recuperación de contraseña, verificación de correo.
- Tabla de roles o permisos editables en la base (el mapa sale de § 3 y vive en código).
- Paginación, borrado, cambio de rol, desactivación (proposal § Assumptions 4 y 8).
- Índices para FEFO o alertas (`expires_on`): entran con S2/S5, que traen las consultas que los justifican.

## Decisions

### D1. Login con sesión: `statefulApi()` y rechazo explícito del origen ajeno (pregunta 1)
`bootstrap/app.php` llama `$middleware->statefulApi()`: el grupo `api` antepone `EnsureFrontendRequestsAreStateful`,
que solo para peticiones cuyo `Origin`/`Referer` está en `SANCTUM_STATEFUL_DOMAINS` (por defecto
`localhost:8090,127.0.0.1:8090`) agrega cifrado de cookies, sesión, CSRF y `AuthenticateSession`. Las rutas viven en
`routes/api.php`; `POST /api/auth/login` queda fuera de `auth:sanctum`, el resto dentro. `/sanctum/csrf-cookie` es la
ruta propia de Sanctum (grupo `web`, 204).
- **Origen ajeno** (o sin `Origin`/`Referer`, p. ej. `curl`): la petición no tiene sesión. `LoginController` comprueba
  `$request->hasSession()`; si es falso lanza `AuthorizationException` → **HTTP 403 `forbidden`**, sin consultar la
  base, sin tocar el limitador y sin `Set-Cookie`. Sin ese corte, `session()->regenerate()` lanza `RuntimeException`
  (500) y el login serviría como oráculo de contraseñas sin CSRF. La prueba de "Origen ajeno a la SPA" fija el 403 y
  además comprueba que `me` con las cookies recibidas da 401.
- **Sin bearer**: `Sanctum::getAccessTokenFromRequestUsing(fn () => null)` en `AppServiceProvider`; no se publica la
  migración `personal_access_tokens` ni se usa `HasApiTokens`. Una cabecera `Authorization: Bearer x` da 401, no 500
  (consulta a tabla inexistente).
- Rechazada: login en el grupo `web` bajo `/api` (rutas en `web.php`). Funciona sin Sanctum para el login, pero parte
  la superficie `/api` en dos pilas de middleware y obliga a duplicar el render JSON. Rechazada: `php artisan
  install:api` tal cual (trae la tabla de tokens que ADR-0001 excluye).
- Revisar si: S8 despliega SPA y API en orígenes distintos (entonces CORS con credenciales y `SESSION_DOMAIN`).

### D2. CSRF real en pruebas y cliente SPA de prueba (pregunta 2)
`App\Http\Middleware\ValidateCsrfToken extends Illuminate\Foundation\Http\Middleware\ValidateCsrfToken` y sobrescribe
`runningUnitTests(): bool` → `false`. Se registra en `config/sanctum.php` (`middleware.validate_csrf_token`) y se
reemplaza en el grupo `web` (`$middleware->web(replace: [...])`). En producción el comportamiento es idéntico (allí
`runningUnitTests()` ya es falso); en pruebas deja de existir el atajo. Sin lista `except`.
- `tests/Support/SpaClient`: envía `Origin: http://localhost:8090`, guarda las cookies de cada respuesta (valor
  cifrado tal cual, reenviado con `withUnencryptedCookies`), pone `X-XSRF-TOKEN` desde la cookie `XSRF-TOKEN` y llama
  `app('auth')->forgetGuards()` antes de cada petición, igual que un proceso FPM nuevo. Sin esto el guardia cachea el
  modelo de la petición anterior y "Cambio de rol en la base…" (M4) sería falso verde o falso rojo.
- Pruebas de autorización por rol usan `actingAs` **sin** `Origin`: no pasan por la pila con estado y no necesitan
  token; nunca prueban login, logout ni CSRF (regla de `tasks.md`).
- Precedencia fijada: en una escritura desde el origen de la SPA, CSRF (419) se evalúa antes que la sesión (401);
  luego `auth:sanctum` (401), enlace de modelo (404), `authorize()` del FormRequest (403) y reglas (422).
- Rechazada: variable `CSRF_IN_TESTS` que activa la verificación solo en ciertas pruebas. Dos comportamientos del
  mismo middleware; el olvido de la variable vuelve al falso verde. Rechazada: `withoutMiddleware` inverso. No existe.
- Revisar si: Laravel cambia la firma de `runningUnitTests()` (la prueba M5 lo detecta: dejaría de fallar).

### D3. Login: limitador, respuesta idéntica y regeneración
`App\Actions\Identity\LoginAction`: normaliza el correo (`trim` + minúsculas); clave del limitador
`'login|'.sha1($email).'|'.$request->ip()` (sin correo en claro en la tabla `cache`); si `tooManyAttempts(5)` →
`TooManyLoginAttempts` (429 + `Retry-After` = `availableIn`). Busca el usuario; si no existe, ejecuta `Hash::check`
contra un hash ficticio fijo para igualar el tiempo; si falla, `hit($key, 60)` y `InvalidCredentials` (422). Si
acierta: `clear($key)`, `Auth::guard('web')->login($user)`, `session()->regenerate()`. La sesión guarda solo el id;
el rol nunca se copia a la sesión.
- `TrustProxies` con `TRUSTED_PROXIES` (por defecto rangos privados `10.0.0.0/8,172.16.0.0/12,192.168.0.0/16`): la
  API está detrás de dos Nginx; sin esto todos los clientes comparten la IP del contenedor `web` y un atacante bloquea
  el correo de cualquiera.
- Rechazada: `throttle:` por ruta. Cuenta todas las peticiones, no solo los fallos, y no se reinicia con el éxito
  (escenario "Éxito reinicia el contador"). Rechazada: `Timebox` (añade ~300 ms a cada prueba de fallo).
- Revisar si: aparecen múltiples réplicas de `api` (el store `database` del limitador ya es compartido; basta).

### D4. Roles y capacidades
- `App\Enums\Role` (string, 5 casos) y `App\Enums\Ability` (string, 13 casos de la spec). `Role::abilities()` devuelve
  la lista exacta de § 3 desde un `match` exhaustivo; `Role::allows(string $ability)` → `Ability::tryFrom` nulo =
  `false` (denegación por defecto). `User::$casts['role'] = Role::class`.
- Cada `Ability` se registra como Gate en `AppServiceProvider` (`Gate::define($a->value, fn (User $u) =>
  $u->role->allows($a->value))`). Policies delegan en el mismo método:

| Policy | Método | Capacidad |
|---|---|---|
| `WarehousePolicy`, `ProductPolicy` | `viewAny` | `catalog.view` |
| `WarehousePolicy`, `ProductPolicy` | `create`, `update` | `catalog.manage` |
| `LotPolicy` | `viewAny` | `catalog.view` |
| `UserPolicy` | `viewAny`, `create` | `users.manage` |

- Sin `Gate::before`: `admin` no es superusuario (no ve pacientes ni inventario).
- Rechazada: `spatie/laravel-permission`. Dependencia nueva y tablas editables para un mapa fijo por enunciado;
  invita a que la base diverja de § 3. Rechazada: capacidades guardadas en la sesión al login (M4 lo prueba).
- Revisar si: el negocio pide roles configurables por IPS.

### D5. Forma de rechazo
`withExceptions()` en `bootstrap/app.php` traduce, solo para `api/*` y siempre a JSON `{code, message[, errors]}`:

| Excepción | HTTP | `code` |
|---|---|---|
| `AuthenticationException` | 401 | `unauthenticated` |
| `AuthorizationException`, `AccessDeniedHttpException` | 403 | `forbidden` |
| `ModelNotFoundException`, `NotFoundHttpException` | 404 | `not_found` |
| `HttpException` 419 (de `TokenMismatchException`) | 419 | `csrf_token_mismatch` |
| `ValidationException` | 422 | `validation_failed` + `errors` |
| `App\Exceptions\InvalidCredentials` | 422 | `invalid_credentials` |
| `App\Exceptions\TooManyLoginAttempts` | 429 | `too_many_attempts` + `Retry-After` |
| cualquier otra | 500 | `server_error` |

Mensajes en `lang/es/errors.php`; `app.locale = es`; `lang/es/validation.php` escrito a mano solo con las reglas usadas
(`required`, `string`, `email`, `max`, `min`, `unique`, `boolean`, `integer`, `in`) y `attributes`. Nunca traza, SQL
ni nombre de modelo (`APP_DEBUG=false` en la imagen; la prueba fija el cuerpo exacto del 403).
- Rechazada: paquete `laravel-lang/lang` (dependencia para nueve cadenas).

### D6. Reloj de negocio separado de `app.timezone`
`app.timezone` sigue en `UTC` (timestamps guardados y log JSON de S0, que exige UTC). `config/dispensart.php` define
`business_timezone = 'America/Bogota'`; `App\Support\BusinessCalendar::today(): CarbonImmutable` es el único lugar que
calcula "hoy". `Lot::isExpiredOn(CarbonImmutable $today): bool` → `expires_on <= $today`; `LotResource` lo llama con
`BusinessCalendar::today()`. Pruebas con `travelTo`, incluida la frontera 23:30 Bogotá = 04:30 UTC del día siguiente.
- Rechazada: `APP_TIMEZONE=America/Bogota` (lo pedía el borrador de 4.2). Rompe el contrato UTC del log de S0 y mezcla
  zonas en columnas `timestamp`. Rechazada: columna `is_expired` (la spec la prohíbe; se desactualiza sola).
- Revisar si: FARTMAR opera bodegas en otra zona horaria.

### D7. Router de la SPA: `react-router` v7 en modo datos (pregunta 3)
S0 no trae router. Se agrega `react-router` (MIT) con `createBrowserRouter`: ruta pública `/login`, ruta de diseño
protegida que envuelve `/` y toda ruta futura, `*` → protegida. La redirección vive en el componente de diseño
(consulta `me` vía TanStack Query), no en `loader`, para no duplicar caché. `router.navigate` permite al manejador
global de `unauthenticated` llevar a `/login` fuera de componentes.
- Rechazada: sin router (estado + `history.pushState` a mano). S6 suma cuatro pantallas; se reimplementaría historial,
  enlaces y rutas anidadas. Rechazada: TanStack Router (tipado fuerte, pero plugin de Vite y generación de código para
  6 rutas). Rechazada: `wouter` (viable y mínimo; menos reconocible para el jurado y sin rutas de diseño anidadas
  nativas).
- Dependencia de UI, no de dominio: no requiere puerto. Revisar si: S6 necesita búsqueda tipada en URL.

### D8. Cliente HTTP de la SPA
`src/lib/api.ts` sobre `fetch` (`credentials: 'same-origin'`, `Accept: application/json`), lee `XSRF-TOKEN` de
`document.cookie` para `X-XSRF-TOKEN`, lanza `ApiError {status, code, errors}`; ante `csrf_token_mismatch` renueva la
cookie y reintenta una sola vez. `QueryCache`/`MutationCache.onError` con `unauthenticated` → `queryClient.clear()` +
`router.navigate('/login', {state: {expired: true}})`. Sesión: `useQuery(['session','me'])` donde 401 = `null`, no error.
Textos en `src/strings.ts`, incluidas las etiquetas de rol. Pruebas Vitest con `vi.stubGlobal('fetch')`.
- Rechazada: `axios` (lee XSRF solo; el reintento único es igual de manual). Rechazada: MSW (dependencia para stubs que
  `fetch` falso cubre en S1). Revisar si: S6 multiplica escenarios de red.

### D9. OpenAPI: Scramble exportado + lint con Redocly (pregunta 4)
`dedoc/scramble` (MIT) como `require-dev`: infiere el contrato de rutas, FormRequests y Resources. Una extensión de
excepciones de Scramble documenta la forma `{code, message, errors}` de D5. `php artisan scramble:export
--path=openapi.json` escribe `software/api/openapi.json` (versionado, entregable de la parte A). Lint con
`@redocly/cli` fijado en el `package.json` raíz (herramientas de desarrollo); un script compara la exportación con el
archivo versionado (`git diff --exit-code`) para detectar deriva. CI corre ambos.
- Rechazada: YAML a mano + lint. Mismo lint, pero el contrato deriva del código sin que nada lo note.
- Rechazada: servir `/docs/api` en la imagen de producción (Scramble no está en `--no-dev`; el archivo basta).
- **Plan B con tiempo acotado (30 min)**: si Scramble no instala en Laravel 13 / PHP 8.5 o no puede expresar la
  forma de error, `software/api/openapi.yaml` a mano con el mismo lint; se anota en el journal. Revisar al aplicar 5.16.

### D10. Contraseña de usuarios semilla vs regla de S0 (pregunta 5)
S0 (`runtime-environment`, "Secretos fuera del repositorio") permite **un** valor por defecto de credencial. S1 añade
otro. Resolución: **delta `MODIFIED` sobre `runtime-environment`** (lo escribe el spec-engineer antes del apply de S1,
con S0 ya archivado): "los valores por defecto de credencial SHALL limitarse a una lista cerrada de contraseñas de
desarrollo local marcadas como tales en `.env.example` (`DB_PASSWORD`, `SEED_USER_PASSWORD`); con `APP_ENV=production`
el valor por defecto de `SEED_USER_PASSWORD` SHALL NOT usarse". No debilita la regla de repo público: el valor protege
usuarios sintéticos de un stack local sin datos reales, igual que la contraseña de BD de desarrollo, y en producción
queda inerte (seed-data "Producción sin contraseña explícita").
- Fuente única del valor por defecto: `config/dispensart.php` (`seed_user_password` = `env('SEED_USER_PASSWORD')`,
  `seed_user_password_dev_default` = literal marcado `solo desarrollo`). Compose **no** define valor por defecto
  (`SEED_USER_PASSWORD: ${SEED_USER_PASSWORD:-}`; vacío = ausente). Así la prueba Pest sin la variable usa el mismo
  valor que el stack, y el seeder distingue "explícita" de "ausente".
- Rechazada: contraseña aleatoria escrita en el log o en `api_state` (log con secreto; el jurado no podría entrar sin
  `exec`). Rechazada: sin valor por defecto (rompe "Arranque desde cero" con un comando). Rechazada: leer la regla de S0
  como "solo infraestructura" sin tocar la spec (reinterpretación que el auditor marcaría).

### D11. Siembra
`DatabaseSeeder` → `WarehouseSeeder`, `ProductSeeder`, `LotSeeder`, `UserSeeder`; cada fila con `firstOrCreate` por
clave natural (`code`; `code`; `product_id`+`lot_code`; `email`). Nunca `updateOrCreate`: no pisa cambios del admin.
Vencimientos relativos a `BusinessCalendar::today()`. El entrypoint de S0 pasa a `migrate --force` → `db:seed --force`
→ `config:cache` → `supervisord`. Producción sin variable: catálogo sí, usuarios no, `Log::warning` sin valor.
- **Segundo `regente_farmacia`: no se siembra en S1.** La spec fija exactamente 5 usuarios ("Un usuario por rol",
  "Admin lista usuarios"); sembrarlo cambiaría escenarios. RN-05 se demuestra con auxiliar que dispensa + regente que
  autoriza; el caso regente-dispensa/otro-regente-autoriza lo resuelve S3 con un `MODIFIED` de `seed-data` (una fila,
  costo mínimo). Las pruebas de S3 usan factories, no semilla.
- Rechazada: siembra en migraciones (mezcla esquema y datos; `down()` borraría datos del admin). Rechazada: servicio
  compose de siembra aparte (otra pieza para una orden de un segundo).

## API contract

CSRF = exige `X-XSRF-TOKEN` cuando la petición viene del origen de la SPA. Todos los rechazos con la forma de D5.

| Método y ruta | Sesión / CSRF | Petición | Respuestas | Prueba HTTP real |
|---|---|---|---|---|
| `GET /sanctum/csrf-cookie` | no / no | — | 204 + cookie `XSRF-TOKEN` (sin HttpOnly) | 4.2 |
| `POST /api/auth/login` | no / sí | `email`, `password` | 200 `{id,name,email,role,abilities}` + cookie de sesión nueva HttpOnly; 403 `forbidden` (origen ajeno); 419; 422 `validation_failed`/`invalid_credentials`; 429 + `Retry-After` | 5.1, 5.2 |
| `POST /api/auth/logout` | sí / sí | — | 204; 401; 419 | 5.4 |
| `GET /api/auth/me` | sí / no | — | 200 `{id,name,email,role,abilities}`; 401 | 5.5 |
| `GET /api/users` | sí / no | — | 200 `[{id,name,email,role}]` por `name`; 401; 403 | 5.6 |
| `POST /api/users` | sí / sí | `name`, `email`, `password` (≥ 8), `role` | 201 `{id,name,email,role}`; 401; 403; 419; 422 | 5.7 |
| `GET /api/warehouses` | sí / no | — | 200 `[{id,code,name}]` por `name`; 401 | 5.8 |
| `POST /api/warehouses` | sí / sí | `code` (≤ 20), `name` (≤ 120) | 201; 401; 403; 419; 422 | 5.9 |
| `PATCH /api/warehouses/{id}` | sí / sí | subconjunto de `code`, `name` | 200; 401; 403; 404; 419; 422 | 5.10 |
| `GET /api/products` | sí / no | — | 200 `[{id,code,name,presentation,is_controlled}]` por `name`; 401 | 5.11 |
| `POST /api/products` | sí / sí | `code` (≤ 30), `name` (≤ 150), `presentation?` (≤ 150), `is_controlled?` | 201; 401; 403; 419; 422 | 5.12 |
| `PATCH /api/products/{id}` | sí / sí | subconjunto de los anteriores | 200; 401; 403; 404; 419; 422 | 5.13 |
| `GET /api/lots` | sí / no | `product_id?` entero ≥ 1 | 200 `[{id,product_id,lot_code,expires_on,is_expired}]` por `expires_on`, `id`; 401; 422 | 5.14 |

Respuestas exitosas con la envoltura por defecto de API Resources (`{"data": …}`, objeto o arreglo), lista para la
paginación de S2+; rechazos sin envoltura (D5). Fechas `AAAA-MM-DD`; ids enteros.

## Data impact

Migraciones nuevas, todas con `down()` y restricciones con nombre explícito (las mutaciones M8/M9 las quitan por nombre):

| Tabla | Columnas | Restricciones |
|---|---|---|
| `users` (alter) | `role varchar(32) NOT NULL`, **sin default** | `users_role_check CHECK (role IN (...5 literales...))` |
| `warehouses` | `id` bigint identity, `code varchar(20)`, `name varchar(120)`, `timestampsTz` | `warehouses_code_unique`, `warehouses_name_unique` |
| `products` | `id`, `code varchar(30)`, `name varchar(150)`, `presentation varchar(150) NULL`, `is_controlled boolean NOT NULL DEFAULT false`, `timestampsTz` | `products_code_unique` |
| `lots` | `id`, `product_id bigint NOT NULL`, `lot_code varchar(50) NOT NULL`, `expires_on date NOT NULL`, `timestampsTz` | FK `lots_product_id_foreign` `ON DELETE RESTRICT`; `lots_product_id_lot_code_unique` (cubre también el índice de la FK) |

- `role` como `varchar` + `CHECK`, no tipo `ENUM` de PostgreSQL: quitar o renombrar un valor de un `ENUM` no tiene
  `ALTER` directo y el `down()` se complica; un `CHECK` con nombre se reemplaza en una sentencia. Los literales se
  escriben en la migración, no desde `Role::cases()` (una migración es una foto: si el enum cambia, la historia no).
- `role` sin default: un default (p. ej. `auditor`) concedería `patients.view` en silencio. Si `users` tuviera filas,
  la migración falla ruidosamente; en S1 está vacía.
- Unicidad de correo: el índice único por defecto + normalización a minúsculas en la acción y en `prepareForValidation`.
  Rechazado `citext` (extensión) y un `CHECK (email = lower(email))` sin escenario que lo ejercite.
- Todo relacional; sin JSONB (no hay atributos variables en S1).
- Reversión: `php artisan migrate:rollback --step=N` deja `users` como en S0; en local, `docker compose down -v`.

## Risks / Trade-offs

1. [Falso verde en flujos de sesión: CSRF omitida en pruebas, guardia cacheado entre peticiones, sesión `array`] →
   D2 (middleware sin atajo, `SpaClient` con `forgetGuards`), mutaciones M4/M5 con control positivo (petición con token
   válido que pasa) y script de humo 7.2 contra el stack real con cookies reales.
2. [Scramble incompatible con Laravel 13 / PHP 8.5 o forma de error mal inferida] → plan B de D9 acotado a 30 min;
   el lint y la comparación de deriva son los mismos en ambos caminos.
3. [Limitador por IP detrás de dos proxies: o todos comparten IP (bloqueo de cuentas ajenas) o se confía en
   `X-Forwarded-For` falsificado] → `TrustProxies` solo en rangos privados (la API nunca se expone al host); la
   prueba del limitador fija `REMOTE_ADDR`; revisar en S8 con el proxy real del destino.

Menores: los vencimientos semilla se desplazan con el tiempo en un volumen viejo (remedio documentado: `down -v`);
el valor por defecto de `SEED_USER_PASSWORD` es público por diseño (D10) y queda inerte en producción.

## Migration Plan

Arranque: `migrate --force` → `db:seed --force` (idempotente). Requisitos previos al apply: S0 archivado y el delta
`MODIFIED` de D10 escrito. Reversión: revertir commits + `migrate:rollback` o `down -v` en local.

## Open Questions

- Si S0 fija un puerto para el servidor de desarrollo de Vite, añadirlo a `SANCTUM_STATEFUL_DOMAINS` en
  `.env.example`. No cambia specs ni tareas (el contrato se prueba contra `localhost:8090`).
