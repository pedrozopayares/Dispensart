# Verification — add-assistant-screen (tier B)

Prefijos: `AS` assistant-screen, `OW` operator-workspace, `SH` app-shell, `CIM` cimiento. Rutas de prueba relativas a
`software/web/src/`. Árbol verificado: `2cf1cfa` (producto y pruebas) más este registro.

## 0. Reparto de líneas

| Categoría | Alcance | Comando | Añadidas / quitadas |
|---|---|---|---|
| Producto | `src/**` sin pruebas | `git diff --numstat e004496^ 2cf1cfa -- software/web`, clasificado con `awk` por ruta | +403 / −56 |
| Prueba | `src/**/*.test.*` | mismo comando | +711 / −25 |
| Registro | `*.md` del cambio y `specs/*/spec.md` | `wc -l` tras escribir este archivo | 568 líneas |
| Mutantes | `mutants/M1–M3.patch` | `wc -l mutants/*.patch` | 54 líneas |

## 1. Matriz escenario → prueba → archivo:línea

| Id | Escenario | Prueba | Archivo:línea |
|---|---|---|---|
| AS-01 | Pantalla para toda sesión: Médico abre el asistente desde el menú | Médico abre el asistente desde el menú | features/assistant/assistant-page.test.tsx:57 |
| AS-02 | Pantalla para toda sesión: Apertura sin preguntas enviadas | Apertura sin preguntas enviadas | features/assistant/assistant-page.test.tsx:70 |
| AS-03 | Pantalla para toda sesión: Acceso directo sin sesión | Acceso directo sin sesión | features/assistant/assistant-page.test.tsx:78 |
| AS-04 | Envío: Pregunta enviada | Pregunta enviada | features/assistant/assistant-page.test.tsx:92 |
| AS-05 | Envío: Pregunta vacía | `it.each` `''`, `'   '` | features/assistant/assistant-page.test.tsx:110 |
| AS-06 | Envío: Pregunta demasiado corta | Pregunta demasiado corta (Enter) | features/assistant/assistant-page.test.tsx:122 |
| AS-07 | Envío: Tope de 500 caracteres | Tope de 500 caracteres | features/assistant/assistant-page.test.tsx:133 |
| AS-08 | Envío: Doble clic produce una sola pregunta | Doble clic produce una sola pregunta | features/assistant/assistant-page.test.tsx:145 |
| AS-09 | Envío: Enter repetido | Enter repetido | features/assistant/assistant-page.test.tsx:161 |
| AS-10 | Envío: Salto de línea sin envío | Shift+Enter no anula el salto (+ control positivo: Enter sí lo anula y envía) | features/assistant/assistant-page.test.tsx:176 |
| AS-11 | Resultado: Pregunta respondida | Pregunta respondida (líneas en elementos distintos, `data-variant="default"`) | features/assistant/assistant-page.test.tsx:195 |
| AS-12 | Resultado: Sin resultados | Sin resultados (`data-variant="outline"`) | features/assistant/assistant-page.test.tsx:212 |
| AS-13 | Resultado: Pregunta fuera de alcance | Pregunta fuera de alcance | features/assistant/assistant-page.test.tsx:225 |
| AS-14 | Resultado: Pregunta sobre un paciente | Pregunta sobre un paciente (texto completo de la entrada) | features/assistant/assistant-page.test.tsx:238 |
| AS-15 | Resultado: Rol sin permiso para la consulta | Rol sin permiso para la consulta | features/assistant/assistant-page.test.tsx:254 |
| AS-16 | Resultado: Sin respuesta | Sin respuesta | features/assistant/assistant-page.test.tsx:273 |
| AS-17 | Resultado: Respuesta con marcado no se interpreta | Respuesta con marcado no se interpreta | features/assistant/assistant-page.test.tsx:285 |
| AS-18 | Consultas: Consulta de existencias | Consulta de existencias | features/assistant/assistant-page.test.tsx:299 |
| AS-19 | Consultas: Estado de traslado en español | Estado de traslado en español | features/assistant/assistant-page.test.tsx:318 |
| AS-20 | Consultas: Llamada negada | Llamada negada | features/assistant/assistant-page.test.tsx:332 |
| AS-21 | Consultas: Herramienta fuera del catálogo | Herramienta fuera del catálogo | features/assistant/assistant-page.test.tsx:351 |
| AS-22 | Consultas: Sin consultas | Sin consultas | features/assistant/assistant-page.test.tsx:369 |
| AS-23 | Historial: Dos preguntas seguidas | Dos preguntas seguidas | features/assistant/assistant-page.test.tsx:379 |
| AS-24 | Historial: Undécima pregunta | Undécima pregunta | features/assistant/assistant-page.test.tsx:396 |
| AS-25 | Historial: Salir y volver vacía el historial | Salir y volver vacía el historial | features/assistant/assistant-page.test.tsx:408 |
| AS-26 | Historial: Pregunta con un documento fuera del navegador persistente | espía de consola + ambos almacenamientos + ubicación del router y de la ventana (control positivo: el barrido halla el documento en la caja antes de enviar) | features/assistant/assistant-page.test.tsx:427 |
| AS-27 | Errores: Validación del servidor | Validación del servidor | features/assistant/assistant-page.test.tsx:460 |
| AS-28 | Errores: Demasiadas preguntas | Demasiadas preguntas · catálogo | features/assistant/assistant-page.test.tsx:480; lib/api-errors.test.ts:72 |
| AS-29 | Errores: Asistente no disponible | Asistente no disponible · catálogo | features/assistant/assistant-page.test.tsx:492; lib/api-errors.test.ts:77 |
| AS-30 | Errores: Fallo de red | `it.each` red, `server_error` | features/assistant/assistant-page.test.tsx:507 |
| AS-31 | Errores: Código desconocido | Código desconocido · catálogo (sin cambio) | features/assistant/assistant-page.test.tsx:520; lib/api-errors.test.ts:43 |
| AS-32 | Errores: Sesión expirada al preguntar | Sesión expirada al preguntar · cliente | features/assistant/assistant-page.test.tsx:530; features/assistant/api.test.ts:40 |
| AS-33 | Errores: Error anterior se limpia | Error anterior se limpia | features/assistant/assistant-page.test.tsx:549 |
| AS-34 | Ejemplos: Ejemplo rellena la caja | Ejemplo rellena la caja | features/assistant/assistant-page.test.tsx:569 |
| AS-35 | Ejemplos: Ejemplos disponibles | Ejemplos disponibles (+ barrido estático de textos § 3) | features/assistant/assistant-page.test.tsx:581 |
| AS-36 | Ejemplos: Ejemplo durante una consulta | Ejemplo durante una consulta (+ control positivo: habilitados al terminar) | features/assistant/assistant-page.test.tsx:597 |
| OW-01 | Navegación por rol: Auxiliar ve sus cuatro pantallas | Auxiliar ve sus cuatro pantallas y al final "Asistente" | app/require-ability.test.tsx:76 |
| OW-02 | Navegación por rol: Auditor ve las cuatro en lectura | Auditor ve las cuatro y al final "Asistente" | app/require-ability.test.tsx:81 |
| OW-03 | Navegación por rol: Médico solo ve Dispensación | Médico solo ve Dispensación, seguida de "Asistente" | app/require-ability.test.tsx:91 |
| OW-04 | Navegación por rol: Admin sin pantallas de operación | Admin: solo "Asistente" | app/require-ability.test.tsx:96 |
| OW-05 | Navegación por rol: Navegación con teclado (sin cambio) | abrir Kardex desde el menú | app/require-ability.test.tsx:101 |
| OW-06 | Inicio con accesos del rol: Accesos del regente | Accesos del regente (+ regente en el menú :86) | app/require-ability.test.tsx:128 |
| OW-07 | Inicio con accesos del rol: Admin sin accesos | Admin sin accesos de operación: un solo acceso, Asistente | app/require-ability.test.tsx:168 |
| OW-08 | Inicio con accesos del rol: Médico sin accesos de inventario | Médico: Dispensación y Asistente | app/require-ability.test.tsx:186 |
| SH-01 | Encabezado: Etiqueta de rol en español (sin cambio) | Etiqueta de rol en español | app/shell-header.test.tsx:30 |
| SH-02 | Encabezado: Página de inicio sin pantallas aún | saludo y acceso "Asistente", sin los dos textos de estado vacío | app/shell-header.test.tsx:40 |
| SH-03 | Encabezado: Página de inicio con saludo | Página de inicio con saludo (5 accesos, sin estado vacío) | app/require-ability.test.tsx:177 |
| SH-04 | Encabezado: Cierre exitoso (sin cambio) | Cierre exitoso | app/shell-header.test.tsx:50 |
| SH-05 | Encabezado: Doble clic en cerrar sesión (sin cambio) | Doble clic en cerrar sesión | app/shell-header.test.tsx:63 |
| SH-06 | Encabezado: Cierre fallido (sin cambio) | `it.each` cierre fallido | app/shell-header.test.tsx:80 |
| SH-07 | Encabezado: Otro usuario no ve datos del anterior (sin cambio) | Otro usuario no ve datos del anterior | app/shell-header.test.tsx:107 |

