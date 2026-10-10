# Verification — add-admin-screens (S13, Tier B)

Árbol: `dev` en `3db9528` (producto y prueba; base `1d69d9a`). MUT: declarados 6, entregados 6.

## 0. Reparto de líneas

Fuente: `git diff --numstat 1d69d9a 3db9528 -- software/web` (agregadas / borradas), clase por nombre `*.test.*`; registro por `wc -l`.

| Clase | Archivos | Líneas |
|---|---|---|
| Producto | 17 archivos de `software/web/src` (pantallas, secciones, API, consultas, textos, tabla de pantallas, rutas, inicio, aviso de éxito) | +1202 / −24 |
| Prueba | 7 archivos `*.test.*` (`users-page`, `catalog-page`, `users/api`, `catalog/api`, `require-ability`, `shell-header`, `assistant-page`) | +1139 / −14 |
| Registro | `verification.md`, sección del implementador en `journal.md`, marcas de `tasks.md`, `mutants/M1..M6.patch` | ver § 7 |

## 1. Escenarios → pruebas

Rutas de prueba relativas a `software/web/src/`. Ancla = cláusula de rechazo → regla de la API o escenario vivo que la produce.

| Capacidad · requisito | Escenario | Prueba (archivo:línea) | Ancla |
|---|---|---|---|
| admin-screens · Acceso | Admin abre Usuarios desde el menú | `features/users/users-page.test.tsx:25` | — |
| admin-screens · Acceso | Admin abre Catálogo desde el inicio | `features/catalog/catalog-page.test.tsx:34` | — |
| admin-screens · Acceso | Otro rol escribe la dirección de Usuarios | `features/users/users-page.test.tsx:37` | — |
| admin-screens · Acceso | Otro rol escribe la dirección de Catálogo | `features/catalog/catalog-page.test.tsx:48` | — |
| admin-screens · Acceso | Acceso sin sesión | `features/users/users-page.test.tsx:47`, `features/catalog/catalog-page.test.tsx:59` | — |
| admin-screens · Lista de usuarios | Lista con los usuarios semilla | `features/users/users-page.test.tsx:102` | — |
| admin-screens · Lista de usuarios | Carga anunciada | `features/users/users-page.test.tsx:127` | — |
| admin-screens · Lista de usuarios | Lista vacía | `features/users/users-page.test.tsx:136` | — |
| admin-screens · Lista de usuarios | Fallo de red al listar | `features/users/users-page.test.tsx:143` | — |
| admin-screens · Lista de usuarios | El servidor niega la lista | `features/users/users-page.test.tsx:154` | identity-access, `users.index` 403 `forbidden` |
| admin-screens · Alta de usuario | Alta exitosa | `features/users/users-page.test.tsx:166` | — |
| admin-screens · Alta de usuario | Campos vacíos sin envío | `features/users/users-page.test.tsx:192` (3 casos) | — |
| admin-screens · Alta de usuario | Correo ya en uso | `features/users/users-page.test.tsx:211` | `software/api/app/Http/Requests/Users/StoreUserRequest.php:36` |
| admin-screens · Alta de usuario | Contraseña demasiado corta | `features/users/users-page.test.tsx:228` | `software/api/app/Http/Requests/Users/StoreUserRequest.php:37` |
| admin-screens · Alta de usuario | Doble clic produce un solo alta | `features/users/users-page.test.tsx:241` | — |
| admin-screens · Alta de usuario | Enter repetido | `features/users/users-page.test.tsx:265` | — |
| admin-screens · Alta de usuario | El servidor niega el alta | `features/users/users-page.test.tsx:284` | identity-access, `users.store` 403 `forbidden` |
| admin-screens · Alta de usuario | Fallo de red al crear | `features/users/users-page.test.tsx:296` | — |
| admin-screens · Alta de usuario | Sesión expirada al crear | `features/users/users-page.test.tsx:308` | app-shell «Sesión expirada y token CSRF vencido» |
| admin-screens · Contraseña nunca visible | Campo enmascarado | `features/users/users-page.test.tsx:351` | — |
| admin-screens · Contraseña nunca visible | Contraseña fuera del documento tras el alta | `features/users/users-page.test.tsx:361` (control positivo en la misma prueba) | — |
| admin-screens · Contraseña nunca visible | Contraseña fuera de la consola tras un fallo | `features/users/users-page.test.tsx:380` | — |
| admin-screens · Lista de bodegas y productos | Catálogo con los datos semilla | `features/catalog/catalog-page.test.tsx:119` | — |
| admin-screens · Lista de bodegas y productos | Producto sin presentación | `features/catalog/catalog-page.test.tsx:139` | — |
| admin-screens · Lista de bodegas y productos | Cargas anunciadas | `features/catalog/catalog-page.test.tsx:147` | — |
| admin-screens · Lista de bodegas y productos | Secciones vacías | `features/catalog/catalog-page.test.tsx:162` | — |
| admin-screens · Lista de bodegas y productos | Falla solo una sección | `features/catalog/catalog-page.test.tsx:170` | — |
| admin-screens · Alta de bodega | Alta exitosa de bodega | `features/catalog/catalog-page.test.tsx:193` | — |
| admin-screens · Alta de bodega | Bodega con campos vacíos | `features/catalog/catalog-page.test.tsx:210` (2 casos) | — |
| admin-screens · Alta de bodega | Código de bodega en uso | `features/catalog/catalog-page.test.tsx:228` | `software/api/app/Http/Requests/Catalog/StoreWarehouseRequest.php:25` |
| admin-screens · Alta de bodega | Doble clic produce una sola bodega | `features/catalog/catalog-page.test.tsx:245` | — |
| admin-screens · Alta de bodega | Fallo de red al crear la bodega | `features/catalog/catalog-page.test.tsx:264` | — |
| admin-screens · Edición de bodega | Cambio de nombre de bodega | `features/catalog/catalog-page.test.tsx:286` | — |
| admin-screens · Edición de bodega | Cancelar la edición de bodega | `features/catalog/catalog-page.test.tsx:303` | — |
| admin-screens · Edición de bodega | Nombre de bodega vaciado | `features/catalog/catalog-page.test.tsx:316` | — |
| admin-screens · Edición de bodega | Código de otra bodega | `features/catalog/catalog-page.test.tsx:329` | `software/api/app/Http/Requests/Catalog/UpdateWarehouseRequest.php:26` |
| admin-screens · Edición de bodega | Bodega que ya no existe | `features/catalog/catalog-page.test.tsx:345` | `openspec/specs/catalog/spec.md:70` «Bodega inexistente» |
| admin-screens · Edición de bodega | Doble clic al guardar la bodega | `features/catalog/catalog-page.test.tsx:357` | — |
| admin-screens · Alta de producto | Alta de producto de control especial | `features/catalog/catalog-page.test.tsx:389` | — |
| admin-screens · Alta de producto | Producto sin campos opcionales | `features/catalog/catalog-page.test.tsx:409` | — |
| admin-screens · Alta de producto | Producto con campos obligatorios vacíos | `features/catalog/catalog-page.test.tsx:429` (2 casos) | — |
| admin-screens · Alta de producto | Código de producto en uso | `features/catalog/catalog-page.test.tsx:445` | `software/api/app/Http/Requests/Catalog/StoreProductRequest.php:25` |
| admin-screens · Alta de producto | Doble clic produce un solo producto | `features/catalog/catalog-page.test.tsx:464` | — |
| admin-screens · Alta de producto | El servidor niega el alta de producto | `features/catalog/catalog-page.test.tsx:481` | catalog, `products.store` 403 `forbidden` |
| admin-screens · Edición de producto | Marcar un producto como control especial | `features/catalog/catalog-page.test.tsx:504` | — |
| admin-screens · Edición de producto | Quitar la presentación | `features/catalog/catalog-page.test.tsx:528` | — |
| admin-screens · Edición de producto | Cancelar la edición de producto | `features/catalog/catalog-page.test.tsx:545` | — |
| admin-screens · Edición de producto | Código de otro producto | `features/catalog/catalog-page.test.tsx:562` | `software/api/app/Http/Requests/Catalog/UpdateProductRequest.php:25` |
| admin-screens · Edición de producto | Producto que ya no existe | `features/catalog/catalog-page.test.tsx:578` | `openspec/specs/catalog/spec.md:131` «Producto inexistente» |
| admin-screens · Edición de producto | Doble clic al guardar el producto | `features/catalog/catalog-page.test.tsx:590` | — |
| assistant-screen · Roles de operación | Médico abre el asistente desde el menú | `features/assistant/assistant-page.test.tsx:57` (sin cambio) | — |
| assistant-screen · Roles de operación | Admin escribe la dirección del asistente | `features/assistant/assistant-page.test.tsx:89`; control positivo `:99` | — |
| operator-workspace · Navegación por rol | Auxiliar ve sus cuatro pantallas | `app/require-ability.test.tsx:78` | — |
| operator-workspace · Navegación por rol | Auditor ve las cuatro en lectura | `app/require-ability.test.tsx:83` | — |
| operator-workspace · Navegación por rol | Médico solo ve Dispensación | `app/require-ability.test.tsx:93` | — |
| operator-workspace · Navegación por rol | Admin sin pantallas de operación | `app/require-ability.test.tsx:98` | — |
| operator-workspace · Navegación por rol | Navegación con teclado | `app/require-ability.test.tsx:103` | — |
| operator-workspace · Inicio con accesos | Accesos del regente | `app/require-ability.test.tsx:130` | — |
| operator-workspace · Inicio con accesos | Admin sin accesos | `app/require-ability.test.tsx:170` | — |
| operator-workspace · Inicio con accesos | Médico sin accesos de inventario | `app/require-ability.test.tsx:190` | — |
| app-shell · Encabezado con sesión y cierre | Página de inicio sin pantallas aún | `app/shell-header.test.tsx:40` | — |

