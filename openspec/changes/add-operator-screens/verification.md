# Verification — add-operator-screens (S6, tier B) — PARCIAL

Aplicación temprana: solo tareas cuya API existe (S1, S2). Grupos 2, 3, 4.2, 6 y 7 esperan S3–S5.
Prefijos: `OW` operator-workspace, `DS` dispensation-screen (nivel de hook), `INV` inventory-screen,
`KDX` kardex-screen, `CIM` cimiento sin escenario. Rutas de prueba relativas a `software/web/src/`.

## 0. Reparto de líneas

Comando: `git diff --numstat -- src package.json` sobre el árbol de trabajo antes de los commits de S6, separado con `awk` por ruta.

| Categoría | Alcance | Líneas añadidas |
|---|---|---|
| Producto — SPA | `src/**` sin pruebas ni `components/ui` | 1163 |
| Prueba — SPA | `src/**/*.test.*`, `src/test/**` | 1174 |
| Generado | `src/components/ui/{table,native-select,alert-dialog}.tsx` (shadcn CLI) | 370 |

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
| EXT-02 | Navegación por rol (parcial: Inventario, Kardex) | menú auxiliar, auditor, médico/admin, página actual | app/require-ability.test.tsx:67, :72, :77, :82 |

| Cimiento | Prueba | Archivo:línea |
|---|---|---|
| CIM-1.2 red simulada | shell como auxiliar · registro de petición · control positivo sin manejador | test/http.test.tsx:10, :20, :37 |
| CIM-1.3 recursos (stock, kardex, catálogo) | bodegas · productos · lotes · existencias · rechazo · kardex | features/catalog/api.test.ts:8, :15, :21; features/inventory/api.test.ts:8, :16; features/kardex/api.test.ts:8 |
| CIM-1.3 invalidación | escritura de stock invalida 4 raíces, no el catálogo | lib/query-keys.test.ts:8 |

## 2. Mutaciones de comprobación (no declaradas `[MUT]`; anti-falso-verde)

| n | Mutación | Aplicada → FALLA | Restaurada → PASA |
|---|---|---|---|
| a | `use-submit-guard.ts`: quitar `if (inFlight.current) return` | 2/4: Doble clic, Enter repetido | 4/4 |
| b | `require-ability.tsx`: guarda siempre permite | 1/9: Acceso directo sin capacidad | 9/9 |

## 3. Comandos

| Comprobación | Comando | Resultado | Control positivo |
|---|---|---|---|
| Cierre | `npm run lint && npm run typecheck && npm test -- --run && npm run build` | lint 0, tsc 0, 102/103, build OK | — |
| Delta tras corregir prueba (aserción sobre estado transitorio) | `npx vitest --run src/components/submit-button.test.tsx` | 4/4 | — |
| Deriva de tipos (1.1) | `npm run api:types:check` | exit 0 | esquema generado desde copia con campo extra → `git diff --exit-code` exit 1 |
| Textos literales (6.3, adelantado) | `/usr/bin/grep -rnE --include='*.tsx' --exclude='*.test.tsx' '<patrón 6.3>' web/src` | 0 coincidencias | muestra de 2 líneas → 2 |
| Consola y almacenamiento | `/usr/bin/grep -rnE 'console\.\|localStorage\|sessionStorage' web/src` sin `.test.` | 0 | `src/app/providers.test.tsx` contiene `console` → 1 archivo |

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
