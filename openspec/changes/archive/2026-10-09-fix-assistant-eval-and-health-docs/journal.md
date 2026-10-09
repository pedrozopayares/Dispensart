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

## 2026-10-09 — backend-implementer: apply

| Hecho | Detalle |
|---|---|
| Commits | `9dc7ad7` contrato de salud · `225e911` comparador por entidad resuelta · `e44c39c` tipos de la SPA · `0393c29` línea de `asistente.md` · commit de registro (este) |
| 1.1 roja | `HealthContractTest.php`: 6 failed / 6, todas con «openapi.json no documenta la operación GET /health.» o «… GET /ready.» |
| 2.1 roja | `EvaluationMatcherCatalogTest.php`: «Misma bodega con otra redacción» roja por el comparador (`… obtenido "farmacia de urgencias"' is null`). «Otra bodega sigue fallando» también roja, por la prueba: su caso de comando esperaba el texto del argumento y D14 reporta antes el outcome |
| Corrida de cierre | ver `verification.md` § 3 (Pint, Larastan `--memory-limit=1G`, Pest en una corrida, exit 0) |
| Mutantes | M1–M5 escritos en `mutants/`, cada uno rojo con su prueba y verde con su control (`verification.md` § 2) |
| Evaluación simulada | ver `verification.md` § 3 |
| Imagen | `api` reconstruida; `/health` y `/ready` responden 200 |

Decisiones:

- Mecánica de Scramble: transformador de documento propio (`HealthDocumentTransformer`), registrado después de `ApiErrorDocumentTransformer` para que la sesión, el CSRF y los 4xx no se apliquen a la salud. Servidor `/` a nivel de ruta, no de operación: así la URL resuelve en la raíz sin tocar el servidor `/api` del documento. Enums tomados de las constantes de `ReadinessResult` y la cabecera de `AssignCorrelationId::HEADER`. Rechazado: subir `api_path` a la raíz (Scramble documentaría `sanctum/csrf-cookie` y `storage/*`).
- Caso de comando de «Otra bodega sigue fallando»: con «farmacia central», la entrada `low-stock-equal-minimum` espera `no_results` y Central sí tiene alertas, así que la fila dice FALLO por outcome (orden D14), no por el argumento. La prueba afirma FALLO, una sola fila caída, total menos uno y código ≠ 0. El texto que nombra `warehouse`, el esperado y el obtenido lo fija la aserción del comparador en la misma prueba. Solo se reescribe la bodega de `get_low_stock_alerts` para que caiga una sola fila.
- Lint Redocly: exit 0 con 2 avisos `operation-4xx-response` en `/health` y `/ready` (antes 0). Son honestos: estas rutas no responden 4xx. No se silencian: un `redocly.yaml` en la raíz queda fuera del alcance del backend; lo decide el Orchestrator.
- D-auv-7 saldada en código (`225e911`): el comparador usa `CatalogResolver`; mover la fila a «Settled» en `DEBT.md` le toca al Orchestrator tras GATE 2.

Deuda nueva: ninguna. Bloqueos: ninguno.

## 2026-10-09 — backend-implementer: hallazgo 1 de la auditoría final

| Hecho | Detalle |
|---|---|
| Hallazgo | «Mismo producto con otra redacción» pasaba con la contención de texto: «Acetaminofén 500 mg» contiene «acetaminofen» |
| Arreglo (prueba) | caso nuevo: esperado «acetaminofen 500 mg», obtenido «Acetaminofén»; ninguno contiene al otro; más un negativo con «Ibuprofeno» |
| `[MUT]` M6 | `mutants/M6.patch` (`CATALOG_ARGUMENTS = ['warehouse']`): rojo en «Mismo producto con otra redacción», control «Misma bodega» verde, restaurado verde (`verification.md` § 2) |
| Aislamiento | delta sobre `dispensart_s9fix` (creada y borrada); sin corrida completa ni `composer openapi`, por presupuesto y por S10 en `dispensart_test` |
| Producto | sin cambios: la prueba nueva pasa con `225e911` |

## 2026-10-09 — GATE 2: APPROVED (final-auditor, delta tier B)

- Primera pasada: OBSERVATIONS (1 mayor de prueba: el producto por catálogo no estaba fijado; 1 menor de prosa: ejemplo del escenario).
- Arreglos: `93a5195` (caso de producto y M6) y `74f0951` (ejemplo). Re-auditoría delta sobre el hallazgo de código: APPROVED.
- D-auv-7 saldada. Incidente: la primera auditoría corrió `composer openapi`, que migra `dispensart_test` hacia adelante,
  mientras S10 trabajaba; sin efecto observado.
