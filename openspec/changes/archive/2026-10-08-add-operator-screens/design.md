# Design — add-operator-screens (S6, tier B)

## Context

Motivación en `proposal.md`; requisitos en `specs/`. Hereda de S1 `react-router` v7 (D7), cliente `src/lib/api.ts`
con `ApiError {status, code, errors}` (D8) y `software/api/openapi.json` exportado por Scramble con control de deriva
(D9); de S3 `Idempotency-Key` y la precedencia de códigos (D4, D5); de S4 las acciones de traslado. Sin cambios de API.

## Goals / Non-Goals

**Goals**: costuras únicas que las cuatro pantallas reutilizan (tipos, red simulada, clave por intención, catálogo de
mensajes, guarda) para que cada escenario se pruebe en el borde HTTP. **Non-Goals**: división de código por ruta
(cuatro pantallas, un bundle), estado global fuera de TanStack Query, E2E en navegador, internacionalización.

## Decisions

### D1. Tipos desde OpenAPI: `openapi-typescript` (dev) + envoltura propia, sin cliente nuevo
`gen:api` = `openapi-typescript ../api/openapi.json -o src/lib/api-schema.d.ts` (archivo versionado; la imagen web no
necesita la API). `check:api` = mismo comando con `--check`; `typecheck` pasa a `npm run check:api && tsc -b`, así la
deriva falla en local y en el paso de tipos que CI ya corre (sin tocar `.github/`). Cadena: código → `openapi.json`
(S1 D9) → `api-schema.d.ts` → `tsc`. `src/lib/api-types.ts` expone ayudantes por ruta (`ResponseOf<'/api/x','get'>`,
`BodyOf<…>`), nunca nombres de componente de Scramble (inestables). Lo que Scramble no infiera (`shortages`, cabecera
`Idempotent-Replayed`) se tipa a mano allí, listado en el journal por la tarea 0.2; nunca `any`.
- Rechazada: `openapi-fetch`. Dependencia de ejecución que duplica D8 (XSRF, reintento único por 419, `ApiError`);
  para ~25 rutas el tipado por ruta de la envoltura basta. Rechazada: tipos a mano (derivan sin aviso).
- Revisar si: las rutas pasan de ~50 o `--check` no existe en la versión fijada (entonces `gen:api` + `git diff --exit-code`).

### D2. Red simulada: MSW (dev) detrás de `src/test/http.ts`
`setupServer` con `onUnhandledRequest: 'error'` (una petición no prevista rompe la prueba: da el control positivo de
"cero peticiones"); `server.events.on('request:start')` alimenta un registro `{method, path, query, headers, body}`
que M1–M3 leen. Las pruebas importan solo `src/test/http.ts`; `src/test/render.tsx` monta proveedores, router en
memoria y sesión de un rol (`abilities` de S1). Las pruebas de S1 con `fetch` falso no se reescriben.
- `api.ts` construye URL absolutas (`new URL(path, window.location.origin)`): el `fetch` de Node rechaza rutas relativas.
- Rechazada: simular el módulo cliente o los hooks (no ve cabeceras ni cuerpo; M1/M3 serían vacuas). Rechazada:
  ampliar el `fetch` falso de S1 (reimplementa enrutado, parámetros y registro para ~150 escenarios).
- Plan B acotado a 30 min: si MSW falla con Vitest 5/jsdom 30, `http.ts` enruta sobre `vi.stubGlobal('fetch')` con la misma API.

### D3. Clave por intención: `useIdempotentIntent` (solo memoria)
`buildDispensationBody(form)` (ítems en el orden de la prescripción, sin credenciales) produce el cuerpo y su huella
`JSON.stringify`: una sola función, cliente y servidor no divergen. Un `useRef<{fingerprint, key}>` guarda la clave;
`keyFor(fingerprint)` la genera si falta o si la huella cambió. Se descarta tras éxito (incluida la repetición
`Idempotent-Replayed`) y tras `idempotency_key_reused`; se conserva ante red, `server_error`, `invalid_authorizer`,
`authorization_required`, `too_many_attempts`, `insufficient_stock` (el servidor no consume la clave al rechazar, S3
D5). Clave = 16 bytes de `crypto.getRandomValues` en base64url (22 caracteres, cumple `[A-Za-z0-9_-]{16,128}`).
- Rechazada: `crypto.randomUUID` (solo en contexto seguro; S8 podría servir por HTTP fuera de `localhost`). Rechazada:
  clave en `useMutation` `variables` o en estado del formulario (un re-render o `reset()` la pierde o la reenvía mal).

### D4. Doble envío: candado síncrono además de `isPending`
`SubmitButton` (Button de shadcn + `Spinner` + `disabled`) y `useSubmitGuard` con `useRef` en vuelo: dos eventos en el
mismo tick llegan antes del re-render, así que `isPending` solo no basta (M2). Mutaciones con `retry: 0` (S1).

### D5. Catálogo de errores: `describeError(error, {productName})` en `src/lib/api-errors.ts`
`ApiError.code` → `strings.errors[code]`; `insufficient_stock` compone una línea por `shortages` con el nombre que da
el llamador (la API devuelve `product_id`); `TypeError` de red o `server_error` → texto de red; desconocido → genérico.
`fieldErrors(error)` → `Record<campo, string>` para `FieldError`. `<ErrorMessage>` (shadcn `Alert`, `role="alert"`) jamás
renderiza `error.message`. Rechazada: mapear en cada pantalla (copias divergentes, códigos filtrados).

### D6. Guarda y navegación desde una tabla de rutas
`src/app/routes.tsx` declara `{path, element, abilities (anyOf), navLabel}`; el menú y `<RequireAbility>` leen la misma
tabla (ninguna ruta huérfana, ninguna entrada sin guarda). La guarda resuelve `can()` desde `['session','me']` antes de
montar la pantalla: sus consultas nunca se crean. `Ability` = unión TS de los 13 literales de S1 D4 en
`src/lib/abilities.ts`. Rechazada: guarda en `loader` (S1 D7 la descartó). Rechazada: solo ocultar el menú.

### D7. Disposición de carpetas
`src/features/{dispensations,transfers,inventory,kardex}/` con `XPage.tsx`, subcomponentes, `api.ts` (funciones por
recurso), `queries.ts` (claves y hooks) y pruebas junto al archivo. Compartido: `src/components/` (`AsyncState`,
`SubmitButton`, `ErrorMessage`, `ConfirmDialog`), `src/components/ui/` (shadcn: table, dialog, alert-dialog, field,
input, select, badge, alert, spinner, skeleton). `src/lib/query-keys.ts` fija raíces `stock`, `kardex`, `alerts`,
`patient` e `invalidateAfterStockWrite()`. Sin barriles. Búsqueda de paciente: `listbox` propio con
`aria-activedescendant`; rechazado `Command` (trae `cmdk` y filtra en cliente). Kardex: filtros en `useSearchParams`.

## Risks / Trade-offs

1. [MSW o URL relativas fallan en Vitest/jsdom] → URL absolutas en `api.ts`; plan B de D2 tras la misma fachada.
2. [Scramble omite o anonimiza esquemas (`shortages`, errores)] → ayudantes por ruta; tipos a mano anotados en 0.2.
3. [Clave mal renovada o doble envío en el mismo tick] → D3 + D4 con M1–M3 leyendo cabeceras del registro de D2.

## Migration Plan

Solo SPA; reversión = revertir commits. Dependencias nuevas, ambas de desarrollo: `openapi-typescript`, `msw`.
