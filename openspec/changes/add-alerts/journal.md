# Journal — add-alerts (S5)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv
- Fase: /proposal adelantada. Apply espera el archivo de S4.
- Tier objetivo según ROADMAP: B.

## 2026-10-07 — spec-engineer: proposal, delta y tareas

- Producido: `proposal.md`, `specs/inventory-alerts/spec.md` (5 requisitos, todos con escenario negativo),
  `tasks.md` (tier B, `[MUT]` M1–M7). `openspec validate add-alerts --strict` válido.
- Ancla de transporte: `/usr/bin/grep -rnE '<patrón de CYCLE-TIERS>' specs/` → 21 hits, 0 sin `[ancla: …]`
  (control positivo: los 21 hits contienen `ancla:`).
- Capacidad nueva `inventory-alerts`; ninguna modificada (S1/S2 no están vivas; se reutiliza `inventory.view`).
- Supuestos (6, en `proposal.md`): mínimos solo por siembra, sin endpoint de escritura; vencidos con existencia
  dentro de `expiring_lots` marcados `is_expired`; existencia vencida y en tránsito no cuentan para el mínimo;
  ventana fija de 90 días; bodega inexistente → listas vacías; mínimo 0 no admitido (sin fila = sin mínimo).
- Preguntas abiertas para el Orchestrator:
  1. Tier: la tabla de mínimos es una migración con `CHECK`, disparador Tier A según `CYCLE-TIERS.md`; ROADMAP
     fija B (y CYCLE-TIERS cita "alert query (RN-11)" como ejemplo B). Borrador en B con pin M6 sobre la
     restricción. Condición 3 de la preaprobación: decide el Orchestrator.
  2. Edición de mínimos por API (admin o regente) fuera de alcance: candidato de fila ROADMAP/deuda.
- Ninguna decisión de ADR ni regla RN-xx debilitada.

## 2026-10-07 — Orchestrator: GATE 1 detenido

- Condición 3 falla: la tabla de mínimos por bodega exige migración con `CHECK`, disparador de Tier A en
  `CYCLE-TIERS.md`; la fila S5 dice B. La preaprobación exige detenerse y preguntar al usuario.
- Artefactos válidos (`openspec validate add-alerts --strict` pasa). Pendiente: decisión del usuario sobre el tier.

## 2026-10-08 — Orchestrator: decisión del usuario sobre el tier

- El usuario resolvió la condición 3 de la preaprobación: **tier A completo** (la migración con `CHECK` es
  disparador de Tier A en `CYCLE-TIERS.md`). La fila S5 de `ROADMAP.md` pasa de B a A con esta referencia.
- Consecuencias: `design.md` del architect, matriz `[MUT]` de tier A en `tasks.md`, auditoría completa de tier A.
- Fase: diseño. GATE 1 se registra cuando el spec-validator devuelva VALID sobre los artefactos refinados.

## 2026-10-08 — architect: design.md y refinamiento de tareas a tier A

- Producido: `design.md` (D1–D8, contrato, Data impact, 3 riesgos) y `tasks.md` refinado a tier A, `[MUT]` M1–M21.
  `openspec validate add-alerts --strict` válido.
- Decisiones:
  - D1: tabla `stock_minimums`, `minimum_quantity integer NOT NULL` + `CHECK > 0`, único (bodega, producto), FKs
    `RESTRICT`. Sin fila = sin mínimo: con 0 habría dos representaciones del mismo hecho. Rechazadas: columna en
    `stocks` (grano de lote), en `products` (no es por bodega), tope superior (YAGNI).
  - D2: "hoy" de `BusinessCalendar` enlazado como parámetro. Ventana, `days_to_expiry` y no vencido se calculan en
    SQL. Rechazado `CURRENT_DATE`: duplica la regla de zona e ignora `travelTo` (mutante M16).
  - D4: tránsito excluido por construcción. S4 descuenta en origen al despachar y suma en destino al recibir; la
    consulta suma solo `stocks` y no toca tablas de traslados.
  - D5: autorización con `StockPolicy::viewAny` existente vía `ListAlertsRequest`. Rechazadas: `AlertPolicy`
    nueva y capacidad `alerts.view` (cambiaría el mapa congelado de S1).
  - D6: sin bloqueos (MVCC, `READ COMMITTED`) y sin índices nuevos fuera de la tabla nueva. Rechazados
    `FOR SHARE` (entraría en el orden de bloqueo global de S2) y `REPEATABLE READ` (YAGNI).
  - D7: siembra por código de bodega + producto, `firstOrCreate`, nunca actualiza. 4 pares: 3 alertados (uno
    solo por excluir el vencido, uno sin filas de existencia) y 1 no alertado.
