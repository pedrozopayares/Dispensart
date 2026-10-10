# Verification — add-postman-collection (S16, Tier B)

Árbol: `dev` en `810b988` (último commit de producto del cambio). Base: `f8e9f39`. MUT declarados: 5 (M1–M5) más
autocontrol M0; entregados: 5 + M0. newman `6.2.3` (`npm view newman version` al empezar), Node del anfitrión
`v26.5.0` (corre sin caer a `.nvmrc`). `C` = `software/docs/postman/dispensart.postman_collection.json`.

## 0. Reparto de líneas

Fuente: `git diff --numstat f8e9f39 810b988` sobre los archivos de este cambio (los de S17, en commits paralelos de
otro implementador, quedan fuera).

| Clase | Archivos | Líneas |
|---|---|---|
| Producto (API, SPA) | ninguno: sin cambio de API, `openapi.json` ni SPA | 0 |
| Prueba (HTTP ejecutable) | `software/docs/postman/dispensart.postman_collection.json` | +4822 |
| Prueba (entorno) | `software/docs/postman/local.postman_environment.json` | +13 |
| Prueba (guarda) | `software/docs/postman/check-coverage.sh` | +67 |
| Prueba (CI) | `.github/workflows/ci.yml` (paso de guarda en `backend`, paso de newman en `staging`) | +11 |
| Prueba (mutantes) | `openspec/changes/add-postman-collection/mutants/M0.jq`…`M5.jq` | +23 |
| Documentación | `software/docs/postman/README.md` · `README.md` § «Documentación de la API» | +76 · +12 |
| Registro | `tasks.md` (marcas), `verification.md`, `journal.md` (sección del implementador) | — |

## 1. Escenarios → evidencia

Corridas: R1–R4 = § 3; F = corrida por carpeta `folder "<carpeta>" "$C"` (§ 4). Anclas de transporte = las de cada
cláusula THEN del delta (live spec / humo), observadas aquí por la aserción citada.

### api-collection

