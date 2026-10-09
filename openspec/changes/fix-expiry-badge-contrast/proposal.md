# Proposal — fix-expiry-badge-contrast (S14, tier C)

## Why

En `/inventory`, la insignia «Vence en N días» usa un ámbar fijo con texto blanco (≈ 2,15:1), por debajo de WCAG
AA (4,5:1), y no pasa por la prueba de contraste de S12 porque no es token de tema. Salda D-auv-8.

## What Changes

- La insignia «Vence en N días» toma texto y fondo de un par de tokens de tema de advertencia, definido en tema
  claro y oscuro, con contraste ≥ 4,5:1 (p. ej. texto azul marino `#212b51` sobre ámbar `#f59e0b` ≈ 6,39:1, o
  texto blanco sobre ámbar oscuro `#b45309` ≈ 5,02:1).
- Conserva su significado de advertencia: ámbar, distinto del rojo de «Vencido» y del verde lima de «Bajo mínimo».
- La prueba de contraste de la SPA evalúa ese par en ambos temas; un par bajo AA hace fallar la suite.
- Fuera de alcance: el resaltado de fila ámbar, los textos, otras insignias y cualquier otro cambio visual.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `inventory-screen`: ADDED — contraste AA de la insignia de vencimiento próximo, fijado por prueba.

## Impact

- Código: `software/web/src/index.css` (par de tokens de advertencia y su mapeo), la insignia en
  `software/web/src/features/inventory/inventory-page.tsx`, y las pruebas `software/web/src/theme-contrast.test.ts`
  e `inventory-alerts.test.tsx`.
- Sin cambios de API, base de datos, dependencias ni textos.
- Reglas de negocio: ninguna RN-xx cambia; RN-11 (la alerta sale solo de la API) queda intacta. Parte D (frontend).
- Tier C. Disparador: valores de tokens CSS y clases de una insignia; sin rama, cómputo ni persistencia en
  producto. La prueba suma pares a un arnés existente, sin tocar su aislamiento, orden ni reporte.

## Assumptions

1. Nombre sugerido del par: `--warning` / `--warning-foreground`; el implementador puede nombrarlo distinto y los
   comandos de `tasks.md` se ajustan.
2. Valores finales a elección del implementador, siempre que cumplan ≥ 4,5:1 en ambos temas y sean ámbar.
3. El resaltado de fila (`bg-amber-100`, `dark:bg-amber-950/40`) no lleva texto propio sobre color saturado y
   queda como está.
4. Sin selector de tema (como en S12): el tema oscuro se fija por prueba; la revisión visual es en tema claro.
