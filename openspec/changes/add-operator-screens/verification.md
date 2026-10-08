# Verification — add-operator-screens (S6, tier B) — PARCIAL

Aplicación temprana: solo tareas cuya API existe (S1–S4). 4.2, 6.2, 6.3 y 7 esperan S5 o el cierre.
Prefijos: `OW` operator-workspace, `DS` dispensation-screen (nivel de hook), `INV` inventory-screen, `TS` transfers-screen,
`KDX` kardex-screen, `CIM` cimiento sin escenario. Rutas de prueba relativas a `software/web/src/`.

## 0. Reparto de líneas

Comando: `git diff --numstat -- src package.json` sobre el árbol de trabajo antes de los commits de S6, separado con `awk` por ruta.

| Categoría | Alcance | Líneas añadidas |
|---|---|---|
| Producto — SPA | `src/**` sin pruebas ni `components/ui` | 1163 |
| Prueba — SPA | `src/**/*.test.*`, `src/test/**` | 1174 |
| Generado | `src/components/ui/{table,native-select,alert-dialog}.tsx` (shadcn CLI) | 370 |
| Producto — SPA, Dispensación (S3) | `features/dispensations/*.ts(x)` sin pruebas + cambios en `lib/`, `app/` | 1045 nuevas + 146 modificadas |
| Prueba — SPA, Dispensación (S3) | `features/dispensations/*.test.*`, `test/dispensation-fixtures.ts`, ajustes de menú y `shortages` | 1007 nuevas + 20 modificadas |
| Generado — tipos | `src/lib/api-schema.ts` (`npm run api:types`, commit propio) | 554 |
| Producto — SPA, Traslados (S4) | `features/transfers/*.ts(x)` sin pruebas + `components/pagination.tsx` (nuevas); `lib/`, `app/`, `home`, `kardex` (modificadas) | 1172 nuevas + 233 / −58 modificadas |
| Prueba — SPA, Traslados (S4) | `features/transfers/*.test.*`, `test/transfer-fixtures.ts` (nuevas); `app/require-ability.test.tsx` (modificada) | 740 nuevas + 66 / −6 modificadas |
| Generado — Traslados | `src/lib/api-schema.ts` (`npm run api:types`, commit propio) · `components/ui/textarea.tsx` (shadcn CLI) | 946 · 17 |

## 1. Matriz escenario → prueba → archivo:línea

