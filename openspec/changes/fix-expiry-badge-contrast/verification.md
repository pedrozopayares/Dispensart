# Verification — fix-expiry-badge-contrast (S14, Tier C)

Árbol: `dev` en `a41100d` (producto y prueba). Base: `27e247a`. MUT declarados: 0 (tier C).

## 0. Reparto de líneas

Fuente: `git diff --numstat 27e247a a41100d` (agregadas/borradas); `wc -l` para los parches de control.

| Clase | Archivos | Líneas |
|---|---|---|
| Producto | `software/web/src/index.css` (par `--warning`/`--warning-foreground` en `:root` y `.dark`, mapeo `@theme inline`) | +7 / −0 |
| Producto | `software/web/src/features/inventory/inventory-page.tsx` (clases de la insignia) | +1 / −1 |
| Prueba | `software/web/src/theme-contrast.test.ts` | +34 / −0 |
| Prueba | `software/web/src/features/inventory/inventory-alerts.test.tsx` | +5 / −1 |
| Registro | `controls/C1.patch`, `controls/C2.patch` | 13 + 13 |
| Registro | `verification.md`, `journal.md` (sección del implementador), `tasks.md` (marcas) | — |

## 1. Escenarios → pruebas

| Escenario | Prueba | Archivo:línea |
|---|---|---|
| Insignia por vencer legible en ambos temas | `tema {claro,oscuro}: todos los pares cumplen AA (foco ≥ 3:1)` con el par `warning-foreground`/`warning` en `COMMON_PAIRS` | `software/web/src/theme-contrast.test.ts:180`, par en `:45` |
| Insignia por vencer con colores de tema | `Lote por vencer resaltado: "Vence en 20 días" solo en la fila de esa bodega` (`toHaveClass('bg-warning', 'text-warning-foreground')`, `not.toHaveClass('text-white')`) · control C2 | `software/web/src/features/inventory/inventory-alerts.test.tsx:82`, aserción en `:90` |
| Advertencia distinta de vencido y bajo mínimo | `tema {claro,oscuro}: la advertencia es ámbar, distinta de vencido y de bajo mínimo` (tono entre destructivo y lima con margen 15°, hex distinto de `destructive`, `secondary` y `#a9cd43`) | `software/web/src/theme-contrast.test.ts:192` |
| Par de advertencia bajo AA hace fallar la suite | control C1 (§ 2) | `openspec/changes/fix-expiry-badge-contrast/controls/C1.patch` |
| Token de advertencia ausente no pasa en vacío | `sin tokens: falta --warning en tema oscuro y el análisis falla nombrando tema y token` · corrida roja de 1.1 (§ 4) | `software/web/src/theme-contrast.test.ts:259` |

## 2. Controles negativos

Comando de la tarea 1.4, desde la raíz, por parche N y prueba T: `git apply …/CN.patch && (cd software/web && npx vitest run T; test $? -ne 0); git apply -R …/CN.patch && (cd software/web && npx vitest run T)`.

| n | mutación | Aplicada → FALLA m/k: prueba (mensaje) | Revertida → PASA k/k | Cadena |
|---|---|---|---|---|
| C1 | `:root --warning-foreground: #212b51` → `#ffffff` (sobre `#f59e0b`) | 1/12: `tema claro: todos los pares cumplen AA (foco ≥ 3:1)` («par warning-foreground/warning en tema claro: razón 2.15:1 < 4.5:1») | 12/12 | exit 0 |
| C2 | insignia `bg-warning text-warning-foreground` → `bg-amber-500 text-white` | 1/7: `Lote por vencer resaltado: …` («expect(element).toHaveClass("bg-warning text-warning-foreground")», recibido `… bg-amber-500 text-white`) | 7/7 | exit 0 |

## 3. Contraste y tono del par de advertencia

Razón WCAG 2.x con la fórmula de la prueba. Tono HSL con la función `hue` de la prueba.

| Par (texto / fondo) | Claro: valores | Claro | Oscuro: valores | Oscuro |
|---|---|---|---|---|
| warning-foreground / warning | `#212b51` / `#f59e0b` | 6,39 | `#232955` / `#fbbf24` | 8,28 |
| `#ffffff` / `#f59e0b` (insignia anterior, C1) | — | 2,15 | — | — |

