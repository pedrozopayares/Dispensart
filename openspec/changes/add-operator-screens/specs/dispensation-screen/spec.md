# Spec Delta — dispensation-screen

## Purpose

Permite dispensar desde la SPA en pocos pasos y con teclado: buscar al paciente, elegir una prescripción
vigente, ver qué lotes asigna FEFO y confirmar una sola vez aunque haya reintentos, con coautorización para
control especial y datos del paciente protegidos según el rol.

## ADDED Requirements

### Requirement: Búsqueda de paciente
La pantalla Dispensación (`/dispensations`) SHALL abrir con el foco en el campo "Documento o nombre del
paciente". Enter SHALL buscar si hay de 3 a 50 caracteres; si no, SHALL mostrar "Escribe al menos 3
caracteres." sin enviar petición. SHALL mostrar carga, error con "Reintentar" y vacío (RN-04, RN-10).

#### Scenario: Búsqueda con resultados
- **WHEN** un `auxiliar_farmacia` escribe "SINT" y pulsa Enter
- **THEN** ve "Buscando pacientes…" mientras llega la respuesta y luego la lista con tipo y número de documento y nombre de cada paciente

#### Scenario: Término demasiado corto
- **WHEN** el usuario escribe "SI" y pulsa Enter
- **THEN** ve "Escribe al menos 3 caracteres." y no se envía petición

#### Scenario: Sin resultados
- **WHEN** la búsqueda no devuelve pacientes
- **THEN** ve "No encontramos pacientes con ese documento o nombre."

#### Scenario: Fallo de la búsqueda
- **WHEN** la búsqueda falla por red
- **THEN** ve "No pudimos conectar con el servidor. Intenta de nuevo." con el botón "Reintentar", que repite la búsqueda con el mismo término

#### Scenario: Selección con teclado
- **WHEN** el usuario baja con las flechas por la lista de resultados y pulsa Enter sobre un paciente
- **THEN** se abre la ficha de ese paciente con sus prescripciones

### Requirement: Datos enmascarados para el auditor
La pantalla SHALL mostrar los datos del paciente tal como los entrega la API. Si la respuesta trae `masked`
`true`, SHALL mostrar el aviso "Datos enmascarados" y SHALL NOT ofrecer forma alguna de verlos en claro
(RN-10).

#### Scenario: Auditor ve datos enmascarados
- **WHEN** un `auditor` busca y abre un paciente y la API devuelve `masked` `true` con documento `*******001`
- **THEN** la ficha muestra `*******001`, el nombre enmascarado y el aviso "Datos enmascarados", sin fecha de nacimiento

#### Scenario: Auditor sin forma de desenmascarar
- **WHEN** un `auditor` revisa la ficha enmascarada
- **THEN** no existe botón, enlace ni atajo que muestre el documento o el nombre completos

#### Scenario: Auxiliar ve datos en claro
- **WHEN** un `auxiliar_farmacia` abre un paciente y la API devuelve `masked` `false`
- **THEN** la ficha muestra el documento y el nombre completos, sin el aviso "Datos enmascarados"

### Requirement: Prescripciones del paciente
La ficha SHALL listar las prescripciones con estado en español (Vigente, Vencida, Agotada), vigencia,
prescriptor y, por ítem, producto, marca "Control especial", prescrita, dispensada y pendiente. Solo una
prescripción Vigente SHALL poder elegirse para dispensar (RN-04, RN-05).

#### Scenario: Prescripción vigente elegible
- **WHEN** un `auxiliar_farmacia` abre un paciente con una prescripción vigente con pendiente
- **THEN** la prescripción muestra "Vigente" y el botón "Dispensar esta prescripción"

#### Scenario: Prescripciones vencida y agotada no elegibles
- **WHEN** el paciente tiene una prescripción vencida y otra agotada
- **THEN** ambas muestran su estado "Vencida" y "Agotada" sin botón "Dispensar esta prescripción"

#### Scenario: Paciente sin prescripciones
- **WHEN** el paciente no tiene prescripciones
- **THEN** la ficha muestra "Este paciente no tiene prescripciones."

#### Scenario: Fallo al cargar la ficha
- **WHEN** la consulta de la ficha falla por red
- **THEN** se muestra "No pudimos conectar con el servidor. Intenta de nuevo." con "Reintentar"

