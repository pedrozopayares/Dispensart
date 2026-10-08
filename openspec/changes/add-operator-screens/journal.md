# Journal — add-operator-screens (S6)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv
- Fase: /proposal adelantada. Apply espera el archivo de S5.
- Tier objetivo según ROADMAP: B.

## 2026-10-07 — spec-engineer: propuesta, deltas y tareas borrador

**Producido**
- `proposal.md` (≤ 1 página): partes B; RN-01, RN-02, RN-04..RN-11 desde la interfaz. Tier B.
- `specs/`: 5 capacidades nuevas — `operator-workspace`, `dispensation-screen`, `transfers-screen`,
  `inventory-screen`, `kardex-screen`. Cada requisito con al menos un escenario negativo; los de UI con
  carga/error/vacío, doble envío y punto de entrada. Escenarios de UI afirman comportamiento visible, sin
  estados HTTP en los THEN.
- `tasks.md` borrador: 0 precondiciones → 1 cimientos compartidos (tipos desde OpenAPI, cliente, catálogo de
  mensajes, componentes, intención idempotente, guarda) → 2–5 pantallas → 6 navegación y privacidad → 7 humo.
  `[MUT]` declarados: 4 (M1 reutilización de clave, M2 doble clic, M3 clave nueva al cambiar intención,
  M4 Aprobar oculto al solicitante).
- `openspec validate add-operator-screens --strict`: válido.
- Ancla de transporte: barrido `/usr/bin/grep -rnE` de CYCLE-TIERS sobre `specs/` → sin coincidencias
  (control positivo: el mismo directorio tiene 116 líneas THEN contadas con `/usr/bin/grep -rc`).

**Supuestos** (7, en `proposal.md` § Assumptions): sin pantallas de ajuste ni de resolución de
discrepancias; Dispensación visible con `dispensations.create` o `patients.view` (modo consulta para auditor y
médico); intención = prescripción + bodega + ítems; vista previa explícita invalidada al editar; resaltado de
alertas solo desde la API; rutas en inglés; humo manual en lugar de E2E en navegador.

**Preguntas abiertas / para el Orchestrator**
- `app-shell` (S1) aún no es spec viva: el `MODIFIED` del inicio queda como tarea 0.1 (precedente: S1 0.1).
- Criterio de complejidad "dependencia nueva": generador de tipos desde OpenAPI y quizá MSW (S1 D8 dejó
  "Revisar si: S6 multiplica escenarios de red"). Decide si pasa por architect.
- Ajustes de inventario y resolución de discrepancias sin pantalla: candidatos de fila de ROADMAP, no de esta
  tajada (condición 1 de la preaprobación).

## 2026-10-07 — architect: design.md y refinamiento de tareas

**Decisiones** (`design.md` D1–D7)
- D1 `openapi-typescript` (dev) → `src/lib/api-schema.d.ts` versionado; `check:api` encadenado en `typecheck`: la deriva falla en CI sin tocar `.github/`. Envoltura propia tipada por ruta sobre el cliente de S1 D8.
- D2 MSW (dev) tras la fachada `src/test/http.ts`, `onUnhandledRequest: 'error'`, registro de peticiones para M1–M3. URL absolutas en `api.ts`.
- D3 clave por intención en `useRef` con huella del mismo cuerpo enviado; se descarta tras éxito/repetición e `idempotency_key_reused`; `crypto.getRandomValues`.
- D4 candado síncrono `useRef` además de `isPending`.
- D5 `describeError`/`fieldErrors` únicos; nunca `error.message`.
- D6 tabla de rutas única para menú y guarda; unión TS `Ability`.
- D7 `src/features/<pantalla>/`, sin barriles, `listbox` propio para pacientes.

**Rechazadas**: `openapi-fetch` (duplica D8, dependencia de ejecución), tipos a mano, simular el módulo cliente o hooks, ampliar el `fetch` falso de S1, `crypto.randomUUID` (contexto seguro), guarda en `loader`, `Command`/`cmdk`, división de código por ruta.

**Tareas**: 1.2 ↔ 1.3 intercambiadas (la red simulada precede a las funciones que se prueban contra ella); referencias a D1–D7 en 1.1–1.7 y 6.1; control positivo de deriva en 1.1. Bloques 2–5 independientes tras 1 (sin contrato nuevo entre ellos); un implementador, en serie.

**Riesgos**: MSW/URL relativas en Vitest 5 + jsdom 30 (plan B 30 min, misma fachada); Scramble anonimiza esquemas (`shortages`, `Idempotent-Replayed`: tipos a mano anotados en 0.2); clave mal renovada o doble envío en el mismo tick (M1–M3).
**Dependencias nuevas**: `openapi-typescript`, `msw` — solo desarrollo, MIT, costo cero.