| Id | Escenario | Prueba | Archivo:línea |
|---|---|---|---|
| OW-01 | Mensajes de error por código: Stock insuficiente con detalle | Stock insuficiente con detalle | lib/api-errors.test.ts:9 |
| OW-02 | Mensajes de error por código: Lote vencido | Lote vencido | lib/api-errors.test.ts:31 |
| OW-03 | Mensajes de error por código: Falta de autorización | Falta de autorización | lib/api-errors.test.ts:37 |
| OW-04 | Mensajes de error por código: Código desconocido | Código desconocido (vía red simulada) | lib/api-errors.test.ts:43 |
| OW-05 | Mensajes de error por código: Fallo de red | Fallo de red (3 casos) | lib/api-errors.test.ts:52 |
| OW-06 | Mensajes de error por código: Error de campo junto al campo | Error de campo junto al campo · fieldErrors | components/submit-button.test.tsx:115; lib/api-errors.test.ts:62 |
| OW-07 | Bloqueo del doble envío: Doble clic produce una sola petición | Doble clic produce una sola petición | components/submit-button.test.tsx:61 |
| OW-08 | Bloqueo del doble envío: Enter repetido en un formulario | Enter repetido | components/submit-button.test.tsx:79 |
| OW-09 | Bloqueo del doble envío: Botón rehabilitado tras un rechazo | Botón rehabilitado tras un rechazo | components/submit-button.test.tsx:95 |
| OW-10 | Disposición común: Confirmación en diálogo propio | Confirmación en diálogo propio | components/confirm-dialog.test.tsx:32 |
| OW-11 | Disposición común: Error anunciado | Error anunciado | components/confirm-dialog.test.tsx:59 |
| OW-12 | Guarda de ruta: Acceso directo sin capacidad | Acceso directo sin capacidad (+ control positivo :27) | app/require-ability.test.tsx:17 |
| OW-13 | Guarda de ruta: Acceso directo con capacidad | Acceso directo con capacidad | app/require-ability.test.tsx:35 |
| OW-14 | Guarda de ruta: El servidor niega aunque la guarda permita | El servidor niega | app/require-ability.test.tsx:47 |
| DS-01 | Confirmación idempotente: Reintento tras fallo de red reutiliza la clave (hook) | Reintento tras fallo de red | lib/use-idempotent-intent.test.ts:29 |
| DS-02 | Confirmación idempotente: Reintento tras autorizador corregido reutiliza la clave (hook) | Reintento tras autorizador corregido | lib/use-idempotent-intent.test.ts:37 |
| DS-03 | Confirmación idempotente: Cambio de cantidad genera clave nueva (hook) | Cambio de cantidad | lib/use-idempotent-intent.test.ts:46 |
| DS-04 | Confirmación idempotente: Nueva dispensación genera clave nueva (hook) | Nueva dispensación | lib/use-idempotent-intent.test.ts:55 |
| DS-05 | Confirmación idempotente: Clave reutilizada con otros datos (hook) | idempotency_key_reused descarta | lib/use-idempotent-intent.test.ts:63 |
| INV-01 | Existencias por bodega y lote: Consulta por bodega | Consulta por bodega | features/inventory/inventory-page.test.tsx:30 |
| INV-02 | Existencias por bodega y lote: Sin existencias para el filtro | Sin existencias | features/inventory/inventory-page.test.tsx:54 |
| INV-03 | Existencias por bodega y lote: Fallo de la consulta | Fallo de la consulta | features/inventory/inventory-page.test.tsx:64 |
| INV-04 | Existencias por bodega y lote: Lote vencido marcado | Lote vencido marcado | features/inventory/inventory-page.test.tsx:90 |
| INV-05 | Existencias por bodega y lote: Auditor sin controles de edición | Auditor sin controles | features/inventory/inventory-page.test.tsx:131 |
| KDX-01 | Historial filtrable: Tipos en español | Tipos en español | features/kardex/kardex-page.test.tsx:20 |
| KDX-02 | Historial filtrable: Cantidad con signo y usuario del sistema | Cantidad con signo | features/kardex/kardex-page.test.tsx:40 |
| KDX-03 | Historial filtrable: Filtro por producto y lote | Filtro por producto y lote | features/kardex/kardex-page.test.tsx:69 |
| KDX-04 | Historial filtrable: Lote deshabilitado sin producto | Lote deshabilitado | features/kardex/kardex-page.test.tsx:96 |
| KDX-05 | Historial filtrable: Sin movimientos | Sin movimientos | features/kardex/kardex-page.test.tsx:105 |
| KDX-06 | Historial filtrable: Fallo de la consulta | Fallo de la consulta | features/kardex/kardex-page.test.tsx:111 |
| KDX-07 | Paginación y filtros en la URL: Siguiente página | Siguiente página | features/kardex/kardex-page.test.tsx:125 |
| KDX-08 | Paginación y filtros en la URL: Recarga conserva los filtros | Recarga conserva | features/kardex/kardex-page.test.tsx:138 |
| KDX-09 | Paginación y filtros en la URL: Parámetro inválido en la URL | Parámetro inválido | features/kardex/kardex-page.test.tsx:151 |
| KDX-10 | Paginación y filtros en la URL: Primera y última página | Primera y última | features/kardex/kardex-page.test.tsx:161 |
| KDX-11 | Movimientos inmutables: Regente sin edición | Regente sin edición | features/kardex/kardex-page.test.tsx:176 |
| EXT-01 | (requisito) fecha en `America/Bogota` | fecha en Bogota con TZ del proceso en Asia/Tokyo | features/kardex/kardex-page.test.tsx:59 |
| DS-06 | Búsqueda de paciente: Búsqueda con resultados | Búsqueda con resultados | features/dispensations/dispensation-page.test.tsx:27 |
| DS-07 | Búsqueda de paciente: Término demasiado corto | Término demasiado corto | features/dispensations/dispensation-page.test.tsx:46 |
| DS-08 | Búsqueda de paciente: Sin resultados | Sin resultados | features/dispensations/dispensation-page.test.tsx:57 |
| DS-09 | Búsqueda de paciente: Fallo de la búsqueda | Fallo de la búsqueda | features/dispensations/dispensation-page.test.tsx:71 |
| DS-10 | Búsqueda de paciente: Selección con teclado | Selección con teclado | features/dispensations/dispensation-page.test.tsx:96 |
| DS-11 | Datos enmascarados: Auditor ve datos enmascarados | Auditor ve datos enmascarados | features/dispensations/dispensation-page.test.tsx:115 |
| DS-12 | Datos enmascarados: Auditor sin forma de desenmascarar | Auditor sin forma de desenmascarar | features/dispensations/dispensation-page.test.tsx:126 |
| DS-13 | Datos enmascarados: Auxiliar ve datos en claro | Auxiliar ve datos en claro | features/dispensations/dispensation-page.test.tsx:136 |
| DS-14 | Prescripciones: Prescripción vigente elegible | Prescripción vigente elegible | features/dispensations/dispensation-page.test.tsx:147 |
| DS-15 | Prescripciones: Vencida y agotada no elegibles | Vencida y agotada | features/dispensations/dispensation-page.test.tsx:167 |
| DS-16 | Prescripciones: Paciente sin prescripciones | Paciente sin prescripciones | features/dispensations/dispensation-page.test.tsx:186 |
| DS-17 | Prescripciones: Fallo al cargar la ficha | Fallo al cargar la ficha | features/dispensations/dispensation-page.test.tsx:193 |
| DS-18 | Modo consulta: Auditor en modo consulta · Médico en modo consulta | `it.each` auditor, medico (+ control positivo :230) | features/dispensations/dispensation-page.test.tsx:216 |
| DS-19 | Vista previa FEFO: Lotes en orden FEFO | Lotes en orden FEFO | features/dispensations/dispensation-confirm.test.tsx:57 |
| DS-20 | Vista previa FEFO: Unidades vencidas excluidas | Unidades vencidas excluidas | features/dispensations/dispensation-confirm.test.tsx:76 |
| DS-21 | Vista previa FEFO: Faltante en la vista previa | Faltante | features/dispensations/dispensation-confirm.test.tsx:89 |
| DS-22 | Vista previa FEFO: Cantidad mayor que el pendiente | Cantidad mayor que el pendiente | features/dispensations/dispensation-confirm.test.tsx:103 |
| DS-23 | Vista previa FEFO: Sin bodega o sin cantidades | Sin bodega o sin cantidades | features/dispensations/dispensation-confirm.test.tsx:118 |
| DS-24 | Vista previa FEFO: Vista previa desactualizada | Vista previa desactualizada | features/dispensations/dispensation-confirm.test.tsx:137 |
| DS-25 | Vista previa FEFO: Prescripción que cambió de estado | `it.each` 3 códigos | features/dispensations/dispensation-confirm.test.tsx:154 |
| DS-26 | Coautorización: Campos de autorizador visibles | Campos visibles | features/dispensations/dispensation-confirm.test.tsx:185 |
| DS-27 | Coautorización: Sin control especial no se piden | Sin control especial | features/dispensations/dispensation-confirm.test.tsx:193 |
| DS-28 | Coautorización: Campos del autorizador vacíos | Campos vacíos | features/dispensations/dispensation-confirm.test.tsx:209 |
| DS-29 | Coautorización: Autorizador inválido | Autorizador inválido | features/dispensations/dispensation-confirm.test.tsx:221 |
| DS-30 | Coautorización: Autorizador igual al dispensador | Igual al dispensador | features/dispensations/dispensation-confirm.test.tsx:237 |
| DS-31 | Coautorización: Autorización requerida por el servidor | Requerida por el servidor | features/dispensations/dispensation-confirm.test.tsx:247 |
| DS-32 | Coautorización: Demasiados intentos del autorizador | Demasiados intentos | features/dispensations/dispensation-confirm.test.tsx:263 |
| DS-33 | Confirmación idempotente: Dispensación exitosa (+ OW «Almacenamiento del navegador vacío de pacientes») | Dispensación exitosa | features/dispensations/dispensation-confirm.test.tsx:277 |
| DS-34 | Confirmación idempotente: Doble clic en Confirmar | Doble clic | features/dispensations/dispensation-confirm.test.tsx:305 |
| DS-35 | Confirmación idempotente: Reintento tras fallo de red reutiliza la clave | Reintento tras fallo de red | features/dispensations/dispensation-confirm.test.tsx:325 |
| DS-36 | Confirmación idempotente: Respuesta repetida tratada como éxito | Respuesta repetida | features/dispensations/dispensation-confirm.test.tsx:344 |
| DS-37 | Confirmación idempotente: Reintento tras autorizador corregido reutiliza la clave | Autorizador corregido | features/dispensations/dispensation-confirm.test.tsx:357 |
| DS-38 | Confirmación idempotente: Cambio de cantidad genera clave nueva | Cambio de cantidad | features/dispensations/dispensation-confirm.test.tsx:379 |
| DS-39 | Confirmación idempotente: Nueva dispensación genera clave nueva | Nueva dispensación | features/dispensations/dispensation-confirm.test.tsx:404 |
| DS-40 | Confirmación idempotente: Stock agotado entre la vista previa y la confirmación | Stock agotado | features/dispensations/dispensation-confirm.test.tsx:425 |
| DS-41 | Confirmación idempotente: Clave reutilizada con otros datos | Clave reutilizada | features/dispensations/dispensation-confirm.test.tsx:446 |
| DS-42 | Confirmación idempotente: Sesión del dispensador sin permiso | Sin permiso | features/dispensations/dispensation-confirm.test.tsx:467 |
| OW-15 | Datos del paciente fuera del navegador: URL sin datos personales | Selección con teclado (URL) | features/dispensations/dispensation-page.test.tsx:96 |
| OW-16 | Datos del paciente fuera del navegador: Error durante una consulta de paciente (adelantado de 6.2) | Fallo de la búsqueda (espía de consola) | features/dispensations/dispensation-page.test.tsx:71 |
| OW-17 | Navegación por rol: Auxiliar ve sus cuatro pantallas | Auxiliar ve sus cuatro pantallas, en ese orden | app/require-ability.test.tsx:70 |
| OW-18 | Navegación por rol: Auditor ve las cuatro en lectura | Auditor ve las cuatro | app/require-ability.test.tsx:75 |
| OW-19 | Navegación por rol: Médico solo ve Dispensación | Médico solo ve Dispensación | app/require-ability.test.tsx:85 |
| OW-20 | Navegación por rol: Admin sin pantallas de operación | Admin sin pantallas | app/require-ability.test.tsx:90 |
| OW-21 | Navegación por rol: Navegación con teclado | abrir Kardex desde el menú lo marca como página actual | app/require-ability.test.tsx:95 |
| OW-22 | Inicio con accesos del rol: Accesos del regente | Accesos del regente (+ menú del regente :80) | app/require-ability.test.tsx:120 |
| OW-23 | Inicio con accesos del rol: Admin sin accesos (+ app-shell «Página de inicio sin pantallas aún», también app/shell-header.test.tsx:39) | Admin sin accesos | app/require-ability.test.tsx:141 |
| OW-24 | app-shell «Página de inicio con saludo» | Página de inicio con saludo (+ médico solo Dispensación :158) | app/require-ability.test.tsx:150 |
| TS-01 | Listado: Listado con estados en español | Listado con estados en español | features/transfers/transfers-page.test.tsx:56 |
| TS-02 | Listado: Filtro por estado | Filtro por estado (consulta enviada y vuelta a página 1) | features/transfers/transfers-page.test.tsx:72 |
| TS-03 | Listado: Sin traslados | Sin traslados | features/transfers/transfers-page.test.tsx:89 |
| TS-04 | Listado: Fallo del listado | Fallo del listado | features/transfers/transfers-page.test.tsx:95 |
| TS-05 | Detalle: Recibido parcial con discrepancias | Recibido parcial con discrepancias | features/transfers/transfer-detail.test.tsx:56 |
| TS-06 | Detalle: Recibido completo sin discrepancias | Recibido completo | features/transfers/transfer-detail.test.tsx:76 |
| TS-07 | Detalle: Traslado inexistente | Traslado inexistente | features/transfers/transfer-detail.test.tsx:84 |
| TS-08 | Creación: Borrador creado | Borrador creado | features/transfers/transfers-page.test.tsx:109 |
| TS-09 | Creación: Lotes vencidos fuera de la lista | Lotes vencidos fuera | features/transfers/transfers-page.test.tsx:129 |
| TS-10 | Creación: Destino igual al origen | Destino igual al origen | features/transfers/transfers-page.test.tsx:141 |
| TS-11 | Creación: Sin líneas o cantidad inválida | Sin líneas · `it.each` '', '0', '1.5' | features/transfers/transfers-page.test.tsx:152, :163 |
| TS-12 | Creación: Doble clic en Crear traslado | Doble clic en Crear traslado | features/transfers/transfers-page.test.tsx:174 |
| TS-13 | Creación: Lote vencido al crear | Lote vencido al crear | features/transfers/transfers-page.test.tsx:190 |
| TS-14 | Creación: Sin capacidad de crear | Sin capacidad de crear (control positivo: el auxiliar abre el formulario en :109) | features/transfers/transfers-page.test.tsx:203 |
| TS-15 | Acciones: Solicitante no ve Aprobar | Solicitante no ve Aprobar | features/transfers/transfer-detail.test.tsx:96 |
| TS-16 | Acciones: Otro regente aprueba | Otro regente aprueba | features/transfers/transfer-detail.test.tsx:103 |
| TS-17 | Acciones: Auxiliar no ve Aprobar | Auxiliar no ve Aprobar | features/transfers/transfer-detail.test.tsx:117 |
| TS-18 | Acciones: Auditor sin acciones | `it.each` 4 estados (control positivo :144) | features/transfers/transfer-detail.test.tsx:123 |
| TS-19 | Acciones: Estado terminal sin acciones | `it.each` 3 estados | features/transfers/transfer-detail.test.tsx:134 |
| TS-20 | Acciones: Segregación rechazada por el servidor | Segregación rechazada | features/transfers/transfer-detail.test.tsx:150 |
| TS-21 | Acciones: Estado cambiado por otro usuario | Estado cambiado (2 GET del detalle) | features/transfers/transfer-detail.test.tsx:161 |
| TS-22 | Acciones: Doble clic en una acción de estado | Doble clic en Solicitar | features/transfers/transfer-detail.test.tsx:173 |
| TS-23 | Despacho: Despacho exitoso (+ OW «Confirmación en diálogo propio»: espía de `window.confirm` sin llamadas) | Despacho exitoso | features/transfers/transfer-detail.test.tsx:199 |
| TS-24 | Despacho: Despacho cancelado | `it.each` Cancelar, Escape | features/transfers/transfer-detail.test.tsx:213 |
| TS-25 | Despacho: Stock insuficiente al despachar · Lote vencido al despachar | `it.each` 2 códigos | features/transfers/transfer-detail.test.tsx:224 |
| TS-26 | Recepción: Recepción completa | Recepción completa | features/transfers/transfer-detail.test.tsx:255 |
| TS-27 | Recepción: Recepción parcial | Recepción parcial | features/transfers/transfer-detail.test.tsx:269 |
| TS-28 | Recepción: Cantidad recibida mayor que la enviada | Mayor que la enviada | features/transfers/transfer-detail.test.tsx:285 |
| TS-29 | Recepción: Doble clic en Confirmar recepción | Doble clic en Confirmar recepción | features/transfers/transfer-detail.test.tsx:295 |
| TS-30 | Anulación: Anulación exitosa | Anulación exitosa | features/transfers/transfer-detail.test.tsx:319 |
| TS-31 | Anulación: Anulación sin motivo | Anulación sin motivo | features/transfers/transfer-detail.test.tsx:332 |
| OW-25 | Mensajes de error: Error de campo junto al campo (en pantalla, `errors.reason` junto a "Motivo") | Error de campo del servidor | features/transfers/transfer-detail.test.tsx:341 |

