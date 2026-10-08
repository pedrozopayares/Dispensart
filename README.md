# Dispensart

Dispensación de medicamentos, inventario por lote (FEFO) y traslados entre bodegas para una IPS con varias
farmacias y bodegas. Construido como prueba técnica de desarrollador full stack senior.

> Estado: harness y planeación listos; el código de la aplicación empieza con el primer cambio OpenSpec (`S0`).
> Este README crece con el producto. Las secciones finales del entregable (ejecución en un comando, decisiones
> de diseño, supuestos, qué quedó fuera) llegan con la última tajada.

## Stack

| Capa | Elección |
|---|---|
| Backend | PHP 8.5 · Laravel 13 · API REST · Sanctum (SPA) · Policies/Gates · Form Requests · API Resources |
| Frontend | React · TypeScript · Vite (SPA) · TanStack Query · Tailwind + shadcn/ui |
| Base de datos | PostgreSQL 16 (restricciones CHECK, bloqueo de filas, kardex solo inserción) |
| Contenedores | Docker multi-stage (PHP-FPM + Nginx, usuario no root) · Docker Compose |
| CI/CD | GitHub Actions · Pint · Larastan · ESLint · Pest · Vitest |
| Asistente de IA | Servicio Laravel tras una interfaz `LlmProvider` · herramientas de solo lectura · `AI_PROVIDER=mock` por defecto |

Las reglas de negocio y el alcance viven en `openspec/config.yaml` y `openspec/ROADMAP.md`.

## Ejecutar la aplicación

Solo requiere Docker.

```sh
git clone <url-del-repo> && cd Dispensart
docker compose up --build
```

Disponible cuando `S0` esté publicado: aquí irán las URLs, los puertos y un usuario sintético por rol.

## Estructura del repositorio

| Ruta | Contenido |
|---|---|
| `software/` | todo el código de la aplicación: `api/` (Laravel), `web/` (SPA React), `docker/`, `compose.yaml` |
| `openspec/` | desarrollo guiado por especificaciones: specs vigentes, cambios, roadmap, deuda y retrospectivas |
| `docs/adr/` | registros de decisiones de arquitectura (ADR-0001..0006: stack, Pest, TanStack Query, kit de UI, licencia, idiomas) |
| `.claude/`, `CLAUDE.md` | harness de IA: agentes, skills, comandos, hooks (ver abajo) |
| `.github/workflows/` | CI/CD |
| `package.json` | solo herramientas de desarrollo: fija la versión del CLI de OpenSpec. No es la aplicación |

## Idiomas

Código (identificadores, tablas, rutas, códigos de error) en inglés. Comentarios, commits, documentación y
textos de la interfaz en español.

## Desarrollo con el harness de IA

El repo incluye un harness para [Claude Code](https://claude.com/claude-code) que conduce un flujo de
desarrollo guiado por especificaciones con [OpenSpec](https://github.com/Fission-AI/OpenSpec): proponer →
aplicar → archivar, con compuertas humanas. Requiere Node.js ≥ 20.19 y Claude Code.

```sh
claude            # desde la raíz del repo; aceptar la confianza del workspace
/opsx:propose "…" # iniciar un cambio
```

La primera sesión instala el CLI de OpenSpec fijado (`npm ci`) de forma automática. Fuera de Claude:
`npx openspec list`. Reglas del proceso: `CLAUDE.md`, `openspec/CYCLE-TIERS.md` (en inglés: son
instrucciones para el agente).

El uso de IA en esta prueba se declara en `AI_USAGE.md` (llega con la última tajada).

## Licencia

Ver `LICENSE` (MIT). Las skills de terceros incluidas en `.claude/skills/` conservan sus propias licencias:
`THIRD_PARTY_NOTICES.md`.