API (tarea 1.2), base de los escenarios de escritura:

| Función | Prueba (archivo:línea) |
|---|---|
| `listUsers`, `createUser` (cuerpo, `X-XSRF-TOKEN`, rechazo por campo) | `features/users/api.test.ts:10`, `:17`, `:33` |
| `createWarehouse`, `updateWarehouse`, `createProduct`, `updateProduct` | `features/catalog/api.test.ts:42`, `:53`, `:64`, `:75` |

## 2. Mutaciones

Comando de cada tarea `[MUT]` (2.4, 2.5, 3.5, 3.6, 3.7, 4.7) tal cual, desde la raíz, sobre `3db9528` con `git status --porcelain -- software/web` vacío; exit 0 en las seis.

| n | mutación | Aplicado → FALLA m/k: prueba (mensaje) | Restaurado → PASA k/k |
|---|---|---|---|
| M1 | fila "Usuarios" con `abilities: ABILITIES` (abierta a toda sesión) | 1/1: «Otro rol escribe la dirección de Usuarios» (no encuentra "No tienes permiso para ver esta pantalla.") | 1/1 |
| M2 | fila "Asistente" con `abilities: ABILITIES` | 1/1: «Admin escribe la dirección del asistente» (no encuentra el aviso de permiso) | 1/1 |
| M3 | contraseña `type="text"` | 1/1: «Campo enmascarado» (`toHaveAttribute("type", "password")`) | 1/1 |
| M4 | `useCreateUser` sin invalidar `['users']` | 1/1: «Alta exitosa» (no encuentra la celda "Nueva Auxiliar") | 1/1 |
| M5 | alta sin `useSubmitGuard`, solo `isPending` | 1/1: «Doble clic produce un solo alta» (2 POST, se esperaba 1) | 1/1 |
| M6 | edición de producto con `is_controlled: undefined` | 1/1: «Marcar un producto como control especial» (cuerpo sin `is_controlled`) | 1/1 |
| G0 | sin parche: primera versión de «Doble clic produce un solo alta» con dos `fireEvent.click` | M5 sobrevivió: el re-render dentro de `act` deshabilitó el botón entre clics | prueba reescrita con dos clics en el mismo `act` y respuesta propia por petición (`5c55158`) → M5 FALLA por conteo |

