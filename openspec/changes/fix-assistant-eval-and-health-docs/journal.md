# Journal — fix-assistant-eval-and-health-docs (S9)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-09 — spec-engineer: proposal, deltas y tareas borrador

- Producido: `proposal.md`; deltas `specs/service-health/spec.md` (1 requisito ADDED, 7 escenarios),
  `specs/project-documentation/spec.md` (1 ADDED, 6), `specs/assistant-evaluation/spec.md` (1 ADDED, 8);
  `tasks.md` (13 tareas, `[MUT]` M1–M5). `openspec validate fix-assistant-eval-and-health-docs --strict` → válido.
- Tier propuesto: **B**. Disparador: lógica real sin disparador A — comparador de la evaluación (resolución de
  catálogo) y una prueba de contrato nueva. Sin migración, sin escritura de stock ni kardex, sin permisos, sin tocar
  herramientas, prompt ni filtro previo del asistente. Punto límite revisado: `assistant:eval` es paso de CI; un
  comparador laxo podría dar un verde falso de la evaluación. No es el arnés de Pest (disparador A) y el riesgo queda
  fijado por M4 y M5. Si el Orchestrator lo lee como arnés, el tier sube a A y la preaprobación de S9 no aplica.
- Desvío del encargo: se pidió MODIFIED en `assistant-evaluation`. Se usó ADDED: el requisito vivo «Comando que
  reporta aciertos» nunca fijó la comparación literal (vive en design D14), y agregarla allí llevaría su descripción
  sobre 500 caracteres (`--strict` del archivo vivo fallaría tras archivar).
- Hallazgos del código: `openapi.json` sale de Scramble (`api_path` = `api`, servidor `/api`), por eso no ve
  `/health` ni `/ready`; hay verificación de deriva (`composer openapi:check`) y lint Redocly, pero ninguna prueba
  de rutas contra rutas registradas. `route:list` en el stack: 36 rutas, de ellas 5 fuera de `/api` (`health`,
  `ready`, `sanctum/csrf-cookie`, `storage/{path}` GET y PUT). `api-schema.ts` se deriva del contrato y CI verifica
  su deriva → tarea 3.1.
- Supuestos (6, en la propuesta): exclusiones de la prueba de rutas; esperado sin resolución compara texto;
  ambiguo = no resuelve; resolución en la base de evaluación; mecánica de Scramble a elección del implementador;
  tier B.
- Preguntas abiertas: ninguna bloqueante. Para el implementador: las rutas fuera de `/api` necesitan un servidor
  propio en el contrato (raíz) para no resolver como `/api/health`; Redocly debe aceptarlo.
- Bloqueos: ninguno. No se tocó código ni la carpeta de S10.

## 2026-10-09 — spec-engineer: correcciones del spec-validator

- Anclas `[ancla: …]` en SH › «Vivacidad documentada», «Disponibilidad documentada con su fallo», «Operaciones
  públicas sin seguridad de sesión» (live spec + `routes/health.php`, `ReadyController.php:17`).
- Alcance (dictamen del Orchestrator): fuera la prueba de todas las rutas y el delta `project-documentation`; la
  prueba de contrato cubre solo `/health` y `/ready`, dentro de `service-health`. Fuera «Contrato coherente con la
  respuesta real» (HTTP real; la vivacidad y disponibilidad ya están probadas por el spec vivo).
- Fuera la medición opcional con Ollama. Documentación: una sola línea en `asistente.md`. Queda como redacción
  (regla 12) la frase de § Medición real que atribuye los casos 7 y 10 al comparador.
- Tareas: 11, cada una con escenarios nombrados; arnés `tools`/`pest`/`mut`/`ctl`/`drift` con
  `--profile tools -e AI_PROVIDER=mock`; parches `mutants/M1–M5.patch` con control positivo. `drift` compara en el
  anfitrión porque `composer openapi:check` usa `git diff` y el contenedor no ve `.git`.

## 2026-10-09 — Orchestrator: GATE 1

- shard = auv
- GATE 1: preaprobado (ROADMAP 2026-10-09, «Haz los dos. Autopiloto»), condiciones 1-4 OK, tier B.
  1. Alcance = fila S9: tras el recorte del Orchestrator (sin prueba de todas las rutas, sin medición con Ollama, una línea de documento).
  2. spec-validator VALID en la segunda pasada; ancla de transporte sin hits sueltos.
  3. Tier B = columna Tier.
  4. Ningún ADR ni RN se debilita.
- Architect no convocado: tier B sin costura nueva.
- Pendiente de apply: los parches `mutants/M1..M5.patch` los escribe el backend-implementer.
