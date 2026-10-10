# Proposal — add-postman-collection (S16)

## Why

La parte A y el § 7 piden la API documentada con OpenAPI o Postman. `openapi.json` importado en Postman no sirve
para probar: sin URL base, sin cuerpos de ejemplo, y la sesión Sanctum SPA exige cookie CSRF, `X-XSRF-TOKEN`
decodificado y `Origin`/`Referer` (sin ellos el login da 403). Una colección ejecutable lo resuelve y queda como
regresión en el staging simulado (parte D).

## What Changes

- `software/docs/postman/`: colección Postman v2.1 y entorno local (`baseUrl` `http://localhost:8090`, correos
  semilla, contraseña por defecto solo de desarrollo `dispensart-dev-only`, ya pública en el repo).
- Script previo de colección: cookie CSRF si falta, `X-XSRF-TOKEN`, `Origin` y `Referer`; exclusión por petición.
- Carpetas con cuerpos de ejemplo y aserciones `pm.test`: salud; sesión de los 5 roles; dispensación FEFO con
  repetición idempotente sin movimiento nuevo (RN-01, RN-02, RN-06, RN-09); control especial (RN-05); traslado
  con discrepancia y segregación (RN-07, RN-08); alertas, existencias, kardex y ajuste (RN-11); asistente con
  `mock`; catálogo y administración; permisos denegados (401, 403, 419).
- Guarda de cobertura: operaciones de `openapi.json` contra peticiones de la colección, en el trabajo de backend.
- `newman` con versión exacta: comando local documentado y paso del staging después de los humos, sin costo.

## Capabilities

### New Capabilities
- `api-collection`: colección y entorno ejecutables con sesión SPA por script, flujos, aserciones y cobertura.

### Modified Capabilities
- `delivery-pipeline`: staging ejecuta la colección tras los humos (ADDED).
- `ci-pipeline`: backend ejecuta la guarda de cobertura (ADDED).
- `project-documentation`: README y guía con el comando de ejecución (ADDED).

## Impact

Nuevo `software/docs/postman/`; pasos en `.github/workflows/ci.yml`; sección del README. Sin cambios en API,
`openapi.json` ni SPA. `newman` es herramienta vía `npx`, no dependencia de la app. Partes A, D, E y § 7.

## Supuestos

1. Catálogo y administración entran por la guarda, que exige toda operación; códigos y correos únicos por corrida.
2. Repetible como los humos: consume stock semilla finito; `down -v` lo repone.
3. Un tarro de cookies por corrida: cada carpeta abre y cierra su sesión.
4. La carrera por la última unidad (RN-03) queda en Pest (`dispensation` › «Carrera por la última unidad»).
5. Alertas con aserciones estables ante los humos: forma de las listas y lote vencido `L-ACE-2401`.
6. `/sanctum/csrf-cookie` está fuera del contrato; la guarda lo admite por lista explícita.
7. Tier B: sin migración, cambio de API, permisos ni arnés de Pest; newman se agrega tras los humos sin alterarlos.