## 3. Comandos y conteos

| Tarea | Comando | Resultado |
|---|---|---|
| 0.1 | `npm --prefix software/web run api:types:check` | exit 0, `git status` sin cambios |
| 0.1 | `/usr/bin/grep -cE '"(users\.store\|warehouses\.update\|products\.update)"' software/web/src/lib/api-schema.ts` | 6 |
| 1.1 | `/usr/bin/grep -c 'Creando usuario…' software/web/src/lib/strings.ts` | 1 |
| 1.2 | `npx vitest run src/features/users/api.test.ts src/features/catalog/api.test.ts` | 2 archivos, 10 pasan |
| 2.1 rojo | comando de 2.1 | exit 0; 8 fallan, 54 pasan |
| 2.2 | 4 archivos de 2.1 + `shell-header.test.tsx` | 5 archivos, 69 pasan |
| 2.2 | `/usr/bin/grep -cE "path: '/(users\|catalog)'" software/web/src/app/screens.tsx` | 2 |
| 2.3 · 3.4 · 4.6 | `test -z "$(git status --porcelain -- software/web)" && git apply --check …/M*.patch` | exit 0 en los tres bloques |
| 3.1 rojo | comando de 3.1 | exit 0; 19 fallan, 3 pasan |
| 3.3 | `npx vitest run src/features/users/users-page.test.tsx` | 22 pasan |
| 4.1 rojo | comando de 4.1 | exit 0; 17 fallan, 3 pasan |
| 4.2 rojo | comando de 4.2 (`-t '[Pp]roducto'`) | exit 0; 18 fallan, 2 pasan, 13 omitidas |
| 4.4 | `npx vitest run src/features/catalog/catalog-page.test.tsx -t '[Bb]odega'` | 19 pasan, 14 omitidas |
| 4.5 | `npx vitest run src/features/catalog/catalog-page.test.tsx` | 33 pasan |
| 6.1 | `npm --prefix software/web run lint` | exit 0 |
| 6.1 | `npm --prefix software/web run typecheck` | exit 0 |
| 6.1 | `npm --prefix software/web test -- --run` (única corrida completa) | 32 archivos, 338 pasan, 0 fallan |
| 6.2 | `docker compose -f software/compose.yaml up -d --build --wait web` | exit 0; `web`, `api`, `db` Healthy |
| 6.2 | `curl -fsS http://localhost:8090/ready` | `{"status":"ready","checks":{"database":"ok","migrations":"ok"}}` |
| 6.2 | `curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:8090/users` · `/catalog` · `/assistant` | 200 · 200 · 200 |
| 6.2 control | `/usr/bin/grep -c 'Creando usuario'` y `'Medicamento de control especial'` sobre el `index-*.js` servido | 1 · 1 (el contenedor sirve la versión nueva) |