| Cimiento | Prueba | Archivo:línea |
|---|---|---|
| CIM-1.2 red simulada | shell como auxiliar · registro de petición · control positivo sin manejador | test/http.test.tsx:10, :20, :37 |
| CIM-1.3 recursos (stock, kardex, catálogo) | bodegas · productos · lotes · existencias · rechazo · kardex | features/catalog/api.test.ts:8, :15, :21; features/inventory/api.test.ts:8, :16; features/kardex/api.test.ts:8 |
| CIM-1.3 recursos S3 (pacientes, prescripciones, vista previa, dispensación) | búsqueda · ficha · prescripción · vista previa · clave + `Idempotent-Replayed` · `shortages` | features/dispensations/api.test.ts:22, :29, :36, :47, :55, :75 |
| CIM-1.3 invalidación | escritura de stock invalida 4 raíces, no el catálogo | lib/query-keys.test.ts:8 |
| CIM-1.3 recursos S4 (traslados) | listado · detalle · creación con XSRF · 5 acciones con su cuerpo · rechazo con código | features/transfers/api.test.ts:15, :25, :32, :42, :56 |

## 2. Mutaciones de comprobación (no declaradas `[MUT]`; anti-falso-verde)

| n | Mutación | Aplicada → FALLA | Restaurada → PASA |
|---|---|---|---|
| a | `use-submit-guard.ts`: quitar `if (inFlight.current) return` | 2/4: Doble clic, Enter repetido | 4/4 |
| b | `require-ability.tsx`: guarda siempre permite | 1/9: Acceso directo sin capacidad | 9/9 |
| c | `transfer-draft.ts`: `eligibleStock` devuelve todas las filas (sin excluir vencidos ni vacías) | 9/52: Lotes vencidos fuera de la lista y las 8 que esperan una sola opción elegible | 52/52 |
| d | `transfer-detail-page.tsx`: sin recarga del detalle ante `invalid_transfer_transition` | 1/52 (misma corrida que c): Estado cambiado por otro usuario | 52/52 |

