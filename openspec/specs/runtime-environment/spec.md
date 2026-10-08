# runtime-environment Specification

## Purpose
Garantiza que todo el sistema (base de datos, API y SPA) arranque desde cero con un solo comando de Docker
Compose, con salud verificada, superficie de red mínima, imágenes no root y ningún secreto en el
repositorio.

## Requirements

### Requirement: Arranque con un solo comando
El sistema SHALL levantarse desde un clon limpio con `docker compose -f software/compose.yaml up --build`,
sin archivo `.env` previo ni pasos manuales, y quedar con `db`, `api` y `web` en estado `healthy`. Los
datos de la base SHALL persistir en un volumen nombrado entre `down` y `up`.

#### Scenario: Arranque desde cero sin .env
- **WHEN** se ejecuta el comando en un clon limpio sin `software/.env`
- **THEN** los tres servicios llegan a `healthy` y `GET http://localhost:8090/ready` responde HTTP 200 [ancla: ruta `GET /ready` vía proxy de `web`, archivo:línea al aplicar]

#### Scenario: Puertos configurables
- **WHEN** se arranca con `WEB_PORT=8095` y `DB_PORT=5440` en el entorno o en `software/.env`
- **THEN** la SPA responde en el puerto 8095 y PostgreSQL en el 5440, y nada escucha en 8090 ni en 5434 por cuenta del stack

#### Scenario: Datos conservados entre reinicios
- **WHEN** se ejecuta `down` sin `-v` y luego `up` otra vez
- **THEN** las tablas y filas existentes siguen presentes y el arranque no falla

#### Scenario: Reinicio desde cero con volúmenes borrados
- **WHEN** se ejecuta `down -v` y luego `up --build`
- **THEN** la base arranca vacía, las migraciones se aplican de nuevo y los tres servicios llegan a `healthy`

### Requirement: Inicialización automática de la API
Al arrancar, el contenedor `api` SHALL aplicar las migraciones pendientes antes de declararse sano. Si
`APP_KEY` no está definida, SHALL generar una, conservarla en un volumen para reinicios posteriores y no
escribirla en logs ni en el repositorio. Una `APP_KEY` provista SHALL usarse sin cambios.

#### Scenario: Migraciones aplicadas al arrancar
- **WHEN** `api` arranca contra una base sin migrar
- **THEN** todas las migraciones quedan aplicadas antes de que `api` figure como `healthy`

#### Scenario: Arranque repetido idempotente
- **WHEN** `api` arranca de nuevo sin migraciones nuevas
- **THEN** no se aplica ninguna migración, el arranque no falla y `api` vuelve a `healthy`

#### Scenario: APP_KEY ausente
- **WHEN** `api` arranca sin `APP_KEY`
- **THEN** genera una clave, llega a `healthy`, la clave no aparece en `docker compose logs api` y tras reiniciar el contenedor la clave efectiva es la misma

#### Scenario: APP_KEY provista
- **WHEN** `api` arranca con una `APP_KEY` definida en el entorno
- **THEN** usa exactamente esa clave y no genera otra

### Requirement: Superficie de red mínima
El stack SHALL publicar al host solo `WEB_PORT` (por defecto 8090) del servicio `web` y `DB_PORT` (por
defecto 5434) del servicio `db`, este último solo en `127.0.0.1`. El servicio `api` SHALL NOT publicar
ningún puerto; solo se alcanza por la red interna de compose.

#### Scenario: Solo dos puertos publicados
- **WHEN** se inspecciona el stack en marcha con `docker compose -f software/compose.yaml ps --format json`
- **THEN** solo `web` y `db` tienen puertos publicados, y `api` no tiene ninguno

#### Scenario: API inalcanzable desde el host
- **WHEN** desde el host se intenta conectar a cualquier puerto interno de `api`
- **THEN** la conexión no se establece; la API solo responde a través de `web`

#### Scenario: Base de datos solo en loopback
- **WHEN** se inspecciona la publicación del puerto de `db`
- **THEN** está enlazada a `127.0.0.1` y no a `0.0.0.0`

### Requirement: Orden de arranque por salud
Cada servicio SHALL declarar un healthcheck. `api` SHALL esperar a que `db` esté `healthy` y `web` a que
`api` esté `healthy`. El healthcheck de `api` SHALL reflejar la disponibilidad (base alcanzable y
migraciones aplicadas), no solo la vivacidad.

#### Scenario: Servicios sanos tras el arranque
- **WHEN** el stack termina de arrancar
- **THEN** `docker compose ps` muestra `db`, `api` y `web` como `healthy`

#### Scenario: Dependencia no sana bloquea el arranque
- **WHEN** `db` no llega a `healthy`
- **THEN** `api` no se inicia, y por lo tanto `web` tampoco

#### Scenario: Base caída marca la API como no sana
- **WHEN** se detiene `db` con el stack en marcha
- **THEN** el healthcheck de `api` pasa a `unhealthy` en a lo sumo tres intervalos y `GET /ready` responde HTTP 503 [ancla: ruta `GET /ready`, archivo:línea al aplicar]

