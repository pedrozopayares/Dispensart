# Design — add-project-skeleton (S0, tier B)

## Context

Repositorio sin código de aplicación; motivación y alcance en `proposal.md`. Restricciones: ADR-0001..0006,
PHP 8.5 solo en Docker (el host trae PHP 8.4.8, no se usa para ejecutar la suite), al host solo `WEB_PORT`
8090 y `DB_PORT` 5434 (loopback), pruebas siempre contra PostgreSQL 16.

## Goals / Non-Goals

**Goals**
- Fijar las costuras que S1–S8 heredan: correlation id + log JSON, comprobación de disponibilidad, arranque
  del contenedor `api`, dónde y contra qué base corre Pest.
- Cada decisión, reversible con un commit (sin datos de dominio en juego).

**Non-Goals**
- Abstracciones para proveedores de salud externos (caché, colas, S3): `/ready` mira solo base + migraciones.
- Publicación de imágenes, fijado de acciones por SHA, despliegue (S8).
- Redacción de excepciones con datos personales en logs (ver Riesgos; entra con el primer dato personal).

## Decisions

### D1. Imagen `api`: Nginx + PHP-FPM en un contenedor, bajo supervisord
Etapas: `base` (php:8.5-fpm-alpine + `pdo_pgsql`), `dev` (base + Composer + dependencias de desarrollo),
`prod` (base + vendor sin dev + Nginx + supervisord). Nginx escucha en 8080 interno (no privilegiado) y
habla con FPM por socket unix; ambos procesos y supervisord corren como usuario no root.
- Rechazada: imagen solo PHP-FPM y FastCGI directo desde el Nginx de `web`. Menos procesos, pero acopla
  `web` a rutas internas de PHP (`SCRIPT_FILENAME`) y deja una imagen `api` que no habla HTTP sola, lo que
  complica S8 (desplegarla aislada). La prueba pide "PHP-FPM con Nginx" para la API.
- Rechazada: FrankenPHP/Octane. No es PHP-FPM.
- Trade-off: supervisord añade Python (~40 MB). Si FPM muere, Nginx sigue vivo con 502; lo detecta el
  healthcheck porque pega a `/ready` atravesando Nginx → FPM → base.
- Revisar si: S8 elige un destino que ejecute un solo proceso por contenedor o el tamaño de imagen penaliza.

### D2. Arranque de `api` y persistencia de `APP_KEY` (pregunta 1)
`docker/api/entrypoint.sh` (sin `set -x`): si `APP_KEY` viene en el entorno, se usa tal cual. Si no, lee
`/var/lib/dispensart/app_key`; si no existe, la genera con `php artisan key:generate --show` capturada en
una variable (nunca a stdout), la escribe con permisos `0600` y la exporta. Luego `php artisan migrate
--force`, `config:cache` y `exec supervisord`. El directorio vive en un volumen nombrado `api_state`; la
imagen crea el directorio con dueño el usuario no root, y Docker copia esa propiedad al volumen vacío.
- Rechazada: escribir en `.env` dentro del contenedor (se pierde al recrear; `.env` está excluido de la
  imagen por spec). Rechazada: guardarla en la base (dependencia circular con el cifrado de sesión).
  Rechazada: exigirla al usuario (rompe "arranque sin .env").
- Revisar si: S8 despliega fuera de compose (allí `APP_KEY` llega siempre por entorno/secreto).

### D3. Base de pruebas: segunda base en el mismo contenedor `db` (pregunta 2)
Script `software/docker/db/init/01-create-test-db.sql` montado en `/docker-entrypoint-initdb.d/` crea
`dispensart_test`. En CI, el servicio `postgres:16` se declara con `POSTGRES_DB=dispensart_test`.
`phpunit.xml` fuerza `DB_CONNECTION=pgsql` y `DB_DATABASE=dispensart_test` (`force="true"`), de modo que
la suite nunca toca `dispensart` ni cae a SQLite; `DB_HOST` llega por entorno (`db` local, `127.0.0.1` CI).
- Rechazada: servicio `db-test` aparte (tmpfs). Más rápido, pero otro contenedor, otra credencial y otra
  pieza de compose para una suite que hoy tarda segundos.
- Revisar si: la suite de backend supera ~2 min localmente.

### D4. Dónde corre Pest localmente
Servicio `api-tools` en compose, `profiles: ["tools"]`, `build.target: dev`, monta `./api:/app`, red interna,
sin puertos. Pint, Larastan y Pest se ejecutan con `docker compose -f software/compose.yaml run --rm
api-tools <cmd>`. Vitest y ESLint corren en el host (Node no tiene restricción de ADR).
- Rechazada: ejecutar en el contenedor `api` en marcha. Su imagen es `prod` y por spec no tiene Pest.
- Rechazada: PHP del host contra `127.0.0.1:5434`. El host tiene 8.4, ADR-0001 fija 8.5.
- Revisar si: aparece fricción de UID con el bind mount en Linux (pasar `--user`).