### Requirement: Modo consulta sin dispensar
Un usuario sin `dispensations.create` (`auditor`, `medico`) SHALL poder buscar pacientes y ver prescripciones,
pero SHALL NOT ver bodega, cantidades, vista previa ni "Confirmar dispensación" (RN-10).

#### Scenario: Auditor en modo consulta
- **WHEN** un `auditor` abre una prescripción vigente
- **THEN** no ve "Dispensar esta prescripción", ni selector de bodega, ni "Ver lotes asignados", ni "Confirmar dispensación"

#### Scenario: Médico en modo consulta
- **WHEN** un `medico` abre un paciente con prescripción vigente
- **THEN** ve la prescripción con sus pendientes y no ve "Dispensar esta prescripción"

### Requirement: Vista previa de lotes FEFO
Al dispensar, el usuario SHALL elegir bodega y cantidad por ítem (por defecto, el pendiente; 0 excluye el
ítem; entre 1 y el pendiente) y pulsar "Ver lotes asignados". La pantalla SHALL mostrar por ítem los lotes
asignados en el orden de la API con código, vencimiento y cantidad, lo vencido excluido y el faltante (RN-01,
RN-02, RN-04).

#### Scenario: Lotes en orden FEFO
- **WHEN** la vista previa asigna L1:3 (vence antes) y L2:2 a un ítem de 5 unidades
- **THEN** el ítem muestra L1 con 3 y su vencimiento, luego L2 con 2, y "Confirmar dispensación" queda habilitado

#### Scenario: Unidades vencidas excluidas
- **WHEN** la vista previa de un ítem trae `expired_excluded_quantity` 4
- **THEN** el ítem muestra "4 unidades en lotes vencidos no se usan."

#### Scenario: Faltante en la vista previa
- **WHEN** la vista previa trae `fulfillable` `false` con un ítem que pide 5 y tiene 2 disponibles
- **THEN** el ítem muestra "Stock insuficiente: {producto} necesita 5 y hay 2 disponibles." y "Confirmar dispensación" queda deshabilitado

#### Scenario: Cantidad mayor que el pendiente
- **WHEN** el usuario escribe 8 en un ítem con pendiente 5
- **THEN** el campo muestra "La cantidad no puede superar lo pendiente (5)." y "Ver lotes asignados" no envía petición

#### Scenario: Sin bodega o sin cantidades
- **WHEN** el usuario pulsa "Ver lotes asignados" sin bodega elegida, o con todas las cantidades en 0
- **THEN** ve "Elige una bodega." o "Indica al menos una cantidad." y no se envía petición

#### Scenario: Vista previa desactualizada
- **WHEN** tras una vista previa el usuario cambia la bodega o una cantidad
- **THEN** la asignación mostrada desaparece, "Confirmar dispensación" queda deshabilitado y se pide "Ver lotes asignados" de nuevo

#### Scenario: Prescripción que cambió de estado
- **WHEN** la vista previa recibe `code` `prescription_expired`, `prescription_exhausted` o `exceeds_prescription`
- **THEN** la pantalla muestra respectivamente "La prescripción está vencida y no se puede dispensar.", "La prescripción ya fue dispensada por completo." o "La cantidad supera lo pendiente en la prescripción.", y recarga la ficha del paciente

### Requirement: Coautorización de control especial
Si la vista previa trae `requires_authorization` `true`, la pantalla SHALL pedir "Correo del regente que
autoriza" y "Contraseña del regente" antes de confirmar. La contraseña SHALL enviarse solo en la petición de
dispensación, SHALL vaciarse tras cualquier respuesta y SHALL NOT mostrarse ni guardarse (RN-05).

#### Scenario: Campos de autorizador visibles
- **WHEN** la vista previa incluye un ítem de control especial
- **THEN** aparecen los campos del autorizador con el aviso "Medicamento de control especial: requiere autorización de un regente distinto de quien dispensa."

#### Scenario: Sin control especial no se piden
- **WHEN** la vista previa trae `requires_authorization` `false`
- **THEN** no aparecen campos de autorizador y la petición de confirmación no lleva `authorizer_email` ni `authorizer_password`

#### Scenario: Campos del autorizador vacíos
- **WHEN** hay control especial y el usuario pulsa "Confirmar dispensación" con el correo o la contraseña vacíos
- **THEN** el campo vacío muestra "Este campo es obligatorio." y no se envía petición