| Escenario | Carpeta › petición (o pieza) | Archivo:línea | Evidencia |
|---|---|---|---|
| Colección y entorno ejecutables | colección completa + `local.postman_environment.json` | `C:1`, entorno `:1` | R1–R4 exit 0 |
| Contraseña sobrescrita al ejecutar | entorno con `password` vacía + `--env-var password=dispensart-dev-only` | entorno `:5` | § 4 fila «sobrescrita»: exit 0 |
| Contraseña equivocada | `01` › «Auxiliar: inicia sesión» | `C:137` | § 4 fila «equivocada»: exit 1; primer login `422 {"code":"invalid_credentials",…}` |
| Sin credenciales reales | barrido § 6 | `software/docs/postman/` | § 6 |
| Primera escritura sin cookie previa | script previo de colección (`pm.sendRequest` a `/sanctum/csrf-cookie`) · `01` › «Auxiliar: inicia sesión» | `C:38`, `C:137` | F `01`: el resumen cuenta 21 peticiones = 20 ítems + 1 `csrf-cookie` (204) del script; login 200 |
| Token con caracteres codificados | `decodeURIComponent` en el script · aserción `X-XSRF-TOKEN = cookie XSRF-TOKEN decodificada` | `C:31`, `C:177` | F `01` y R1–R4 sin fallas |
| Escritura excluida del token | `08` › «Médico: crea bodega sin X-XSRF-TOKEN …» (`X-Omit-Headers: X-XSRF-TOKEN`) · «Admin: la bodega del 419 no existe» | `C:4270`, `C:4392` | F `08` exit 0; M4 |
| Login excluido de Origin y Referer | `08` › «Login sin Origin, Referer ni X-XSRF-TOKEN» · «Usuario actual tras el login rechazado» | `C:4117`, `C:4166` | F `08` exit 0 (403 `forbidden`, luego 401) |
| Recorrido de sesión por rol | `01` › login / usuario actual / cierra sesión × 5 roles | `C:137`–`C:783` | F `01` exit 0 |
| Usuario actual tras cerrar sesión | `01` › «… usuario actual tras cerrar sesión» × 5 | `C:247`, `:381`, `:515`, `:649`, `:783` | F `01` exit 0 (401 `unauthenticated`) |
| Vista previa en orden FEFO sin lote vencido | `02` › «Auxiliar: vista previa FEFO de 2 unidades de MED-004 en BH» | `C:1179`, constante `C:1216` | F `02` exit 0; M5 |
| Dispensación con un movimiento nuevo en el kardex | `02` › «dispensa con Idempotency-Key» · «kardex tras la dispensación» | `C:1279`, `C:1329` | F `02` exit 0 |
| Repetición idempotente | `02` › «repite la dispensación con la misma clave» · «kardex tras la repetición» | `C:1377` (`Idempotent-Replayed` `C:1418`), `C:1425` | F `02` exit 0; M3 |
| Dispensación sin clave | `02` › «dispensa sin Idempotency-Key» · «kardex tras el envío sin clave» | `C:1471`, `C:1514` | F `02` exit 0 |
| Corrida repetida sobre la misma base | colección completa tras R1, y tras los humos | — | R2 y R4 exit 0 |
| Vista previa exige autorización | `03` › «Auxiliar: vista previa de MED-006» | `C:1886` | F `03` exit 0 |
| Control especial sin autorizador | `03` › «dispensa MED-006 sin autorizador» | `C:1931` | F `03` exit 0 |
| Control especial coautorizado por el regente | `03` › «dispensa MED-006 coautorizado por el regente» | `C:1978` | F `03` exit 0 |
| Recorrido hasta la recepción parcial | `04` › crea `:2158`, solicita `:2204`, aprueba `:2309`, despacha `:2596`, recibe 1 de 2 `:2627` | `C:2158`–`C:2627` | F `04` exit 0 ×2 |
| Autoaprobación del regente | `04` › «aprueba su propia solicitud» · «sigue SOLICITADO» · «anula su traslado» | `C:2416`, `C:2447`, `C:2477` | F `04` exit 0 ×2 |
| Despachar un traslado ya recibido | `04` › «despacha de nuevo un traslado ya recibido» | `C:2676` | F `04` exit 0 ×2 |
| Resolución de la discrepancia | `04` › «resuelve la discrepancia» · «resuelve de nuevo» | `C:2781`, `C:2828` | F `04` exit 0 ×2 |
| Consulta del traslado | `04` › «detalle del traslado» · «traslados en RECIBIDO_PARCIAL» | `C:2949`, `C:2980` | F `04` exit 0 ×2 |
| Alertas con el lote vencido sembrado | `05` › «Regente: alertas de vencimiento y stock bajo» | `C:3109` | F `05` exit 0; R4 (tras humos) |
| Ajuste con movimiento en el kardex | `05` › existencias `:3176`, ajuste `:3214`, existencia `:3257`, kardex `:3296` | `C:3176`–`C:3296` | F `05` exit 0 |
| Ajuste mayor que la existencia | `05` › «ajuste mayor que la existencia» · «existencia intacta tras el 409» | `C:3340`, `C:3393` | F `05` exit 0 |
| Lista de modelos con mock primero | `06` › «Regente: modelos disponibles» | `C:3512` | F `06` exit 0 (anfitrión con Ollama); CI staging sin Ollama (§ 5) |
| Pregunta con el modelo mock | `06` › «pregunta con el modelo mock» | `C:3542` | F `06` exit 0 |
| Modelo fuera de la lista | `06` › «pregunta con un modelo fuera de la lista» | `C:3586` | F `06` exit 0 |
| Altas y ediciones del admin | `07` › usuario `:3720`, lista `:3763`, bodega `:3792`, edita `:3835`, producto `:3923`, edita `:3966`, lotes `:4010` | `C:3720`–`C:4010` | F `07` exit 0 ×2 |
| Código de bodega duplicado | `07` › «crea de nuevo la bodega con el mismo código» | `C:3879` | F `07` exit 0 ×2 |
| Sin sesión | `08` › «Sin sesión: bodegas» | `C:4078` | F `08` exit 0 |
| Rol sin la capacidad | `08` › médico alertas `:4241`, admin pacientes `:4421`, auxiliar ajuste `:4524`, auditor vista previa `:4642`, regente usuarios `:4761` | `C:4241`–`C:4761` | F `08` exit 0 |
| CSRF antes que permisos | `08` › «Médico: crea bodega sin X-XSRF-TOKEN» | `C:4270` | F `08` exit 0; M4 |
| Salud y disponibilidad con el stack sano | `00` › «Vivacidad» · «Disponibilidad» | `C:74`, `C:103` | F `00` exit 0 |
| Base de datos detenida | `00` con `db` detenido | `C:103` | § 4 fila «db detenida» |
| Salud fuera del prefijo /api | aserción `fuera del prefijo /api` · guarda (prefijo por `servers` de la ruta) | `C:96`, `C:124`; `check-coverage.sh:29` | F `00` exit 0; guarda § 2 lista `GET /health`, `GET /ready` sin `/api` |
| Colección completa | guarda sobre `C` | `check-coverage.sh:1` | § 2 fila «C» |
| Operación sin petición | guarda sobre M1 | `mutants/M1.jq` | § 5 M1 |
| Petición fuera del contrato | guarda sobre M2 | `mutants/M2.jq` | § 5 M2 |
| Corrida verde | colección completa, stack recién sembrado | — | R1 |
| Aserción fallida | repetición con otra clave (M3), exclusión quitada (M4), lote esperado cambiado (M5) | `mutants/M3.jq`–`M5.jq` | § 5 M3–M5 |

