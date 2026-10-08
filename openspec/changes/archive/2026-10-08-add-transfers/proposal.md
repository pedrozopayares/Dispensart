# Proposal — add-transfers (S4)

## Why

Traslados y kardex pesan 15 % (§ 10). Hoy los traslados no tienen trazabilidad (§ 2): el stock sale sin rastro,
nadie responde por lo que no llegó y quien pide también aprueba (RN-07, RN-08).

## What Changes

- Traslado con origen ≠ destino, observaciones libres y líneas lote + cantidad (producto derivado del lote);
  lote vencido rechazado al crear y al despachar (RN-01).
- Máquina de estados RN-07 con tabla explícita de transiciones: `BORRADOR → SOLICITADO → APROBADO →
  EN_TRANSITO → RECIBIDO | RECIBIDO_PARCIAL`; `ANULADO` desde `BORRADOR`, `SOLICITADO` o `APROBADO`. Toda
  transición no listada → 409 `invalid_transfer_transition` sin efecto.
- Roles (§ 3): `transfers.create` crea, solicita (solo el creador) y despacha; `transfers.approve` aprueba;
  `transfers.receive` recibe; anula el creador o quien tiene `transfers.approve`. Quien solicita nunca
  aprueba, tampoco siendo regente (RN-08): 403 `segregation_of_duties`, también defendido en la base.
- Despacho: todo o nada, un `salida_traslado` por línea en origen bajo el bloqueo de existencias de S2;
  stock insuficiente → 409 `insufficient_stock` sin efecto; nunca negativo frente a una dispensación
  simultánea (RN-03, RN-06).
- Recepción por línea: `entrada_traslado` en destino (crea la existencia); faltante → `RECIBIDO_PARCIAL` con
  discrepancia pendiente por línea (RN-07); sobre-recepción → 422.
- Resolución de discrepancias por el regente con motivo: `returned_to_origin` (ajuste positivo en origen) o
  `written_off` (pérdida, sin movimiento).
- Despacho, recepción o resolución repetidos o simultáneos nunca duplican movimientos (bloqueo del traslado).
- Bitácora de operaciones sensibles de S3 para aprobación, anulación y resolución.

## Capabilities

### New Capabilities
- `transfers`: traslado, máquina de estados, segregación, despacho, recepción, discrepancias, consulta.

### Modified Capabilities
- Ninguna. Usa `transfers.*` y rechazos de S1, la costura de stock y kardex de S2 y la bitácora de S3.

## Impact

Tier A. Parte A; RN-01, RN-03, RN-06..08. Solo `software/api`: migraciones, servicio, Policy, 9 endpoints,
carreras, OpenAPI. Requiere S1–S3 archivados. Fuera: pantalla (S6), alertas (S5).

## Assumptions

1. Lote elegido explícitamente por línea, sin FEFO: el operador ve qué lote mueve.
2. Crear no verifica ni reserva stock; la verificación autoritativa es al despachar.
3. Borrador no editable: se anula y se crea otro. Crear un traslado dos veces crea dos borradores.
4. Despacho con `transfers.create`: S1 no tiene capacidad propia y § 3 no lo asigna.
5. Usuarios sin bodega asignada: cualquier usuario con la capacidad opera cualquier bodega.
6. Sin `Idempotency-Key`: la máquina de estados vuelve 409 a un reintento, sin duplicar efecto.
7. Recibir acepta un lote que venció en tránsito: el stock ya salió; queda visible como vencido.
8. `RECIBIDO_PARCIAL` es terminal; resolver discrepancias no cambia el estado.
9. RN-05 no aplica a traslados de control especial.
10. `returned_to_origin` sobre lote vencido → 422 `lot_expired` (regla de ajuste de S2); queda `written_off`.
11. Observaciones son dato, nunca instrucción (S7 lo defiende).
