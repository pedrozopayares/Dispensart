# Proposal — add-sensitive-operation-audit (S10)

## Why

La parte A pide «bitácora de auditoría de accesos a datos de pacientes y de las operaciones sensibles». La
auditoría de cobertura encontró dos operaciones sensibles sin fila en la bitácora de operaciones: el alta de
un usuario (darle un rol abre el acceso a datos de pacientes, RN-10) y el ajuste manual de inventario (cambia
existencias fuera de toda dispensación o traslado; hoy solo deja rastro en el kardex).

## What Changes

- `POST /api/users` exitoso escribe una fila `user.created` (actor = admin, objeto = usuario creado) en la
  misma transacción que el alta. Si la fila no puede escribirse, no queda usuario y la API responde 500.
- `POST /api/stock-adjustments` exitoso escribe una fila `stock.adjusted` (actor = quien ajusta, objeto = el
  movimiento `ajuste` creado) en la misma transacción que la existencia y el kardex. Si la fila falla, la
  existencia y el kardex quedan como estaban.
- Ninguna fila lleva contraseña, hash, correo, nombre ni el `reason` del ajuste: solo `id`s, como S3 y S4.
- Rechazos (401, 403, 409, 419, 422) no escriben fila. La carrera por la última unidad deja una sola fila.
- Migración nueva que reemplaza por nombre los `CHECK` de acción, tipo de objeto y emparejamiento de
  `audit_events`, con `down()` reversible (patrón de la migración de traslados). El disparador de solo
  inserción no se toca y sigue cubriendo las filas nuevas.

## Capabilities

### New Capabilities
- Ninguna.

### Modified Capabilities
- `audit-trail`: tres requisitos `ADDED` (alta de usuarios, ajustes de inventario, integridad de acciones en
  la base). Los requisitos vivos no cambian.

## Impact

Tier A (migración y `CHECK`, ruta de escritura de stock, superficie de permisos). Parte A; RN-03, RN-06,
RN-10. Solo `software/api`: una migración, el enum de acciones, alta de usuario y ajuste en transacción con la
bitácora, pruebas Pest. Sin endpoint nuevo, sin cambio de contrato HTTP de éxito, sin trabajo `web`.
`openapi.json` no cambia (ninguna ruta documenta 500). RN-09 no se debilita: la dispensación y su
repetición idempotente no se tocan. RN-03 y RN-06 tampoco: el ajuste sigue por la costura única de stock.

## Assumptions

1. Sin ruta de lectura para el `auditor`: no existe hoy (README la lista fuera de alcance; el mapa de
   capacidades no la da). No se agrega; las filas se leen en la base.
2. Cambio de rol y desactivación de usuarios no existen como acción de la API (solo alta y listado). Quedan
   fuera; un cambio de rol hecho directo en la base no pasa por la app y no se audita.
3. Solo el ajuste manual (`POST /api/stock-adjustments`) escribe `stock.adjusted`. El `ajuste` que genera la
   resolución `returned_to_origin` de una discrepancia ya queda en `transfer.discrepancy_resolved` y no
   duplica fila: cada operación, una sola acción.
4. El rol del usuario creado no va en la fila (el detalle solo admite números); se lee del usuario por su `id`.
5. Alta y ajuste no son idempotentes (spec viva): dos peticiones exitosas iguales son dos operaciones y dos
   filas.