### 2.1 `[MUT]` declarados (archivo `features/dispensations/dispensation-confirm.test.tsx`, 26 pruebas)

| Id | Mutación | Aplicada → FALLA | Restaurada → PASA |
|---|---|---|---|
| M1 | `dispensation-form.tsx`: clave aleatoria nueva en cada intento en lugar de `intent.keyFor(...)` | 2/26: Reintento tras fallo de red · Reintento tras autorizador corregido | 26/26 |
| M2 | `dispensation-form.tsx`: sin `guard` y `pending={false}` en "Confirmar dispensación" | 1/26: Doble clic en Confirmar | 26/26 |
| M3 | `use-idempotent-intent.ts`: clave solo si no hay ninguna (`current.current === null`), sin comparar huella | 1/26: Cambio de cantidad genera clave nueva | 26/26 |

### 2.2 `[MUT]` M4 (archivo `features/transfers/transfer-detail.test.tsx`, 29 pruebas)

| Id | Mutación | Aplicada → FALLA | Restaurada → PASA |
|---|---|---|---|
| M4 | `transfer-rules.ts`: `SOLICITADO && canApprove` sin `!isRequester` (Aprobar para todo `transfers.approve`) | 1/29: Solicitante no ve Aprobar | 29/29 |

## 3. Comandos

