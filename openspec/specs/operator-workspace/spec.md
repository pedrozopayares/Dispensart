# operator-workspace Specification

## Purpose
Reúne las convenciones que comparten las pantallas de operación de la SPA: navegación según las capacidades
del rol, guarda de rutas, mensajes comprensibles por código de rechazo, bloqueo del doble envío y protección de
los datos del paciente en el navegador.

## Requirements

### Requirement: Navegación por rol
El shell SHALL mostrar un menú con solo las pantallas que el rol puede abrir, según `abilities` del usuario
actual: "Dispensación" con `dispensations.create` o `patients.view`; "Traslados" con `transfers.view`;
"Inventario" y "Kardex" con `inventory.view`. El enlace activo SHALL marcarse como página actual y el menú
SHALL recorrerse con teclado (RN-10).

#### Scenario: Auxiliar ve sus cuatro pantallas
- **WHEN** inicia sesión un `auxiliar_farmacia`
- **THEN** el menú muestra "Dispensación", "Traslados", "Inventario" y "Kardex", en ese orden

#### Scenario: Auditor ve las cuatro en lectura
- **WHEN** inicia sesión un `auditor`
- **THEN** el menú muestra "Dispensación", "Traslados", "Inventario" y "Kardex"

#### Scenario: Médico solo ve Dispensación
- **WHEN** inicia sesión un `medico`
- **THEN** el menú muestra solo "Dispensación" y no muestra "Traslados", "Inventario" ni "Kardex"

#### Scenario: Admin sin pantallas de operación
- **WHEN** inicia sesión un `admin`
- **THEN** el menú no muestra ninguna de las cuatro pantallas

#### Scenario: Navegación con teclado
- **WHEN** el usuario recorre el encabezado con Tab y pulsa Enter sobre "Kardex"
- **THEN** la SPA abre la pantalla Kardex y el enlace "Kardex" queda marcado como página actual

### Requirement: Guarda de ruta por capacidad
Cada pantalla de operación SHALL verificar la capacidad que la habilita antes de pedir datos. Sin ella SHALL
mostrar "No tienes permiso para ver esta pantalla." con el enlace "Volver al inicio", sin enviar peticiones de
esa pantalla. El servidor sigue siendo la autoridad; la guarda solo evita pantallas rotas (RN-10).

#### Scenario: Acceso directo sin capacidad
- **WHEN** un `medico` abre `/inventory` escribiendo la dirección
- **THEN** ve "No tienes permiso para ver esta pantalla." y el enlace "Volver al inicio", y no se envía ninguna consulta de existencias ni de alertas

#### Scenario: Acceso directo con capacidad
- **WHEN** un `auditor` abre `/kardex` escribiendo la dirección
- **THEN** ve la pantalla Kardex con sus filtros

#### Scenario: El servidor niega aunque la guarda permita
- **WHEN** una consulta de una pantalla habilitada recibe `code` `forbidden`
- **THEN** la pantalla muestra "No tienes permiso para realizar esta acción." en lugar de los datos, sin mostrar el código

### Requirement: Inicio con accesos del rol
La página de inicio SHALL saludar al usuario y mostrar un acceso por cada pantalla de su menú. Un rol sin
pantallas de operación SHALL ver "Tu rol no tiene pantallas de operación en esta versión.".

#### Scenario: Accesos del regente
- **WHEN** un `regente_farmacia` abre `/`
- **THEN** ve "Bienvenido, {nombre}" y cuatro accesos: Dispensación, Traslados, Inventario y Kardex

#### Scenario: Admin sin accesos
- **WHEN** un `admin` abre `/`
- **THEN** ve "Bienvenido, {nombre}" y "Tu rol no tiene pantallas de operación en esta versión.", sin accesos

### Requirement: Mensajes de error por código
Toda pantalla SHALL traducir el `code` de un rechazo a un texto en español del módulo central de textos y
nunca mostrar el código, la traza ni el texto crudo de un error de red. Un código desconocido SHALL mostrar
"Ocurrió un error inesperado. Intenta de nuevo." Los errores de campo (`errors`) SHALL mostrarse junto al
campo (RN-01, RN-03, RN-05, RN-08).