## 2026-10-07 — spec-engineer: correcciones del spec-validator

- `tasks.md`: 0.1, 1.6 y 7.1 nombran escenarios; 0.2, 1.2, 1.3 y 6.3 llevan comandos de verificación
  ejecutables con su control positivo (el patrón de 6.3 da 2 coincidencias sobre la muestra de control).
  Orden 1.2/1.3 del architect conservado.
- `proposal.md`: Modified Capabilities explica que el delta `MODIFIED` de `app-shell` lo añade la tarea 0.1
  cuando S1 se archive.
- `openspec validate add-operator-screens --strict`: válido. Ancla de transporte: sin coincidencias.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-operator-screens` tras correcciones de prosa (citas y comandos de verificación,
  delta diferido de `app-shell`). Ancla de transporte: sin hits (escenarios de UI afirman lo visible).
- Condición 1 (alcance exacto de S6): OK. Dispensación, Traslados, Inventario, Kardex, navegación por rol.
  Sin pantallas de ajustes ni de resolución de discrepancias (supuestos de la propuesta).
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): B, coincide con ROADMAP. `design.md` corto por dependencias nuevas de desarrollo.
- Condición 4 (ADR / RN): `openapi-typescript` y MSW solo en desarrollo; respetan ADR-0003/0004.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier B
- Apply espera el archivo de S5 (bloqueado por decisión de tier del usuario).

## 2026-10-08 — Orchestrator: apply parcial adelantado

- S1 y S2 archivados. Se adelanta la parte de S6 cuya API existe: cimientos (grupo 1, recursos de stock,
  kardex y catálogo), Inventario sin panel de alertas (4.1), Kardex (5.x) y sus rutas. Dispensación espera
  S3; Traslados espera S4; alertas (4.2) espera S5. Tarea 0.1 (delta de `app-shell`) al spec-engineer.

## 2026-10-08 — spec-engineer: tarea 0.1 (delta MODIFIED de app-shell)

- S1 archivado; `specs/app-shell/spec.md` con `MODIFIED` de "Encabezado con sesión y cierre", bloque completo.
  El validador exige conservar los nombres de escenario: «Página de inicio sin pantallas aún» se reescribe para
  el rol sin pantallas (`admin`); se añade «Página de inicio con saludo». Descripción: el inicio saluda y los
  accesos los fija `operator-workspace`.
- `proposal.md` Modified Capabilities y tarea 6.1 actualizadas; 0.1 marcada `[x]`.
- `openspec validate add-operator-screens --strict`: válido. Ancla de transporte: sin coincidencias.

## 2026-10-08 — frontend-implementer: apply parcial (1.1, 1.2, 1.4–1.7, 4.1, 5.1, 5.2)

**Cerradas**: 1.1, 1.2, 1.4, 1.5, 1.6, 1.7, 4.1, 5.1, 5.2. **Parciales, sin marcar**: 0.2 (solo S1/S2), 1.3
(stock, kardex, catálogo), 6.1 (rutas `/inventory`, `/kardex` en la tabla y el menú). Matriz en `verification.md`.

**0.2 — contraste del contrato (S1/S2)**
- Las rutas de `openapi.json` no llevan `/api` (servidor `/api`): el patrón `"/api/(…)"` de 0.2 devuelve 0. Con
  `"/(…)"` aparecen `auth/me`, `stock`, `stock-adjustments`, `kardex`, `products`, `lots`, `warehouses`. Faltan
  `patients`, `prescriptions`, `dispensations` (S3), `transfers` (S4), `alerts` (S5); `Idempotency-Key` e
  `Idempotent-Replayed` aún sin publicar. La tarea 0.2 debe corregir su patrón al cerrarse; ningún escenario cambia.
- Tipado a mano en `src/lib/api-types.ts`: `Shortage` (Scramble no publica `shortages`). `ApiErrorCode` admite
  `string & {}`: el enum del documento no trae los códigos de S3–S5 y el servidor puede enviar uno desconocido.
- `AuthenticatedUserResource.abilities` es `unknown[]` en el documento: la unión `Ability` vive en `src/lib/abilities.ts`.

**Decisiones**
- 1.1: se reutiliza `api:types` / `api:types:check` de S1 (archivo `src/lib/api-schema.ts`, paso propio en CI) en
  lugar de `gen:api` / `check:api` en `typecheck`: mismo control, sin duplicarlo. Se añaden ayudantes por ruta
  `ResponseOf`, `QueryOf`, `BodyOf`.
- D2: MSW funciona con Vitest 5 + jsdom 30; plan B no usado. Petición sin manejador → registrada y la prueba falla.
  Las pruebas de S1 con `fetch` falso siguen igual; su ayudante compara rutas sin origen (URL absolutas en `api.ts`).
- D5: catálogo único `describeError`; `errorMessage` de S1 retirado y sus tres usos apuntan al catálogo.
- D6: la tabla de pantallas vive en `src/app/screens.tsx` (no en `routes.tsx`) para evitar el ciclo
  routes → layout → encabezado → routes; `routes.tsx` y el menú la leen.
- Filtros con `select` nativo (shadcn `native-select`): teclado y pruebas sin trabajo extra.
- Kardex: el lote solo aplica con producto elegido; fecha formateada en `America/Bogota` explícita.
- Pruebas de pantalla sin reintento automático de consultas (`retry: false` en `src/test/render.tsx`).

**Ejecuciones**: cierre completo lint/typecheck/test/build: 102/103; la falla era la prueba (aserción sobre el
texto de progreso de una respuesta inmediata), corregida con `waitFor` y re-ejecutada solo ese archivo: 4/4.
Mutaciones de comprobación a y b en `verification.md` § 2.

**Humo**: stack en `:8090` con `web` reconstruido; capturas en `captures/s6-*.png`, tabla en `verification.md` § 4.

**Deuda (prosa)**: la tarea 0.2 queda abierta hasta S3–S5 con su patrón de búsqueda a corregir. Una página del
kardex más allá de la última (p. ej. `?page=9`) muestra vacío sin paginador para volver.

## 2026-10-08 — frontend-implementer: Dispensación sobre el contrato de S3 (2.1–2.9, parciales 0.2, 1.3, 6.1)

**Cerradas**: 2.1–2.9 (M1–M3 en `verification.md` § 2.1). **Parciales, sin marcar**: 0.2 (S1–S3 contrastados;
faltan `alerts`, `transfers`), 1.3 (faltan alertas y traslados), 6.1 (ruta `/dispensations` y entrada de menú
en la tabla de pantallas; faltan Traslados y el inicio con accesos).

**Tipos**: `npm run api:types` regenerado y comprometido aparte (`fix: tipos de la SPA alineados con el contrato
de dispensación`) antes de cualquier código.

**0.2 — contraste S3**
- El patrón `"/api/(…)"` de la tarea sigue dando 0: las rutas del documento no llevan `/api` (servidor `/api`).
  Con `"/(…)"`: `patients`, `patients/{patient}`, `prescriptions`, `dispensations`, `dispensations/preview`.
  `Idempotency-Key` e `Idempotent-Replayed`: 3 apariciones. El patrón es del spec-engineer: no lo edito.
- Ningún nombre de campo difiere de la tabla de contrato de S3 ni de los escenarios.
- `Shortage` deja de ser tipo a mano: el documento publica `shortages` (con `prescription_item_id` obligatorio).
- Sigue a mano: `PrescriptionStatus` (`vigente|vencida|agotada`); el documento publica `status: string`.

**Decisiones**
- `apiRequestWithHeaders` en el cliente: cabeceras propias por petición (`Idempotency-Key`, reenviada igual en el
  reintento por 419) y lectura de `Idempotent-Replayed`; `apiRequest` queda como envoltura.
- Hook de intención a nivel de pantalla, no del formulario: cerrar y reabrir el formulario no regenera la clave,
  y "Nueva dispensación" solo cambia la clave por `settleSuccess` (la prueba lo distingue).
- La intención guardada junto a la vista previa es el cuerpo confirmado: huella y cuerpo salen de la misma
  función (`buildDispensationIntent`).
- Rechazos `prescription_*` en vista previa o confirmación: aviso en la ficha (sobrevive al desmontaje del
  formulario cuando la prescripción deja de estar Vigente) y recarga de la ficha.
- Búsqueda como `combobox` + `listbox` propio con `aria-activedescendant`; el término solo en memoria.
- Invalidación tras dispensar sin esperar la recarga (`void`), para no retener "Confirmando…".

**Ejecuciones**: delta `src/features/dispensations` (47/47) durante el desarrollo; cierre completo = ejecución 2
de 3 del presupuesto: lint 0, tsc 0, 150/150, build OK. Repetí `npm test -- --run` sobre el mismo árbol solo para
leer el total que la salida truncó (150/150): queda registrado como lectura, no como ejecución nueva de cambios.

**Humo**: `web` reconstruido; auxiliar dispensa (FEFO con vencido excluido) y dispensa morfina con coautorización
del regente (201); auditor ve ficha enmascarada sin botón de dispensar; médico en modo consulta. Capturas
`captures/s6-dispensacion-*.png`, tabla en `verification.md` § 4.

**Deuda (prosa)**: el patrón de búsqueda de la tarea 0.2 debe perder el prefijo `/api`. La ficha del paciente
semilla Ana acumula prescripciones agotadas creadas por pruebas previas contra el stack (datos, no código).
El ítem `invalid_idempotency_key` no tiene texto propio (cae en el genérico): la SPA siempre envía una clave válida.