- Riesgos señalados: divergencia del predicado SQL frente a `Lot::isExpiredOn` (M11), escenario de tránsito
  ausente en la spec (bloquea GATE 1), siembra que envejece en un staging de larga vida.
- **Bloqueo para GATE 1 (al spec-engineer):** la spec delta no tiene escenario de tránsito. Sin él, M12 y M13 no
  tienen prueba que los mate. El THEN debe afirmar el disponible de origen y de destino (design Open Questions 1).
- Redacción, no defecto: el ancla «Policy de alertas» se resuelve a `ListAlertsRequest::authorize()` +
  `StockPolicy::viewAny`.
- Sin bloque `web`: S6 consume el contrato después de 5.1. Ningún ADR ni RN-xx debilitado. Edición de mínimos por
  API queda como candidato de deuda.

## 2026-10-08 — spec-engineer: escenario de tránsito (cierra Open Questions 1 de design.md)

- Producido: en `specs/inventory-alerts/spec.md`, requisito «Alerta de stock bajo mínimo», escenario «Unidades en
  tránsito no cuentan hasta la recepción». Mínimo 10 en Central (12) y Urgencias (6), despacho de 5 que queda
  `EN_TRANSITO`, consulta, recepción completa, segunda consulta. THEN: primera consulta Central 7 y Urgencias 6;
  segunda consulta Central 7 y Urgencias ausente (11). Afirma origen y destino, como exige design.
- Texto del requisito: agrega que las unidades `EN_TRANSITO` no cuentan en origen ni destino (RN-07). Se recortó
  para no pasar 500 caracteres (`--strict`). El sentido es el mismo: «Existencia igual al mínimo» sigue fijando `<`.
- `tasks.md`: el encabezado ya no marca el bloqueo de GATE 1. 3.3 cita el escenario por nombre. En 3.4, M12 falla
  porque Urgencias suma 11 y sale de `low_stock`, y M13 porque Central queda en 2 y no en 7.
- Consistencia: igual a design D4 (la consulta solo lee `stocks`) y a transfers vivo («Despacho exitoso»: el destino
  no cambia; «Recepción completa»: el destino suma). Estos dos van en el ancla.
- Verificación:

| Comprobación | Resultado |
|---|---|
| `npx openspec validate add-alerts --strict` | válido |
| THEN en la spec delta | 30 |
| hits del barrido de anclas | 22 |
| hits sin `[ancla:` | 0 |
| hits del escenario nuevo | 1 |
| control positivo (copia en scratchpad + 1 THEN sin ancla) | 23 hits, 1 sin ancla |

- Supuestos: 0. Preguntas abiertas: ninguna.

## 2026-10-08 — architect: corrección de los 4 hallazgos del spec-validator

- `[MUT]` ejecutables: la cabecera de `tasks.md` define un arnés bash. `pest <archivo> <filtro>` corre con
  `--fail-on-empty-test-suite` (PHPUnit 13.4), así que un filtro que no casa no da falso PASA. `mut` aplica un
  parche, exige FALLA, revierte, exige PASA y árbol limpio. `ctl` exige PASA con el mutante (control positivo).
  Cada mutante es un parche en `mutants/M<n>.patch`, que el implementador escribe contra el árbol confirmado. Las
  tareas 1.3, 2.2, 3.2, 3.4 y 4.3 llaman `mut`/`ctl` con archivo de prueba y título de escenario literales. Los
  archivos de prueba quedan fijados.
- 6.1 ejecutable: humo nuevo `software/docker/smoke/alerts-smoke.sh` (patrón de `stock-smoke.sh`). El comando
  hace `down -v`, `up --build`, humo, cuenta `stock_minimums` = 4, `--force-recreate api` (resiembra), humo y
  conteo igual.
- M22: quitar `min:1` → «Filtro mal formado» FALLA en el caso `0`; control: `abc` sigue en 422.
- M23: falta de fila tratada como mínimo **1** → «Producto sin mínimo» FALLA. Con 0 el mutante es equivalente
  (0 < 0 nunca alerta) y sobreviviría; por eso no se usa 0.
