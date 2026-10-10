# Tasks

Tier B. Dueño de todas las tareas: `devops-implementer`. Sin código de producto: no cambia la API, `openapi.json`
ni la SPA, así que no hay prueba de feature HTTP nueva; la colección misma es la prueba HTTP real.
`[MUT]` declarados: M1–M5, solo donde una implementación perezosa pasaría (guarda que nunca falla, aserción que
solo mira el estado, exclusión de cabecera que no excluye). Suite: corridas delta por carpeta y **una** corrida
de cierre completa (grupo 5); Pest y Vitest no se tocan y los corre el CI.

Prefijos de escenario: AC = delta `api-collection`, DP = delta `delivery-pipeline`, CI = delta `ci-pipeline`,
PD = delta `project-documentation`.

Archivos fijos (los usan los comandos): colección `software/docs/postman/dispensart.postman_collection.json`,
entorno `software/docs/postman/local.postman_environment.json`, guarda
`software/docs/postman/check-coverage.sh` (bash + jq), guía `software/docs/postman/README.md`. Carpetas de la
colección, en este orden y con estos nombres exactos (los usa `--folder`): `00 Salud`, `01 Sesión por rol`,
`02 Dispensación FEFO e idempotencia`, `03 Control especial`, `04 Traslado con discrepancia`,
`05 Alertas, existencias y kardex`, `06 Asistente`, `07 Catálogo y administración`, `08 Permisos denegados`.
**Cada carpeta es autónoma**: abre su sesión, crea sus datos de la corrida (prescripción, códigos, claves) y
cierra su sesión; así corre sola con `--folder` y repetida.

**Arnés** (bash, desde la raíz del repo). `NV` = versión exacta de newman, confirmada con `npm view newman
version` al empezar (6.2.3 al proponer) y la misma en README, guía y `ci.yml`. Si newman no corre con el Node
del anfitrión, se usa el de `software/web/.nvmrc` y se anota en `journal.md`.

```bash
P=software/docs/postman; C=$P/dispensart.postman_collection.json; E=$P/local.postman_environment.json
NV=6.2.3
nm() { npx --yes "newman@$NV" run "$1" -e "$E" "${@:2}"; }          # nm <colección> [opciones de newman]
guard() { bash "$P/check-coverage.sh" "$1"; }                         # guard <colección>
folder() { nm "$2" --folder "$1"; }                                   # folder <nombre> <colección>
up() { docker compose -f software/compose.yaml down -v && docker compose -f software/compose.yaml up --build -d --wait; }
M=openspec/changes/add-postman-collection/mutants
# mut <Mn> <función> [args]: copia mutada por el filtro jq $M/<Mn>.jq; la función debe FALLAR sobre la copia y PASAR
# sobre el original; el árbol de software/ queda intacto. Sale 0 solo si ocurre todo. La colección va como último arg.
mut() { local n="$1" f t; shift; f="$M/$n.jq"; t="$(mktemp -d)/$n.json"
  jq -f "$f" "$C" > "$t" || return 2
  "$@" "$t" && return 3
  "$@" "$C" || return 4
  git diff --quiet -- software || return 5; }
gate() { test -z "$(git status --porcelain -- software .github README.md)"; }   # tras cada commit
```

Cada grupo cierra con un commit en español cuyo asunto nombra el objetivo de la prueba (Git law) y `gate` sale 0.

## 1. Guarda de cobertura del contrato

- [x] 1.1 Escribir `check-coverage.sh <colección>`: operaciones de `software/api/openapi.json` como `MÉTODO ruta`
  (`/health` y `/ready` en la raíz, el resto bajo `/api`, parámetros de ruta normalizados), peticiones de la
  colección recorriendo carpetas anidadas (sin `{{baseUrl}}`, segmentos `{{var}}` o `:var` normalizados), lista
  explícita con `GET /sanctum/csrf-cookie`; imprime operaciones cubiertas; sale distinto de 0 nombrando cada
  operación sin petición y cada petición fuera del contrato. Comentarios en español. Cubre AC › «Operación sin
  petición», «Petición fuera del contrato». Verifica: con una colección v2.1 vacía en `/tmp`, `guard /tmp/vacia.json`
  sale distinto de 0 y lista todas las operaciones del contrato (control positivo de que lee `openapi.json`).

