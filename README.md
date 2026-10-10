# Dispensart

Dispensación de medicamentos, inventario por lote (FEFO) y traslados entre bodegas para una IPS con varias
farmacias y bodegas. Prueba técnica de desarrollador full stack senior. Todos los datos son sintéticos.

## Arranque en un comando

Solo requiere Docker (con Compose v2). Desde la raíz del repositorio, sin `.env`:

```sh
docker compose -f software/compose.yaml up --build
```

El primer arranque construye las imágenes, crea la base, genera `APP_KEY`, migra y siembra. Repetirlo no
duplica datos. Para empezar de cero: `docker compose -f software/compose.yaml down -v`.

| URL | Qué es |
|---|---|
| http://localhost:8090 | SPA (inicio de sesión en `/login`) |
| http://localhost:8090/api | API REST (mismo origen que la SPA; Nginx de `web` reenvía `/api` y `/sanctum`) |
| http://localhost:8090/health | Vida: responde 200 sin tocar dependencias |
| http://localhost:8090/ready | Disponibilidad: base alcanzable y migraciones aplicadas |
| `localhost:5434` | PostgreSQL, solo en loopback |

Los puertos cambian con `WEB_PORT` y `DB_PORT` (ver `software/.env.example`).

### Usuarios sintéticos por rol

| Correo | Rol | Qué ve en la SPA |
|---|---|---|
| `auxiliar@dispensart.test` | Auxiliar de farmacia | Dispensación, traslados, inventario, kardex, asistente |
| `regente@dispensart.test` | Regente de farmacia | Lo del auxiliar, más aprobar traslados y autorizar control especial |
| `medico@dispensart.test` | Médico | Pacientes en modo consulta y asistente; crea prescripciones por API |
| `auditor@dispensart.test` | Auditor | Consulta de inventario, kardex y traslados; pacientes enmascarados; asistente |
| `admin@dispensart.test` | Administrador | Usuarios (listar, crear con rol) y Catálogo (bodegas y productos: listar, crear, editar); sin asistente |

Contraseña de los cinco: `dispensart-dev-only`. **No es un secreto**: es un valor por defecto solo de
desarrollo local para usuarios sintéticos, y la API nunca lo aplica con `APP_ENV=production`. Se reemplaza
con `SEED_USER_PASSWORD`, que solo tiene efecto al crear cada usuario (la siembra no toca usuarios existentes).

## Mapa de la prueba

| Parte | Dónde verlo |
|---|---|
| A · Modelo y API | `software/api` (Laravel 13); contrato en `software/api/openapi.json`; bitácora `audit_events` (solo ids) en la misma transacción que cada operación sensible, incluidas creación de usuario y ajuste manual de stock |
| B · Frontend | `software/web` (React + Vite): dispensación, traslados, inventario con alertas, kardex, asistente (`/assistant`, para los cuatro roles de operación, con selector de modelo), y para el admin Usuarios (`/users`) y Catálogo (`/catalog`); tabla de pantallas en `software/web/src/app/screens.tsx` |
| C · IA | `software/docs/asistente.md`; conjunto de evaluación en `software/api/resources/assistant/evaluation-set.json` |
| D · DevOps | `software/compose.yaml`, `software/docker/`, `.github/workflows/ci.yml`, `software/docs/deployment.md` |
| E · Calidad | Pest y Vitest en CI; decisiones en `docs/adr/`; uso de IA en `AI_USAGE.md` |

## Compromisos de diseño

### Compromiso 1 · Existencias con bloqueo pesimista en orden fijo

| | |
|---|---|
| Elegido | `software/api/app/Services/Inventory/StockLedger.php` es la única puerta de escritura de existencias: `SELECT … FOR UPDATE` en un orden global fijo (vencimiento, lote, bodega), saldo nunca negativo y un movimiento de kardex por cambio, en la misma transacción. La consulta FEFO bloquea en ese mismo orden. |
| Descartado | Control optimista (versión o `UPDATE` condicional) y `SERIALIZABLE` con reintentos: reintentar no cabe dentro de la transacción de la dispensación, y al agotar los intentos la carrera por la última unidad termina en 500. |
| Costo | Las escrituras sobre la misma existencia esperan en fila; bajo contención alta baja el rendimiento. Cualquier escritura que esquive `StockLedger` rompe la garantía (lo vigilan pruebas `arch()` y la prueba de carrera). |

### Compromiso 2 · Kardex de solo inserción impuesto por la base

