## ADDED Requirements

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