### delivery-pipeline

| Escenario | Pieza | Archivo:línea | Evidencia |
|---|---|---|---|
| Colección verde en staging | paso «Colección de Postman (newman)» tras «Humo del stack y de dominio» | `.github/workflows/ci.yml:343` | § 7 |
| Aserción fallida en staging | sin `if:` en el paso; «Logs del stack» `if: failure()`; «Bajar el stack» `if: always()` | `ci.yml:343`, `:347`, `:351` | § 6 barrido «silenciado» |
| Humos fallidos | paso de newman sin `if:` después de los humos: no corre si fallan | `ci.yml:338`, `:343` | orden en § 6 fila «orden staging» |
| Fallo no silenciado | sin `continue-on-error` ni `\|\| true` | `ci.yml:343` | § 6 fila «silenciado» |
| Versión fijada y sin secretos | `newman@6.2.3`, sin `secrets.` en el paso | `ci.yml:344` | § 6 filas «versión», «secrets staging» |

### ci-pipeline

| Escenario | Pieza | Archivo:línea | Evidencia |
|---|---|---|---|
| Contrato cubierto | paso «Colección de Postman cubre el contrato» en `backend` | `ci.yml:134` | § 7 |
| Operación nueva sin petición | misma lógica de la guarda | `check-coverage.sh:41` | M1 (§ 5) |
| Pull request desde un fork con la guarda | `backend` con `permissions: contents: read`, paso sin `secrets.` | `ci.yml:36`, `:136` | § 6 fila «secrets backend» |

### project-documentation

| Escenario | Pieza | Archivo:línea | Evidencia |
|---|---|---|---|
| Comando documentado ejecutable | comando de la guía, copiado literal | `software/docs/postman/README.md:17` | R1 |
| Rutas de la guía existentes | rutas de la guía y de README § API | § 6 tabla de rutas | § 6 |
| Contraseña marcada como solo de desarrollo | guía § «Contraseña» | `software/docs/postman/README.md:41`, `:46` | § 6 fila «contraseña en la guía» |
| Versión desalineada | versión única en README, guía y `ci.yml` | `README.md:155`, guía `:17`, `ci.yml:344` | § 6 fila «versión» |

## 2. Guarda de cobertura

| Colección | Comando | Exit | Salida (última línea) |
|---|---|---|---|
| vacía v2.1 (`/tmp/vacia.json`, control positivo de lectura de `openapi.json`) | `guard /tmp/vacia.json` | 1 | `Cobertura del contrato INCOMPLETA: 34 sin petición, 0 fuera del contrato` (lista las 34 operaciones) |
| `C` | `guard "$C"` | 0 | `Cobertura del contrato COMPLETA: 34 operaciones` |

## 3. Corridas de cierre (tarea 5.1)

Stack: `docker compose -f software/compose.yaml down -v && … up --build -d --wait` → exit 0; db, api, web
`healthy`; `/health` 200, `/ready` 200; `id -u` api `1000`, web `101`. Las peticiones incluyen la de
`/sanctum/csrf-cookie` del script (ítems de la colección: 122).

| Corrida | Comando | Exit | Peticiones (ejecutadas / fallidas) | Aserciones (ejecutadas / fallidas) | test-scripts | prerequest-scripts |
|---|---|---|---|---|---|---|
| R1 | `npx --yes newman@6.2.3 run software/docs/postman/dispensart.postman_collection.json -e software/docs/postman/local.postman_environment.json` (guía, literal) | 0 | 123 / 0 | 235 / 0 | 244 / 0 | 131 / 0 |
| R2 | `nm "$C"` (repetida, misma base) | 0 | 123 / 0 | 235 / 0 | 244 / 0 | 131 / 0 |
| — | `bash software/docker/smoke.sh` | 0 | — | — | — | — |
| R4 | `nm "$C"` (tras los humos, orden del staging) | 0 | 123 / 0 | 235 / 0 | 244 / 0 | 131 / 0 |

## 4. Corridas por carpeta y negativas (grupo 2)