| Comprobación | Comando | Resultado | Control positivo |
|---|---|---|---|
| Cierre | `npm run lint && npm run typecheck && npm test -- --run && npm run build` | lint 0, tsc 0, 102/103, build OK | — |
| Tipos regenerados (S3) | `npm run api:types` | 554 líneas en `api-schema.ts`, commit propio; `tsc -b` 0 | — |
| Delta de desarrollo (no suite completa) | `npx vitest --run src/features/dispensations` | 47/47 tras corregir el ayudante `openPatient` (esperaba el campo antes de montar la sesión) | — |
| Cierre S3 (ejecución 2 de 3) | `npm run lint && npm run typecheck && npm test -- --run && npm run build` | lint 0, tsc 0, 150/150 (22 archivos), build OK | — |
| Lectura del recuento | `npm test -- --run` repetido sobre el mismo árbol solo para leer el total (la salida del cierre se truncó) | 150/150 | — |
| Contrato 0.2 (S3) | `/usr/bin/grep -oE '"/(auth/me\|patients\|prescriptions\|dispensations\|stock\|kardex\|alerts\|transfers\|products\|lots\|warehouses)[a-z/{}_-]*"' api/openapi.json \| sort -u` | 14 rutas; faltan `alerts`, `transfers` | patrón de la tarea con `/api/` → 0 (rutas sin prefijo) |
| Cabeceras de idempotencia | `/usr/bin/grep -cE 'Idempotency-Key\|Idempotent-Replayed' api/openapi.json` | 3 (≥ 2) | — |
| Delta tras corregir prueba (aserción sobre estado transitorio) | `npx vitest --run src/components/submit-button.test.tsx` | 4/4 | — |
| Deriva de tipos (1.1) | `npm run api:types:check` | exit 0 | esquema generado desde copia con campo extra → `git diff --exit-code` exit 1 |
| Textos literales (6.3, adelantado; repetido con Dispensación) | `/usr/bin/grep -rnE --include='*.tsx' --exclude='*.test.tsx' '<patrón 6.3>' web/src` | 0 coincidencias | muestra de 2 líneas → 2 |
| Tipos regenerados (S4) | `npm run api:types` | 946 añadidas en `api-schema.ts`, commit propio `c793acb`; `tsc -b` 0 | — |
| Delta Traslados (desarrollo) | `npx vitest --run src/features/transfers` | 40/52 → 52/52 tras corregir 3 ayudantes de prueba (texto de carga tras la sesión, bodegas aún sin cargar, encabezado oculto por el diálogo) | — |
| Delta afectados (cierre de Traslados) | `npx vitest --run src/app src/App.test.tsx src/features/kardex src/lib/api-errors.test.ts src/lib/query-keys.test.ts src/features/transfers` | 111/111 (12 archivos) | — |
| Lint, tipos y build (S4) | `npm run lint && npm run typecheck && npm run build` | lint 0, tsc 0, build OK (aviso de bloque > 500 kB, sin división por ruta: no-objetivo del diseño) | — |
| Contrato 0.2 (S4) | mismo barrido de rutas sin `/api` | 22 rutas, 7 de `transfers`; falta `alerts` | `"url": "/api"` → 1 |
| `fetch` fuera del cliente | `/usr/bin/grep -rnE '(^\|[^a-zA-Z])fetch\(' web/src/features web/src/components` | 0 | `web/src/lib/api.ts` → 1 |
| Consola y almacenamiento | `/usr/bin/grep -rnE 'console\.\|localStorage\|sessionStorage' web/src` sin `.test.` | 0 | `src/app/providers.test.tsx` contiene `console` → 1 archivo; tras S3, `dispensation-confirm.test.tsx` (lee `localStorage`) → 1 archivo |