## 2. Colección y entorno

- [x] 2.1 Entorno local (`baseUrl`, correos de los 5 usuarios semilla, `password` = `dispensart-dev-only`) y
  esqueleto de colección v2.1 con el script previo de colección (cookie CSRF si falta, `X-XSRF-TOKEN` decodificado
  en escrituras, `Origin` y `Referer`, exclusión por petición de cada cabecera) y carpetas `00 Salud` y
  `01 Sesión por rol`. Cubre AC › «Colección y entorno ejecutables», «Primera escritura sin cookie previa», «Token
  con caracteres codificados», «Recorrido de sesión por rol», «Usuario actual tras cerrar sesión», «Salud y
  disponibilidad con el stack sano», «Base de datos detenida», «Salud fuera del prefijo /api». Verifica: tras `up`,
  `folder "00 Salud" "$C"` y `folder "01 Sesión por rol" "$C"` salen 0; con `docker compose -f software/compose.yaml
  stop db`, `folder "00 Salud" "$C"` sale distinto de 0 con la aserción de `/ready` fallida; luego `docker compose -f
  software/compose.yaml start db` y `/ready` vuelve a 200.
- [x] 2.2 Carpeta `02 Dispensación FEFO e idempotencia`: el `medico` crea la prescripción de la corrida
  (`valid_until` calculado), el `auxiliar` busca el paciente semilla `9999010001`, resuelve `MED-004` y `BH` por
  código, vista previa, instantánea del kardex, confirmación con `Idempotency-Key` de la corrida (patrón
  `^[A-Za-z0-9_-]{16,128}$`), instantánea, repetición, instantánea y envío sin clave. Cubre AC › «Vista previa en
  orden FEFO sin lote vencido», «Dispensación con un movimiento nuevo en el kardex», «Repetición idempotente»,
  «Dispensación sin clave». Verifica: `folder "02 Dispensación FEFO e idempotencia" "$C"` sale 0 dos veces seguidas.
- [x] 2.3 Carpeta `03 Control especial` con su propia prescripción de `MED-006` y la bodega elegida por
  existencia vigente en `GET /api/stock`. Cubre AC › «Vista previa exige autorización», «Control especial sin
  autorizador», «Control especial coautorizado por el regente». Verifica: `folder "03 Control especial" "$C"` sale 0.
- [x] 2.4 Carpeta `04 Traslado con discrepancia`: lote vigente con existencia en dos bodegas elegido de
  `GET /api/stock` como en `software/docker/smoke/transfer-smoke.sh`, recorrido completo, autoaprobación del
  regente y anulación. Cubre AC › «Recorrido hasta la recepción parcial», «Autoaprobación del regente», «Despachar
  un traslado ya recibido», «Resolución de la discrepancia», «Consulta del traslado». Verifica:
  `folder "04 Traslado con discrepancia" "$C"` sale 0 dos veces seguidas.
- [x] 2.5 Carpeta `05 Alertas, existencias y kardex`: alertas, existencia semilla leída antes del ajuste de -1,
  kardex con el ajuste y ajuste excesivo. Cubre AC › «Alertas con el lote vencido sembrado», «Ajuste con movimiento
  en el kardex», «Ajuste mayor que la existencia». Verifica: `folder "05 Alertas, existencias y kardex" "$C"` sale 0.
- [x] 2.6 Carpeta `06 Asistente`. Cubre AC › «Lista de modelos con mock primero», «Pregunta con el modelo mock»,
  «Modelo fuera de la lista». Verifica: `folder "06 Asistente" "$C"` sale 0.
