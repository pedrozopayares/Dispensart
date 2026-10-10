# Journal — add-admin-screens (S13)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-09 — spec-engineer: proposal, deltas y tareas borrador

| Artefacto | Comando (desde la carpeta del cambio) | Resultado |
|---|---|---|
| Requisitos ADDED `admin-screens` | `/usr/bin/grep -c '^### Requirement:' specs/admin-screens/spec.md` | 9 |
| Escenarios `admin-screens` | `/usr/bin/grep -c '^#### Scenario:' specs/admin-screens/spec.md` | 50 |
| MODIFIED `assistant-screen` (con RENAMED) | `/usr/bin/grep -c '^### Requirement:' specs/assistant-screen/spec.md` | 1 |
| MODIFIED `operator-workspace` | `/usr/bin/grep -c '^### Requirement:' specs/operator-workspace/spec.md` | 2 |
| MODIFIED `app-shell` | `/usr/bin/grep -c '^### Requirement:' specs/app-shell/spec.md` | 1 |
| Tareas (`[MUT]` declarados: 6) | `/usr/bin/grep -c '^- \[ \]' tasks.md` | 26 |
| Supuestos en `proposal.md` | `/usr/bin/grep -c '^[0-9]\. ' proposal.md` | 8 |
| Descripción más larga (caracteres, tope 500) | `python3 -I` sobre `specs/*/spec.md` | 494 |
| Validación | `openspec validate add-admin-screens --strict` | válido |
| Ancla de transporte | patrón de `CYCLE-TIERS.md` sobre `specs/` | 0 por archivo; control `../../specs/transfers/` = 72 |
| Palabras de `proposal.md` | `wc -w proposal.md` | 455 |

| Barrido | Comando | Resultado |
|---|---|---|
| Ancla de transporte | `/usr/bin/grep -rnEc '^- \*\*THEN\*\*.*(HTTP\|[^0-9](200\|201\|204\|400\|401\|403\|404\|409\|419\|422\|423\|500)[^0-9]\|5xx\|invalid_\|not_found\|conflict\|error code\|refus\|reject\|forbidden\|insufficient\|expired)' specs/` | 0 en los 4 archivos; los THEN de rechazo llevan igual su ancla al FormRequest o al escenario vivo |
| Control positivo | mismo patrón sobre `../../specs/transfers/` | `spec.md:72` |
| Falso positivo corregido | primera pasada: «Acetaminofén 500 mg» casaba `500` | escenario reescrito con «Omeprazol 20 mg» |

- Alcance: igual a la fila S13 (Usuarios: listar y crear con rol; Catálogo: bodegas y productos, listar, crear,
  editar; `admin` sin Asistente en menú, inicio y ruta). Nada barrido fuera de la fila.
- Tier propuesto: **B**. Disparador: flujos de pantalla con escritura sobre endpoints existentes. Sin disparador A:
  sin migración ni escritura de stock o kardex, Policies y rutas de la API intactas, sin datos de pacientes, sin
  superficie del asistente (la ruta `POST /api/assistant/ask` no cambia), sin cambio del arnés de pruebas. La guarda
  de la SPA no autoriza (operator-workspace «Guarda de ruta por capacidad»: el servidor sigue siendo la autoridad).
- Contrato: suficiente; ningún hueco. Reglas leídas de los FormRequest y Resources:

| Recurso | Reglas |
|---|---|
| `UserResource` | `id`, `name`, `email`, `role`; sin contraseña |
| `StoreUserRequest` | `password` min 8 max 255; `role` del conjunto; `email` único sin mayúsculas |
| Bodega | `code` max 20 único; `name` max 120 único |
| Producto | `code` max 30 único; `name` max 150; `presentation` opcional nula; `is_controlled` booleano |

  Mensajes de campo en español desde `lang/es/validation.php`. Listas sin paginar, ordenadas por nombre.
- MODIFIED: la herramienta rechaza renombrar escenarios dentro de un MODIFIED; se conservan los nombres vivos
  («Admin sin pantallas de operación», «Admin sin accesos», «Página de inicio sin pantallas aún») con cuerpo nuevo.
  El requisito del asistente se renombra con RENAMED (FROM «Pantalla Asistente para toda sesión»).
- Pruebas existentes que cambian: menú e inicio del `admin` en `src/app/require-ability.test.tsx`; «Llamada negada»
  de `assistant-page.test.tsx` usa `admin` y pasa a `medico`.
- Hallazgo de la convención `[MUT]`: `vitest run -t` sin coincidencias pasa en vacío (tabla siguiente); los comandos
  exigen `Tests N failed` con el mutante y `Tests N passed` restaurado.

