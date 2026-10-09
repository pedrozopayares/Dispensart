# Proposal — apply-brand-palette (S12, tier C)

## Why

La SPA usa la paleta gris por defecto de shadcn/ui. Llevar los colores públicos de la IPS a los tokens de tema
acerca la entrega al contexto real (parte D, frontend) sin tocar lógica, y fija por prueba que el cambio no baja
la legibilidad (contraste WCAG AA).

## What Changes

- Tema claro: tokens de `software/web/src/index.css` (`:root`) mapeados a la paleta pública de la IPS —
  azul marino `#232955` (primario), verde lima `#a9cd43` (secundario / acento), fondo `#f8f9fa`, texto `#212b51`.
- Tema oscuro (`.dark`): derivado de la misma paleta — superficies azul marino oscuras, texto claro, primario
  verde lima con texto azul marino.
- El verde lima nunca lleva texto sobre fondos claros (≈ 1,8:1 sobre blanco): solo relleno con texto azul marino
  o indicador. El foco (`ring`) usa un color con ≥ 3:1 sobre el fondo.
- Destructivo conserva su rojo y su contraste en ambos temas.
- Prueba Vitest nueva que lee los tokens del archivo de tema y exige ≥ 4,5:1 en cada par texto/fondo (≥ 3:1 en
  el foco), en claro y oscuro.
- Fuera de alcance: logo, nombre comercial, dependencias nuevas, reestructurar componentes, selector de tema.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `app-shell`: ADDED — paleta de la IPS en el tema claro, tema oscuro derivado y contraste AA de los pares de
  tokens fijado por prueba.

## Impact

- Código: `software/web/src/index.css` (valores de tokens) y una prueba nueva en `software/web/src`.
- Sin cambios de API, base de datos, dependencias, textos ni estructura de componentes.
- Reglas de negocio: ninguna RN-xx se toca (cambio presentacional). Parte de la prueba: D (frontend).
- Tier C. Disparador: solo valores de tokens CSS; sin rama, cómputo ni persistencia en producto. La prueba de
  contraste calcula, pero es código de prueba nuevo y no toca aislamiento, orden ni reporte del arnés.

## Assumptions

1. La paleta es información pública de marca (CSS `:root` de https://www.farmartips.com/, consultado
   2026-10-09), no datos de la IPS ni de pacientes (regla § 8 de la prueba): solo se usan los colores.
2. No existe selector de tema hoy (nada aplica la clase `dark`); el bloque `.dark` se deriva y se prueba, pero
   no se agrega selector.
3. Los pares que fija la prueba son los que usan los componentes: `foreground`/`background`, `card`, `popover`,
   `primary`, `secondary`, `muted` (también `muted-foreground` sobre `background`), `accent`, `sidebar`,
   `sidebar-primary`, `sidebar-accent`, `destructive` sobre `background` y `card`, y blanco sobre `destructive`
   (insignia y botón destructivos); `ring` sobre `background` a ≥ 3:1 (WCAG 1.4.11).
4. Los tokens de gráficos (`chart-*`), `border` e `input` se pueden ajustar al tono de la paleta pero no entran a
   la prueba de contraste: no llevan texto.
5. La insignia «Por vencer» del inventario (`bg-amber-500 text-white`, ≈ 2,2:1) es color fijo, no token: queda
   fuera de la fila S12 y se reporta como deuda.