| Cimiento | Prueba | Archivo:línea |
|---|---|---|
| CIM-0.1 contrato sin cambio | `npm run api:types:check` (§ 3) | — |
| CIM-1.2 cliente | método, ruta, `X-XSRF-TOKEN`, cuerpo `{"question": …}` · reintento único ante `csrf_token_mismatch` | features/assistant/api.test.ts:11, :24 |

## 2. `[MUT]` declarados (archivo `features/assistant/assistant-page.test.tsx`)

Declarados 3, entregados 3. Parche en `mutants/M<n>.patch`; aplicado con `git apply`, restaurado con `git apply -R`.

| n | Mutación | Aplicada → FALLA | Restaurada → PASA |
|---|---|---|---|
| M1 | `assistant-page.tsx`: sin `guard(...)` (función inmediata con `release` vacío); queda solo `isPending` en el botón | 1/38: Enter repetido | 38/38 |
| M2 | `assistant-labels.ts`: `toolLabel` devuelve `tool` crudo fuera del catálogo | 1/38: Herramienta fuera del catálogo | 38/38 |
| M3 | `assistant-answer.tsx`: `answer` con `dangerouslySetInnerHTML` | 2/38: Respuesta con marcado no se interpreta · Pregunta respondida | 38/38 |

**Decisión (M1).** La tarea 2.7 nombra «Doble clic» como prueba que muere; con M1 aplicada sobrevive y muere «Enter
repetido» del mismo requisito. Causa: el envío limpia el error previo (`setSubmitError(null)`), ese re-render lee la
instantánea ya `pending` de la mutación y deshabilita el botón antes del segundo clic, así que el clic doble lo frena
el botón deshabilitado. Enter llega a una caja que sigue habilitada: solo el candado síncrono lo frena. El pin
queda en «Enter repetido»; no se fuerza la prueba de doble clic para que muera con M1.