#### Scenario: Autorizador inválido
- **WHEN** la confirmación recibe `code` `invalid_authorizer`
- **THEN** la pantalla muestra "Las credenciales del autorizador no son válidas o no tiene permiso para autorizar.", conserva el correo, vacía la contraseña y conserva la vista previa

#### Scenario: Autorizador igual al dispensador
- **WHEN** la confirmación recibe `code` `authorizer_must_differ`
- **THEN** la pantalla muestra "El autorizador debe ser un regente distinto de quien dispensa." y vacía la contraseña

#### Scenario: Autorización requerida por el servidor
- **WHEN** la confirmación recibe `code` `authorization_required`
- **THEN** la pantalla muestra los campos del autorizador con "Este medicamento requiere la autorización de un regente."

#### Scenario: Demasiados intentos del autorizador
- **WHEN** la confirmación recibe `code` `too_many_attempts`
- **THEN** la pantalla muestra "Demasiados intentos. Espera un momento antes de volver a intentar." y vacía la contraseña

### Requirement: Confirmación idempotente
"Confirmar dispensación" SHALL enviar una `Idempotency-Key` generada en el primer envío de una intención
(prescripción + bodega + ítems) y reutilizada en todo reintento de esa intención, incluido tras fallo de red,
error de servidor o rechazo; las credenciales del autorizador no cambian la intención. Cambiar la intención o
un éxito SHALL generar clave nueva (RN-09, ADR-0003).

#### Scenario: Dispensación exitosa
- **WHEN** el usuario confirma y la API responde con la dispensación creada
- **THEN** la pantalla muestra "Dispensación registrada" con lote, vencimiento y cantidad de cada línea, y el botón "Nueva dispensación"; inventario, kardex y la ficha del paciente se recargan al consultarse

#### Scenario: Doble clic en Confirmar
- **WHEN** el usuario pulsa "Confirmar dispensación" dos veces seguidas
- **THEN** se envía una sola petición y el botón muestra "Confirmando…" deshabilitado hasta la respuesta

#### Scenario: Reintento tras fallo de red reutiliza la clave
- **WHEN** la confirmación falla por red y el usuario pulsa "Reintentar"
- **THEN** la segunda petición lleva la misma `Idempotency-Key` y el mismo cuerpo que la primera

#### Scenario: Respuesta repetida tratada como éxito
- **WHEN** el reintento recibe la dispensación original con la cabecera `Idempotent-Replayed` `true`
- **THEN** la pantalla muestra una sola vez "Dispensación registrada", igual que un éxito normal, sin aviso de duplicado

#### Scenario: Reintento tras autorizador corregido reutiliza la clave
- **WHEN** la confirmación recibe `code` `invalid_authorizer` y el usuario corrige la contraseña y confirma de nuevo
- **THEN** la nueva petición lleva la misma `Idempotency-Key`

#### Scenario: Cambio de cantidad genera clave nueva
- **WHEN** tras un fallo de red el usuario cambia una cantidad, pide de nuevo la vista previa y confirma
- **THEN** la nueva confirmación lleva una `Idempotency-Key` distinta de la anterior

#### Scenario: Nueva dispensación genera clave nueva
- **WHEN** tras una dispensación exitosa el usuario pulsa "Nueva dispensación" y confirma otra de la misma prescripción
- **THEN** la nueva confirmación lleva una `Idempotency-Key` distinta y el foco vuelve al campo de búsqueda

#### Scenario: Stock agotado entre la vista previa y la confirmación
- **WHEN** la confirmación recibe `code` `insufficient_stock` con `shortages`
- **THEN** la pantalla muestra el mensaje de stock insuficiente por producto, deshabilita "Confirmar dispensación" y ofrece "Recalcular asignación", que pide la vista previa de nuevo

#### Scenario: Clave reutilizada con otros datos
- **WHEN** la confirmación recibe `code` `idempotency_key_reused`
- **THEN** la pantalla muestra "Esta confirmación ya se usó con otros datos. Revisa la asignación y confirma de nuevo.", descarta la clave y exige una vista previa nueva

#### Scenario: Sesión del dispensador sin permiso
- **WHEN** la confirmación recibe `code` `forbidden`
- **THEN** la pantalla muestra "No tienes permiso para realizar esta acción." y no ofrece reintentar
