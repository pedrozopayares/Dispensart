# Tasks — add-project-skeleton (S0, tier B)

Citas: `SH` = `service-health`, `RE` = `runtime-environment`, `CI` = `ci-pipeline`, seguidas del nombre del
escenario. "Cimiento" marca andamiaje sin escenario propio: no lleva prueba, se verifica con el comando
indicado. Toda prueba de backend corre contra PostgreSQL 16, nunca SQLite (ADR-0001).

## 1. Base de datos

- [x] 1.1 Crear `software/compose.yaml` solo con el servicio `db` (PostgreSQL 16, volumen nombrado, healthcheck, `127.0.0.1:${DB_PORT:-5434}`, contraseña de desarrollo local marcada como tal) y un script de inicio que cree además la base de pruebas. Verificar: `docker compose -f software/compose.yaml up -d db --wait` queda `healthy` y `psql` lista ambas bases. Cita: RE › Base de datos solo en loopback, RE › Credencial reemplazable.

## 2. API Laravel

- [ ] 2.1 Generar Laravel 13 en `software/api` (Composer local solo para el scaffolding), conexión `pgsql` por defecto, locale `es` con base `lang/es`, zona UTC. Verificar: `php artisan about` muestra `pgsql` y `es`. Cimiento.
- [x] 2.2 Crear `software/docker/api/Dockerfile` con las etapas `base` (PHP 8.5-FPM + `pdo_pgsql`) y `dev` (Composer + dependencias de desarrollo) y el servicio `api-tools` en `compose.yaml` (`profiles: ["tools"]`, `target: dev`, monta `./api`, sin puertos, `depends_on: db` sano) (design D4). Toda ejecución posterior de PHP, Pint, Larastan y Pest va por `docker compose -f software/compose.yaml run --rm api-tools`. Verificar: `run --rm api-tools php -v` reporta 8.5 y `php artisan about` muestra `pgsql` y `es`. Cimiento.
- [ ] 2.3 Reemplazar PHPUnit por Pest (ADR-0002), configurar el entorno de pruebas contra la base de pruebas PostgreSQL y agregar una prueba `arch()` base. Verificar: `vendor/bin/pest` en verde y la conexión de pruebas reporta `pgsql`. Cita: CI › Pruebas contra PostgreSQL.
- [ ] 2.4 Configurar Pint y Larastan con su nivel base y dejarlos limpios sobre el scaffolding. Verificar: `vendor/bin/pint --test` y `vendor/bin/phpstan analyse` salen con 0. Cita: CI › Backend correcto.
- [ ] 2.5 Implementar el identificador de correlación global (respetar válido, generar si falta o es inválido, devolver en toda respuesta, incluidas las 500) con pruebas de feature por HTTP real. Verificar: pruebas en verde. Cita: SH › Cabecera válida respetada, SH › Cabecera ausente, SH › Cabecera inválida reemplazada, SH › Error no controlado conserva el identificador.
- [ ] 2.6 Implementar el log JSON por línea a stderr con `correlation_id`, la línea de cierre por petición y la exclusión de query string, cuerpo, cookies y cabeceras; pruebas que capturan la salida del log. Verificar: pruebas en verde. Cita: SH › Línea de cierre por petición, SH › Cada línea es JSON válido, SH › Query string, cuerpo y cabeceras excluidos, SH › Log fuera de una petición.
- [ ] 2.7 Implementar `GET /health` sin tocar base, caché ni sesión, con prueba de feature por HTTP real que incluye la base inalcanzable. Verificar: pruebas en verde. Cita: SH › API viva con dependencias sanas, SH › API viva con la base de datos caída, SH › Vivacidad sin estado ni datos internos.
- [ ] 2.8 Implementar `GET /ready` (consulta a la base + migraciones pendientes, 200/503, cuerpo sin detalles internos) con pruebas de feature por HTTP real contra PostgreSQL para cada resultado. Verificar: pruebas en verde. Cita: SH › API lista, SH › Base de datos inalcanzable, SH › Migraciones pendientes, SH › Fallo sin filtrar detalles internos.
- [ ] 2.9 Asegurar que una ruta desconocida bajo `/api` responde 404 en JSON con `X-Correlation-Id`, con prueba de feature por HTTP real. Verificar: prueba en verde. Cita: RE › Ruta de API desconocida no cae en la SPA.

## 3. SPA React

Paralelizable con el grupo 2: la página shell no consume ningún contrato de la API.

