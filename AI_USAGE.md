# Uso de IA en Dispensart

El código, las pruebas y la documentación se produjeron con asistencia de IA, bajo un proceso con compuertas humanas.
Las decisiones de fondo (stack, ADR, alcance, tier de cada cambio, fusión a `main`, aprobación de producción)
son del autor. Cada hecho de este documento cita el registro donde consta (`journal.md` o `verification.md`
del cambio, con número de línea).

## Herramientas

| Herramienta | Uso |
|---|---|
| Claude Code (modelos Opus; Haiku para validación) | Único asistente. El hilo principal actúa como **Orchestrator**: no escribe código ni specs, delega en agentes declarados y lleva compuertas y bitácora (`CLAUDE.md`). |
| Agentes declarados en `.claude/agents/` | spec-engineer, architect, backend-implementer, frontend-implementer, devops-implementer, spec-validator, final-auditor. Cada uno con alcance de archivos y leyes propias. |
| OpenSpec (desarrollo guiado por especificaciones) | Cada tajada S0–S8 es un cambio: propuesta, specs delta con escenarios, diseño, tareas con comando de verificación, bitácora y matriz de verificación. Archivados en `openspec/changes/archive/`. |
| Skills en `.claude/skills/` | Guías vendorizadas (Laravel, PostgreSQL, diseño de API, GitHub Actions, shadcn, accesibilidad, TDD, seguridad…). Origen, hash y licencia en `THIRD_PARTY_NOTICES.md` y `skills-lock.json`. Si una skill contradice un ADR, gana el repositorio. |
| Comandos en `.claude/commands/` | `/opsx:*` (ciclo OpenSpec), `/autopilot` (encadena tajadas del roadmap), `/nitro` (cambios pequeños con los roles en línea). |

Proveedor de IA dentro del producto: ninguno de pago. El asistente usa `mock` por defecto u Ollama local
(`software/docs/asistente.md`).

## Tareas por agente

| Agente | Qué hizo |
|---|---|
| Orchestrator | Abrir cada cambio, asignar tier, delegar por bloques, contar corridas de suite (máx. 3 por cambio), registrar GATE 1 y GATE 2, conciliar `openspec/DEBT.md` |
| spec-engineer | `proposal.md`, specs delta con escenarios `WHEN`/`THEN` y borrador de `tasks.md` |
| architect | `design.md` (decisiones con alternativas rechazadas), matriz de mutantes `[MUT]` y refinamiento de tareas, cuando el cambio toca contrato, datos, seguridad o concurrencia |
| backend-implementer | `software/api`: modelo, migraciones con `CHECK` y disparadores, FEFO, bloqueo, idempotencia, traslados, kardex, asistente; pruebas Pest |
| frontend-implementer | `software/web`: pantallas de dispensación, traslados, inventario y kardex; pruebas Vitest |
| devops-implementer | Dockerfiles, `software/compose.yaml`, `.github/workflows/ci.yml`, humos, `software/docs/deployment.md`, este documento y el README |
| spec-validator | Validación estructural de cada artefacto (`openspec validate --strict`, anclas, comandos ejecutables) |
| final-auditor | Auditoría de solo lectura al cerrar cada cambio; único que emite `APPROVED` |

## Ejemplos aceptados

| Qué propuso la IA | Por qué se aceptó | Dónde consta |
|---|---|---|
| El final-auditor de S3 marcó como **mayor** que el `auditor` podía buscar pacientes por prefijo de documento o fragmento de nombre: un oráculo para reconstruir los datos enmascarados (RN-10). | Hallazgo de privacidad real. Se corrigió: sin `viewIdentifiable` solo coincide el documento completo exacto, con prueba y mutante M15 que falla sin el arreglo; la re-auditoría dio `APPROVED`. | `openspec/changes/archive/2026-10-08-add-dispensation/journal.md` línea 212 (y GATE 2 en la línea 230) |
| El spec-validator de S5 exigió que los mutantes `[MUT]` fueran comandos ejecutables, no prosa. El architect definió un arnés (`mut` aplica el parche, exige FALLA, revierte, exige PASA) y fijó M22 y M23; M23 usa mínimo 1 y no 0 porque con 0 el mutante es equivalente y sobreviviría. | Convierte "la prueba protege la regla" en algo comprobable por cualquiera, y evita un mutante que nunca podría fallar. | `openspec/changes/archive/2026-10-08-add-alerts/journal.md` líneas 95, 104 y 105 |