| | |
|---|---|
| Elegido | Disparador `BEFORE UPDATE OR DELETE OR TRUNCATE … FOR EACH STATEMENT` con `ENABLE ALWAYS` sobre `kardex_movements` (`software/api/database/migrations/2026_10_08_000003_create_kardex_movements_table.php`). Igual para las bitácoras de auditoría. |
| Descartado | Solo convención en el código; `REVOKE UPDATE, DELETE` (sin efecto sobre el dueño de la tabla); `RULE … DO INSTEAD NOTHING` (silencia en vez de rechazar). |
| Costo | Un error se corrige con un movimiento compensatorio, nunca editando. Las pruebas no pueden vaciar la tabla y necesitan limpieza propia. El dueño de la tabla aún puede borrar el disparador (ver compromiso 5). |

### Compromiso 3 · El servidor redacta la respuesta del asistente

| | |
|---|---|
| Elegido | El modelo solo elige herramienta y argumentos; el servidor ejecuta herramientas de solo lectura, decide el resultado y redacta la respuesta con los datos (`software/api/app/Services/Assistant/AnswerComposer.php`). El texto del modelo nunca llega al usuario. |
| Descartado | Devolver el texto libre del modelo con los resultados en contexto: una observación de traslado maliciosa podría dictar la respuesta, y el modelo podría inventar cifras. |
| Costo | Respuestas de plantilla, menos naturales; cada intención nueva exige una herramienta y su redacción en código. |

### Compromiso 4 · `mock` por defecto y Ollama local, costo cero

| | |
|---|---|
| Elegido | Interfaz `LlmProvider` con `AI_PROVIDER=mock` (reglas deterministas, sin red ni llaves) por defecto y en CI; `ollama` en el anfitrión como modelo real, con `qwen2.5:3b` por defecto y cambiable con `OLLAMA_MODEL`. En la pantalla Asistente el usuario elige entre `mock` y los modelos de Ollama descargados con herramientas (`GET /api/assistant/models`); la primera visita usa `mock` y la elección queda en el navegador. `AI_PROVIDER` decide solo las preguntas sin `model`, la evaluación y CI. |
| Descartado | Proveedor externo de pago: exige llaves, saca datos de la máquina y tiene costo. |
| Costo | Un modelo local pequeño tarda segundos por pregunta y elige peor que uno grande. Medido con `gemma4:e2b-mlx` (Ollama en el equipo Apple Silicon del autor): el 2026-10-08 `assistant:eval` dio 20/24 con el comparador literal, entre 7 y 19 s por pregunta; el 2026-10-09, ya comparando bodega y producto resueltos por `CatalogResolver`, dio 22/24. Los 2 fallos son del modelo: el caso 8 lo dio por fuera de alcance y el 10 respondió «desconocido» en vez de «sin resultados». El caso 4 falló el 2026-10-08 y pasó el 2026-10-09: el modelo local no es determinista entre corridas. |

### Compromiso 5 · Usuario de base de la app con todos los privilegios (riesgo aceptado)

| | |
|---|---|
| Elegido | La app usa el usuario que crea la imagen de PostgreSQL (superusuario y dueño de las tablas): migra y opera con la misma credencial. |
| Descartado | Rol de mínimo privilegio separado del dueño de migraciones, en este alcance. |
| Costo | Con esa credencial se podría deshabilitar el disparador del kardex por DDL. La defensa queda en el código (única puerta de escritura) y en el disparador frente a DML. Cerrarlo es un cambio de tier A propio. |

### Compromiso 6 · Filtro de pacientes del asistente por palabras clave (riesgo aceptado)

| | |
|---|---|
| Elegido | Heurística por patrones antes de llamar al modelo (`software/api/app/Services/Assistant/QuestionPreFilter.php`): bloquea vocabulario clínico (paciente, prescribieron, recetaron, fórmula), dispensación dirigida a una persona («le dispensaron», «dispensó a/al …») y 7 o más dígitos seguidos. Responde `out_of_scope` sin consultar al modelo. |
| Descartado | Un clasificador que distinga nombre de persona frente a nombre de producto: necesitaría una lista de palabras que mantener. |
| Costo | Una pregunta como «¿qué dispensaciones tiene <nombre>?» aún llega al modelo. Se acepta porque ninguna herramienta devuelve datos de pacientes y el proveedor es `mock` u Ollama local: nada sale de la máquina. |

## Librerías relevantes