| Tarea | Comando | Exit | Peticiones | Aserciones (ejec. / fallidas) |
|---|---|---|---|---|
| 2.1 | `folder "00 Salud" "$C"` | 0 | 2 | 5 / 0 |
| 2.1 | `folder "01 Sesión por rol" "$C"` | 0 | 21 | 36 / 0 |
| 2.1 db detenida | `docker compose … stop db` → `folder "00 Salud" "$C"` | 1 | 2 (`/ready` 503) | 5 / 1 (`HTTP 200`: «got 503») |
| 2.1 db de vuelta | `docker compose … start db` → `curl /ready` | — | — | HTTP 200 |
| 2.2 | `folder "02 Dispensación FEFO e idempotencia" "$C"` ×2 | 0, 0 | 19, 19 | 41 / 0, 41 / 0 |
| 2.3 | `folder "03 Control especial" "$C"` | 0 | 12 | 22 / 0 |
| 2.4 | `folder "04 Traslado con discrepancia" "$C"` ×2 | 0, 0 | 27, 27 | 48 / 0, 48 / 0 |
| 2.5 | `folder "05 Alertas, existencias y kardex" "$C"` | 0 | 11 | 21 / 0 |
| 2.6 | `folder "06 Asistente" "$C"` | 0 | 6 | 9 / 0 |
| 2.7 | `folder "07 Catálogo y administración" "$C"` ×2 | 0, 0 | 11, 11 | 18 / 0, 18 / 0 |
| 2.8 | `folder "08 Permisos denegados" "$C"` | 0 | 20 | 35 / 0 |
| 2.9 sobrescrita | `npx --yes "newman@$NV" run "$C" -e /tmp/e.json --env-var password=dispensart-dev-only` (`password` vacía en `/tmp/e.json`) | 0 | 123 | 235 / 0 |
| 2.9 equivocada | `nm "$C" --env-var password=no-es-la-clave` | 1 | 123 (logins 422) | 220 / 194 |
| 2.9 equivocada, cuerpo | igual, `--folder "01 Sesión por rol" --reporters json` tras 70 s (limitador de login, 60 s) | 1 | primer login: `422 {"code":"invalid_credentials",…}` | — |

## 5. [MUT] (tarea 2.10)

Arnés de `tasks.md` literal (`/tmp/pmgen/harness.sh`). Árbol de 2.1–2.9 confirmado en `db73b5b`; `git status
--porcelain -- software .github README.md` vacío antes de cada `mut`. `mut` sale 0 solo si la copia mutada FALLA,
el original PASA y `software/` queda intacto.

| n | mutación (filtro jq sobre una copia) | Aplicada → FALLA | Original → PASA | `mut` exit |
|---|---|---|---|---|
| M0 | identidad `.` (autocontrol del arnés) | no falla: `guard` exit 0 | — | **3** (esperado: `mut` distingue) |
| M1 | quita la petición `POST /api/transfers/{transfer}/void` | `guard` exit 1: «FALLA operación del contrato sin petición en la colección: POST /api/transfers/{}/void» | `guard "$C"` exit 0 (34) | 0 |
| M2 | agrega `GET /api/no-existe` | `guard` exit 1: «FALLA petición fuera del contrato: GET /api/no-existe» | `guard "$C"` exit 0 (34) | 0 |
| M3 | repetición con `Idempotency-Key` `postman-otra-clave-{{runId}}` | `02`: 3/41 fallidas: `HTTP 201 (estado original)` (422 `prescription_exhausted`), `mismo cuerpo que la primera respuesta`, `Idempotent-Replayed: true` | `02`: 41/41 | 0 |
| M4 | quita `X-Omit-Headers` de la petición del médico en `08` | `08`: 2/35 fallidas: `HTTP 419` («got 403»), `code csrf_token_mismatch` («forbidden») | `08`: 35/35 | 0 |
| M5 | `EXPIRED_LOT` de la vista previa `L-LOS-2401` → `L-LOS-2403` | `02`: 1/41 fallida: `ninguna asignación del lote vencido L-LOS-2403` («expected [ 'L-LOS-2403' ] to not include 'L-LOS-2403'») | `02`: 41/41 | 0 |

## 6. Barridos y controles positivos

