# Verification — apply-brand-palette (S12, Tier C)

Árbol: `dev` en `e7177c0` (producto `d8b6872`, prueba y control `e7177c0`). MUT declarados: 0 (tier C).

## 0. Reparto de líneas

Fuente: `git diff --numstat f87875d e7177c0` filtrado por ruta (agregadas/borradas), y `wc -l` para el registro.

| Clase | Archivos | Líneas |
|---|---|---|
| Producto | `software/web/src/index.css` (solo valores de tokens y dos comentarios) | +63 / −59 |
| Prueba | `software/web/src/theme-contrast.test.ts` | +236 / −0 |
| Registro | `verification.md`, `journal.md` (sección del implementador), `tasks.md` (marcas), `controls/C1.patch` | ver § 6 |

## 1. Escenarios → pruebas

| Escenario | Prueba | Archivo:línea |
|---|---|---|
| Pantalla de inicio de sesión con la paleta | `tema claro: usa la paleta de la IPS` (fondo, texto, primario, texto del primario) · recorrido visual § 5 | `software/web/src/theme-contrast.test.ts:182` |
| Insignia secundaria en verde lima | `tema claro: usa la paleta de la IPS` (`secondary` = `#a9cd43`, `secondary-foreground` = `#232955`) · insignia de rol `variant="secondary"` en `software/web/src/app/shell-header.tsx:37` · recorrido visual § 5 | `software/web/src/theme-contrast.test.ts:182` |
| Verde lima sin texto sobre fondo claro | `tema claro: el verde lima no es token de texto ni de foco` | `software/web/src/theme-contrast.test.ts:192` |
| Sin elementos de marca | `git diff --exit-code -- software/web/src/lib/strings.ts` exit 0 (§ 4) · diff web limitado a `index.css` y la prueba (§ 4) · recorrido visual § 5 | — |
| Tokens oscuros derivados | `tema oscuro: derivado de la paleta (fondo azul marino oscuro, primario verde lima)` | `software/web/src/theme-contrast.test.ts:205` |
| Sin azul marino sobre azul marino | `tema oscuro: todos los pares cumplen AA (foco ≥ 3:1)` | `software/web/src/theme-contrast.test.ts:169` |
| Todos los pares cumplen AA | `tema claro: …` y `tema oscuro: todos los pares cumplen AA (foco ≥ 3:1)` (afirma además que se evaluaron todos los pares de la lista) | `software/web/src/theme-contrast.test.ts:169` |
| Par bajo AA hace fallar la suite | control C1 (§ 2) | `openspec/changes/apply-brand-palette/controls/C1.patch` |
| Prueba de contraste sin tokens no pasa en vacío | `sin tokens: un bloque vacío …` · `sin tokens: falta --secondary-foreground …` | `software/web/src/theme-contrast.test.ts:218`, `:225` |
| Destructivo conserva color y contraste | `tema {claro,oscuro}: el destructivo conserva el rojo` · pares `destructive/*` y `#ffffff/destructive` (§ 3) | `software/web/src/theme-contrast.test.ts:175`, `:169` |

## 2. Control negativo

Comando de la tarea 1.5, desde la raíz: `git apply …/C1.patch && (cd software/web && npx vitest run src/theme-contrast.test.ts; test $? -ne 0); git apply -R …/C1.patch && (cd software/web && npx vitest run src/theme-contrast.test.ts)`.

| n | mutación | Aplicada → FALLA m/k: prueba (mensaje) | Revertida → PASA k/k | Cadena |
|---|---|---|---|---|
| C1 | `:root --secondary-foreground: #232955` → `#ffffff` | 2/9: `tema claro: todos los pares cumplen AA` («par secondary-foreground/secondary en tema claro: razón 1.83:1 < 4.5:1») · `tema claro: usa la paleta de la IPS` («expected '#ffffff' to be '#232955'») | 9/9 | exit 0 |
| G0 | sin parche: `index.css?raw` con `css: false` de Vitest llegó vacío en la primera corrida | 8/9 rojas, la guarda nombró «tema claro: el bloque :root no tiene tokens» | lectura por `readFileSync` → corrida roja esperada de 1.1 (§ 4) | — |