- [x] 2.7 Carpeta `07 Catálogo y administración` con sufijo único de la corrida en correo y códigos (`code` de
  bodega ≤ 20 caracteres). Cubre AC › «Altas y ediciones del admin», «Código de bodega duplicado». Verifica:
  `folder "07 Catálogo y administración" "$C"` sale 0 dos veces seguidas.
- [x] 2.8 Carpeta `08 Permisos denegados`, con peticiones excluidas de cabeceras por el mecanismo de 2.1. Cubre AC
  › «Escritura excluida del token», «Login excluido de Origin y Referer», «Sin sesión», «Rol sin la capacidad»,
  «CSRF antes que permisos». Verifica: `folder "08 Permisos denegados" "$C"` sale 0.
- [x] 2.9 Guarda verde y contraseña del entorno. Cubre AC › «Colección completa», «Contraseña sobrescrita al
  ejecutar», «Contraseña equivocada», «Sin credenciales reales». Verifica: `guard "$C"` sale 0; con el entorno copiado
  a `/tmp/e.json` y `password` vacía, `npx --yes "newman@$NV" run "$C" -e /tmp/e.json --env-var
  password=dispensart-dev-only` sale 0; `nm "$C" --env-var password=no-es-la-clave` sale distinto de 0 y el primer
  login muestra 422; `/usr/bin/grep -rnEi 'password|token|cookie|app_key' software/docs/postman/` revisado fila a
  fila en `verification.md`, con control positivo: el patrón encuentra la línea de `password` del entorno.
- [x] 2.10 [MUT] Pines. Primero, con el árbol de 2.1–2.9 ya confirmado en git, escribir los filtros jq
  `$M/M0.jq` … `$M/M5.jq` (uno por mutante, descritos abajo; `M0.jq` = `.`). Después, en este orden: autocontrol del
  arnés `mut M0 guard` sale **3** (el mutante identidad no falla: `mut` distingue); luego cada `mut` de M1–M5 sale 0:
  M1 quita la petición de `POST /api/transfers/{transfer}/void`: `mut M1 guard` (AC › «Operación sin petición»).
  M2 agrega una petición a `GET /api/no-existe`: `mut M2 guard` (AC › «Petición fuera del contrato»).
  M3 cambia la `Idempotency-Key` de la repetición por otra: `mut M3 folder "02 Dispensación FEFO e idempotencia"`
  (AC › «Repetición idempotente», «Aserción fallida»).
  M4 quita la exclusión de `X-XSRF-TOKEN` de la petición del `medico` en la carpeta 08:
  `mut M4 folder "08 Permisos denegados"` (AC › «CSRF antes que permisos»).
  M5 cambia en la aserción de la vista previa el lote vencido esperado fuera de las asignaciones por el vigente
  `L-LOS-2403`: `mut M5 folder "02 Dispensación FEFO e idempotencia"` (AC › «Vista previa en orden FEFO sin lote
  vencido»).
  Control positivo de M1–M2: `guard "$C"` sale 0 tras cada `mut`. Verifica: filas M1–M5 en `verification.md`.

## 3. CI

- [x] 3.1 Paso nuevo en el trabajo `backend` de `.github/workflows/ci.yml`, después del lint de Redocly:
  `bash software/docs/postman/check-coverage.sh software/docs/postman/dispensart.postman_collection.json`, sin
  permisos nuevos ni `secrets.`. Cubre CI › «Contrato cubierto», «Operación nueva sin petición» (la lógica la pinea
  M1), «Pull request desde un fork con la guarda». Verifica: `awk '/^  backend:/,/^  frontend:/'
  .github/workflows/ci.yml | /usr/bin/grep -n 'check-coverage.sh'` da una línea posterior a `openapi:lint`; el mismo
  bloque piped a `/usr/bin/grep -c 'secrets\.'` da 0, con control positivo: el patrón da ≥ 1 sobre el bloque `staging`.
