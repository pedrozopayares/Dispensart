# inventory-screen Specification

## Purpose
Permite consultar las existencias por bodega, producto y lote con su vencimiento, y ver de un vistazo qué lotes
vencen pronto o ya vencieron y qué productos están bajo el stock mínimo de su bodega.

## Requirements

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

### Requirement: Contraste AA de la insignia de vencimiento próximo
La insignia "Vence en {n} días" de `/inventory` SHALL tomar texto y fondo de un par de tokens de tema de
advertencia, definido en tema claro y oscuro, con contraste ≥ 4,5:1 en ambos. SHALL conservar el significado de
advertencia en ámbar, distinto del rojo de "Vencido" y del verde lima de "Bajo mínimo". La prueba de contraste de
la SPA SHALL evaluar ese par y fallar si queda por debajo. Textos y resaltado de fila no cambian.

#### Scenario: Insignia por vencer legible en ambos temas
- **WHEN** corre la suite de pruebas de la SPA
- **THEN** la prueba de contraste evalúa el par texto/fondo de la insignia de advertencia en tema claro y oscuro, ambos quedan ≥ 4,5:1 y la prueba pasa

#### Scenario: Insignia por vencer con colores de tema
- **WHEN** las alertas incluyen un lote de Farmacia Central con `days_to_expiry` 20
- **THEN** la fila muestra "Vence en 20 días" en una insignia cuyo texto y fondo vienen del par de advertencia del tema, no de colores fijos con texto blanco

#### Scenario: Advertencia distinta de vencido y bajo mínimo
- **WHEN** se leen los tokens de tema claro y oscuro
- **THEN** el fondo de advertencia es un ámbar, distinto del token destructivo (rojo) y del secundario (verde lima `#a9cd43`)

#### Scenario: Par de advertencia bajo AA hace fallar la suite
- **WHEN** el texto de advertencia se cambia a blanco sobre ámbar `#f59e0b` (≈ 2,15:1) en un tema
- **THEN** la prueba de contraste falla y su mensaje nombra el par de advertencia, el tema y la razón obtenida

#### Scenario: Token de advertencia ausente no pasa en vacío
- **WHEN** falta en un tema el token de texto o de fondo de advertencia
- **THEN** la prueba de contraste falla y su mensaje nombra el tema y el token ausente

### Requirement: Registro de ingreso de lote
La pantalla Inventario SHALL mostrar el formulario «Registrar ingreso» solo a usuarios con `inventory.adjust`.
El formulario SHALL validar obligatorios en el cliente, mostrar los rechazos de la API sin perder el borrador y,
tras el éxito, confirmar, limpiar y refrescar existencias, kardex y alertas. Textos del módulo de strings.

#### Scenario: Regente ve el formulario
- **WHEN** un `regente_farmacia` abre la pantalla Inventario
- **THEN** ve el formulario «Registrar ingreso» con sus seis campos

#### Scenario: Otros roles sin formulario
- **WHEN** un `auxiliar_farmacia` o un `auditor` abre la pantalla Inventario
- **THEN** no ve el formulario «Registrar ingreso» ni su botón

#### Scenario: Ingreso exitoso
- **WHEN** el regente completa los campos y envía, y la API responde 201
- **THEN** la SPA envía `POST /api/stock-entries` con los seis campos, muestra la confirmación, limpia el formulario y vuelve a pedir existencias, kardex y alertas

#### Scenario: Campos obligatorios vacíos
- **WHEN** el regente envía el formulario con campos vacíos
- **THEN** cada campo vacío muestra su mensaje y no se envía ninguna petición

#### Scenario: Rechazo de la API
- **WHEN** la API responde 422 `lot_expired`, o 422 `validation_failed` con `errors.expires_on`
- **THEN** la SPA muestra el mensaje en español (general para `lot_expired`, junto al vencimiento para `errors.expires_on`) y conserva los valores escritos [ancla: escenarios inventory «Lote vencido rechazado» y «Vencimiento distinto del lote existente»]