## 3. Contraste por par y tema

Razón WCAG 2.x calculada con la misma fórmula de la prueba (luminancia relativa sRGB). Mínimo: 4,5:1 texto, 3:1 foco. Overlay oscuro = `dark:bg-destructive/60` de `badge.tsx:15` y `button.tsx:13`, compuesto en sRGB sobre el fondo indicado.

| Par (texto / fondo) | Claro: valores | Claro | Oscuro: valores | Oscuro |
|---|---|---|---|---|
| foreground / background | `#212b51` / `#f8f9fa` | 13,03 | `#f1f3f8` / `#12162d` | 16,05 |
| card-foreground / card | `#212b51` / `#ffffff` | 13,73 | `#f1f3f8` / `#1b2042` | 14,20 |
| popover-foreground / popover | `#212b51` / `#ffffff` | 13,73 | `#f1f3f8` / `#1b2042` | 14,20 |
| primary-foreground / primary | `#f8f9fa` / `#232955` | 13,11 | `#232955` / `#a9cd43` | 7,57 |
| primary / background | `#232955` / `#f8f9fa` | 13,11 | `#a9cd43` / `#12162d` | 9,76 |
| secondary-foreground / secondary | `#232955` / `#a9cd43` | 7,57 | `#f1f3f8` / `#2a3060` | 11,19 |
| muted-foreground / muted | `#4d5578` / `#eef0f4` | 6,38 | `#a9b0cc` / `#252a50` | 6,40 |
| muted-foreground / background | `#4d5578` / `#f8f9fa` | 6,90 | `#a9b0cc` / `#12162d` | 8,29 |
| muted-foreground / card | `#4d5578` / `#ffffff` | 7,28 | `#a9b0cc` / `#1b2042` | 7,33 |
| accent-foreground / accent | `#232955` / `#e6f0c8` | 11,62 | `#f1f3f8` / `#2a3060` | 11,19 |
| sidebar-foreground / sidebar | `#212b51` / `#ffffff` | 13,73 | `#f1f3f8` / `#1b2042` | 14,20 |
| sidebar-primary-foreground / sidebar-primary | `#f8f9fa` / `#232955` | 13,11 | `#232955` / `#a9cd43` | 7,57 |
| sidebar-accent-foreground / sidebar-accent | `#232955` / `#e6f0c8` | 11,62 | `#f1f3f8` / `#2a3060` | 11,19 |
| destructive / background | `#c62828` / `#f8f9fa` | 5,33 | `#f87171` / `#12162d` | 6,44 |
| destructive / card | `#c62828` / `#ffffff` | 5,62 | `#f87171` / `#1b2042` | 5,70 |
| `#ffffff` / destructive | `#ffffff` / `#c62828` | 5,62 | — | — |
| `#ffffff` / destructive@0.6·background | — | — | `#ffffff` / `#f87171`·`#12162d` | 5,82 |
| `#ffffff` / destructive@0.6·card | — | — | `#ffffff` / `#f87171`·`#1b2042` | 5,48 |
| ring / background (≥ 3:1) | `#4a5291` / `#f8f9fa` | 6,85 | `#a9cd43` / `#12162d` | 9,76 |
| **Mínimo de texto** | | **5,33** | | **5,48** |

## 4. Comandos y resultados

Corridas desde `software/web` salvo indicación.

