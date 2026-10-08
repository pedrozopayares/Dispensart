# Proposal — add-stock-and-kardex (S2)

## Why

Dispensar (S3) y trasladar (S4) exigen existencias por bodega + producto + lote (RN-01) y un kardex que nadie
pueda reescribir (RN-06). La parte A pide defender la integridad en la base (cantidades no negativas) y
operaciones de stock transaccionales y seguras ante concurrencia (RN-03).

## What Changes

- Existencias únicas por bodega + producto + lote, coherentes con el producto del lote; `CHECK` cantidad ≥ 0.
- Kardex de solo inserción: tipos `entrada`, `salida_dispensacion`, `salida_traslado`, `entrada_traslado`,
  `ajuste` (todos declarados ya; S3/S4 escriben los suyos), cantidad con signo, saldo resultante, usuario,
  fecha del servidor, motivo. Un trigger rechaza `UPDATE`, `DELETE` y `TRUNCATE`.
- Todo cambio de existencia escribe exactamente un movimiento en la misma transacción.
- `GET /api/stock` y `GET /api/kardex` (paginado), filtrables por bodega, producto y lote; capacidad
  `inventory.view` de S1.
- `POST /api/stock-adjustments`: solo `regente_farmacia` (`inventory.adjust`), con motivo obligatorio; nunca deja
  stock negativo (409 `insufficient_stock`), ni bajo carrera por la última unidad.
- Siembra de existencias iniciales con un movimiento `entrada` por fila, idempotente.

## Capabilities

### New Capabilities
- `inventory`: existencias por bodega + producto + lote, consulta, ajustes y existencias semilla.
- `kardex`: movimientos inmutables, regla de un movimiento por cambio, consulta filtrable.

### Modified Capabilities
- Ninguna. Reutiliza capacidades, Policies y forma de rechazo de S1 sin cambiar sus requisitos.

## Impact

Tier A. Parte A; RN-01, RN-03, RN-06. Solo `software/api`: migraciones, trigger, servicio de libro de stock,
Policies, 3 endpoints, seeder, OpenAPI. Dos códigos de rechazo nuevos con la forma de S1. Fuera: FEFO,
dispensación, idempotencia por clave (S3), traslados (S4), alertas (S5), pantallas (S6).

## Assumptions

1. Tipos de movimiento con literales de RN-06 en español (como los roles de S1).
2. Cantidades en unidades enteras.
3. Sin endpoint de entrada ni alta de lotes: la `entrada` solo la escribe la siembra.
4. Ajuste positivo sobre lote vencido → 422 `lot_expired`; negativo permitido (baja por vencimiento).
5. Ajuste positivo sin existencia previa crea la fila; negativo responde 409.
6. Ajustes sin `Idempotency-Key`: un reintento crea un segundo ajuste, corregible con otro ajuste.
7. RN-05 aplica a la dispensación (S3); un ajuste de producto controlado no pide un segundo regente.
8. `GET /api/stock` lista solo cantidades > 0, sin paginar; `GET /api/kardex` pagina (50 por defecto, máx. 100).
9. Movimientos de siembra con usuario nulo (sistema); `ajuste` exige usuario y motivo, también en la base.
