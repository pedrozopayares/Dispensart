# Journal — add-assistant-model-selector (S15)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-09 — spec-engineer: proposal, deltas y tareas borrador

GATE 1 **no** está preaprobado para S15: lo aprueba el usuario tras leer el resumen.

| Artefacto | Comando (desde la carpeta del cambio) | Resultado |
|---|---|---|
| Requisitos `inventory-assistant` (3 ADDED + 2 MODIFIED) | `/usr/bin/grep -c '^### Requirement:' specs/inventory-assistant/spec.md` | 5 |
| Escenarios `inventory-assistant` | `/usr/bin/grep -c '^#### Scenario:' specs/inventory-assistant/spec.md` | 28 |
| Requisitos `assistant-screen` (3 ADDED + 3 MODIFIED) | `/usr/bin/grep -c '^### Requirement:' specs/assistant-screen/spec.md` | 6 |
| Escenarios `assistant-screen` | `/usr/bin/grep -c '^#### Scenario:' specs/assistant-screen/spec.md` | 33 |
| Tareas (`[MUT]` declarados en borrador: 10) | `/usr/bin/grep -c '^- \[ \]' tasks.md` | 29 |
| Supuestos en `proposal.md` | `/usr/bin/grep -c '^[0-9]\. ' proposal.md` | 8 |
| Descripción más larga (tope 500) | `python3 -I` sobre `specs/*/spec.md` | 461 |
| Escenarios sin cita en `tasks.md` | `python3 -I`, títulos `#### Scenario:` contra citas «…» | 0 de 61 |
| MODIFIED contra texto vivo | `difflib` por bloque contra `openspec/specs/<cap>/spec.md` | solo las ediciones previstas |
| Validación | `openspec validate add-assistant-model-selector --strict` | válido |
| Palabras de `proposal.md` | `wc -w proposal.md` | 541 |

| Barrido | Comando | Resultado |
|---|---|---|
| Ancla de transporte | `/usr/bin/grep -rnE '^- \*\*THEN\*\*.*(HTTP\|[^0-9](200\|201\|204\|400\|401\|403\|404\|409\|419\|422\|423\|500)[^0-9]\|5xx\|invalid_\|not_found\|conflict\|error code\|refus\|reject\|forbidden\|insufficient\|expired)' specs/` | 20 hits en `inventory-assistant`, 1 en `assistant-screen` (`Tope de 500 caracteres`, ancla viva); los 21 llevan `[ancla: …]`; control: el mismo patrón sobre `../../specs/transfers/spec.md` da 72 hits |
| Hits sin ancla | mismo patrón \| `/usr/bin/grep -vc '\[ancla:'` | 0; control positivo: una línea sintética `- **THEN** la API responde HTTP 422 sin ancla` por la misma tubería da 1 |

- Alcance: igual a la fila S15. Nada barrido fuera de la fila.
- Tier propuesto: **A**. Disparadores: superficie del asistente (una entrada del cliente elige el proveedor que recibe
  la pregunta; validación contra lista para que ningún texto arbitrario llegue a Ollama) y camino de datos hacia el
  modelo (RN-10). Sin migración ni escritura de stock.
- Decisiones registradas como supuestos (proposal § Supuestos): "activos" = `/api/tags`, no `/api/ps`; solo modelos con
  `tools`; plazo 2 s; lista para toda sesión; la SPA arranca en `mock` y `AI_PROVIDER` decide solo las preguntas sin
  `model`, `assistant:eval` y CI; identificadores `mock` / `ollama:<nombre>`; modelo caído entre lista y pregunta →
  422, falla en `/api/chat` → 503; `data.model` también con el filtro previo.
- Decisiones de pantalla en el spec: escritura en `localStorage` solo al elegir (el regreso a `mock` no sobrescribe la
  elección guardada, para que vuelva cuando Ollama vuelva); el valor guardado nunca viaja sin validar; "Preguntar"
  espera la lista; la elección sobrevive al cierre de sesión (solo un nombre de modelo, sin dato personal).
- `assistant-evaluation` sin delta: el comando sigue con `AI_PROVIDER`; la tarea 3.3 lo verifica.
- Para el `architect` (design decide): costura del proveedor por petición sin romper la prueba `arch()`; forma exacta
  del `id`; caché o límite de `GET /api/assistant/models` (cada llamada consulta Ollama 1 + N veces) y de la
  validación de `model` en `ask`; reparto del presupuesto de 2 s entre `/api/tags` y `/api/show`; precedencia del
  422 de `model` frente al de `question`.
