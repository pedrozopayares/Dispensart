# Journal — add-project-skeleton (S0)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: arranque de autopiloto

- shard = auv
- Preflight de máquina: árbol limpio, `dev` al día, `openspec validate --all --strict` sin elementos,
  Docker 28.5.1 responde, puertos 8090 y 5434 libres, Node v26.5.0, PHP 8.4.8, Composer 2.8.9.
- Fase: /proposal. Tier objetivo según ROADMAP: B.

## 2026-10-07 — spec-engineer: proposal, deltas, tasks

- Producido: `proposal.md`, `specs/service-health/spec.md`, `specs/runtime-environment/spec.md`,
  `specs/ci-pipeline/spec.md`, `tasks.md` (5 grupos, orden base de datos → api → web → contenedores → CI).
- Capacidades nuevas: `service-health`, `runtime-environment`, `ci-pipeline` (S8 la extiende).
- `openspec validate add-project-skeleton --strict`: válido. Ancla de transporte: todos los hits del barrido
  de `CYCLE-TIERS.md` llevan `[ancla: …]` con archivo:línea a fijar en apply. Cada escenario citado en tasks.
- Supuestos (en `proposal.md` § Assumptions): `/health` y `/ready` en la raíz de la API y reenviados por
  `web`; arranque sin `.env` con contraseña de desarrollo local por defecto; `DB_PORT` solo en loopback;
  `APP_KEY` generada persistida en volumen; shell sin peticiones (sin estados carga/error/vacío hasta S6);
  formato válido de correlation id 1–128 `[A-Za-z0-9._-]`.
- Decisiones de alcance: datos semilla automáticos diferidos a S1; el healthcheck de `api` refleja
  disponibilidad, no solo vivacidad; el CI no incluye verificación de tipos ni build de imágenes.
- Preguntas abiertas para architect: mecanismo de persistencia de `APP_KEY`; base de pruebas separada en
  el mismo contenedor `db`; cómo simular base inalcanzable y migración pendiente en pruebas de feature.

## 2026-10-07 — architect: design.md y refinamiento de tasks

- Producido: `design.md` (D1–D7). `openspec validate add-project-skeleton --strict`: válido.
- Respuestas: (1) `APP_KEY` ausente → entrypoint la genera capturada en variable y la persiste en volumen
  `api_state` (`0600`); rechazados `.env` interno, base de datos, exigirla. (2) Base de pruebas
  `dispensart_test` en el mismo `db` vía `initdb`; CI con `POSTGRES_DB=dispensart_test`; `phpunit.xml`
  la fuerza; rechazado servicio `db-test`. (3) `/ready`: `ReadinessChecker` concreto; base caída = puerto
  cerrado + `DB::purge` en archivo sin `RefreshDatabase`; pendiente = `migrator->path(tmp)`; rechazada
  interfaz con doble (simula la propiedad bajo prueba).
- Otras: imagen `api` Nginx + FPM bajo supervisord (rechazado FPM solo con FastCGI desde `web`: acopla y
  complica S8); Pest local en servicio `api-tools` (perfil `tools`, etapa `dev`), PHP del host no se usa;
  log = formateador derivado de `JsonFormatter` vía `tap` + `Context` (rechazado `JsonFormatter` crudo:
  claves no cumplen spec); acciones de CI por etiqueta mayor hasta S8.
- tasks.md: nueva 2.2 (etapas `base`/`dev` + `api-tools`) antes de Pest; 2.2–2.8 renumeradas a 2.3–2.9;
  grupo 3 marcado paralelizable con grupo 2; dependencias de grupos 4 y 5 explícitas.