| Token | Claro: valor | Claro: tono | Oscuro: valor | Oscuro: tono |
|---|---|---|---|---|
| destructive («Vencido») | `#c62828` | 0,0° | `#f87171` | 0,0° |
| warning («Vence en N días») | `#f59e0b` | 37,7° | `#fbbf24` | 43,3° |
| secondary («Bajo mínimo») | `#a9cd43` | 75,7° | `#2a3060` | — (azul marino; hex distinto) |

## 4. Comandos y resultados

Corridas desde `software/web` salvo indicación.

| Paso | Comando | Resultado |
|---|---|---|
| 1.1 roja (sin tokens) | `npx vitest run src/theme-contrast.test.ts` | exit 1 · 9 failed / 12 · mensajes «tema claro: falta el token --warning-foreground» y «tema oscuro: falta el token --warning-foreground» (el par resuelve primero el texto) |
| 1.2 verde | `npx vitest run src/theme-contrast.test.ts` | exit 0 · 12 passed |
| 1.3 alertas | `npx vitest run src/features/inventory/inventory-alerts.test.tsx` | exit 0 · 7 passed |
| 1.3 barrido | `/usr/bin/grep -c 'bg-amber-500' software/web/src/features/inventory/inventory-page.tsx` (raíz) | 0 · control positivo `/usr/bin/grep -c 'bg-amber-100'` mismo archivo → 1 |
| 1.4 C1 | § 2 | exit 0 · rojo 1/12 · verde 12/12 |
| 1.4 C2 | § 2 | exit 0 · rojo 1/7 · verde 7/7 |
| 1.5 lint | `npm run lint` | exit 0 |
| 1.5 tipos | `npm run typecheck` | exit 0 |
| 1.5 suite | `npx vitest run` | exit 0 · 29 archivos, 274 passed |
| 1.5 textos | `git diff --exit-code -- software/web/src/lib/strings.ts` (raíz) | exit 0 |
| 2.1 imagen | `docker compose -f software/compose.yaml up -d --build --wait web && curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:8090/inventory` (raíz) | exit 0 · 200 |
| 2.1 CSS servido | `curl -fsS http://localhost:8090/assets/index-CVu-b6rT.css`, `/usr/bin/grep -oE` por patrón | `.bg-warning` 1 · `.text-warning-foreground` 1 · `--warning:#f59e0b` 1 · `--warning-foreground:#212b51` 1 · `.bg-amber-500` 0 (control positivo `.bg-amber-100` → 1) |

## 5. Barridos de higiene

| Barrido | Comando (raíz) | Resultado | Control positivo |
|---|---|---|---|
| Color fijo en la pantalla | `/usr/bin/grep -cE 'bg-amber-500\|text-white' software/web/src/features/inventory/inventory-page.tsx` | 0 | mismo patrón sobre `controls/C2.patch` → 1 |
| Hex literal en la pantalla | `/usr/bin/grep -cE '#[0-9a-fA-F]{3,6}\b' software/web/src/features/inventory/inventory-page.tsx` | 0 | mismo patrón sobre `software/web/src/index.css` → 64 |
| Dependencias nuevas | `git diff --name-only 27e247a a41100d -- software/web/package.json software/web/package-lock.json \| wc -l` | 0 | `git diff --name-only 27e247a a41100d \| wc -l` → 4 |
| `console` agregado | `git diff 27e247a a41100d \| /usr/bin/grep -cE '^\+.*console\.'` | 0 | `printf '+console.log(x)\n' \| /usr/bin/grep -cE '^\+.*console\.'` → 1 |

## 6. Recorrido visual (Orchestrator, tarea 2.2)

| Pantalla | Tema | Captura | Insignia «Vence en N días» legible y ámbar | Distinta de «Vencido» y «Bajo mínimo» | Resto sin cambios |
|---|---|---|---|---|---|
| `/inventory` | claro | `captures/inventario.jpg` (pendiente) | pendiente | pendiente | pendiente |