## 3. Comandos

| Comprobación | Comando | Resultado | Control positivo |
|---|---|---|---|
| Contrato 0.1 | `npm --prefix software/web run api:types:check` | exit 0 (sin diferencias) | `/usr/bin/grep -c 'assistant.ask' software/web/src/lib/api-schema.ts` → 3 |
| Delta 1.1–1.2 | `npx vitest --run features/assistant lib/api-errors.test.ts` | 15/15 (2 archivos) | — |
| Delta navegación e inicio | `npx vitest --run src/app src/App.test.tsx` | 35/35 (5 archivos) | — |
| Delta pantalla | `npx vitest --run src/features/assistant` | 41/41 tras 4 ajustes de prueba (encabezado h3 dentro de la entrada, dos alertas en validación, esperar `/login` antes del aviso) | — |
| Textos literales 4.1 | `/usr/bin/grep -rnE --include='*.tsx' --exclude='*.test.tsx' '<patrón de 4.1>' software/web/src/features/assistant` | 0 (exit 1) | `printf '<p>Texto literal</p>\n<input placeholder="Buscar" />\n' \| /usr/bin/grep -cE '<patrón de 4.1>'` → 2 |
| Archivos leídos por el barrido | `find -L software/web/src/features/assistant -type f -not -name '*.test.tsx' -print0 \| xargs -0 /usr/bin/grep -c 'strings'` | 6 archivos leídos de 6 presentes sin `.test.tsx`; 3 con `strings` (página, respuesta, etiquetas) | `find … -type f \| wc -l` → 7 (incluye la prueba de la pantalla) |
| Privacidad 4.1 | `/usr/bin/grep -rnE 'console\.\|localStorage\|sessionStorage' software/web/src/features/assistant --exclude='*.test.tsx'` | 0 (exit 1) | mismo patrón con `-c` sobre `software/web/src/test/console-spy.ts` → 1 |
| `fetch` fuera del cliente | `/usr/bin/grep -rnE '(^\|[^a-zA-Z])fetch\(' software/web/src/features/assistant` | 0 (exit 1) | `software/web/src/lib/api.ts` → 1 (S6) |
| Ancla de transporte | barrido de `CYCLE-TIERS.md` sobre `specs/` | 1 acierto: AS-07 «Tope de 500», sin estado HTTP | 51 líneas `THEN` leídas |
| **Cierre 5.1 (corrida completa única)** | `npm run lint` · `npm run typecheck` · `npm test -- --run` | exit 0 · exit 0 · exit 0 | — |
| Lectura del recuento | `npm test -- --run` sobre el mismo árbol (`2cf1cfa`), solo para leer el total (la salida del cierre se cortó con `tail`) | **262/262 (28 archivos)**, exit 0 | — |
| Imagen `web` | `docker compose up -d --build web` (desde `software/`) | contenedor reconstruido y arrancado | — |
| SPA servida | `curl -fsS http://localhost:8090/ready` · `curl -fsS http://localhost:8090/assistant` | 200 · 200 `text/html` con `<div id="root">` | paquete servido contiene "Asistente de inventario", `/assistant/ask`, "Herramienta fuera del catálogo" → 1 cada uno |

## 4. Ancla de transporte

| Cláusula THEN | Ruta archivo:línea |
|---|---|
| AS-07 «Tope de 500 caracteres»: la pregunta enviada tiene 500 caracteres | `software/api/app/Http/Requests/Assistant/AskAssistantRequest.php:20` (`'min:3', 'max:500'`); ruta `software/api/routes/api.php:98` |

## 5. Humo sobre el stack (tarea 5.2)

| Rol | Menú | Pregunta | Etiqueta mostrada | Captura |
|---|---|---|---|---|
| — | — | — | pendiente: recorrido del Orchestrator en el navegador | — |