- [x] 3.1 Generar la SPA React + TypeScript + Vite en `software/web` con ESLint, Vitest y lockfile. Verificar: `npm ci`, `npm run lint`, `npm run build` y `npx vitest run` salen con 0. Cita: CI › Frontend correcto.
- [x] 3.2 Integrar Tailwind y la base de shadcn/ui (ADR-0004). Verificar: `npm run build` sale con 0 y un componente de shadcn renderiza en una prueba. Cimiento.
- [x] 3.3 Crear el módulo central de textos en español, el proveedor de TanStack Query (ADR-0003) en la raíz y la página shell mínima (nombre del producto y bienvenida, `lang="es"`), con prueba de Vitest. Verificar: prueba en verde. Cita: RE › Textos del shell desde el módulo central, RE › Shell en la raíz.

## 4. Contenedores y compose

Requiere los grupos 2 y 3 completos. 4.1 agrega la etapa `prod` sobre la `base` de 2.2.

- [ ] 4.1 Escribir el Dockerfile multi-stage de `api` (dependencias sin desarrollo, PHP 8.5-FPM + Nginx, usuario no root) y su `.dockerignore`. Verificar: la imagen construye, `id -u` ≠ 0, sin Pest/Larastan ni Node, sin `.env`. Cita: RE › Procesos sin root, RE › Imagen de API sin dependencias de desarrollo, RE › Archivo .env local excluido de las imágenes.
- [ ] 4.2 Implementar el arranque de `api`: `APP_KEY` generada y persistida si falta (respetada si viene), migraciones antes de declararse sano, healthcheck por disponibilidad. Verificar con arranques sucesivos. Cita: RE › Migraciones aplicadas al arrancar, RE › Arranque repetido idempotente, RE › APP_KEY ausente, RE › APP_KEY provista.
- [ ] 4.3 Escribir el Dockerfile multi-stage de `web` (build de Vite → Nginx no root), su `.dockerignore` y la configuración de Nginx: SPA con respaldo a `index.html`, proxy de `/api`, `/sanctum`, `/health`, `/ready` conservando `X-Correlation-Id`. Verificar: imagen sin `node_modules` ni fuentes TS, `id -u` ≠ 0. Cita: RE › Imagen web solo con artefactos compilados, RE › Procesos sin root, RE › Enlace profundo de la SPA.
- [ ] 4.4 Completar `compose.yaml` con `api` y `web` (healthchecks, `depends_on: service_healthy`, solo `WEB_PORT` publicado, `api` sin puertos), `software/.env.example` y reglas de `.gitignore` para `.env`. Verificar: `docker compose config` y `git check-ignore`. Cita: RE › Solo dos puertos publicados, RE › Plantilla completa, RE › .env ignorado por git, RE › Dependencia no sana bloquea el arranque.
- [ ] 4.5 Verificación integral desde cero: `down -v`, `up --build` sin `.env`; comprobar salud, `/health` y `/ready` vía `web`, ruta de API desconocida, enlace profundo, inalcanzabilidad de `api`, logs JSON con correlation id, reinicio con datos, puertos alternativos y caída de `db`. Registrar comandos y salidas para `verification.md`. Cita: RE › Arranque desde cero sin .env, RE › Puertos configurables, RE › Datos conservados entre reinicios, RE › Reinicio desde cero con volúmenes borrados, RE › Servicios sanos tras el arranque, RE › API inalcanzable desde el host, RE › Base caída marca la API como no sana, RE › Ruta de API desconocida no cae en la SPA.

## 5. CI

Requiere 2.3, 2.4 y 3.1 (configuración de Pest, Pint, Larastan, ESLint y Vitest ya en verde).

- [ ] 5.1 Escribir `.github/workflows/ci.yml`: disparo por push y pull request con filtros `paths` (`software/**` y el workflow), `permissions: contents: read`, trabajo de backend (Pint en modo verificación, Larastan, Pest contra servicio PostgreSQL 16) y trabajo de frontend (`npm ci`, ESLint, Vitest), sin secretos ni publicación. Verificar: push a `dev` y corrida en verde con `gh run watch`. Cita: CI › Cambio en el código de la aplicación, CI › Cambio en el workflow, CI › Backend correcto, CI › Frontend correcto, CI › Permisos de solo lectura, CI › Sin secretos ni publicación, CI › Pull request desde un fork.
- [ ] 5.2 Verificar las ramas negativas del CI: un commit solo de `openspec/` no dispara corrida (`gh run list`); en `feat/add-project-skeleton`, una violación de Pint, una prueba de Vitest rota y una dependencia en `package.json` ausente del lockfile, deliberadas, hacen fallar la corrida, y se revierten. Cita: CI › Cambio solo de documentación de proceso, CI › Violación de formato, CI › Hallazgo de análisis estático o prueba fallida, CI › Error de lint o prueba fallida, CI › Lockfile desincronizado, CI › Pruebas contra PostgreSQL.

## Workflow follow-up

- `verification.md` con las matrices escenario → prueba y la columna del ancla de transporte (cláusula → ruta archivo:línea).
- final-auditor (GATE 2) y luego `openspec archive add-project-skeleton -y`.
