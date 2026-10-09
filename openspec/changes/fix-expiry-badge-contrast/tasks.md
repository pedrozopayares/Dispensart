# Tasks — fix-expiry-badge-contrast (S14, tier C)

Tier C: sin `design.md`, sin `[MUT]`, solo corridas delta. Controles negativos por parche
en `controls/`. Nombres de token sugeridos `warning` / `warning-foreground` (si cambian, se ajustan los comandos).

## 1. Par de advertencia y prueba (web)

- [ ] 1.1 En `software/web/src/theme-contrast.test.ts`, sumar a los pares de ambos temas `warning-foreground`/`warning` (≥ 4,5:1) y un caso que afirme, en claro y oscuro, que `warning` es ámbar (tono entre rojo y verde lima) y distinto de `destructive` y de `secondary`. Verificar (rojo esperado: los tokens aún no existen): `cd software/web && npx vitest run src/theme-contrast.test.ts` → exit ≠ 0 con "falta el token --warning". Escenarios: «Token de advertencia ausente no pasa en vacío», «Insignia por vencer legible en ambos temas».
- [ ] 1.2 En `software/web/src/index.css`, definir `--warning` y `--warning-foreground` en `:root` y `.dark` (ámbar, ≥ 4,5:1) y mapearlos en `@theme inline` como `--color-warning` y `--color-warning-foreground`. Solo esos tokens. Verificar: `cd software/web && npx vitest run src/theme-contrast.test.ts` → exit 0. Escenarios: «Insignia por vencer legible en ambos temas», «Advertencia distinta de vencido y bajo mínimo».
- [ ] 1.3 En `inventory-page.tsx`, la insignia "Vence en" usa las clases del par de advertencia en lugar de `bg-amber-500 text-white`; el resaltado de fila no cambia. En `inventory-alerts.test.tsx`, el caso «Lote por vencer resaltado» (escenario vivo de `inventory-screen`) afirma que la insignia lleva `bg-warning` y `text-warning-foreground` y no `text-white`. Verificar: `cd software/web && npx vitest run src/features/inventory/inventory-alerts.test.tsx` → exit 0; `/usr/bin/grep -c 'bg-amber-500' software/web/src/features/inventory/inventory-page.tsx` → 0, con control positivo `/usr/bin/grep -c 'bg-amber-100' software/web/src/features/inventory/inventory-page.tsx` → ≥ 1. Escenario: «Insignia por vencer con colores de tema».
- [ ] 1.4 Controles negativos: escribir `openspec/changes/fix-expiry-badge-contrast/controls/C1.patch` (pone `#ffffff` en `--warning-foreground` de `:root` sobre ámbar `#f59e0b`) y `controls/C2.patch` (devuelve la insignia a `bg-amber-500 text-white`). Verificar desde la raíz, por parche N y prueba T (C1 → `src/theme-contrast.test.ts`, C2 → `src/features/inventory/inventory-alerts.test.tsx`): `git apply openspec/changes/fix-expiry-badge-contrast/controls/CN.patch && (cd software/web && npx vitest run T; test $? -ne 0); git apply -R openspec/changes/fix-expiry-badge-contrast/controls/CN.patch && (cd software/web && npx vitest run T)` → exit 0; la salida roja de C1 nombra par de advertencia, tema claro y razón. Escenarios: «Par de advertencia bajo AA hace fallar la suite», «Insignia por vencer con colores de tema».
- [ ] 1.5 Corrida delta: `cd software/web && npm run lint && npm run typecheck && npx vitest run` → exit 0; `git diff --exit-code -- software/web/src/lib/strings.ts` → exit 0 (textos sin cambios). Escenarios: «Insignia por vencer legible en ambos temas», «Advertencia distinta de vencido y bajo mínimo».

## 2. Verificación integrada

- [ ] 2.1 Reconstruir la web: `docker compose -f software/compose.yaml up -d --build --wait web && curl -fsS -o /dev/null -w '%{http_code}\n' http://localhost:8090/inventory` → exit 0 y 200. Escenario: «Insignia por vencer con colores de tema».
- [ ] 2.2 Revisión visual del Orchestrator en `/inventory` (tema claro, datos semilla con lote por vencer): insignia "Vence en N días" legible, ámbar, distinta de "Vencido" y "Bajo mínimo"; resto de la pantalla sin cambios. Entregable: `openspec/changes/fix-expiry-badge-contrast/captures/inventario.jpg` y fila en `verification.md`. Escenarios: «Insignia por vencer con colores de tema», «Advertencia distinta de vencido y bajo mínimo».

## Workflow follow-up

- GATE 2 por `final-auditor` (delta sobre el diff), luego `/archive fix-expiry-badge-contrast` y saldar D-auv-8 en
  `openspec/DEBT.md`.