## 4. Barridos (tarea 5.1)

| Barrido | Comando | Resultado | Control positivo |
|---|---|---|---|
| Textos literales | `/usr/bin/grep -rnE --include='*.tsx' --exclude='*.test.tsx' '>[[:space:]]*[A-Za-zÁÉÍÓÚÑáéíóúñ¿¡][^<>{}]*<\|(aria-label\|placeholder\|title\|alt)="[^"]*[A-Za-zÁÉÍÓÚÑáéíóúñ]' software/web/src/features/users software/web/src/features/catalog` | 0 (exit 1) | `printf` de la tarea → 2 |
| Colores fijos | patrón de la tarea sobre `features/users`, `features/catalog` y `components/success-notice.tsx` | 0 (exit 1) | mismo patrón sobre `features/inventory/inventory-page.tsx` → 1 |
| Privacidad | `/usr/bin/grep -rnE --exclude='*.test.*' 'console\.\|localStorage\|sessionStorage'` sobre los mismos | 0 (exit 1) | mismo patrón sobre `src/test/console-spy.ts` → 1 |
| Archivos barridos | `find -L software/web/src/features/users software/web/src/features/catalog -type f \| wc -l` · enlaces `-type l` | 15 · 0 | — |
| Lint | `npm --prefix software/web run lint` | exit 0 | — |

## 5. Decisiones y desviaciones

| Decisión | Motivo | Medición |
|---|---|---|
| Casilla nativa `input type="checkbox"` con `accent-primary` en vez del `Checkbox` de shadcn | El `Checkbox` de Radix exige `ResizeObserver`, ausente en jsdom; añadirlo tocaba el arnés de pruebas (`setup.ts`) | con el kit, las pruebas de catálogo que montan la pantalla caen con `ResizeObserver is not defined`; con la casilla nativa 33 pasan (§ 3, 4.5); ningún archivo nuevo en `components/ui` |
| Variante `'session'` retirada de `Screen.abilities`; el asistente toma la unión de capacidades de las cuatro pantallas de operación | Una sola guarda para toda fila, sin costura nueva; el admin no tiene ninguna de esas capacidades | M2 rojo/verde (§ 2) |
| Edición en una fila adicional bajo la fila editada | La fila original sigue visible: «la fila no cambia» es comprobable mientras la edición está abierta | `catalog-page.test.tsx:329`, `:562` |
| Mutaciones terminan tras refrescar la lista (`onSuccess` devuelve `invalidateQueries`) | Confirmación y fila nueva aparecen juntas, sin parpadeo del valor anterior | M4 rojo/verde (§ 2) |
| `shell-header.test.tsx` actualizado (fuera de la lista de archivos de 2.1) | Afirmaba el acceso "Asistente" del admin, que el escenario modificado retira | `app/shell-header.test.tsx:40` |
| Commit de bloque 1 separado del bloque 2 | Historia en pasos pequeños; cada commit con su bloque verde | `8d14fef`, `f83e90c` |

## 6. Recorrido visual (tarea 6.3)

| Paso | Resultado |
|---|---|
| Recorrido en navegador del Orchestrator | pendiente |

## 7. Registro

| Archivo | `wc -l` |
|---|---|
| `verification.md` | 164 |
| `mutants/M1..M6.patch` | 111 |
| `journal.md` (sección del implementador) | 24 |
| `tasks.md` | 25 marcas `[x]`, sin líneas nuevas |