- Preguntas abiertas al usuario: ninguna bloqueante.
- Riesgo: la validación de `model` de Ollama hace red dentro del FormRequest; sin caché, cada pregunta suma hasta 2 s.

## 2026-10-09 — architect: design.md y tasks.md refinadas

| Artefacto / barrido | Comando (desde la carpeta del cambio) | Resultado |
|---|---|---|
| Validación | `openspec validate add-assistant-model-selector --strict` | válido |
| Escenarios sin cita en `tasks.md` | `python3 -I`, títulos `#### Scenario:` contra citas «…» (sin saltos dentro de «») | 0 de 61; control: título inventado sí aparece como faltante |
| Tareas sin cita o sin `Verifica` | `python3 -I` sobre bloques `- [ ]` | 0 y 0 de 30 |
| Ancla de transporte | patrón de `CYCLE-TIERS.md` sobre `specs/` \| `/usr/bin/grep -vc '\[ancla:'` | 0 sin ancla; control: el mismo patrón sin filtro da 21 hits |
| Pins `[MUT]` declarados | `/usr/bin/grep -o 'M[0-9]*'` sobre `tasks.md` | M1–M13 |

- Decisiones (design D1–D10): `id` = `mock` \| `ollama:<name>` partido en el primer `:` con alfabeto cerrado;
  `ModelCatalog` con plazo total 2 s (`/api/tags`, luego `/api/show` en `Http::pool`, solo `tools`); caché de nombres
  30 s en almacén `file` (también la lista vacía), `array` forzado en `phpunit.xml`; `LlmProviderResolver` con
  fábrica cerrada en `AssistantServiceProvider` (reglas `arch()` sin cambio; `null` → enlace por `AI_PROVIDER`, así
  dobles de prueba y `assistant:eval` intactos); validación de `model` en el FormRequest con regla `AvailableModel`
  (`mock` y prefijos ajenos sin red); ruta de lista bajo `auth:sanctum` sin `throttle`, misma autorización que `ask`;
  log con `model`; SPA con `NativeSelect`, clave `dispensart.assistant.model`, `retry: false`, `staleTime` 30 s.
- Rechazadas: `id` opaco; caché `database` (escribe en la base, contra el spec); sin caché; `/api/ps`; `/api/show` en
  serie; enlace del proveedor leyendo la petición en el contenedor; `new OllamaLlmProvider` fuera del service
  provider; `throttle:assistant` en la lista; excluir `admin` en la API; `Select` de Radix; `sessionStorage`.
- Orden: api (0–3) → tipos (4) → web (5) → cierre (7); documentación (6) en paralelo con 5. Sin api ∥ web.
- Riesgos: caché desfasada hasta 30 s (503 en modelo borrado); fuga de `AI_PROVIDER=ollama` de `software/.env` a
  `api-tools` (comandos con `-e AI_PROVIDER=mock`); entrada del cliente elige destino (pins M4–M6).
- Deuda propuesta (el Orchestrator asigna id): fijar `AI_PROVIDER=mock` en `phpunit.xml` para que el arnés no dependa
  del `.env` local; fuera de la fila S15.
- Sin commit (instrucción del Orchestrator).

## 2026-10-09 — Orchestrator: apertura y deuda

- shard = auv
- La fijación de `AI_PROVIDER=mock` en `phpunit.xml` propuesta por el architect queda registrada como D-auv-9 (menor) en
  `openspec/DEBT.md`; fuera del alcance de S15.

## 2026-10-09 — architect: correcciones del spec-validator (NOT VALID)

| Hallazgo | Corrección |
|---|---|
| journal cita la tarea del eval con número viejo | apunta a 3.3 |
| cuentas en prosa de `tasks.md` | pins a tabla de encabezado; datasets nombrados por valor; "cinco/seis escenarios" → títulos; sin cuentas de pruebas ni de filas |
| 7.2/7.3 sin comando ejecutable | nueva 7.2: humo `software/docker/smoke/assistant-smoke.sh` amplía `GET /api/assistant/models` (401 sin sesión, `mock` primero, `data.model`) con `SMOKE_EXPECT_OLLAMA_MODEL` / `SMOKE_EXPECT_MOCK_ONLY`; 7.3 y 7.4 lo corren; `curl` a `/assistant` en 7.1; capturas quedan como artefacto |
| filtros `-t` de Vitest por requisito | alternancia de títulos literales de escenario |
| sin pin sobre `auth:sanctum` de la lista | M14 en design D6/D10 y tarea 1.5, con control «Modelos con Ollama disponible» que PASA con la mutación aplicada |
| tareas sin decisión de design citada | cada tarea cita `design Dn`; D2, D3, D6, D9 referenciadas |
| referencias a una tarea 3.9 inexistente | apuntan a 3.5 |

