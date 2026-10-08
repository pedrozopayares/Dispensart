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