### Requirement: Imágenes multi-stage no root
Las imágenes de `api` y `web` SHALL construirse en varias etapas, ejecutar sus procesos con un usuario
distinto de root y no contener dependencias de desarrollo, código fuente sin compilar de la SPA ni archivos
`.env`. Cada contexto de construcción SHALL tener `.dockerignore`.

#### Scenario: Procesos sin root
- **WHEN** se ejecuta `id -u` dentro de los contenedores `api` y `web` en marcha
- **THEN** ninguno devuelve `0`

#### Scenario: Imagen de API sin dependencias de desarrollo
- **WHEN** se inspecciona el sistema de archivos de la imagen de `api`
- **THEN** no contiene los paquetes de desarrollo de Composer (Pest, Larastan) ni Node.js

#### Scenario: Imagen web solo con artefactos compilados
- **WHEN** se inspecciona el sistema de archivos de la imagen de `web`
- **THEN** contiene los archivos estáticos compilados y la configuración de Nginx, y no contiene `node_modules` ni el código fuente TypeScript

#### Scenario: Archivo .env local excluido de las imágenes
- **WHEN** existe `software/.env` o `software/api/.env` en el árbol de trabajo y se construyen las imágenes
- **THEN** ninguna imagen contiene un archivo `.env`

### Requirement: Secretos fuera del repositorio
`software/.env.example` SHALL documentar cada variable que lee el stack, con marcadores de posición y
`APP_KEY` vacía. Los archivos `.env` SHALL estar ignorados por git. Toda credencial SHALL poder
reemplazarse por entorno; los valores por defecto de credencial SHALL limitarse a la lista cerrada
`DB_PASSWORD` y `SEED_USER_PASSWORD`, contraseñas de desarrollo local marcadas como tales en
`.env.example`. Con `APP_ENV=production` el valor por defecto de `SEED_USER_PASSWORD` SHALL NOT usarse.

#### Scenario: Plantilla completa
- **WHEN** se compara cada variable `${...}` referida en `software/compose.yaml` con `software/.env.example`
- **THEN** todas aparecen en la plantilla y `APP_KEY` está vacía

#### Scenario: .env ignorado por git
- **WHEN** se ejecuta `git check-ignore software/.env software/api/.env software/web/.env`
- **THEN** git confirma las tres rutas como ignoradas

#### Scenario: Credencial reemplazable
- **WHEN** se arranca con `DB_PASSWORD` o `SEED_USER_PASSWORD` definida en el entorno
- **THEN** el stack usa ese valor y no el de desarrollo local: `db` y `api` con `DB_PASSWORD`, los usuarios semilla con `SEED_USER_PASSWORD`

#### Scenario: Lista cerrada de valores por defecto
- **WHEN** se revisan `software/compose.yaml`, `software/.env.example` y la configuración de la API en busca de credenciales con valor por defecto
- **THEN** solo `DB_PASSWORD` y `SEED_USER_PASSWORD` lo tienen, ambas marcadas como solo desarrollo, y compose no define valor por defecto para `SEED_USER_PASSWORD`

#### Scenario: Valor por defecto de la siembra inerte en producción
- **WHEN** el stack arranca con `APP_ENV=production` y sin `SEED_USER_PASSWORD`
- **THEN** no se crea ningún usuario semilla con el valor por defecto de desarrollo

### Requirement: SPA servida en el mismo origen
El servicio `web` SHALL servir la SPA en `/` y reenviar a `api` las rutas `/api/*`, `/sanctum/*`, `/health`
y `/ready`. Toda otra ruta SHALL devolver el documento de la SPA. La página shell SHALL declarar
`lang="es"` y mostrar solo textos del módulo central de textos en español.

#### Scenario: Shell en la raíz
- **WHEN** un navegador abre `http://localhost:8090/`
- **THEN** recibe HTTP 200 con el documento de la SPA y la página muestra el nombre del producto y el mensaje de bienvenida en español [ancla: configuración Nginx de `web`, archivo:línea al aplicar]

#### Scenario: Enlace profundo de la SPA
- **WHEN** un navegador abre `http://localhost:8090/inventario`
- **THEN** recibe HTTP 200 con el mismo documento de la SPA, no una página 404 de Nginx [ancla: regla de respaldo de Nginx de `web`, archivo:línea al aplicar]

#### Scenario: Ruta de API desconocida no cae en la SPA
- **WHEN** un cliente envía `GET http://localhost:8090/api/no-existe` con `Accept: application/json`
- **THEN** recibe HTTP 404 en JSON emitido por la API, con `X-Correlation-Id`, y no el documento de la SPA [ancla: enrutador de la API vía proxy de `web`, archivo:línea al aplicar]

#### Scenario: Textos del shell desde el módulo central
- **WHEN** se renderiza la página shell en una prueba de componentes
- **THEN** cada texto visible coincide con un valor del módulo central de textos y el documento declara `lang="es"`