### D5. Simulación de base caída y migración pendiente en `/ready` (pregunta 3)
`App\Health\ReadinessChecker` (clase concreta, sin interfaz) hace `select 1` sobre la conexión por defecto
y compara `migrator->getMigrationFiles([database_path('migrations'), ...migrator->paths()])` contra
`repository->getRan()`. El controlador solo traduce el resultado a 200/503.
- **Base caída**: la prueba sobrescribe `database.connections.pgsql.port` a un puerto cerrado y hace
  `DB::purge('pgsql')`. Ejercita el driver real de PostgreSQL. Va en un archivo de prueba **sin**
  `RefreshDatabase`, así no se rompe la transacción de aislamiento; la config se reconstruye en cada prueba.
  `DB_CONNECT_TIMEOUT` (2 s, pasado a `connect_timeout`) acota la espera también en producción.
- **Migración pendiente**: la prueba crea un directorio temporal con una migración vacía y lo registra con
  `app('migrator')->path($dir)`. El migrador es por instancia de aplicación: no se filtra a otras pruebas.
- Rechazada: interfaz `ReadinessProbe` con un doble en el contenedor. Simularía justo la propiedad bajo
  prueba; la tarea exige PostgreSQL real por resultado.
- Rechazada: detener `db` desde la prueba. No es aislable ni corre en CI. Queda para la verificación
  integral (tarea 4.5).
- Revisar si: `/ready` suma dependencias (entonces sí, un checker por dependencia tras una interfaz).

### D6. Log JSON y correlation id
- Middleware global antepuesto `AssignCorrelationId`: valida `^[A-Za-z0-9._-]{1,128}$`, si no `Str::uuid()`;
  `Context::add('correlation_id', $id)`; pone la cabecera en la respuesta. Respaldo en
  `withExceptions()->respond()` que añade la cabecera desde `Context` a toda respuesta de error.
- Canal `stderr` (Monolog `StreamHandler`) con `tap` `App\Logging\JsonLineTap`: instala un formateador
  derivado de `JsonFormatter` que emite plano `timestamp` (ISO-8601 UTC), `level`, `message`,
  `correlation_id` (de `extra`, donde Laravel vuelca `Context`; `null` fuera de petición) y `context`.
- Línea de cierre en `terminate()` del mismo middleware: `method`, `path` (`$request->path()`, sin query),
  `status`, `duration_ms`. Nunca se registran cuerpo, cookies ni cabeceras.
- Pruebas: sobrescriben `logging.channels.stderr.with.stream` a un archivo temporal y decodifican cada línea.
  Prueban el formateador real, no un `Log::fake`.
- Rechazada: `JsonFormatter` sin modificar vía `LOG_STDERR_FORMATTER`. Emite `level_name`/`datetime`/`extra`
  anidado; no cumple las claves de la spec.
- `/health` y `/ready` se registran en `routes/health.php` sin grupo de middleware (sin sesión ni cookies),
  y se quita la ruta `/up` por defecto. `shouldRenderJsonWhen` para `api/*` garantiza 404 JSON sin `Accept`.

### D7. CI
Un workflow, dos trabajos (`backend`, `frontend`). Backend: `shivammathur/setup-php` (8.5, `pdo_pgsql`),
servicio `postgres:16` con health options. Frontend: `setup-node` con `node-version-file` (`.nvmrc`).
Acciones fijadas por etiqueta mayor.
- Rechazada: fijar por SHA ya. Sin secretos ni permisos de escritura el riesgo es bajo. Revisar en S8, cuando
  entren credenciales de despliegue.

## API contract

| Método y ruta | Respuestas | Prueba HTTP real |
|---|---|---|
| `GET /health` | 200 `{"status":"ok"}`, sin `Set-Cookie` | tarea 2.7 |
| `GET /ready` | 200 `ready` / 503 `not_ready` con `checks.database`, `checks.migrations` | tarea 2.8 |
| cualquier ruta | cabecera `X-Correlation-Id` | tarea 2.5 |
| `GET /api/<desconocida>` | 404 JSON | tarea 2.9 |

## Data impact

Sin tablas de dominio. Se conservan las migraciones por defecto de Laravel (`users`, `cache`, `jobs`), todas
con `down()`: S1 construye sobre `users` y `sessions` (Sanctum SPA), y la verificación de "migraciones
aplicadas al arrancar" tiene filas reales que contar (control positivo). Nueva base `dispensart_test`, solo
local/CI. Volúmenes: `db_data`, `api_state`.

## Risks / Trade-offs

1. [El script de `initdb` solo corre con el volumen vacío; un volumen previo queda sin `dispensart_test`] →
   S0 crea el volumen por primera vez; Pest falla ruidosamente (sin SQLite de respaldo); el remedio
   documentado es `down -v`.
2. [Mensajes de excepción en logs (p. ej. `QueryException` con bindings) pueden filtrar datos personales
   desde S1 (RN-10)] → En S0 no hay datos personales; se reporta como deuda para que el formateador recorte
   bindings cuando entre el primer dato de paciente.
3. [Volumen `api_state` no escribible por el usuario no root] → el directorio se crea en la imagen con su
   dueño antes de declararlo `VOLUME`; la tarea 4.2 lo verifica con dos arranques sucesivos.

## Migration Plan

Sin despliegue. Revertir = revertir commits y `docker compose down -v`.
