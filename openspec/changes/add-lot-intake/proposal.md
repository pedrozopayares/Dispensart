# Proposal — add-lot-intake (S18)

## Why

Hoy un lote solo nace por la siembra. El regente no puede registrar la llegada de mercancía nueva: un lote de
proveedor (producto + código + vencimiento) no tiene alta, y el único camino para sumar unidades es el ajuste
manual, que el kardex registra como `ajuste` y no como `entrada` (RN-06 distingue ambos). Además, el ajuste no
tiene pantalla. El inventario por lote (RN-01) queda sin su puerta de entrada.

## What Changes

- `POST /api/stock-entries` (regente): registra el ingreso de mercancía en una bodega con `product_id`,
  `lot_code`, `expires_on`, `quantity` positiva y `reason` (documento o motivo). Si el lote (producto + código)
  no existe, lo crea. Si existe, lo reutiliza solo si el vencimiento coincide; si no, 422 en `expires_on`.
- Lote vencido (vence hoy o antes) → 422 `lot_expired` sin efecto (RN-01).
- Escribe un movimiento `entrada` con usuario y motivo por la costura única de stock (RN-03, RN-06) y una fila
  `stock.received` en la bitácora (solo ids), todo en una transacción.
- Dos ingresos simultáneos del mismo lote nuevo crean un solo lote y suman ambos.
- Pantalla Inventario: formulario «Registrar ingreso» visible solo con `inventory.adjust`; tras el éxito se
  refrescan existencias, kardex y alertas. Auditor y auxiliar no lo ven.
- Migración que reemplaza por nombre los `CHECK` de `audit_events` para `stock.received`, con `down()`.
- Contrato OpenAPI, tipos de la SPA y colección de Postman actualizados.

## Capabilities

### New Capabilities
- Ninguna.

### Modified Capabilities
- `inventory`: requisito `ADDED` «Ingreso de lote».
- `audit-trail`: requisito `ADDED` «Ingresos de lote registrados».
- `inventory-screen`: requisito `ADDED` «Registro de ingreso de lote».

## Impact

Tier A (escritura de stock y kardex, migración de `CHECK`, superficie de permisos). Parte A y B; RN-01, RN-03,
RN-06. `software/api` (ruta, FormRequest, Policy, acción, migración, enum, pruebas Pest), `software/web`
(formulario, strings, pruebas Vitest), `openapi.json`, colección Postman. Ninguna RN se debilita.

## Assumptions

1. Permiso: se reutiliza `inventory.adjust` (solo regente). El enunciado da al regente los cambios de
   inventario; una capacidad nueva cambiaría el mapa de roles sin ganancia.
2. El código de lote se compara exacto, sin cambiar mayúsculas, tras recortar espacios.
3. El ingreso no es idempotente, igual que el ajuste: dos envíos son dos entradas.
4. Pantalla de ajuste manual: fuera de alcance; queda como fila pendiente del ROADMAP.