| Barrido | Comando (desde la carpeta del cambio) | Resultado |
|---|---|---|
| Validación | `openspec validate add-assistant-model-selector --strict` | válido |
| Escenarios sin cita | `python3 -I`, títulos contra citas «…» | ninguno; control: título inventado aparece como faltante |
| Tareas sin cita, sin `Verifica` o sin `design Dn` | `python3 -I` sobre bloques `- [ ]` | ninguna en los tres casos |
| Filtros `-t`/`--filter` no literales | `python3 -I`, cada alternativa contra títulos de escenario | ninguno (marcadores `<título del escenario>` excluidos) |
| Ancla de transporte | patrón de `CYCLE-TIERS.md` sobre `specs/` \| `/usr/bin/grep -vc '\[ancla:'` | 0 sin ancla; control: 21 hits sin filtro |

## 2026-10-09 — Orchestrator: GATE 1

- GATE 1: preaprobado (ROADMAP 2026-10-09, «Este ajuste es en autopiloto hasta terminar»), condiciones 1-4 OK, tier A.
  1. Alcance = fila S15 (lista de modelos, `model` validado en `ask`, selector persistido en el navegador, `mock` por
     defecto). 2. spec-validator VALID en la segunda pasada; ancla sin hits sueltos. 3. Tier A = columna.
  4. RN-10 intacta (sin datos de pacientes hacia el modelo; defensa contra inyección y permisos de herramientas sin cambios);
     ningún ADR se debilita.
- Orden de apply: backend → tipos de la SPA → frontend → humo y recorrido. Presupuesto: 3 corridas completas.

## 2026-10-09 — backend-implementer: tareas 0, 1, 2, 3 y 4

| Fase | Comando | Resultado |
|---|---|---|
| Base Pest (corrida completa 1 de 3) | `… -e AI_PROVIDER=mock api-tools vendor/bin/pest` | 1039 passed |
| Base Vitest | `cd software/web && npx vitest run` | 338 passed, 32 archivos |
| Base contrato | `composer openapi` + `git diff --exit-code` en el anfitrión; `api:types:check` | sin deriva |
| Rojo de 1.2 | `… vendor/bin/pest tests/Feature/Assistant/AssistantModelsEndpointTest.php` | falla por 404 en todas, sin error de sintaxis |
| Asistente + Arch (2.3) | `… vendor/bin/pest tests/Feature/Assistant tests/Arch` | 246 passed; `AssistantArchTest.php` sin diff |
| `assistant:eval` simulado | `… -e AI_PROVIDER=mock api-tools php artisan assistant:eval` | 24/24, exit 0 |
| Cierre backend (corrida completa 2 de 3) | Pint `--test`, Larastan `--memory-limit=1G`, Pest completo | PASS; No errors; 1071 passed |
| Pins backend | M1–M9, M14 | todos FALLAN aplicados y PASAN restaurados (`verification.md` § 3) |

Commits: `3f5417a` lista de modelos; `a09eb72` modelo elegido, validación y log; OpenAPI; tipos de la SPA.

Decisiones y desvíos:
- Clave de caché con `hash('sha256', …)` en lugar de `sha1` (design D3): el preset `security` de Pest prohíbe `sha1`;
  mismo efecto (cambiar la URL invalida la clave).
- `composer openapi:check` sale 129 dentro de `api-tools`: el contenedor no tiene `.git`. Se verifica con
  `composer openapi` en el contenedor y `git diff --exit-code` en el anfitrión, como hace CI en su checkout.
- No se agrega `AskAssistantBody` (tarea 4.1): `AskAssistantRequest` ya es `BodyOf<'/assistant/ask','post'>` y ahora
  lleva `model?: string`; un segundo alias idéntico sería duplicado. Se agrega `AssistantModel`.