- Riesgos: `initdb` solo con volumen vacío; bindings de `QueryException` en logs pueden filtrar datos
  personales desde S1 (candidato a deuda, RN-10); propiedad del volumen `api_state` para usuario no root.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-project-skeleton`. Ancla de transporte: 11 hits, 11 con ancla.
- Condición 1 (alcance exacto de la fila S0): OK. Esqueletos api/web, compose, `/health` `/ready`, CI con lint y pruebas; imágenes publicadas, despliegues y documentos quedan en S8.
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): B, coincide con ROADMAP.
- Condición 4 (ADR / RN): ninguna decisión congelada ni regla debilitada.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier B
- Riesgo del architect (mensajes de excepción con datos de paciente en logs): pertenece a RN-10, alcance de S3. No se fila deuda; se pasa como requisito a la propuesta de S3.

## 2026-10-07 — Orchestrator: /apply

- `openspec validate add-project-skeleton --strict`: válido.
- Preflight de suite: no aplica (S0 crea el stack y las suites; no hay línea base previa).
- Delegación: hilo 1 devops-implementer (1.1, 2.2: servicio `db`, etapas `base`/`dev` y `api-tools`) ∥ hilo 2
  frontend-implementer (grupo 3, sin contrato con la API). Luego backend-implementer (2.1, 2.3–2.9).
  Luego devops-implementer (grupo 4 y 5).

## 2026-10-07 — Orchestrator: incidente de harness

- Los tipos `backend-implementer`, `frontend-implementer` y `devops-implementer` no se registran en esta
  sesión (`Agent type ... not found`); su frontmatter está sano y los demás tipos sí cargan.
- Rodeo: agentes `general-purpose` que leen su archivo de rol en `.claude/agents/` como definición
  obligatoria. Mismo alcance, misma ley. Lección candidata para RETROSPECTIVES al cierre.

## 2026-10-07 — Orchestrator: rodeo revertido, apply en espera

- Instrucción del usuario: no usar `general-purpose`; solo agentes declarados y skills instaladas.
  Los dos agentes del rodeo se detuvieron a mitad de trabajo.
- Estado parcial: `software/web/` sin commit (scaffolding Vite incompleto del rodeo); ningún archivo de
  compose ni Dockerfile creado. El frontend-implementer real decide si lo reutiliza o lo regenera.
- Causa probable: la sesión arrancó 19:49 y los tres `*-implementer.md` se crearon después; los tipos de
  agente se registran al iniciar la sesión y `/clear` no los recarga. Desbloqueo: reiniciar Claude Code
  desde la raíz del repo.
- Mientras tanto avanzan las propuestas que hacen los agentes registrados (S1 diseño, S2 propuesta).
- Commit `cdaf9b5` (sin push) renombrado a `ce7b09c spec: propone esqueleto Laravel + React, Docker
  Compose, /health y CI` por la regla de títulos legibles para el jurado.

## 2026-10-07 — Orchestrator: handoff para la sesión nueva

Punto de reanudación: S0 en fase /apply, GATE 1 registrado, ninguna tarea `[x]`, sin código en `software/`.
- El scaffolding parcial de `software/web` del rodeo se retiró del repo (árbol limpio para el preflight).
- Primer paso en la sesión nueva: quitar `bloqueado` de S0 (volver a `en curso`) y delegar según la sección
  /apply de arriba: devops-implementer (1.1, 2.2) ∥ frontend-implementer (grupo 3); luego
  backend-implementer (2.1, 2.3–2.9); luego devops-implementer (grupos 4 y 5).
- Commits con títulos legibles para el jurado (Git law, `CLAUDE.md`).
- S1 y S2: propuestas en curso en sus carpetas; ver sus `journal.md`.

## 2026-10-07 — Orchestrator: reanudación tras reinicio

- Sesión reiniciada; los tipos `*-implementer` ya se registran. S0 vuelve a `en curso`.
- Preflight: Docker 28.5.1, puertos 8090 y 5434 libres, `dev` en `86a0f0f`.
- Delegación: devops-implementer (1.1, 2.2) ∥ frontend-implementer (grupo 3).

## 2026-10-07 — devops-implementer: tareas 1.1 y 2.2

Archivos: `software/compose.yaml`, `software/docker/db/init/01-create-test-db.sql`,
`software/docker/api/Dockerfile`, `software/docker/api/Dockerfile.dockerignore`.

| Comprobación | Comando | Resultado |
|---|---|---|
| Config compose | `docker compose -f software/compose.yaml config -q` | sale 0 |
| `db` en frío | `down -v` → `up -d db --wait` | `healthy`, `127.0.0.1:5434->5432/tcp` |
| Bases creadas | `psql -Atc "select datname ... like 'dispensart%'"` | `dispensart`, `dispensart_test` |
| Versión base | `select version()` | PostgreSQL 16.15 |
| PHP en `api-tools` | `--profile tools run --rm api-tools php -v` | PHP 8.5.11 (NTS), OPcache |
| Extensiones | `php -m` | `pdo_pgsql`, `intl`, `bcmath`, `pcov` |
| Composer | `composer --version` | 2.10.3 |
| Usuario | `id -u` en `api-tools` | 1000 (no root) |
| Enlace a la base de pruebas | `new PDO("pgsql:host=db;...dbname=dispensart_test")` | conecta |
| Etapa `base` sola | `docker build --target base` | construye |
| Puertos | `config --format json` | `api-tools` sin puertos; `db` solo `127.0.0.1` |
| Barrido de secretos | `/usr/bin/grep -rnEi '(api_key\|secret\|token\|password)\s*[:=]'` | 2 aciertos, ambos la contraseña local marcada `dispensart_local_dev_only`; control positivo (`API_KEY=sk-test123` en scratchpad) detectado |

Decisiones:
- Imágenes fijadas: `postgres:16.15-alpine3.24`, `php:8.5.11-fpm-alpine3.24`, `composer:2.10.3`.
- Contexto de construcción de la API = `software/` (la etapa `prod` de 4.1 copiará `api/` y `docker/api/`);
  `Dockerfile.dockerignore` excluye `.env`, `web/`, `vendor/`, `.git`. 4.1 lo afina.
- `api-tools` recibe `DB_PORT: 5432` literal (puerto interno), no el `DB_PORT` del host.
- `pcov` en `dev` para cobertura/mutación de Pest; no entra en `prod`.
- El bind mount crea `software/api/` vacío al correr `api-tools`; se borró (2.1 lo crea el backend-implementer).
- Pendiente de 2.1: la verificación `php artisan about` (`pgsql`, `es`) de 2.2 la cierra el backend-implementer
  tras el scaffolding. Sin `actionlint` en el host (no hubo workflows en esta entrega).

## 2026-10-07 — frontend-implementer: grupo 3 (SPA React)

- Andamiaje `create-vite` (react-ts). La plantilla trae oxlint; se reemplaza por ESLint (flat config,
  typescript-eslint, react-hooks, react-refresh, `--max-warnings 0`): la pila fija ESLint y la spec de CI lo nombra.
- shadcn/ui iniciado con base Radix, estilo new-york, color neutral; componentes `card` y `button`. El CLI
  instala el paquete oficial `cn` (shadcn-ui/cn) en lugar de clsx + tailwind-merge.
- Compatibilidad Node 22: `.nvmrc` = `22` en `software/web` (para `setup-node` de 5.1); `engines.node`
  `>=22.22.2`, piso exigido por jsdom 30.
- Decisión: `QueryClient` con `mutations.retry = false`; un reintento automático no debe duplicar escrituras.
- Corrida de cierre única: `npm run lint && npm run typecheck && npm test -- --run && npm run build`.

| Comando | Resultado |
|---|---|
| `npm run lint` | 0 errores, 0 avisos |
| `npm run typecheck` | 0 errores |
| `npm test -- --run` | 3 archivos, 8/8 pruebas en verde |
| `npm run build` | sale con 0 |
| `npm ci` | sale con 0 (lockfile sincronizado) |

| Escenario | Prueba | Archivo:línea |
|---|---|---|
| RE › Shell en la raíz (componente) | muestra el nombre del producto y el mensaje de bienvenida en español | `software/web/src/App.test.tsx:35` |
| RE › Textos del shell desde el módulo central (textos) | cada texto visible coincide con un valor del módulo central | `software/web/src/App.test.tsx:52` |
| RE › Textos del shell desde el módulo central (control positivo) | control positivo: un texto fuera del módulo es detectado | `software/web/src/App.test.tsx:59` |
| RE › Textos del shell desde el módulo central (`lang="es"`) | declara lang="es" en <html> | `software/web/src/document.test.ts:6` |
| 3.2 Cimiento shadcn renderiza | monta un componente de shadcn/ui (tarjeta) | `software/web/src/App.test.tsx:42` |
| 3.3 Proveedor TanStack Query en la raíz | entrega un QueryClient a los descendientes | `software/web/src/app/providers.test.tsx:13` |
| 3.3 Proveedor (negativo) | sin el proveedor, useQueryClient falla | `software/web/src/app/providers.test.tsx:22` |
| Decisión reintentos | las mutaciones no se reintentan solas | `software/web/src/app/providers.test.tsx:29` |
| CI › Frontend correcto (local) | corrida de cierre arriba | `software/web/package.json` scripts |

| Barrido (`/usr/bin/grep`, sobre `find -L src -type f`) | Coincidencias | Control positivo |
|---|---|---|
| `console\.` | 0 | `printf 'console.log(1)'` → 1 |
| `fetch\(\|localStorage\|sessionStorage` | 0 | muestra de 2 líneas → 2 |
| texto literal en JSX fuera de pruebas | 0 | `printf '<p>Hola</p>'` → 1 |
| `console` (amplio) | 1 (`providers.test.tsx:24`, spy silenciador) | — |
| archivos leídos / presentes | 13 / 13 | — |

- Pendiente para 4.5: comprobación renderizada contra el stack compose (no listo en esta tarea).
- Ancla de transporte de "RE › Shell en la raíz" (Nginx de `web`) queda para 4.3.
