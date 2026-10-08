# Spec Delta — transfers-screen

## Purpose

Permite operar traslados entre bodegas desde la SPA: listarlos por estado, crearlos con lotes concretos y
moverlos por su ciclo de vida con acciones en contexto, mostrando siempre el estado, los actores y las
discrepancias de lo no recibido.

## ADDED Requirements

### Requirement: Listado de traslados
La pantalla Traslados (`/transfers`) SHALL listar, del más reciente al más antiguo, número, origen, destino,
estado en español (Borrador, Solicitado, Aprobado, En tránsito, Recibido, Recibido parcial, Anulado) y fecha,
con filtro por estado y paginación "Anterior"/"Siguiente". SHALL mostrar carga, error con "Reintentar" y vacío
(RN-07).

#### Scenario: Listado con estados en español
- **WHEN** un `auxiliar_farmacia` abre `/transfers` con traslados en varios estados
- **THEN** ve "Cargando traslados…" y luego la lista con cada estado en español, nunca `EN_TRANSITO` ni otro literal de la API

#### Scenario: Filtro por estado
- **WHEN** el usuario elige el estado "En tránsito"
- **THEN** la lista muestra solo traslados en tránsito y vuelve a la página 1

#### Scenario: Sin traslados
- **WHEN** la consulta no devuelve traslados para el filtro elegido
- **THEN** ve "No hay traslados para este filtro."

#### Scenario: Fallo del listado
- **WHEN** la consulta del listado falla por red
- **THEN** ve "No pudimos conectar con el servidor. Intenta de nuevo." con "Reintentar"

### Requirement: Detalle con estado y discrepancias
Al abrir un traslado la pantalla SHALL mostrar estado, origen, destino, observaciones, quién y cuándo creó,
solicitó, aprobó, despachó, recibió o anuló (con motivo), y por línea producto, lote, vencimiento, enviada y
recibida. En `RECIBIDO_PARCIAL` SHALL mostrar "Discrepancias" con faltante y estado por línea (RN-07).

#### Scenario: Recibido parcial con discrepancias
- **WHEN** el usuario abre un traslado recibido parcialmente con una línea enviada 3 y recibida 2
- **THEN** ve el estado "Recibido parcial" y la sección "Discrepancias" con esa línea, faltante 1 y estado "Pendiente"

#### Scenario: Recibido completo sin discrepancias
- **WHEN** el usuario abre un traslado recibido completo
- **THEN** ve el estado "Recibido" y no ve la sección "Discrepancias"

#### Scenario: Traslado inexistente
- **WHEN** el usuario abre `/transfers/999999` y la API responde `code` `not_found`
- **THEN** ve "No encontramos el traslado." con el enlace "Volver a traslados"

### Requirement: Creación de traslado
Con `transfers.create`, "Nuevo traslado" SHALL abrir un formulario con origen, destino, observaciones
opcionales (≤ 1000) y líneas de lote + cantidad. Los lotes elegibles SHALL ser los no vencidos con existencia
en el origen, mostrando producto, lote, vencimiento y disponible. Al crear SHALL abrirse el detalle del
borrador (RN-01, RN-07).

#### Scenario: Borrador creado
- **WHEN** un `auxiliar_farmacia` elige origen, destino, un lote y cantidad 3 y pulsa "Crear traslado"
- **THEN** ve el detalle del traslado en estado "Borrador" con el botón "Solicitar"

#### Scenario: Lotes vencidos fuera de la lista
- **WHEN** el origen tiene un lote vencido con existencia
- **THEN** ese lote no aparece entre los lotes elegibles

#### Scenario: Destino igual al origen
- **WHEN** el usuario elige el mismo origen y destino y pulsa "Crear traslado"
- **THEN** ve "El destino debe ser distinto del origen." y no se envía petición

#### Scenario: Sin líneas o cantidad inválida
- **WHEN** el usuario pulsa "Crear traslado" sin líneas, o con una cantidad vacía, 0 o no entera
- **THEN** ve "Agrega al menos un lote." o "La cantidad debe ser un entero mayor que 0." y no se envía petición

#### Scenario: Doble clic en Crear traslado
- **WHEN** el usuario pulsa "Crear traslado" dos veces seguidas
- **THEN** se envía una sola petición y existe un solo borrador nuevo en la lista

#### Scenario: Lote vencido al crear
- **WHEN** la creación recibe `code` `lot_expired`
- **THEN** ve "El lote está vencido y no puede usarse." y el formulario conserva lo escrito

#### Scenario: Sin capacidad de crear
- **WHEN** un `auditor` abre `/transfers`
- **THEN** no ve el botón "Nuevo traslado"

### Requirement: Acciones según estado y rol
El detalle SHALL ofrecer solo las acciones válidas para el estado y el rol: "Solicitar" (creador, Borrador),
"Aprobar" (`transfers.approve`, Solicitado, nunca quien solicitó), "Despachar" (`transfers.create`,
Aprobado), "Recibir" (`transfers.receive`, En tránsito) y "Anular" (creador o `transfers.approve`; Borrador,
Solicitado o Aprobado). Tras cada éxito SHALL recargar el detalle (RN-07, RN-08).

