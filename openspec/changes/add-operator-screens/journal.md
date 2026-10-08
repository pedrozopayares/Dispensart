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
