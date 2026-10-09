# Proposal

## Why

Dos huecos visibles para el jurado (fila S9 de `ROADMAP.md`, partes A, C y D). Primero: `software/api/openapi.json`
no documenta `GET /health` ni `GET /ready`, que sí existen, y ninguna prueba detecta su ausencia. Segundo: `php artisan assistant:eval` compara el texto literal de los argumentos, así que «farmacia de
urgencias» cuenta como fallo aunque las herramientas resuelven la misma bodega que «farmacia urgencias» (deuda
D-auv-7: la medición con Ollama dio 20/24 con dos fallos del comparador, no del modelo).

## What Changes

- El contrato OpenAPI incluye `GET /health` (200) y `GET /ready` (200 y 503) con sus cuerpos, la cabecera
  `X-Correlation-Id` y la URL en la raíz del mismo origen (no bajo `/api`), generado por el mismo `composer openapi`
  que exporta el resto (la verificación de deriva sigue verde).
- Nueva prueba de contrato: falla si `GET /health` o `GET /ready` falta en `openapi.json`, nombrando la operación.
- `software/web/src/lib/api-schema.ts` se regenera desde el contrato (`npm run api:types`).
- `assistant:eval` compara los argumentos `warehouse` y `product` por la entidad del catálogo que resuelven, con la
  misma resolución que usan las herramientas. Una bodega o producto distinto, ambiguo o inexistente sigue fallando.
  Los demás argumentos no cambian. El modo simulado sigue en 24/24 en CI.
- `software/docs/asistente.md`: una línea dice que el comparador usa la entidad resuelta.
- Salda D-auv-7.

Fuera de alcance: prompt, herramientas y filtro previo del asistente; auditoría de operaciones sensibles (S10).

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `service-health`: requisito nuevo «Contrato OpenAPI de salud y disponibilidad».
- `assistant-evaluation`: requisito nuevo «Argumentos de catálogo comparados por entidad resuelta».

## Impact

- `software/api`: generación del contrato (Scramble, solo desarrollo), `openapi.json`, comparador de la evaluación,
  pruebas Pest nuevas. Sin migraciones, sin rutas nuevas, sin cambio de comportamiento HTTP.
- `software/web`: solo tipos generados; sin pantallas.
- CI: sin pasos nuevos; `composer openapi:check`, `openapi:lint`, `api:types:check` y `assistant:eval` (mock) ya
  corren y deben seguir verdes.
- Reglas de negocio: ninguna se toca. RN-10 se respeta: el contrato de salud no declara datos internos.

## Assumptions

1. La prueba de contrato cubre solo `/health` y `/ready` (dictamen del Orchestrator: alcance exacto de la fila S9);
   una prueba de todas las rutas registradas queda fuera.
2. El texto esperado que no resuelve en el catálogo de evaluación (p. ej. `zzzmedicamento`) se compara como texto,
   igual que hoy; así la entrada «sin resultados» conserva su sentido.
3. Ambiguo (varios candidatos) cuenta como «no resuelve», igual que en las herramientas.
4. La resolución corre dentro de la base de evaluación desechable, contra el catálogo sembrado por la evaluación.
5. La mecánica para que Scramble emita rutas fuera de `/api` (transformador de documento, resolvedor de rutas u
   otra) la elige el implementador; la spec fija solo el contrato observable.
6. Tier B (ver `journal.md`).