- [x] 3.2 Paso nuevo en el trabajo `staging`, después de «Humo del stack y de dominio» y antes de «Logs del stack»:
  Node por `actions/setup-node` con el mismo SHA fijado del archivo si el runner lo requiere, y `npx --yes
  newman@<NV> run` sobre la colección y el entorno local. Sin `continue-on-error`, sin `|| true`, sin `secrets.`.
  Cubre DP › «Colección verde en staging» (la cierra 5.3), «Aserción fallida en staging», «Humos fallidos», «Fallo
  no silenciado», «Versión fijada y sin secretos». Verifica: `awk '/^  staging:/,/^  production:/'
  .github/workflows/ci.yml` muestra el paso de newman entre el de humos y el de logs; `/usr/bin/grep -nE
  'continue-on-error|\|\| *true'` sobre ese bloque da 0, con control positivo: el mismo patrón da ≥ 1 sobre una copia
  en `/tmp` con `|| true` agregado al paso.

## 4. Documentación

- [x] 4.1 Guía `software/docs/postman/README.md` (importar en Postman, entorno, sesión resuelta por el script,
  contraseña solo de desarrollo y cómo sobrescribirla con `--env-var`, comando newman con `NV`, guarda) y en
  `README.md` § «Documentación de la API» la ruta de la colección y el mismo comando. Cubre PD › «Rutas de la guía
  existentes», «Contraseña marcada como solo de desarrollo», «Versión desalineada». Verifica: cada ruta relativa
  citada en la guía y en esa sección existe (`test -e` por ruta, tabla en `verification.md`);
  `/usr/bin/grep -hoE 'newman@[0-9]+\.[0-9]+\.[0-9]+' README.md software/docs/postman/README.md
  .github/workflows/ci.yml | sort -u` da una sola línea, con control positivo: `/usr/bin/grep -lE 'newman@[0-9]'`
  sobre los mismos tres archivos lista los tres.

## 5. Cierre

- [x] 5.1 Corrida de cierre: `up`; el comando de la guía, copiado literalmente, sale 0; `nm "$C"` de nuevo sale 0
  (repetible); `bash software/docker/smoke.sh` y luego `nm "$C"` salen 0 (orden del staging). Registrar en
  `verification.md` por corrida: peticiones, aserciones, aserciones fallidas, código de salida, con el comando. Cubre
  AC › «Corrida verde», «Corrida repetida sobre la misma base», PD › «Comando documentado ejecutable».
- [x] 5.2 `verification.md` en tablas: § 0 reparto de líneas (producto, prueba, registro); escenario → carpeta y
  petición; cláusula de estado → ancla; filas M0–M5; barridos con `/usr/bin/grep` y su control positivo. Cubre AC ›
  «Sin credenciales reales» (fila del barrido), «Aserción fallida» (filas M3–M5), DP › «Fallo no silenciado» (fila
  del barrido de 3.2). Verifica: cada escenario de los cuatro deltas tiene fila, comprobado con
  `/usr/bin/grep -h '^#### Scenario:' specs/*/spec.md | sed 's/#### Scenario: //'` contra la columna de escenarios.
- [x] 5.3 Push a `dev` y run del CI en verde: el paso de la guarda en `backend` y el de newman en `staging` pasan.
  Verifica: `gh run list --branch dev --workflow CI -L 1 --json databaseId,conclusion` da `success`; `gh run view
  <id> --log | /usr/bin/grep -nE 'check-coverage|newman'` muestra ambos pasos; id y URL del run en `verification.md`.
  Cubre DP › «Colección verde en staging», CI › «Contrato cubierto».

## Seguimiento del flujo

- GATE 2 por `final-auditor` (auditoría delta sobre el diff, Tier B).
- Archivo después de S17 (`fix-test-env-isolation`), según `ROADMAP.md` § Preaprobación de GATE 1 para S16–S17;
  la capacidad nueva `api-collection` recibe su `## Purpose` del delta.