#### Scenario: Solicitante no ve Aprobar
- **WHEN** un `regente_farmacia` abre un traslado Solicitado que él mismo solicitó
- **THEN** no ve "Aprobar" y ve "Lo solicitaste tú: otro regente debe aprobarlo."

#### Scenario: Otro regente aprueba
- **WHEN** un `regente_farmacia` distinto del solicitante pulsa "Aprobar"
- **THEN** el detalle pasa a "Aprobado" con su nombre como aprobador

#### Scenario: Auxiliar no ve Aprobar
- **WHEN** un `auxiliar_farmacia` abre un traslado Solicitado
- **THEN** no ve "Aprobar"

#### Scenario: Auditor sin acciones
- **WHEN** un `auditor` abre un traslado en cualquier estado
- **THEN** no ve "Solicitar", "Aprobar", "Despachar", "Recibir" ni "Anular"

#### Scenario: Estado terminal sin acciones
- **WHEN** un `regente_farmacia` abre un traslado Recibido, Recibido parcial o Anulado
- **THEN** no ve ninguna acción de estado

#### Scenario: Segregación rechazada por el servidor
- **WHEN** la aprobación recibe `code` `segregation_of_duties`
- **THEN** ve "Quien solicitó el traslado no puede aprobarlo." y el estado sigue "Solicitado"

#### Scenario: Estado cambiado por otro usuario
- **WHEN** cualquier acción recibe `code` `invalid_transfer_transition`
- **THEN** ve "El traslado cambió de estado mientras lo revisabas. Actualizamos la información." y el detalle se recarga con el estado real

#### Scenario: Doble clic en una acción de estado
- **WHEN** el usuario pulsa "Solicitar" dos veces seguidas
- **THEN** se envía una sola petición y el botón muestra "Procesando…" deshabilitado hasta la respuesta

### Requirement: Despacho confirmado
"Despachar" SHALL abrir un diálogo "Despachar traslado" que avisa "El stock saldrá de {origen} y quedará en
tránsito." con "Confirmar despacho" y "Cancelar". Stock insuficiente o lote vencido SHALL mostrarse con el
mensaje de su código y el traslado sigue Aprobado (RN-01, RN-03, RN-07).

#### Scenario: Despacho exitoso
- **WHEN** el usuario confirma el despacho
- **THEN** el detalle pasa a "En tránsito" con su nombre como despachador

#### Scenario: Despacho cancelado
- **WHEN** el usuario pulsa "Cancelar" o Escape en el diálogo
- **THEN** el diálogo se cierra sin enviar petición y el traslado sigue "Aprobado"

#### Scenario: Stock insuficiente al despachar
- **WHEN** el despacho recibe `code` `insufficient_stock`
- **THEN** el diálogo muestra "Stock insuficiente en la bodega de origen para despachar este traslado." y el detalle sigue "Aprobado"

#### Scenario: Lote vencido al despachar
- **WHEN** el despacho recibe `code` `lot_expired`
- **THEN** el diálogo muestra "El lote está vencido y no puede usarse." y el detalle sigue "Aprobado"

### Requirement: Recepción por línea
"Recibir" SHALL abrir un formulario con, por línea, la cantidad enviada y "Cantidad recibida" (por defecto la
enviada; entero de 0 a la enviada). Si se recibe menos SHALL avisar "Lo no recibido quedará como discrepancia
pendiente." antes de confirmar (RN-06, RN-07).

#### Scenario: Recepción completa
- **WHEN** el usuario confirma la recepción con las cantidades por defecto
- **THEN** el detalle pasa a "Recibido" sin sección de discrepancias

#### Scenario: Recepción parcial
- **WHEN** el usuario escribe 2 en una línea enviada 3 y confirma
- **THEN** ve el aviso de discrepancia antes de confirmar y luego el detalle "Recibido parcial" con una discrepancia de faltante 1

#### Scenario: Cantidad recibida mayor que la enviada
- **WHEN** el usuario escribe 4 en una línea enviada 3
- **THEN** el campo muestra "No puede superar lo enviado (3)." y no se envía petición

#### Scenario: Doble clic en Confirmar recepción
- **WHEN** el usuario pulsa "Confirmar recepción" dos veces seguidas
- **THEN** se envía una sola petición

### Requirement: Anulación con motivo
"Anular" SHALL abrir un diálogo con "Motivo" obligatorio (≤ 500) y "Confirmar anulación". Sin motivo SHALL
mostrar "Escribe el motivo de la anulación." sin enviar petición (RN-07).

#### Scenario: Anulación exitosa
- **WHEN** el creador escribe un motivo y confirma la anulación de su borrador
- **THEN** el detalle pasa a "Anulado" con su nombre, la fecha y el motivo

#### Scenario: Anulación sin motivo
- **WHEN** el usuario pulsa "Confirmar anulación" con el motivo vacío
- **THEN** ve "Escribe el motivo de la anulación." y no se envía petición
