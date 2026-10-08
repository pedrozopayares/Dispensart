# Proposal — add-project-skeleton (S0)

## Why

Ninguna tajada de negocio (S1–S8) puede construirse ni probarse sin una API Laravel, una SPA React, una base
PostgreSQL y una compuerta de CI que existan y arranquen igual en cualquier máquina. La prueba (partes D y E)
evalúa además, por sí mismos, el arranque con un solo comando, los healthchecks, `/health` y `/ready`, los
logs estructurados con identificador de correlación y el CI con lint y pruebas.

## What Changes

- `software/api`: aplicación Laravel 13 nueva (PHP 8.5 en Docker), Pest con una prueba `arch()` base, Pint,
  Larastan, conexión PostgreSQL 16, locale `es` con base `lang/es`.
- Endpoints `GET /health` (vivacidad, sin dependencias) y `GET /ready` (base alcanzable + migraciones
  aplicadas; no listo → 503).
- Logs JSON por línea con `correlation_id`; cabecera `X-Correlation-Id` respetada si es válida, generada si
  falta o es inválida, y devuelta en toda respuesta. Sin cuerpo ni query string en los logs (RN-10).
- `software/web`: SPA React + TypeScript + Vite, Tailwind + shadcn/ui, proveedor de TanStack Query, Vitest,
  ESLint, módulo único de textos en español y una página shell mínima. Sin pantallas de negocio.
- `software/compose.yaml`, Dockerfiles multi-stage no root con `.dockerignore`, `software/.env.example`:
  `db` (PostgreSQL 16, volumen nombrado), `api` (PHP-FPM + Nginx), `web` (Nginx con la SPA y proxy de
  `/api`, `/sanctum`, `/health`, `/ready`). Healthchecks y `depends_on: service_healthy`. Al host solo
  `WEB_PORT` (8090) y `DB_PORT` (5434). Migraciones automáticas al arrancar; `APP_KEY` generada si falta.
- `.github/workflows/ci.yml`: Pint, Larastan, ESLint, Pest contra PostgreSQL, Vitest; filtros `paths`;
  permisos mínimos. Sin imágenes ni despliegues (S8).

## Capabilities

### New Capabilities
- `service-health`: vivacidad y disponibilidad de la API, identificador de correlación y logs estructurados.
- `runtime-environment`: stack de contenedores de un solo comando, superficie de red, imágenes, secretos e
  inicialización automática; la SPA servida en el mismo origen.
- `ci-pipeline`: compuerta de integración continua (lint, análisis estático, pruebas); S8 la extiende.

### Modified Capabilities
- Ninguna (no hay specs vivas).

## Impact

Código nuevo bajo `software/` y `.github/workflows/`. Sin contrato de negocio, sin tablas de dominio. Cubre
partes D y E (parcial); RN-10 solo en lo que toca a logs. Tier B. Fuera de alcance: usuarios, roles,
autenticación, dominio, pantallas, IA, publicación de imágenes, despliegues, README/AI_USAGE/doc de
despliegue (S1–S8). Datos semilla automáticos: llegan con S1.

## Assumptions

1. `/health` y `/ready` viven en la raíz de la API (no bajo `/api`), tal como los nombra la prueba; el Nginx
   de `web` los reenvía, así que desde el host son `http://localhost:${WEB_PORT}/health` y `/ready`.
2. El arranque funciona sin archivo `.env`: compose trae valores por defecto no secretos y una contraseña de
   base de datos solo local, marcada como tal; `.env.example` documenta cada variable.
3. `DB_PORT` se publica solo en loopback (`127.0.0.1`).
4. La `APP_KEY` generada persiste en un volumen y sobrevive a reinicios (cifrado y sesiones de S1+).
5. La página shell no hace peticiones; no tiene estados de carga, error ni vacío. Esos criterios entran con
   S6.
6. Correlation id válido: 1–128 caracteres de `[A-Za-z0-9._-]`; si no cumple, se reemplaza por un UUID.