| Barrido | Comando | Resultado | Control positivo |
|---|---|---|---|
| secretos (total) | `/usr/bin/grep -rnEi 'password\|token\|cookie\|app_key' software/docs/postman/` | 89 líneas: colección 76, guía 10, guarda 2, entorno 1 | el patrón encuentra la línea `password` del entorno (`local.postman_environment.json:5`) |
| clase A: cuerpos con `{{password}}` | `/usr/bin/grep -cE '\{\{password\}\}' "$C"` | 25 (23 logins, alta de usuario, `authorizer_password`) | valores `password` en cuerpos JSON por `jq`: 24 × `{{password}}`, ningún otro valor |
| clase B: valor de contraseña | `jq '.values[] \| select(.key=="password") .value'` entorno | `dispensart-dev-only` (único) | — |
| clase C: script y nombres XSRF/cookie | `/usr/bin/grep -Ei 'token\|cookie\|app_key' "$C" \| /usr/bin/grep -vci password` | 50 líneas de código del script, cabeceras `X-Omit-Headers`, nombres y descripciones; + 1 aserción `authorizer_password` = 76 | — |
| guía y guarda | mismas líneas | guía: contraseña marcada solo de desarrollo, comandos; guarda: `/sanctum/csrf-cookie` | — |
| `APP_KEY` | `/usr/bin/grep -rcE 'app_key\|APP_KEY\|base64:' software/docs/postman/` | 0 | el mismo patrón `-i` del barrido total sí corre sobre el árbol (89 hits) |
| cookies guardadas | `/usr/bin/grep -rc 'eyJ' software/docs/postman/` (prefijo de cookie Laravel cifrada) | 0 | 2 en un tarro de curl de la exploración (`/tmp/pmx/jar.admin`) |
| correos | `/usr/bin/grep -rhoE '[A-Za-z0-9._%+{}-]+@[A-Za-z0-9.-]+' software/docs/postman/` | 7 distintos `@dispensart.test` (5 semilla + `postman-{{runId}}`); el resto: `newman@6.2.3` (no es correo) | el patrón encuentra los 5 correos semilla del entorno |
| secrets backend | `awk '/^  backend:/,/^  frontend:/' ci.yml \| /usr/bin/grep -c 'secrets\.'` | 0; la guarda en la línea 136, posterior a `openapi:lint` (130) | 1 sobre el bloque `staging` |
| silenciado staging | `awk '/^  staging:/,/^  production:/' ci.yml \| /usr/bin/grep -cE 'continue-on-error\|\|\| *true'` | 0 | 1 sobre una copia en `/tmp` con `\|\| true` agregado al paso |
| orden staging | `awk '/^  staging:/,/^  production:/' ci.yml \| /usr/bin/grep -n 'name:'` | humos (bloque `:46`) → newman (`:51`) → logs (`:54`) → bajar (`:58`) | — |
| versión | `/usr/bin/grep -hoE 'newman@[0-9]+\.[0-9]+\.[0-9]+' README.md software/docs/postman/README.md .github/workflows/ci.yml \| sort -u` | 1 línea: `newman@6.2.3` | `/usr/bin/grep -lE 'newman@[0-9]'` lista los 3 archivos |
| contraseña en la guía | `/usr/bin/grep -n 'dispensart-dev-only\|password' software/docs/postman/README.md` | `:41` valor «SOLO de desarrollo … no es secreto»; `:46` `--env-var password=<contraseña>` | — |
| actionlint | `docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:1.7.7 -no-color -oneline .github/workflows/ci.yml` | exit 0, sin hallazgos | — |

Rutas citadas (guía + README § «Documentación de la API»), `test -e` por ruta:

| Ruta | Existe |
|---|---|
| `software/api/openapi.json` | sí |
| `software/docs/postman/README.md` | sí |
| `software/docs/postman/check-coverage.sh` | sí |
| `software/docs/postman/dispensart.postman_collection.json` | sí |
| `software/docs/postman/local.postman_environment.json` | sí |

## 7. CI (tarea 5.3)

Push de `dev` con `436d874` (contiene `52092df`…`810b988`). `gh run list --branch dev --workflow CI -L 1 --json
databaseId,conclusion` → `38052524687` `success`. `gh run view 38052524687 --log | /usr/bin/grep -nE
'check-coverage|newman'` muestra ambos pasos.

| Run | Commit | Resultado | Paso guarda (`backend`) | Paso newman (`staging`, tras «Humo VERDE») |
|---|---|---|---|---|
| [38052524687](https://github.com/pedrozopayares/Dispensart/actions/runs/38052524687) | `436d874` | `success` (backend, frontend, build, staging; production `skipped` fuera de `main`) | `Cobertura del contrato COMPLETA: 34 operaciones` | peticiones 123 / 0, aserciones 235 / 0, test-scripts 244 / 0, prerequest-scripts 131 / 0 (stack recién sembrado, sin Ollama) |
