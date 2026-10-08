# Proposal — add-alerts (S5)

## Why

RN-11 exige alertar lotes que vencen en 90 días o menos y productos por debajo del stock mínimo definido por
bodega; la parte A la lista entre las operaciones mínimas y la pantalla Inventario de la parte B (S6) la muestra.
Hoy no existe el mínimo por bodega (S1 lo difirió a S5) ni consulta alguna que calcule las alertas.

## What Changes

- Stock mínimo por bodega + producto: entero > 0, único por par, defendido en la base. Sin fila = sin mínimo.
- Siembra de mínimos idempotente que deja al menos una alerta de stock bajo visible con los datos semilla.
- `GET /api/alerts` (capacidad `inventory.view` de S1), filtro opcional `warehouse_id`, dos listas:
  - `expiring_lots`: existencias con cantidad > 0 cuyo lote vence en ≤ 90 días (día 90 incluido, 91 no),
    incluidos los ya vencidos marcados como tales, por fecha de vencimiento.
  - `low_stock`: pares bodega + producto con mínimo cuya suma de existencias no vencidas es menor que el mínimo;
    igual al mínimo no alerta; sin existencias alerta con 0.
- "Hoy" y "vencido" con la regla de `catalog` (S1): `America/Bogota`, vence hoy = vencido.

## Capabilities

### New Capabilities
- `inventory-alerts`: stock mínimo por bodega, su siembra y las alertas de vencimiento y de stock bajo.

### Modified Capabilities
- Ninguna. Reutiliza `inventory.view`, la forma de rechazo y la regla de vencimiento de S1, y las existencias de S2.

## Impact

Tier B (ROADMAP). Parte A; RN-11, apoyo de RN-01. Solo `software/api`: una migración, seeder, consulta, Policy,
un endpoint de lectura, OpenAPI. Sin escritura de stock ni kardex. Fuera: notificaciones, correo, pantalla (S6),
edición de mínimos por API.

## Assumptions

1. Mínimos solo por siembra (o SQL directo); sin endpoint de escritura. Candidato de ROADMAP/deuda.
2. Lotes ya vencidos con existencia aparecen en `expiring_lots` con `is_expired` `true`: son la alerta más urgente.
3. Existencia vencida no cuenta para el mínimo (RN-01: inutilizable). Stock en tránsito (S4) tampoco.
4. Ventana fija de 90 días, sin parámetro.
5. Bodega inexistente en el filtro → 200 con listas vacías (como S2); sin paginación.
6. Mínimo 0 no se admite: ausencia de fila expresa "sin mínimo".
