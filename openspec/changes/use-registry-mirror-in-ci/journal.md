# Journal — use-registry-mirror-in-ci (S11)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-09 — spec-engineer: proposal, delta y tareas borrador

- Producido: `proposal.md`; delta `specs/ci-pipeline/spec.md` (2 requisitos ADDED: «Imágenes de Docker Hub desde
  el espejo en CI», «Guarda contra descargas directas de Docker Hub en CI»); `tasks.md` (`[MUT]` M1–M4, solo en la
  guarda). Comandos y conteos:

  | Comando | Resultado |
  |---|---|
  | `openspec validate use-registry-mirror-in-ci --strict` | válido |
  | barrido de ancla de transporte (`CYCLE-TIERS.md`) sobre `specs/` | 0 hits; control: mismo patrón sobre `archive/2026-10-09-add-sensitive-operation-audit/specs/audit-trail/spec.md` = 3 |
  | `/usr/bin/grep -c '^#### Scenario' specs/ci-pipeline/spec.md` | 13 |
  | `/usr/bin/grep -c '^- \[ \]' tasks.md` | 14 |

- Tier propuesto: **B**. Disparador: «a CI job» (tabla de `CYCLE-TIERS.md`), lógica real en la guarda, sin
  disparador A. Punto límite revisado: el servicio PostgreSQL de Pest cambia de origen, no de contenido (mismo
  digest, verificado en 4.2); no toca aislamiento, orden ni reporte del arnés de pruebas, así que no puede fabricar
  un verde falso de RN-01..RN-11. Sin `design.md` (ni contrato, ni migración, ni permisos, ni concurrencia).
  Orchestrator decide si `mirror.gcr.io` cuenta como «nueva dependencia» para pedir architect; recomendación: no.
- Hallazgos del código: además de las cuatro imágenes base, el CI descarga de Docker Hub de forma implícita el
  frontend de BuildKit (`# syntax=docker/dockerfile:1` en ambos Dockerfiles) y la imagen del constructor que crea
  `docker/setup-buildx-action` (`moby/buildkit:buildx-stable-1`). Ambos sirven en `mirror.gcr.io` (HTTP 200 el
  2026-10-09) y entran al alcance como «cualquier otro pull de Docker Hub en CI». El espejo responde también sin el
  prefijo `library/`. Digest de `postgres:16.15-alpine3.24` en el espejo: `sha256:721873c34ceb…`.
- `runtime-environment` › «Secretos fuera del repositorio» exige que `.env.example` documente toda variable que lee
  el stack: `DB_IMAGE` se agrega (tarea 1.2), sin delta a esa capacidad.
- `ci-pipeline` › «Sin secretos ni publicación» busca registros en los PASOS de calidad: la guarda va en un script
  y el paso solo lo invoca, así el escenario vivo no cambia. La imagen del servicio vive en `services`, no en pasos.
- Supuestos (5, en la propuesta): disponibilidad del espejo; sin caída automática a Docker Hub; ubicación de la
  guarda (`software/docker/`, trabajo frontend); mecanismo de BuildKit a elección; sin `design.md`.
- Riesgos: (1) fallo de caché del espejo para una etiqueta nueva o poco usada → el pull falla, sin respaldo
  automático (escenario «Espejo sin la etiqueta pedida»; vuelta documentada en 4.1); (2) `mirror.gcr.io` no tiene
  SLA; (3) precedencia de `BUILDKIT_SYNTAX` sobre `# syntax=` no verificada aquí — el log de 5.1/5.2 decide; (4) la
  caché `type=gha` del build puede fallar una vez por cambio de referencia `FROM`.
- Preguntas abiertas: ninguna bloqueante. Alternativa para el implementador: configuración de espejo de buildkitd
  (`[registry."docker.io"] mirrors`) cubre frontend y bases sin argumentos, pero cae en silencio a Docker Hub y la
  guarda no la ve; los argumentos explícitos que pidió el usuario sí son verificables.
- Bloqueos: ninguno. No se tocó código ni se hizo commit.

## 2026-10-09 — architect: design.md y tareas refinadas

- Producido: `design.md` (D1–D4); `tasks.md` reescrito; delta `ci-pipeline` ajustado (escenario nuevo «Constructor de
  BuildKit sin redirigir»; texto del requisito de la guarda acortado, aviso de longitud de `--strict`).
- Decisiones: D1 `BUILDKIT_SYNTAX=mirror.gcr.io/docker/dockerfile:1` como build-arg (rechazadas: reescribir
  `# syntax=`, quitar la línea, espejo en `buildkitd.toml`); D2 `driver-opts: image=mirror.gcr.io/moby/buildkit:v0.33.1`
  (hoy mismo digest que `buildx-stable-1`, `sha256:cec9f139…`, consultado en `mirror.gcr.io/v2/moby/buildkit/tags/list`;
  rechazada la etiqueta móvil); D3 guarda bash+awk sin `yq`, sobrescritura buscada en el mismo paso que declara `file:`,
  exit 0/1/2, falla cerrada; D4 local intacto, sin respaldo automático, vuelta de emergencia por commit (rechazado
  interruptor `ALLOW_DOCKERHUB`).
- Tareas: harness de funciones eliminado, cada `[MUT]` autocontenido (M1–M7; M4 = D1, M5–M6 = D2, M7 = variable de
  compose no fijada); puerta commit + `git status --porcelain` (5.1) antes de los pines; control de 3.4 ejecutable
  contra `df7f657`; 4.1 cita solo «Vuelta documentada a Docker Hub»; 4.3 nueva ejerce «Espejo sin la etiqueta pedida»;
  6.3 cita escenarios; encabezado «Seguimiento del flujo».

  | Comando | Resultado |
  |---|---|
  | `openspec validate use-registry-mirror-in-ci --strict` | válido |
  | barrido de ancla de transporte sobre `specs/` | 0 hits (sin cambio de cláusulas THEN de estado) |
  | `/usr/bin/grep -c '^#### Scenario' specs/ci-pipeline/spec.md` | 14 |
  | `/usr/bin/grep -c '^- \[ \]' tasks.md` | 16 |
  | `/usr/bin/grep -cE '\$G\|mutroot\|mut ' tasks.md` | 0 |

- Riesgos: (1) espejo sin SLA o sin etiqueta → run rojo, mitigado por D4 y 4.3; (2) precedencia de `BUILDKIT_SYNTAX`
  no observada aún en este repo → decide el barrido de 6.2 con control; (3) guarda por texto frágil ante formas YAML
  no previstas → falla cerrada (exit 2) y M1–M7.
- Pendiente para spec-engineer: `proposal.md` Supuestos 4–5 («mecanismo a elección», «sin design.md») quedaron
  superados por D1–D2 y este design; corrección de redacción, no reabre nada.
- No se tocó código ni se hizo commit.

## 2026-10-09 — Orchestrator: GATE 1 en pausa

- shard = auv
- spec-validator VALID (segunda pasada). Tier B, con `design.md` por costura nueva (guardia y BuildKit).
- GATE 1: NO aprobado. Respuesta del usuario: «No apruebo S11. Podemos dejarlo en pausa mientras aplicamos otros
  ajustes y lotes de tareas.» No se construye nada hasta una aprobación explícita.
- Contexto: el CI de `main` en `df7f657` (run 37999814571) pasó sin fallar al descargar de Docker Hub.
- Prosa pendiente, no bloqueante: supuestos 4–5 de `proposal.md` desactualizados frente a D1–D2.