| Librería | Por qué |
|---|---|
| Laravel 13 + Sanctum | Policies, Form Requests y API Resources; Sanctum da sesión por cookie con CSRF para una SPA del mismo origen, sin tokens en `localStorage`. Roles en un enum con denegación por defecto, sin `spatie/laravel-permission`. |
| Pest (`arch()`) | Reglas de arquitectura ejecutables: lógica fuera de controladores, asistente sin acceso a pacientes (ADR-0002). |
| Larastan (nivel 6) + Pint | Análisis estático y estilo como compuertas de CI. |
| `dedoc/scramble` (solo desarrollo) | OpenAPI derivado del código; CI falla si `openapi.json` deriva. |
| TanStack Query | Caché, invalidación tras escrituras y estado pendiente que bloquea el doble envío; una `Idempotency-Key` por intención (ADR-0003). |
| `openapi-typescript` | Tipos de la SPA derivados del contrato; CI falla si deriva. |
| Tailwind + shadcn/ui (Radix) | Componentes accesibles copiados al repo, sin dependencia de interfaz en tiempo de ejecución (ADR-0004). |
| Vitest + Testing Library + MSW | Pruebas de pantalla contra una API simulada con los mismos códigos de error. |
| PostgreSQL 16 | `CHECK`, bloqueo de filas y disparadores: las reglas críticas viven también en la base. |

## Supuestos

| Supuesto | Consecuencia |
|---|---|
| La siembra es idempotente por clave natural (código de bodega, código de producto, producto + lote, correo) y nunca actualiza | Reiniciar el stack no duplica ni pisa datos; cambiar la semilla exige `down -v` |
| "Hoy" es el día calendario de Bogotá (`America/Bogota`); la app guarda en UTC | Un lote que vence hoy ya cuenta como vencido; la ventana de vencimiento es de 90 días |
| Los mínimos de stock por bodega llegan solo por la siembra | Las alertas de stock bajo funcionan con 4 mínimos sembrados; no hay edición |
| Las cantidades son unidades enteras | Sin fracciones de presentación |
| Los colores de la SPA son la paleta pública de la IPS (azul marino `#232955`, verde lima `#a9cd43`, fondo `#f8f9fa`, texto `#212b51`), sin logo ni nombre comercial | Contraste WCAG AA de los tokens de tema, incluida la insignia «Vence en N días», verificado en CI por `software/web/src/theme-contrast.test.ts` |

## Fuera de alcance

| Qué | Por qué |
|---|---|
| Endpoint de lectura de bitácoras (accesos a pacientes, eventos de auditoría) para el `auditor` | Las bitácoras se escriben y son de solo inserción; el mapa de capacidades de § 3 no le da al auditor una consulta de bitácoras y el presupuesto fue a las reglas RN. Hoy se leen en la base. |
| Rol de base de mínimo privilegio | Toca los permisos de escritura del kardex: cambio de tier A que excede la entrega (compromiso 5). Fila candidata del roadmap. |
| Edición de mínimos por API | Ninguna parte de la prueba la exige; los mínimos se siembran. |
| Filtro de alertas por producto | La API filtra alertas solo por bodega; la SPA muestra las de la bodega elegida. |
| Bloquear al admin en `POST /api/assistant/ask` | La SPA ya no le muestra el asistente, pero la API aún lo acepta; cada herramienta le responde `not_permitted`, sin datos. |
| División del bundle por ruta | La SPA pesa unos 530 kB en un solo bloque; para una herramienta interna de carga única se dejó como no-objetivo. |
| Evaluación con Ollama en CI | CI corre la evaluación con `mock` (estable, sin red); el modelo real se midió a mano dos veces (compromiso 4) y el comando queda documentado para repetirlo. |

### Deuda abierta

Una fila menor, D-auv-9: `phpunit.xml` no fija `AI_PROVIDER` ni `OLLAMA_MODEL`, así que con `ollama` en
`software/.env` las pruebas deben correrse con `-e AI_PROVIDER=mock`. El registro de deuda es `openspec/DEBT.md`;
las demás filas menores abiertas durante esta entrega se saldaron en ella (humos de dominio en staging, filtro de pacientes del asistente, saneo de nombres de herramienta en el
log, comparador de `assistant:eval` por bodega y producto resueltos, contraste de la insignia de vencimiento).

## Documentación de la API

OpenAPI 3.1 en `software/api/openapi.json`, generado desde el código con Scramble y verificado en CI. Incluye `/health` y `/ready`.