| Paso | Comando | Resultado |
|---|---|---|
| 1.1 roja (tokens grises) | `npx vitest run src/theme-contrast.test.ts` | exit 1 · 3 failed / 9 (paleta clara, AA claro: `muted-foreground/muted` 4,34 y `ring/background` 2,59, oscuro derivado) |
| 1.2 guardas | `npx vitest run src/theme-contrast.test.ts -t "sin tokens"` | exit 0 · 2 passed, 7 skipped |
| 1.3 claro | `npx vitest run src/theme-contrast.test.ts -t "claro"` | exit 0 · 4 passed, 5 skipped |
| 1.4 completo | `npx vitest run src/theme-contrast.test.ts` | exit 0 · 9 passed |
| 1.5 control C1 | § 2 | exit 0 · rojo 2/9 · verde 9/9 |
| 1.6 lint | `npm run lint` | exit 0 |
| 1.6 tipos | `npm run typecheck` | exit 0 |
| 1.6 suite | `npx vitest run` | exit 0 · 29 archivos, 271 passed |
| 1.6 textos | `git diff --exit-code -- software/web/src/lib/strings.ts` (raíz) | exit 0 |
| Diff web | `git diff --stat f87875d e7177c0 -- software/web` | 2 archivos: `index.css`, `theme-contrast.test.ts` |
| Barrido verde lima | `/usr/bin/grep -rli 'a9cd43' software/web/src \| wc -l` (raíz) | 2 (`index.css`, `theme-contrast.test.ts`; control positivo: ambos esperados presentes) |
| Barrido `console.` en la prueba | `/usr/bin/grep -c 'console\.' software/web/src/theme-contrast.test.ts` | 0 · control: `/usr/bin/grep -rl 'console\.' software/web/src \| wc -l` = 1 |
| Barrido `catch` vacío en la prueba | `/usr/bin/grep -cE 'catch *(\([^)]*\))? *\{ *\}' software/web/src/theme-contrast.test.ts` | 0 · control: `printf 'try { x() } catch {}\n' \| /usr/bin/grep -cE …` = 1 |
| 2.1 imagen web | `docker compose -f software/compose.yaml up -d --build web && curl -fsS …/ready && curl -fsS -o /dev/null …/login` | BLOQUEADO: el build quedó en `[api] resolve image config for docker-image://docker.io/docker/dockerfile:1` y Docker Desktop cayó («Docker Desktop is unable to start»); `curl …/ready` «Connection reset by peer». Pendiente. |
| 2.2 rutas | `for r in /login / /dispensations /transfers /inventory /kardex /assistant; do curl …; done` | BLOQUEADO (mismo motivo). Pendiente. |

## 5. Recorrido visual (Orchestrator)

Stack reconstruido tras reiniciar Docker Desktop: `docker compose -f software/compose.yaml up -d --build --wait web`.
CSS servido `assets/index-63XT5Pii.css` con `--primary:#232955`, `--secondary:#a9cd43`, `--background:#f8f9fa`,
`--foreground:#212b51` (claro) y el bloque oscuro. Navegador, usuario `regente@dispensart.test`, 2026-10-09.

| Ruta | HTTP |
|---|---|
| `/login` | 200 |
| `/` | 200 |
| `/dispensations` | 200 |
| `/transfers` | 200 |
| `/inventory` | 200 |
| `/kardex` | 200 |
| `/assistant` | 200 |

| Pantalla | Paleta aplicada | Sin logo ni nombre comercial | Legibilidad de insignias y avisos | Captura |
|---|---|---|---|---|
| `/login` | Fondo `#f8f9fa`, botón azul marino con texto blanco | Sí: título «Dispensart» | Sin insignias | `captures/login.jpg` |
| `/` | Insignia de rol en verde lima con texto azul marino | Sí | Legible | `captures/inicio.jpg` |
| `/dispensations` | Pestaña activa y botón «Buscar» en verde lima con texto oscuro | Sí | Legible | `captures/dispensacion.jpg` |
| `/transfers` | Botón «Nuevo traslado» azul marino; pestaña activa verde lima | Sí | Insignias de estado legibles | `captures/traslados.jpg` |
| `/inventory` | «Bajo mínimo» verde lima con texto oscuro; «Vencido» rojo con texto blanco; filas resaltadas | Sí | «Vence en N días» ámbar con texto blanco: bajo AA, fuera de alcance (D-auv-8) | `captures/inventario.jpg` |
| `/kardex` | Pestaña activa verde lima; tabla con texto `#212b51` | Sí | Legible | `captures/kardex.jpg` |
| `/assistant` | Botón «Preguntar» azul marino; pestaña activa verde lima | Sí | Legible | `captures/asistente.jpg` |