#### Scenario: Stock insuficiente con detalle
- **WHEN** una escritura recibe `code` `insufficient_stock` con `shortages` de un producto que pide 5 y tiene 2 disponibles
- **THEN** la pantalla muestra "Stock insuficiente: {producto} necesita 5 y hay 2 disponibles."

#### Scenario: Lote vencido
- **WHEN** una escritura recibe `code` `lot_expired`
- **THEN** la pantalla muestra "El lote está vencido y no puede usarse."

#### Scenario: Falta de autorización
- **WHEN** una escritura recibe `code` `forbidden`
- **THEN** la pantalla muestra "No tienes permiso para realizar esta acción."

#### Scenario: Código desconocido
- **WHEN** una escritura recibe un `code` que el catálogo no conoce, por ejemplo `quota_exceeded`
- **THEN** la pantalla muestra "Ocurrió un error inesperado. Intenta de nuevo." y no muestra `quota_exceeded`

#### Scenario: Fallo de red
- **WHEN** una petición falla por red o con `code` `server_error`
- **THEN** la pantalla muestra "No pudimos conectar con el servidor. Intenta de nuevo." y no muestra el mensaje técnico del navegador

#### Scenario: Error de campo junto al campo
- **WHEN** una escritura recibe `code` `validation_failed` con `errors.reason`
- **THEN** el mensaje de `errors.reason` aparece junto al campo "Motivo" y el resto del formulario conserva lo escrito

### Requirement: Bloqueo del doble envío
Todo botón que dispara una escritura SHALL quedar deshabilitado y mostrar un texto de progreso mientras la
petición está en curso, y SHALL ignorar nuevas pulsaciones, clics o Enter, hasta recibir respuesta.

#### Scenario: Doble clic produce una sola petición
- **WHEN** el usuario pulsa dos veces seguidas un botón de escritura
- **THEN** se envía una sola petición y el botón queda deshabilitado con su texto de progreso hasta la respuesta

#### Scenario: Enter repetido en un formulario
- **WHEN** el usuario pulsa Enter dos veces seguidas en un formulario de escritura
- **THEN** se envía una sola petición

#### Scenario: Botón rehabilitado tras un rechazo
- **WHEN** la escritura en curso recibe un rechazo
- **THEN** el botón vuelve a habilitarse, el formulario conserva lo escrito y se muestra el mensaje del código

### Requirement: Datos del paciente fuera del navegador persistente
La SPA SHALL NOT escribir datos de pacientes (documento, nombre, teléfono, fecha de nacimiento) en la consola,
en `localStorage`, en `sessionStorage` ni en la URL. Al cerrar sesión SHALL descartarlos de la caché (RN-10,
Ley 1581).

#### Scenario: Error durante una consulta de paciente
- **WHEN** la búsqueda de pacientes falla por red
- **THEN** la consola no recibe ninguna llamada con el término buscado ni con datos del paciente

#### Scenario: URL sin datos personales
- **WHEN** el usuario busca por documento y abre la ficha de un paciente
- **THEN** la URL no contiene el documento, el nombre ni el teléfono

#### Scenario: Almacenamiento del navegador vacío de pacientes
- **WHEN** el usuario completa una dispensación
- **THEN** `localStorage` y `sessionStorage` no contienen documento, nombre, teléfono ni fecha de nacimiento del paciente

### Requirement: Disposición común de las pantallas
Cada pantalla SHALL vivir dentro del shell de S1, con un título, los filtros arriba y los resultados debajo,
usando los componentes del kit (ADR-0004). Las acciones SHALL ocurrir en contexto (fila, detalle o diálogo
propio), nunca con `window.alert` ni `window.confirm`. Los estados de carga SHALL anunciarse y los errores
SHALL anunciarse como alerta a lectores de pantalla.

#### Scenario: Confirmación en diálogo propio
- **WHEN** el usuario elige una acción que pide confirmación, como despachar un traslado
- **THEN** se abre un diálogo de la SPA con el foco dentro, Escape lo cierra sin enviar y nunca se invoca `window.confirm`

#### Scenario: Error anunciado
- **WHEN** una pantalla muestra un mensaje de error
- **THEN** el mensaje está en una región con rol de alerta