| Medición | Comando | Resultado |
|---|---|---|
| Filtro sin coincidencias | `npx vitest run src/app/require-ability.test.tsx -t "zzz título inexistente"` | `Tests 15 skipped`, salida 0 |

- Supuestos: en la propuesta (API del asistente sigue aceptando a `admin`; sin editar/borrar usuarios; mensajes de
  campo del servidor; contraseña enmascarada sin confirmación; sin paginación; presentación en blanco = `null`; sin
  costura nueva ni `design.md`; `## Purpose` de `assistant-screen` se corrige al archivar).
- Deuda a reportar (prosa; el Orchestrator decide fila): negar `POST /api/assistant/ask` a `admin` en la API es un
  cambio aparte de Tier A.
- Preguntas abiertas: ninguna. Bloqueos: ninguno.

## 2026-10-09 — spec-engineer: correcciones del spec-validator

- Tarea 0.1: el barrido de operaciones espera el valor real (cada operación aparece en `paths` y en `operations`).
- `assistant-screen`: el MODIFIED copia el texto vivo y cambia solo la parte de roles.
- Tareas: escenarios citados por nombre, sin «todos»; verificaciones en rojo con comando y aserción de salida;
  commit explícito y árbol limpio antes de cada parche `[MUT]`; encabezados en español; pines en tabla.
- Propuesta recortada a una página, sin cifras en prosa; decisiones conservadas.

| Barrido | Comando | Resultado |
|---|---|---|
| Validación | `openspec validate add-admin-screens --strict` | válido |
| Ancla de transporte | patrón de `CYCLE-TIERS.md` sobre `specs/` | 0 por archivo; control `../../specs/transfers/` = 72 |
| Palabras de `proposal.md` | `wc -w proposal.md` | 455 |

## 2026-10-09 — Orchestrator: GATE 1

- shard = auv
- GATE 1: preaprobado (ROADMAP 2026-10-09, «… Aprobado S13»), condiciones 1-4 OK, tier B.
  1. Alcance = fila S13: pantallas Usuarios y Catálogo para `admin` sobre la API existente; `admin` deja de ver el
     Asistente en menú, inicio y ruta. Sin cambio de backend; el rechazo del endpoint para `admin` queda fuera (tier A aparte).
  2. spec-validator VALID en la segunda pasada; ancla sin hits. 3. Tier B = columna. 4. Ningún ADR ni RN se debilita.
- Architect no convocado: tier B sin costura nueva (patrones de pantallas, mutaciones y catálogo de errores existentes).

## 2026-10-09 — frontend-implementer: apply

| Hecho | Evidencia |
|---|---|
| Tareas cerradas | 0.1–6.2 `[x]`; 6.3 (recorrido del Orchestrator) abierta |
| Commits en `dev` | `8d14fef` API y textos · `f83e90c` menú y guardas · `f0f8e77` Usuarios · `5c55158` prueba de doble clic · `3db9528` Catálogo |
| Corrida completa única (6.1) | lint exit 0 · typecheck exit 0 · 32 archivos, 338 pruebas pasan |
| Mutantes | M1–M6 rojo con el parche, verde restaurado, exit 0 (`verification.md` § 2) |
| Contenedor `web` (6.2) | reconstruido, Healthy; `/users`, `/catalog`, `/assistant` → 200; el bundle servido trae los textos nuevos |
| Barridos (5.1) | textos, colores y privacidad en 0 con su control positivo (`verification.md` § 4) |

- Decisión: casilla nativa con `accent-primary` en vez del `Checkbox` de shadcn. El de Radix exige `ResizeObserver`
  (ausente en jsdom) y polirrellenarlo tocaba el arnés de pruebas. Sin paquete ni archivo de kit nuevo.
- Decisión: la variante `'session'` de la tabla de pantallas se retira; el asistente exige la unión de capacidades de
  las cuatro pantallas de operación. Misma guarda para toda fila, sin costura nueva.
- Desviación: `src/app/shell-header.test.tsx` actualizado (afirmaba el acceso "Asistente" del admin); no figuraba en
  la lista de archivos de 2.1.
- Hallazgo de prueba: con dos `fireEvent.click` el re-render dentro de `act` deshabilita el botón entre clics y M5
  sobrevivía. Las pruebas de doble clic ahora pulsan dos veces dentro de un solo `act` y cada petición recibe su
  propia respuesta; M5 cae por conteo de peticiones.
- Deuda (prosa, el Orchestrator decide fila): negar `POST /api/assistant/ask` a `admin` en la API sigue fuera (tier A
  aparte, ya anotado en la propuesta). La pantalla Usuarios no reutiliza el `TextField` del catálogo; unificar ambos
  formularios es una limpieza menor sin efecto en escenarios.
- Bloqueos: ninguno.