## Ejemplos rechazados o corregidos

| Qué pasó | Corrección y por qué | Dónde consta |
|---|---|---|
| **Corregido.** En S7 el mutante M4 sobrevivió: la regla de arquitectura `arch()->expect([ns1, ns2])->not->toUse(...)` de Pest pasa con un arreglo de objetivos aunque haya uso. Era un falso verde escrito por la IA. | Se separó una regla por espacio de nombres y M4 pasó a fallar. Sin el mutante, la prueba que "aísla al asistente de los pacientes" no protegía nada. | `openspec/changes/archive/2026-10-08-add-inventory-assistant/verification.md` línea 178; `openspec/changes/archive/2026-10-08-add-inventory-assistant/journal.md` línea 88 |
| **Corregido.** En S5 los primeros parches de M12 y M13 tenían un error de sintaxis PHP: la "FALLA" venía de un `ParseError`, no de la regla. | El control positivo (`ctl M12` exige PASA con el mutante) lo destapó; se rehicieron los parches, se pasó `php -l` y se repitió la cadena. Un mutante que no compila no prueba nada. | `openspec/changes/archive/2026-10-08-add-alerts/journal.md` línea 144; `openspec/changes/archive/2026-10-08-add-alerts/verification.md` línea 133 |
| **Rechazado.** El spec-validator objetó que tareas de cimiento con comando ejecutable no tenían escenario propio. | Se descartó por la regla 12 ("la redacción no es un defecto"): no cambiaba código, pruebas ni decisiones, y contradecía la convención aceptada en S0–S7. | `openspec/changes/add-delivery-pipeline/journal.md` línea 60; otro caso en `openspec/changes/archive/2026-10-08-add-alerts/journal.md` línea 121 |
| **Rechazado por el autor.** El Orchestrator rodeó un fallo de registro de agentes con agentes `general-purpose` que leían el archivo de rol. | El autor lo prohibió: solo agentes declarados y skills instaladas, para que el alcance y las leyes de cada rol se apliquen de verdad. Se detuvo el trabajo a medias y se reinició la sesión. | `openspec/changes/archive/2026-10-07-add-project-skeleton/journal.md` líneas 68 y 73 |
| **Corregido por el autor.** S5 (alertas) estaba planeado como tier B; la migración con `CHECK` de mínimos es disparador de tier A. | El autor decidió tier A completo: diseño, matriz `[MUT]` y auditoría completa. La IA se detuvo a preguntar en vez de decidir el tier sola. | `openspec/changes/archive/2026-10-08-add-alerts/journal.md` línea 36 |

## Supervisión humana

| Compuerta | Quién decide | Qué exige |
|---|---|---|
| GATE 1 (propuesta) | El autor. Para el lote S0–S8 la preaprobó con cuatro condiciones; si una falla, la IA se detiene y pregunta (caso del tier de S5) | Alcance exacto, validación estricta, tier correcto, ningún ADR ni regla RN debilitados (`openspec/ROADMAP.md`) |
| GATE 2 (cierre) | final-auditor emite `APPROVED`; nunca está preaprobada | Todas las tareas cerradas, matrices de verificación completas, hallazgos corregidos con evidencia |
| Rama `main` | Solo el autor fusiona | Los agentes publican en `dev` o ramas `feat/*`, sin force-push |
| Producción | El autor aprueba en el entorno `production` de GitHub | Revisor requerido; la guarda del pipeline falla si el entorno no lo exige (`software/docs/deployment.md`) |

Las decisiones congeladas viven en `docs/adr/` y solo el autor las reabre. La deuda la registra el
Orchestrator en `openspec/DEBT.md` y ningún cambio con deuda mayor abierta se archiva.