- `ModelChoice::ollama()` exige el alfabeto seguro también para `OLLAMA_MODEL`: un valor fuera de él deja el proveedor
  por defecto en `unavailable` (503), nunca un nombre sin validar hacia Ollama.
- Las pruebas nuevas fijan `assistant.provider` y `assistant.ollama.model` por config: `software/.env` fija
  `OLLAMA_MODEL` y se filtra a `api-tools` igual que `AI_PROVIDER` (misma causa que D-auv-9).
- Pruebas extra sin escenario propio: alfabeto seguro y orden por nombre (design D1, D2); control de `Http::recorded()`
  para el barrido de destinos; dataset `null` en «Modelo con tipo inválido» y `models` no lista en «Ollama con error».
- Para la SPA: `GET /api/assistant/models` → `{"data":[{"id","provider","name"}]}`, `mock` primero; `data.model` en
  la respuesta de `ask` (`mock` | `ollama:<name>`); 422 `errors.model` = `["El modelo elegido no está disponible."]`.

Deuda observada (para el Orchestrator): la fuga de `OLLAMA_MODEL` de `software/.env` a `api-tools` acompaña a la de
`AI_PROVIDER` ya registrada; fijar ambos en `phpunit.xml` cerraría las dos.

## 2026-10-09 — devops-implementer: tarea 7.2

- `assistant-smoke.sh` comprueba lista sin sesión (401), lista con `mock` primero, `ask` con `model=mock` →
  `data.model == "mock"` y modelo fuera de la lista → 422 `errors.model`. No exige Ollama: CI (`AI_PROVIDER=mock`)
  pasa igual. `SMOKE_EXPECT_OLLAMA_MODEL` y `SMOKE_EXPECT_MOCK_ONLY` quedan opcionales para 7.3 y 7.4.
- Stack reconstruido (`api`, `web`) con `--wait`: todos `Healthy`. Humo del asistente exit 0 (19/0); humo completo
  `Humo VERDE`; control negativo de las variables opcionales exit 1 (2 fallas esperadas). Tablas en
  `verification.md` § 6.
- Desvío: el humo pasa de 5 a 7 preguntas por corrida (sigue bajo el límite de 20 por minuto).

## 2026-10-09 — frontend-implementer: tareas 5 y 6

| Fase | Comando | Resultado |
|---|---|---|
| Delta del asistente | `cd software/web && npx vitest run src/features/assistant` | 65 passed, 3 archivos |
| Pins SPA | M10–M13 | todos FALLAN aplicados y PASAN restaurados (`verification.md` § 8) |
| Cierre frontend (corrida completa Vitest 2 del cambio: base + cierre) | `npm run lint && npm run typecheck && npx vitest run` | lint y `tsc -b` exit 0; 33 archivos, 0 fallidos (caché de resultados de esa corrida); 360 por derivación (`verification.md` § 9) |

Commits: `60a6e7f` selector, preferencia e historial; `785c250` guía y README.

Decisiones y desvíos:
- `resolveModel(ids, stored, chosen)` solo con la lista recibida; mientras carga o si falla, `mock` sin aviso de
  preferencia perdida (el aviso de lista fallida tiene prioridad). Aviso de preferencia también para un valor
  guardado manipulado no vacío: nunca se pinta el valor.
- Invalidación de `['assistant','models']` ante 422 con `errors.model` en `onError` de `useAskAssistant`.
- `chooseModel` ignora cambios con la pregunta en curso además de `disabled` (spec «Selector durante una consulta»).
- Prueba S8 «Pregunta sobre un paciente» (texto completo de la entrada) gana `Respondió: Simulado (sin red)`: consecuencia
  del requisito «Modelo que respondió». «Médico abre el asistente desde el menú» espera "Preguntar" habilitado con
  `waitFor` (misma aserción; el botón espera la lista).
- La salida de la corrida de cierre quedó cortada por `tail`; no se repitió para no pasar el tope de corridas. Evidencia:
  33 entornos jsdom en la salida y `results.json` de Vitest sin archivos fallidos; el conteo exacto lo da la
  confirmación del auditor.
- Captura de la pantalla: no tomada aquí (stack sin reconstruir; 7.1 y 7.3 son del Orchestrator).
- Árbol de los pins: `openspec/DEBT.md` y `software/docker/smoke/assistant-smoke.sh` estaban modificados por otros
  agentes; `software/web` limpio antes y después de cada pin.