- Cabecera: declarados 23 · entregados 0. La tarea 6.5 nueva lo cierra contra las filas de `verification.md`.
- Notas viejas: el riesgo 2 de `design.md` pasa a "dependencia del flujo de S4" y Open Questions queda en
  "ninguna". «Policy de alertas» → `StockPolicy::viewAny`, invocada desde `ListAlertsRequest::authorize()`.
- `npx openspec validate add-alerts --strict` válido. Sin commit.

## 2026-10-08 — Orchestrator: GATE 1 APROBADO

- Condición 1 (alcance exacto de la fila S5): cumple, según el spec-validator. El filtro `warehouse_id` y la
  inclusión de lotes ya vencidos quedan dentro de «por bodega» y «≤ 90 días».
- Condición 2: spec-validator VALID (re-chequeo delta). `openspec validate add-alerts --strict` pasa; el barrido
  de anclas da 22 hits y 0 sin `[ancla:` (control positivo: 1 de 1).
- Condición 3: tier A por decisión explícita del usuario (2026-10-08), que reemplaza el B de la fila.
- Condición 4: ninguna ADR ni regla RN-xx debilitada.
- Matriz `[MUT]`: 23 declaradas (M1–M23), 0 entregadas.
- Notas de redacción sin reapertura (regla 12): el ancla «Policy de alertas» en `spec.md:81` se resuelve en
  `StockPolicy::viewAny` (`design.md`); el control positivo de 6.5 se escribe dentro del comando al cerrar.

## 2026-10-08 — Orchestrator: inicio de apply

- Preflight: stack `api`, `db` y `web` sanos. Línea base de las suites: CI 37769634376 sobre `d350c4c` en verde
  (backend y frontend). No consume corrida completa local.
- Presupuesto: cierre del implementer (6.2) y confirmación del auditor; tope de 3.
- Orden: backend-implementer 1–5 y 6.2–6.5; luego devops-implementer 6.1 (humo). No hay tareas de frontend en S5.

## 2026-10-08 — backend-implementer: apply de 1–5 y cierre 6.2–6.5

- Tareas cerradas: 1.1–1.3, 2.1–2.2, 3.1–3.5, 4.1–4.3, 5.1, 6.2–6.5. 6.1 (humo) queda para devops-implementer.
- Commits en `dev`: `ff4c137` mínimos en base, `9bed223` consultas, `c208a5b` siembra, `da49eb6` endpoint,
  `db01342` contrato OpenAPI + `api-schema.ts`, `6bdf70f` arreglo de la reversión de S4, y el del registro.
- Decisiones:
  - `App\Queries\InventoryAlerts` (DTO de solo lectura con las dos colecciones) como recurso de `AlertsResource`:
    con un arreglo, Scramble no infería el modelo (aviso JR001) y documentaba ambas listas como `string`. Con
    `@mixin InventoryAlerts`, el esquema queda tipado. No altera D8: el recurso sigue envolviendo ambas listas.
  - El disponible de stock bajo es una constante `AlertQuery::AVAILABLE`, usada en el `SELECT` y en el `WHERE`.
    Así el valor servido y el comparado no divergen. M12 y M13 la mutan.
  - Escenarios de alerta con datos y afirmación en `tests/Helpers/Alerts.php`, uno por título literal. Los recorren
    la prueba de consulta y el dataset de la ruta (mismos datos, misma afirmación, rol del WHEN).
- Incidente `[MUT]`: la primera cadena de 3.4 trajo M12 y M13 con un error de sintaxis (comilla de cierre). La
  «FALLA» venía de un `ParseError`, no de la propiedad, y `ctl M12` lo destapó. Se rehicieron los parches, se pasó
  `php -l` sobre los 15 de `AlertQuery.php` y se repitió la cadena completa, que salió 0. Detalle en
  `verification.md` § 3.
- Corrida completa 6.2 (una, en `db01342`): Pint y Larastan pasan; en Pest falla una prueba de S4,
  `TransferRaceTest` «revierte las 4 migraciones de traslados…». Usaba `--step=4` fijo y la migración de S5 es ahora
  la última. Arreglo: contar los pasos desde la primera migración de S4. Se saneó con una corrida delta del archivo,
  que pasa. No se repitió la suite completa, por el tope de 3. La confirmación completa en verde queda para la
  corrida del auditor. Cifras en `verification.md` § 5.
- `composer openapi:check` no corre dentro del contenedor (`git` no ve el repositorio). La deriva se comprobó con
  re-export en el contenedor y `git diff --exit-code` en el host, sin diferencias. En CI corre en el runner.
