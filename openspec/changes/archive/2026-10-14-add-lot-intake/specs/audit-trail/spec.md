## ADDED Requirements

### Requirement: Ingresos de lote registrados
Un `POST /api/stock-entries` exitoso SHALL escribir, en su misma transacción, una fila `stock.received` con el
usuario como actor, el movimiento `entrada` como objeto y solo ids en el detalle. Un ingreso rechazado no
escribe fila. La base SHALL emparejar `stock.received` solo con `kardex_movement`.

#### Scenario: Ingreso registrado
- **WHEN** un `regente_farmacia` registra un ingreso válido
- **THEN** hay una fila `stock.received` con su id como actor, el id del movimiento `entrada` como objeto y detalle `{warehouse_id, lot_id}` [ancla: ruta `POST /api/stock-entries`, archivo:línea al aplicar]

#### Scenario: Sin texto libre en la fila
- **WHEN** un `regente_farmacia` registra un ingreso con `reason` `Factura FAC-9911 proveedor`
- **THEN** la fila `stock.received` no contiene `FAC-9911`, ni el `lot_code`, ni otro valor que no sea número

#### Scenario: Ingreso rechazado sin fila
- **WHEN** un ingreso se rechaza por lote vencido, por datos inválidos o por rol sin permiso
- **THEN** no se escribe ninguna fila `stock.received` [ancla: render de LotExpired, FormRequest y Policy de ingreso, archivo:línea al aplicar]

#### Scenario: Acción con objeto equivocado rechazada por la base
- **WHEN** se inserta directo en la base una fila `stock.received` con `subject_type` `user`
- **THEN** la base la rechaza por el `CHECK` de emparejamiento