## 4. Humo renderizado (http://localhost:8090, contenedor `web` reconstruido)

| Rol | Menú | Pantalla | Resultado | Captura |
|---|---|---|---|---|
| auxiliar_farmacia | Inventario, Kardex | /inventory | 14 filas, 2 marcadas "Vencido", "Control especial" en morfina | captures/s6-inventario-auxiliar.png |
| auxiliar_farmacia | — | /inventory filtro bodega | solo la bodega elegida | captures/s6-inventario-filtrado-bodega.png |
| auxiliar_farmacia | — | /kardex | 15 movimientos, "Sistema", signo, "Página 1 de 1", enlace actual marcado | captures/s6-kardex-auxiliar.png |
| auxiliar_farmacia | — | /kardex producto + lote | URL `?product_id=1&lot_id=1`, 1 fila | captures/s6-kardex-filtro-producto-lote.png |
| auditor | Inventario, Kardex | /kardex?lot_id=abc&product_id=1 | sin error, solo botones Anterior/Siguiente | captures/s6-kardex-auditor-parametro-invalido.png |
| medico | ninguno | /inventory directo | "No tienes permiso para ver esta pantalla." + "Volver al inicio" | captures/s6-guarda-medico-inventario.png |
| admin | ninguno | / | sin menú | — |
| auxiliar_farmacia | Dispensación, Inventario, Kardex | /dispensations, CC 9999010001, FC, Acetaminofén 2 | vista previa L-ACE-2402:2 y "5 unidades en lotes vencidos no se usan."; 201 con `Idempotency-Key`; "Dispensación registrada" | captures/s6-dispensacion-vista-previa-fefo.png, captures/s6-dispensacion-registrada-auxiliar.png |
| auxiliar_farmacia + regente | — | /dispensations, CC 9999010002, FC, Morfina 1 | aviso de control especial y campos del regente; 201 con coautorización | captures/s6-dispensacion-control-especial-coautorizacion.png, captures/s6-dispensacion-controlado-registrada.png |
| auditor | Dispensación, Inventario, Kardex | /dispensations, "Sint" | "Datos enmascarados", `*******001`, sin fecha de nacimiento; 0 botones "Dispensar"; documento en claro ausente del texto | captures/s6-dispensacion-auditor-enmascarado.png |
| medico | Dispensación | /dispensations, TI 9999010003 | modo consulta: 0 botones "Dispensar" | captures/s6-dispensacion-medico-consulta.png |
| regente_farmacia | Dispensación, Traslados, Inventario, Kardex | / | saludo y cuatro accesos | captures/s6-inicio-accesos-regente.png |
| regente_farmacia | — | /transfers → Nuevo traslado | 4 lotes elegibles de Farmacia Central con vencimiento y disponible; 201; detalle #23 "Borrador" | captures/s6-traslados-nuevo-formulario.png |
| regente_farmacia (solicitante) | — | /transfers/23 → Solicitar | 200; "Solicitado"; 0 botones "Aprobar"; "Lo solicitaste tú: otro regente debe aprobarlo." | captures/s6-traslado-solicitado-sin-aprobar.png |
| regente2 (otro regente, sintético, creado por el admin vía `POST /api/users`) | Dispensación, Traslados, Inventario, Kardex | /transfers/23 → Aprobar | 200; "Aprobado"; "Aprobado por Regente Dos" | captures/s6-traslado-aprobado-otro-regente.png |
| auxiliar_farmacia | Dispensación, Traslados, Inventario, Kardex | /transfers | listado con estados en español | captures/s6-traslados-listado.png |
| auxiliar_farmacia | — | /transfers/23 → Despachar | diálogo "El stock saldrá de Farmacia Central y quedará en tránsito."; 200; "En tránsito" | captures/s6-traslado-despacho-dialogo.png |
| auxiliar_farmacia | — | /transfers/23 → Recibir 2 de 3 | aviso de discrepancia antes de confirmar; 200; "Recibido parcial"; discrepancia faltante 1 "Pendiente"; 0 claves en almacenamiento | captures/s6-traslado-recepcion-parcial-aviso.png, captures/s6-traslado-recibido-parcial-discrepancia.png |
| los 3 anteriores | — | URL tras abrir ficha | `/dispensations` sin consulta; foco inicial en la búsqueda; 0 claves en `localStorage`/`sessionStorage` | — |