- Deuda (prosa, sin id): edición de mínimos por API (`admin` o `regente`), ya anotada como candidata por
  spec-engineer y architect. Fragilidad general: las pruebas de reversión que dependen de que su migración sea la
  última. Barrido S7: hoy no queda otra.

## 2026-10-08 — devops-implementer: humo de alertas (6.1)

- Tarea cerrada: 6.1. Script nuevo `software/docker/smoke/alerts-smoke.sh` con el patrón de `stock-smoke.sh`
  (bash + curl + jq, contraseña nunca impresa, sale 0 solo si las 18 comprobaciones pasan; solo lectura).
- Desvío del comando de 6.1: sin `down -v` inicial, por orden del Orchestrator (el volumen del stack de desarrollo lo
  usan otros agentes). La migración de S5 no estaba aplicada: `stock_minimums` nació en el `up --build`, y la
  resiembra se ejerció con `up --wait --force-recreate api` (el entrypoint corre `db:seed` siempre). Sin
  `migrate:fresh`. El staging de CI arranca desde volumen vacío (migra y siembra) pero solo corre `smoke.sh`:
  el humo de alertas sobre volumen vacío no tiene corrida.
- Corridas: 3 del humo, 18/18 cada una; `stock_minimums` 4 antes y 4 después de la resiembra; comando compuesto
  sale 0. Controles negativos: contraseña errónea sale 1; aserción invertida (FC/MED-001 exigido en `low_stock`)
  sale 1. Humos existentes tras las corridas: auth, stock, dispensation y transfer salen 0, sin fallas. Tablas en
  `verification.md` § 6.
- Salida del comando de 6.1 (sin las líneas de contenedores de compose):

```
Humo de alertas de inventario contra http://localhost:8090
PASA  [regente] GET /sanctum/csrf-cookie: HTTP 204
PASA  [regente] POST /api/auth/login: HTTP 200
PASA  [regente] GET /api/alerts: HTTP 200
PASA  expiring_lots no vacía
PASA  low_stock no vacía
PASA  L-ACE-2401 listado con is_expired true y days_to_expiry < 0
PASA  low_stock contiene FC/MED-006
PASA  low_stock contiene BH/MED-004
PASA  low_stock contiene BH/MED-006
PASA  low_stock no contiene FC/MED-001 (con mínimo, sin alerta)
PASA  cada fila de low_stock con available_quantity < minimum_quantity
PASA  [auditor] GET /sanctum/csrf-cookie: HTTP 204
PASA  [auditor] POST /api/auth/login: HTTP 200
PASA  [auditor] GET /api/alerts: HTTP 200
PASA  [auditor] cuerpo idéntico al del regente
PASA  [medico] GET /sanctum/csrf-cookie: HTTP 204
PASA  [medico] POST /api/auth/login: HTTP 200
PASA  [medico] GET /api/alerts: HTTP 403
Comprobaciones: 18, fallas: 0
Humo de alertas de inventario contra http://localhost:8090
PASA  [regente] GET /sanctum/csrf-cookie: HTTP 204
PASA  [regente] POST /api/auth/login: HTTP 200
PASA  [regente] GET /api/alerts: HTTP 200
PASA  expiring_lots no vacía
PASA  low_stock no vacía
PASA  L-ACE-2401 listado con is_expired true y days_to_expiry < 0
PASA  low_stock contiene FC/MED-006
PASA  low_stock contiene BH/MED-004
PASA  low_stock contiene BH/MED-006
PASA  low_stock no contiene FC/MED-001 (con mínimo, sin alerta)
PASA  cada fila de low_stock con available_quantity < minimum_quantity
PASA  [auditor] GET /sanctum/csrf-cookie: HTTP 204
PASA  [auditor] POST /api/auth/login: HTTP 200
PASA  [auditor] GET /api/alerts: HTTP 200
PASA  [auditor] cuerpo idéntico al del regente
PASA  [medico] GET /sanctum/csrf-cookie: HTTP 204
PASA  [medico] POST /api/auth/login: HTTP 200
PASA  [medico] GET /api/alerts: HTTP 403
Comprobaciones: 18, fallas: 0
n1=4 n2=4
EXIT=0
```
- Deuda (prosa, sin id): el staging de CI no corre los humos por capacidad (auth, stock, dispensation, transfer,
  alerts); sumarlos daría la corrida desde volumen vacío que aquí se omitió. `actionlint` no está instalado; no se
  tocaron workflows.
