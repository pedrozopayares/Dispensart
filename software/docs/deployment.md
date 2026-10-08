# Despliegue de Dispensart

Pipeline: `.github/workflows/ci.yml`. Stack: `software/compose.yaml` (`db`, `api`, `web`). Comandos desde la
raíz del repositorio.

## Estrategia de despliegue

1. **Compuertas.** Todo push o pull request corre backend (Pint, Larastan, Pest sobre PostgreSQL 16, contrato
   OpenAPI) y frontend (ESLint, tipos, Vitest). La entrega empieza solo en push a `dev` o `main` y solo si
   ambas compuertas pasan en ese commit.
2. **Imágenes inmutables.** `build` publica `ghcr.io/<dueño>/dispensart-api` y `dispensart-web` con etiqueta
   = SHA completo del commit, sin `latest` ni etiquetas de rama. Los digests pasan como salidas a los
   trabajos siguientes: lo que se prueba es exactamente lo que se promueve.
3. **Staging simulado.** En el runner, el mismo `compose.yaml` arranca con `API_IMAGE`/`WEB_IMAGE` fijadas
   a `@sha256:<digest>` y `--no-build`; contraseña de base efímera y enmascarada. El humo
   (`software/docker/smoke.sh`) exige `healthy`, `/health` y `/ready` en 200, la SPA en `/` y procesos sin
   root. Si falla, imprime los logs y el stack se baja con sus volúmenes.
4. **Producción con aprobación.** Solo desde `main`, tras staging verde, en el entorno `production` (revisor
   requerido, ramas desplegables: `main`). Ningún paso corre antes de aprobar. Una guarda falla cerrada si
   el entorno no exige revisor. El despliegue simulado deja en el resumen del run los digests y el comando
   que se ejecutaría en el servidor:

```sh
API_IMAGE=ghcr.io/<dueño>/dispensart-api@sha256:<digest> \
WEB_IMAGE=ghcr.io/<dueño>/dispensart-web@sha256:<digest> \
docker compose -f software/compose.yaml up -d --no-build --wait
```

La API aplica migraciones al arrancar; el sembrado es idempotente. Una aprobación pendiente en `main` retiene
el grupo de concurrencia: aprobar o rechazar libera el siguiente run.

## Rollback

- **Mismo esquema.** Abrir el último run verde de `main` en Actions y re-ejecutar solo el trabajo
  `Producción` (`gh run rerun <run-id> --job <job-id>`). GitHub reutiliza las salidas de `build` de ese
  run: vuelve al digest exacto, con nueva aprobación. Límite: runs de hasta 30 días.
- **Esquema incompatible.** Si la versión nueva aplicó migraciones que la anterior no entiende, re-desplegar
  no basta: detener `api` y `web`, restaurar el respaldo tomado antes del despliegue (sección siguiente) y
  luego re-ejecutar producción del run anterior. Se pierden las escrituras posteriores al respaldo: por eso
  se respalda siempre antes de aprobar un despliegue con migraciones.

## Respaldo y restauración de la base

Formato custom de `pg_dump`; las credenciales salen del propio contenedor `db`, nunca de la línea de comandos.

Respaldo:

```sh
docker compose -f software/compose.yaml exec -T db \
  sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$HOME/dispensart.dump"
```

Restauración (reemplaza los objetos existentes; con el stack arriba):

```sh
docker compose -f software/compose.yaml exec -T db \
  sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner' < "$HOME/dispensart.dump"
docker compose -f software/compose.yaml restart api
docker compose -f software/compose.yaml up -d --wait
curl -fsS "http://localhost:${WEB_PORT:-8090}/ready"
```

`restart api` descarta conexiones previas a la restauración; `up --wait` espera `healthy`; `/ready` confirma
base alcanzable y migraciones aplicadas. En local la contraseña por defecto `dispensart_local_dev_only` es solo
de desarrollo; en cualquier otro entorno `DB_PASSWORD` llega por variable de entorno. El respaldo queda fuera
del repositorio (`$HOME`) porque contiene datos.
