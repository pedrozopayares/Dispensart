# Design — add-lot-intake (S18, tier A)

## Context

Las existencias solo cambian por `StockLedger::apply()` (costura única: bloqueo en la clave global, saldo nunca
negativo, un movimiento por cambio). El ajuste manual (`AdjustStockManually`) ya muestra el patrón: la acción
abre una transacción, llama al libro y escribe su fila de bitácora. Los lotes solo se crean en la siembra; la
tabla `lots` tiene `UNIQUE (product_id, lot_code)`.

## Goals / Non-Goals

- Goal: alta de lote + movimiento `entrada` en una operación atómica, segura ante concurrencia, con bitácora.
- Non-goal: editar lotes, pantalla de ajuste manual, idempotencia del ingreso, órdenes de compra.

## Decisions

**D1. Permiso.** `StockPolicy::receive()` devuelve `inventory.adjust`. El mapa de capacidades
(identity-access) no cambia, y tampoco las pruebas que lo fijan. Se rechazó una capacidad `inventory.receive`
nueva: solo el regente la tendría, igual que `inventory.adjust`.

**D2. Acción `ReceiveLot`.** Dentro de `DB::transaction`, en este orden:
1. `expires_on` ≤ hoy (`BusinessCalendar::today()`) → `LotExpired`, antes de escribir nada.
2. Resolver el lote: `insertOrIgnore` en `lots` (`ON CONFLICT DO NOTHING` en PostgreSQL) y luego leer por
   producto + código. Si el vencimiento leído difiere del pedido → `ValidationException` en `expires_on`
   (422 `validation_failed`, mismo render que el resto). Se rechazaron dos alternativas:
   - `firstOrCreate`: dos altas simultáneas chocan en el `UNIQUE` y una responde 500.
   - Bloqueo de tabla: serializa todos los ingresos.
3. `StockLedger::apply([StockChange(..., MovementType::Inbound, userId, reason)])`. El libro crea la existencia
   si falta y bloquea en la clave global. El orden de bloqueo no cambia: primero el lote (solo inserción) y
   luego las existencias, igual que el resto de escrituras. No hay ciclo, porque nadie bloquea `lots`
   `FOR UPDATE`.
4. `AuditTrail::record(actor, StockReceived, movement.id, {warehouse_id, lot_id})`.

**D3. Concurrencia del lote nuevo.** Dos transacciones que insertan el mismo producto + código: la segunda
espera en el índice único hasta que la primera confirma, no inserta nada y lee el lote ya confirmado (READ
COMMITTED, sentencia nueva). Ambas suman al libro. Lo fija la prueba de carrera con `RaceRunner`.

**D4. Migración de bitácora.** `2026_10_14_000001_extend_audit_events_for_lot_intake`, con el patrón de S10:
reemplaza por nombre los tres `CHECK` agregando `stock.received` ↔ `kardex_movement`. `down()` repone los
conjuntos de S10 con `NOT VALID`. El disparador de solo inserción y el `CHECK` de detalle numérico no cambian.

**D5. Contrato.** `POST /api/stock-entries` → 201 `{data: KardexMovementResource}`, el mismo recurso que el
ajuste. Rechazos: 401, 403, 419, 422 (`validation_failed`, `lot_expired`). `openapi.json` se exporta con
Scramble. La colección de Postman gana una petición, porque la guarda de cobertura lo exige.

**D6. SPA.** Componente `StockEntryForm` en `features/inventory`. Reutiliza `useCatalogForm` (obligatorios,
candado contra doble envío, errores de campo), `TextField`, `FormFooter` y `SelectFilter`. Mutación sin
reintento; al terminar llama a `invalidateAfterStockWrite`. Se muestra solo con `can(user, 'inventory.adjust')`.

## Risks / Trade-offs

- `insertOrIgnore` consume un valor de la secuencia aunque no inserte. Es aceptable: los ids no son
  consecutivos por contrato.
- Un lote creado y luego rechazado en el mismo ingreso no se confirma, porque la transacción se revierte.
