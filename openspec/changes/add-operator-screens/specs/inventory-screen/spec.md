# Spec Delta — inventory-screen

## Purpose

Permite consultar las existencias por bodega, producto y lote con su vencimiento, y ver de un vistazo qué lotes
vencen pronto o ya vencieron y qué productos están bajo el stock mínimo de su bodega.

## ADDED Requirements

### Requirement: Existencias por bodega y lote
La pantalla Inventario (`/inventory`) SHALL listar existencias con bodega, producto (código, nombre, marca
"Control especial"), lote, vencimiento y cantidad, filtrables por bodega ("Todas las bodegas" por defecto) y
producto. SHALL mostrar carga, error con "Reintentar" y vacío. Solo lectura: sin controles de edición (RN-01).

#### Scenario: Consulta por bodega
- **WHEN** un `auxiliar_farmacia` elige "Farmacia Urgencias"
- **THEN** ve "Cargando inventario…" y luego solo existencias de Farmacia Urgencias con lote, vencimiento y cantidad

#### Scenario: Sin existencias para el filtro
- **WHEN** la consulta no devuelve existencias para los filtros elegidos
- **THEN** ve "No hay existencias para los filtros elegidos."

#### Scenario: Fallo de la consulta
- **WHEN** la consulta de existencias falla por red
- **THEN** ve "No pudimos conectar con el servidor. Intenta de nuevo." con "Reintentar", que repite la consulta con los mismos filtros

#### Scenario: Lote vencido marcado
- **WHEN** una existencia tiene `lot.is_expired` `true`
- **THEN** su fila muestra la marca "Vencido" con un estilo distinto del resto, además del texto

#### Scenario: Auditor sin controles de edición
- **WHEN** un `auditor` abre `/inventory`
- **THEN** ve las existencias y no ve ningún botón de ajuste, edición ni borrado

### Requirement: Alertas de vencimiento y stock mínimo
La pantalla SHALL consultar las alertas con el mismo filtro de bodega y SHALL resaltar las filas cuyo lote está
en `expiring_lots` con "Vence en {n} días" (o "Vencido") y mostrar "Productos bajo mínimo" con bodega,
producto, mínimo y disponible. El resaltado SHALL salir solo de la API, nunca del reloj del navegador (RN-11).

#### Scenario: Lote por vencer resaltado
- **WHEN** las alertas incluyen un lote de Farmacia Central con `days_to_expiry` 20
- **THEN** la fila de ese lote en Farmacia Central muestra "Vence en 20 días" resaltada

#### Scenario: Lote fuera de la ventana sin resaltar
- **WHEN** una existencia no figura en `expiring_lots`, aunque su vencimiento parezca cercano para el reloj del navegador
- **THEN** su fila no muestra "Vence en" ni resaltado

#### Scenario: Producto bajo mínimo
- **WHEN** las alertas incluyen un producto de Farmacia Urgencias con mínimo 10 y disponible 4
- **THEN** "Productos bajo mínimo" lo lista con 10 y 4, y sus filas en Farmacia Urgencias muestran "Bajo mínimo"

#### Scenario: Producto bajo mínimo sin existencias
- **WHEN** las alertas incluyen un producto con disponible 0 que no tiene filas de existencia
- **THEN** "Productos bajo mínimo" lo lista con disponible 0

#### Scenario: Sin alertas
- **WHEN** ambas listas de alertas llegan vacías
- **THEN** ve "Sin alertas de vencimiento ni de stock mínimo." y ninguna fila resaltada

#### Scenario: Alertas fallan y existencias no
- **WHEN** la consulta de alertas falla y la de existencias responde
- **THEN** la tabla de existencias se muestra completa y la zona de alertas muestra "No pudimos cargar las alertas." con "Reintentar"
