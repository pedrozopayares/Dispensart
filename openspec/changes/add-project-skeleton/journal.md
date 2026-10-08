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