Colección de Postman ejecutable en `software/docs/postman/dispensart.postman_collection.json`, con su entorno
`software/docs/postman/local.postman_environment.json`: cuerpos de ejemplo, aserciones por flujo y la sesión
Sanctum resuelta por script; cubre cada operación del contrato (`software/docs/postman/check-coverage.sh`, en CI).
Contraseña del entorno: el valor por defecto solo de desarrollo, sobrescribible con `--env-var password=…`. Con el
stack arriba, desde la raíz (lo mismo corre en staging):

```sh
npx --yes newman@6.2.3 run software/docs/postman/dispensart.postman_collection.json -e software/docs/postman/local.postman_environment.json
```

Guía para importar en Postman: `software/docs/postman/README.md`.

## Asistente de IA: evaluación

Responde las 24 preguntas del conjunto, con su respuesta esperada, sobre una base desechable e imprime
`Aciertos: N/T`; sale con 0 solo si todas aciertan. Con el stack arriba:

```sh
docker compose -f software/compose.yaml --profile tools run --rm api-tools php artisan assistant:eval
```

Con Ollama en el anfitrión, agregar `-e AI_PROVIDER=ollama` antes de `api-tools`, y `-e OLLAMA_MODEL=<modelo>`
para medir un modelo distinto de `qwen2.5:3b`:

```sh
docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=ollama -e OLLAMA_MODEL=gemma4:e2b-mlx api-tools php artisan assistant:eval
```

Detalle: `software/docs/asistente.md`.

## Pruebas y calidad

```sh
# Backend: Pint, Larastan, Pest (contra la base de pruebas del mismo PostgreSQL)
docker compose -f software/compose.yaml --profile tools run --rm api-tools vendor/bin/pint --test
docker compose -f software/compose.yaml --profile tools run --rm api-tools vendor/bin/phpstan analyse --memory-limit=1G
docker compose -f software/compose.yaml --profile tools run --rm api-tools vendor/bin/pest

# Frontend (Node 22): ESLint, tipos, Vitest
cd software/web && npm ci && npm run lint && npm run typecheck && npm test -- --run
```

Humo del stack y de dominio (lo mismo que corre staging): `bash software/docker/smoke.sh`.

## Despliegue

`.github/workflows/ci.yml`: compuertas de calidad en todo push y pull request; en `dev` y `main`, imágenes en
GHCR por SHA, staging simulado por digest con humo, y producción solo desde `main` con aprobación manual en el
entorno `production`. Estrategia, rollback y respaldo: `software/docs/deployment.md`. El CI descarga las imágenes
base de Docker Hub sin credenciales y puede fallar a ratos por su límite de descargas anónimas; se repite con
`gh run rerun <id> --failed` (el espejo de registro quedó en pausa, sin construir).

## Estructura del repositorio

| Ruta | Contenido |
|---|---|
| `software/` | todo el código: `api/` (Laravel), `web/` (SPA React), `docker/`, `compose.yaml`, `docs/` |
| `openspec/` | desarrollo guiado por especificaciones: specs vigentes, cambios archivados, roadmap, deuda |
| `docs/adr/` | decisiones de arquitectura (ADR-0001..0006: stack, Pest, TanStack Query, kit de UI, licencia, idiomas) |
| `.claude/`, `CLAUDE.md` | harness de IA: agentes, skills, comandos, hooks (ver `AI_USAGE.md`) |
| `.github/workflows/` | CI/CD |
| `package.json` | solo herramientas de desarrollo: fija la versión del CLI de OpenSpec. No es la aplicación |

## Idiomas

Código (identificadores, tablas, rutas, códigos de error) en inglés. Comentarios, commits, documentación y
textos de la interfaz en español; los textos de la SPA viven en `software/web/src/lib/strings.ts`.

## Desarrollo con el harness de IA

El repositorio incluye un harness para [Claude Code](https://claude.com/claude-code) que conduce un flujo
guiado por especificaciones con [OpenSpec](https://github.com/Fission-AI/OpenSpec): proponer → aplicar →
archivar, con compuertas humanas. Requiere Node.js ≥ 20.19 y Claude Code. La primera sesión instala el CLI de
OpenSpec fijado (`npm ci`); fuera de Claude: `npx openspec list`. Cómo se usó: `AI_USAGE.md`.

## Licencia

MIT (`LICENSE`). Las skills de terceros en `.claude/skills/` conservan sus licencias: `THIRD_PARTY_NOTICES.md`.
