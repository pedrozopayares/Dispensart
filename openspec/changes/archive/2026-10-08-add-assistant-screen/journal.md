# Journal — add-assistant-screen

## 2026-10-08 — spec-engineer: propuesta, deltas y tareas en borrador

Pedido del usuario (2026-10-08, literal): "Agrega una pantalla de asistente de IA para poder probar como la
usaría un usuario de la aplicación".

**Producido**: `proposal.md`; deltas `specs/assistant-screen/spec.md` (ADDED, 7 requisitos),
`specs/operator-workspace/spec.md` (MODIFIED "Navegación por rol", "Inicio con accesos del rol"),
`specs/app-shell/spec.md` (MODIFIED "Encabezado con sesión y cierre"); `tasks.md` en borrador.
`openspec validate add-assistant-screen --strict` válido.

**Tier propuesto: B.** Disparador: flujo de pantalla con lógica (decide qué mostrar por `outcome`, por
estado de llamada y por código de error). Ningún disparador de Tier A: sin cambio de contrato, sin tocar el
catálogo de herramientas ni la defensa contra inyección, sin cambio de autorización (la ruta ya está abierta
a toda sesión y el servidor sigue siendo la autoridad), sin datos de pacientes servidos por la API; el texto
que el usuario escriba queda solo en memoria de la pantalla. Precedente: S6 (pantallas de operación) fue B.
`design.md`: no se ve costura nueva (cliente, mutación, catálogo de errores, tabla de pantallas y botón de
envío existen); la fila de la tabla abierta a toda sesión es el único cambio de forma, a juicio del
Orchestrator si amerita architect (criterio "nueva capacidad" de CLAUDE.md).

**Ancla de transporte**: el barrido de `CYCLE-TIERS.md` sobre `specs/` da un solo acierto, un conteo de
caracteres ("500/500"), anclado en línea a `AskAssistantRequest.php:21`. Ningún THEN afirma un estado HTTP.

**Decisiones de alcance**: los escenarios renombrados de una MODIFIED conservan su nombre vivo porque el CLI
no permite quitar escenarios; "Asistente" se trata como acceso aparte de las cuatro pantallas de operación
para que esos nombres sigan siendo ciertos.

**Hueco de contrato (no se amplía el alcance)**: el filtro previo de pacientes de S7 responde `out_of_scope`
con el mismo `answer` y `tool_calls` vacío que una pregunta ajena al inventario; la SPA no puede mostrar
"bloqueada por datos de pacientes" como resultado distinto sin cambiar la API. Se muestra como "Fuera de
alcance" más un aviso fijo de privacidad. Si se quiere distinguirlo: cambio de API aparte (p. ej. un motivo
en la respuesta), Tier A por tocar el manejo de datos de pacientes.

**Supuestos**: 8, en `proposal.md` § Assumptions (roles = toda sesión; filtro de pacientes como fuera de
alcance; historial de 10 en memoria; Enter/Shift+Enter; ejemplo rellena sin enviar; `answer` como texto plano;
429 sin cuenta regresiva; humo manual sin E2E).

**Preguntas abiertas**: ninguna bloqueante.

## 2026-10-08 — Orchestrator: apertura y decisiones previas a GATE 1

- shard = auv
- Fuera del lote S0–S8: GATE 1 no está preaprobado; requiere aprobación explícita del usuario.
- Tier: B (disparador: flujo de pantalla con lógica de presentación por `outcome`, estado de llamada y código de error).
- Architect: no se convoca. La tabla de tiers manda `design.md` en B solo con costura nueva; la capacidad nueva
  es una pantalla más sobre cliente, mutación y catálogo de errores existentes (mismo precedente que S6).
- spec-validator: 3 hallazgos (tareas sin escenario citado, propuesta larga, shard ausente). Los dos primeros
  vuelven a spec-engineer; el shard queda registrado aquí.

## 2026-10-08 — GATE 1: APROBADO por el usuario

- Respuesta literal: "Aprobado." Tier B registrado. Alcance: solo `software/web` y deltas de especificación.
- Nota del usuario en la misma respuesta: en este equipo corre Ollama con el modelo `gemma4:e2b-mlx`; se pide usarlo
  con el proveedor Ollama ya existente (S7). Es configuración local (`AI_PROVIDER`, `OLLAMA_MODEL`), no código de
  este cambio.

## 2026-10-08 — frontend-implementer: apply (tareas 0.1–5.1)

- Commits en `dev`: `e004496` (cliente y catálogo de errores), `2cf1cfa` (pantalla, tabla de pantallas, menú e inicio).
- Tareas 0.1–5.1 cerradas `[x]`; 5.2 (humo en el navegador) queda para el Orchestrator.
- Corrida completa única de cierre: lint, typecheck y Vitest en verde; recuento releído sobre el mismo árbol porque la
  salida del cierre se cortó (detalle y cifras en `verification.md` § 3).
- `[MUT]` M1–M3 aplicados y restaurados con parche en `mutants/`; los tres mueren. Desvío: M1 muere por «Enter
  repetido» y no por «Doble clic» (el re-render del envío deshabilita el botón antes del segundo clic); razón en
  `verification.md` § 2.
- Decisión: `too_many_requests` lleva en el catálogo central el texto de preguntas porque solo la ruta del asistente
  tiene `throttle`; si otra ruta gana límite, el texto pasa a genérico y el asistente lo sobrescribe.
- Decisión: la mutación usa `gcTime: 0` para que la pregunta no quede en la caché de mutaciones al salir de la pantalla.
- Hallazgo menor (sin código cambiado): ante `unauthenticated` la pantalla pinta un instante la alerta con el mismo
  texto de sesión expirada antes de que app-shell navegue a `/login`.
- Imagen `web` reconstruida; `/assistant` servido por el contenedor (`verification.md` § 3).

## 2026-10-08 — Orchestrator: cierre de apply

- Tareas 16/16. 5.2 hecha por el Orchestrator en el navegador con los 5 roles; tablas en `verification.md` § 5.
- Desvío: el recorrido usó Ollama local (`gemma4:e2b-mlx`) en vez de `mock`, por pedido del usuario; las etiquetas
  dependen del `outcome`, no del proveedor.
- Medición del modelo real con `assistant:eval`: 20/24. Deuda D-auv-7 (comparador literal de argumentos) registrada.
- Suites: el implementador corrió dos veces la suite completa (la segunda solo para leer totales); dentro del tope de 3.
- Pantalla alcanzable desde menú e inicio en los 5 roles: sin rutas huérfanas.
- Siguiente: final-auditor, modo delta (tier B).

## 2026-10-08 — GATE 2: APPROVED (final-auditor, delta tier B)

- Veredicto del final-auditor sobre `3bbf3c4..1bd7364`: APPROVED, sin observaciones.
- Deuda al archivar: D-auv-7 (menor, backend de evaluación) abierta; no la causó este cambio y puede viajar un cambio.
