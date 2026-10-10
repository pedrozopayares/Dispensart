# admin-screens Specification

## Purpose
Da al rol `admin` las pantallas de la SPA para su función de la prueba (§ 3), gestión de usuarios y catálogos:
listar y crear usuarios con su rol, y listar, crear y editar bodegas y productos, sobre la API existente.

## Requirements

### Requirement: Acceso a las pantallas de administración
La SPA SHALL ofrecer "Usuarios" en `/users` con `users.manage` y "Catálogo" en `/catalog` con `catalog.manage`,
en el shell, el menú y el inicio, con la disposición común de operator-workspace, el kit (ADR-0004), el módulo
central de textos y los tokens de tema, sin colores fijos. Sin la capacidad SHALL verse "No tienes permiso para
ver esta pantalla." con "Volver al inicio", sin peticiones de esa pantalla (§ 3).

#### Scenario: Admin abre Usuarios desde el menú
- **WHEN** un `admin` con sesión pulsa "Usuarios" en el menú
- **THEN** la SPA abre `/users` con el título "Usuarios" y el enlace "Usuarios" marcado como página actual

#### Scenario: Admin abre Catálogo desde el inicio
- **WHEN** un `admin` en `/` pulsa el acceso "Catálogo"
- **THEN** la SPA abre `/catalog` con el título "Catálogo" y las secciones "Bodegas" y "Productos"

#### Scenario: Otro rol escribe la dirección de Usuarios
- **WHEN** un `auxiliar_farmacia` abre `/users` escribiendo la dirección
- **THEN** ve "No tienes permiso para ver esta pantalla." y el enlace "Volver al inicio", y no se envía ninguna petición a `/api/users`

#### Scenario: Otro rol escribe la dirección de Catálogo
- **WHEN** un `regente_farmacia` abre `/catalog` escribiendo la dirección
- **THEN** ve "No tienes permiso para ver esta pantalla." y el enlace "Volver al inicio", y la pantalla no envía ninguna petición de bodegas ni de productos

#### Scenario: Acceso sin sesión
- **WHEN** un visitante sin sesión abre `/users` o `/catalog`
- **THEN** la SPA lo lleva a `/login` sin mostrar la pantalla

### Requirement: Lista de usuarios
"Usuarios" SHALL listar lo que devuelve `GET /api/users`, en el orden recibido, con las columnas "Nombre",
"Correo electrónico" y "Rol"; el rol SHALL verse con su etiqueta en español, nunca con su código. Carga
anunciada "Cargando usuarios…", lista vacía "No hay usuarios registrados." y error en región de alerta con el
texto del catálogo por `code` y "Reintentar". La lista SHALL NOT mostrar contraseñas (§ 3).

#### Scenario: Lista con los usuarios semilla
- **WHEN** un `admin` abre `/users` y la API devuelve los 5 usuarios semilla
- **THEN** se ven 5 filas con nombre, correo y las etiquetas "Auxiliar de farmacia", "Regente de farmacia", "Médico", "Auditor" y "Administrador", y ningún código de rol como `auxiliar_farmacia`

#### Scenario: Carga anunciada
- **WHEN** la consulta de usuarios está en curso
- **THEN** se ve "Cargando usuarios…" en una región de estado

#### Scenario: Lista vacía
- **WHEN** la API devuelve una lista de usuarios vacía
- **THEN** se ve "No hay usuarios registrados." y ninguna tabla con filas

#### Scenario: Fallo de red al listar
- **WHEN** la consulta de usuarios falla por red
- **THEN** se ve "No pudimos conectar con el servidor. Intenta de nuevo." en una región de alerta con "Reintentar", que repite la consulta

#### Scenario: El servidor niega la lista
- **WHEN** la consulta de usuarios recibe `code` `forbidden`
- **THEN** se ve "No tienes permiso para realizar esta acción." sin filas, sin "Reintentar" y sin mostrar el código

### Requirement: Alta de usuario
"Usuarios" SHALL ofrecer "Nuevo usuario": "Nombre", "Correo electrónico", "Contraseña" (ayuda "Mínimo 8
caracteres.") y "Rol" (5 etiquetas, sin preselección). "Crear usuario" SHALL enviar `name`, `email`, `password`
y `role` a `POST /api/users`, con "Creando usuario…" y sin doble envío. Al crear: "Usuario {nombre} creado.",
formulario vacío y lista actualizada. Campo vacío: "Este campo es obligatorio." sin enviar; un rechazo SHALL
conservar lo escrito (§ 3).

#### Scenario: Alta exitosa
- **WHEN** un `admin` escribe "Nueva Auxiliar", "nueva.aux@dispensart.test", una contraseña de 12 caracteres, elige "Auxiliar de farmacia" y pulsa "Crear usuario"
- **THEN** se envía una sola petición `POST /api/users` con esos datos y `role` `auxiliar_farmacia`; se ve "Usuario Nueva Auxiliar creado.", los cuatro campos quedan vacíos y la lista muestra "Nueva Auxiliar" con "Auxiliar de farmacia"

#### Scenario: Campos vacíos sin envío
- **WHEN** el `admin` pulsa "Crear usuario" sin nombre, con un nombre solo de espacios o sin elegir rol
- **THEN** cada campo vacío muestra "Este campo es obligatorio." y no se envía ninguna petición

#### Scenario: Correo ya en uso
- **WHEN** la API responde al alta con `code` `validation_failed` y `errors.email` "El valor de correo electrónico ya está en uso."
- **THEN** ese mensaje aparece junto a "Correo electrónico", el formulario conserva nombre, correo y rol, "Crear usuario" vuelve a habilitarse y la lista no gana filas [ancla: `software/api/app/Http/Requests/Users/StoreUserRequest.php:36` y identity-access «Alta de usuarios: Correo duplicado sin distinguir mayúsculas»]

#### Scenario: Contraseña demasiado corta
- **WHEN** la API responde al alta con `code` `validation_failed` y `errors.password` "El campo contraseña debe tener al menos 8 caracteres."
- **THEN** ese mensaje aparece junto a "Contraseña" y no se muestra "Usuario … creado." [ancla: `software/api/app/Http/Requests/Users/StoreUserRequest.php:37` e identity-access «Alta de usuarios: Rol inválido o datos incompletos»]

#### Scenario: Doble clic produce un solo alta
- **WHEN** el `admin` pulsa "Crear usuario" dos veces seguidas con datos válidos
- **THEN** se envía una sola petición `POST /api/users` y el botón muestra "Creando usuario…" deshabilitado hasta la respuesta

#### Scenario: Enter repetido
- **WHEN** el `admin` pulsa Enter dos veces seguidas en el formulario con datos válidos
- **THEN** se envía una sola petición `POST /api/users`

#### Scenario: El servidor niega el alta
- **WHEN** la API responde al alta con `code` `forbidden`
- **THEN** se ve "No tienes permiso para realizar esta acción.", el formulario conserva lo escrito y la lista no cambia

#### Scenario: Fallo de red al crear
- **WHEN** el alta falla por red
- **THEN** se ve "No pudimos conectar con el servidor. Intenta de nuevo.", el formulario conserva lo escrito y "Crear usuario" vuelve a habilitarse

#### Scenario: Sesión expirada al crear
- **WHEN** el alta recibe `code` `unauthenticated`
- **THEN** la SPA navega a `/login` con "Tu sesión expiró. Inicia sesión de nuevo." como fija app-shell «Sesión expirada y token CSRF vencido»

### Requirement: Contraseña nunca visible
El campo "Contraseña" SHALL ser enmascarado. La SPA SHALL NOT mostrar la contraseña como texto, ni escribirla
en la lista, la URL, `localStorage`, `sessionStorage` ni la consola, ni antes ni después del alta, con éxito o
con rechazo. Tras un alta exitosa el campo SHALL quedar vacío (§ 3).

#### Scenario: Campo enmascarado
- **WHEN** el `admin` escribe "Clave-Sintetica-2026" en "Contraseña"
- **THEN** el campo es de tipo contraseña y ningún texto visible del documento contiene "Clave-Sintetica-2026"

#### Scenario: Contraseña fuera del documento tras el alta
- **WHEN** el alta con contraseña "Clave-Sintetica-2026" termina con éxito
- **THEN** ni el texto ni el valor de ningún campo del documento, ni `localStorage`, ni `sessionStorage`, ni la URL, ni ninguna llamada a la consola contienen "Clave-Sintetica-2026"

#### Scenario: Contraseña fuera de la consola tras un fallo
- **WHEN** el alta con contraseña "Clave-Sintetica-2026" falla por red
- **THEN** ninguna llamada a la consola, ni la URL, ni `localStorage`, ni `sessionStorage` contienen "Clave-Sintetica-2026", y el campo la conserva enmascarada

### Requirement: Lista de bodegas y productos
"Catálogo" SHALL mostrar "Bodegas" (columnas "Código", "Nombre") y "Productos" ("Código", "Nombre",
"Presentación", "Control especial" con "Sí" o "No") con lo que devuelven `GET /api/warehouses` y `GET
/api/products`, en el orden recibido. Cada sección SHALL tener carga, vacío y error propios; el fallo de una
SHALL NOT ocultar la otra. Presentación nula SHALL verse "Sin presentación" (RN-05).

#### Scenario: Catálogo con los datos semilla
- **WHEN** un `admin` abre `/catalog` y la API devuelve 3 bodegas y 6 productos, uno con `is_controlled` `true`
- **THEN** "Bodegas" lista 3 filas con código y nombre, "Productos" lista 6 filas y exactamente una muestra "Sí" en "Control especial"

#### Scenario: Producto sin presentación
- **WHEN** la API devuelve un producto con `presentation` `null`
- **THEN** su fila muestra "Sin presentación" en "Presentación"

#### Scenario: Cargas anunciadas
- **WHEN** las consultas de bodegas y de productos están en curso
- **THEN** se ven "Cargando bodegas…" y "Cargando productos…" en regiones de estado

#### Scenario: Secciones vacías
- **WHEN** la API devuelve listas vacías de bodegas y de productos
- **THEN** se ven "No hay bodegas registradas." y "No hay productos registrados."

#### Scenario: Falla solo una sección
- **WHEN** la consulta de productos falla por red y la de bodegas responde con 3 bodegas
- **THEN** "Productos" muestra "No pudimos conectar con el servidor. Intenta de nuevo." con "Reintentar" en una región de alerta, y "Bodegas" sigue mostrando sus 3 filas

### Requirement: Alta de bodega
"Bodegas" SHALL ofrecer "Nueva bodega" con "Código" y "Nombre". "Crear bodega" SHALL enviar `code` y `name` a
`POST /api/warehouses`, con "Creando bodega…" y bloqueo del doble envío. Al crear: "Bodega {nombre} creada.",
formulario vacío y lista actualizada. Un campo vacío SHALL mostrar "Este campo es obligatorio." sin enviar; un
rechazo SHALL mostrar el mensaje de campo junto al campo, o el del catálogo por `code`, y conservar lo escrito
(parte A).

#### Scenario: Alta exitosa de bodega
- **WHEN** un `admin` escribe "FC2" y "Farmacia Consulta Externa" y pulsa "Crear bodega"
- **THEN** se envía una sola petición `POST /api/warehouses` con `{"code":"FC2","name":"Farmacia Consulta Externa"}`, se ve "Bodega Farmacia Consulta Externa creada.", el formulario queda vacío y la lista muestra la bodega nueva

#### Scenario: Bodega con campos vacíos
- **WHEN** el `admin` pulsa "Crear bodega" sin código o sin nombre
- **THEN** el campo vacío muestra "Este campo es obligatorio." y no se envía ninguna petición

#### Scenario: Código de bodega en uso
- **WHEN** la API responde al alta con `code` `validation_failed` y `errors.code` "El valor de código ya está en uso."
- **THEN** ese mensaje aparece junto a "Código", el formulario conserva lo escrito y la lista no gana filas [ancla: `software/api/app/Http/Requests/Catalog/StoreWarehouseRequest.php:25` y catalog «Alta de bodegas: Código o nombre duplicado»]

#### Scenario: Doble clic produce una sola bodega
- **WHEN** el `admin` pulsa "Crear bodega" dos veces seguidas con datos válidos
- **THEN** se envía una sola petición `POST /api/warehouses` y el botón muestra "Creando bodega…" deshabilitado hasta la respuesta

#### Scenario: Fallo de red al crear la bodega
- **WHEN** el alta de la bodega falla por red
- **THEN** se ve "No pudimos conectar con el servidor. Intenta de nuevo.", el formulario conserva lo escrito y "Crear bodega" vuelve a habilitarse

### Requirement: Edición de bodega
Cada fila de "Bodegas" SHALL ofrecer "Editar", que abre en contexto el código y el nombre actuales.
"Guardar cambios" SHALL enviar `code` y `name` a `PATCH /api/warehouses/{id}`, con "Guardando…" y bloqueo del
doble envío; "Cancelar" SHALL cerrar sin enviar. Al guardar: "Bodega {nombre} actualizada." y la fila con los
valores nuevos. Un campo vaciado SHALL mostrar "Este campo es obligatorio." sin enviar; un rechazo SHALL
conservar la edición abierta con lo escrito (parte A).

#### Scenario: Cambio de nombre de bodega
- **WHEN** el `admin` pulsa "Editar" en "Farmacia Central", cambia el nombre a "Farmacia Central Norte" y pulsa "Guardar cambios"
- **THEN** se envía una sola petición `PATCH /api/warehouses/{id}` de esa bodega con el código actual y el nombre nuevo, se ve "Bodega Farmacia Central Norte actualizada." y la fila muestra el nombre nuevo

#### Scenario: Cancelar la edición de bodega
- **WHEN** el `admin` pulsa "Editar", cambia el nombre y pulsa "Cancelar"
- **THEN** la edición se cierra, la fila conserva el nombre anterior y no se envía ninguna petición

#### Scenario: Nombre de bodega vaciado
- **WHEN** el `admin` borra el nombre en la edición y pulsa "Guardar cambios"
- **THEN** el campo muestra "Este campo es obligatorio." y no se envía ninguna petición

#### Scenario: Código de otra bodega
- **WHEN** la API responde a la edición con `code` `validation_failed` y `errors.code` "El valor de código ya está en uso."
- **THEN** ese mensaje aparece junto a "Código", la edición sigue abierta con lo escrito y la fila no cambia [ancla: `software/api/app/Http/Requests/Catalog/UpdateWarehouseRequest.php:26` y catalog «Modificación de bodegas: Código de otra bodega»]

#### Scenario: Bodega que ya no existe
- **WHEN** la API responde a la edición con `code` `not_found`
- **THEN** se ve "El recurso solicitado no existe." en una región de alerta y la edición sigue abierta [ancla: catalog «Modificación de bodegas: Bodega inexistente»]

#### Scenario: Doble clic al guardar la bodega
- **WHEN** el `admin` pulsa "Guardar cambios" dos veces seguidas
- **THEN** se envía una sola petición `PATCH` y el botón muestra "Guardando…" deshabilitado hasta la respuesta

### Requirement: Alta de producto
"Productos" SHALL ofrecer "Nuevo producto" con "Código", "Nombre", "Presentación" (opcional) y la casilla
"Medicamento de control especial" sin marcar. "Crear producto" SHALL enviar `code`, `name`, `presentation`
(`null` si está en blanco) e `is_controlled` a `POST /api/products`, con "Creando producto…" y bloqueo del
doble envío. Al crear: "Producto {nombre} creado.", formulario vacío y lista actualizada. Código o nombre
vacío SHALL mostrar "Este campo es obligatorio." sin enviar (RN-05).

#### Scenario: Alta de producto de control especial
- **WHEN** un `admin` escribe "MED-099", "Hidromorfona 2 mg/mL" y "Ampolla 1 mL", marca "Medicamento de control especial" y pulsa "Crear producto"
- **THEN** se envía una sola petición `POST /api/products` con esos valores e `is_controlled` `true`, se ve "Producto Hidromorfona 2 mg/mL creado." y su fila muestra "Sí" en "Control especial"

#### Scenario: Producto sin campos opcionales
- **WHEN** el `admin` crea un producto solo con código y nombre
- **THEN** la petición lleva `presentation` `null` e `is_controlled` `false`, y la fila nueva muestra "Sin presentación" y "No"

#### Scenario: Producto con campos obligatorios vacíos
- **WHEN** el `admin` pulsa "Crear producto" sin código o con un nombre solo de espacios
- **THEN** el campo vacío muestra "Este campo es obligatorio." y no se envía ninguna petición

#### Scenario: Código de producto en uso
- **WHEN** la API responde al alta con `code` `validation_failed` y `errors.code` "El valor de código ya está en uso."
- **THEN** ese mensaje aparece junto a "Código", el formulario conserva lo escrito, incluida la casilla, y la lista no gana filas [ancla: `software/api/app/Http/Requests/Catalog/StoreProductRequest.php:25` y catalog «Alta de productos: Datos inválidos»]

#### Scenario: Doble clic produce un solo producto
- **WHEN** el `admin` pulsa "Crear producto" dos veces seguidas con datos válidos
- **THEN** se envía una sola petición `POST /api/products` y el botón muestra "Creando producto…" deshabilitado hasta la respuesta

#### Scenario: El servidor niega el alta de producto
- **WHEN** la API responde al alta con `code` `forbidden`
- **THEN** se ve "No tienes permiso para realizar esta acción.", el formulario conserva lo escrito y la lista no cambia

### Requirement: Edición de producto
Cada fila de "Productos" SHALL ofrecer "Editar", que abre en contexto código, nombre, presentación y la casilla
de control especial actuales. "Guardar cambios" SHALL enviar los cuatro campos a `PATCH /api/products/{id}`
(`presentation` `null` si queda en blanco), con "Guardando…" y bloqueo del doble envío; "Cancelar" SHALL cerrar
sin enviar. Al guardar: "Producto {nombre} actualizado." y la fila con los valores nuevos. Un rechazo SHALL
conservar la edición abierta con lo escrito (RN-05).

#### Scenario: Marcar un producto como control especial
- **WHEN** el `admin` pulsa "Editar" en "Omeprazol 20 mg", marca "Medicamento de control especial" y pulsa "Guardar cambios"
- **THEN** se envía una sola petición `PATCH /api/products/{id}` de ese producto con `is_controlled` `true` y los otros tres campos actuales, se ve "Producto Omeprazol 20 mg actualizado." y la fila muestra "Sí"

#### Scenario: Quitar la presentación
- **WHEN** el `admin` borra la presentación en la edición y guarda
- **THEN** la petición lleva `presentation` `null` y la fila muestra "Sin presentación"

#### Scenario: Cancelar la edición de producto
- **WHEN** el `admin` pulsa "Editar", desmarca la casilla y pulsa "Cancelar"
- **THEN** la edición se cierra, la fila conserva su valor de "Control especial" y no se envía ninguna petición

#### Scenario: Código de otro producto
- **WHEN** la API responde a la edición con `code` `validation_failed` y `errors.code` "El valor de código ya está en uso."
- **THEN** ese mensaje aparece junto a "Código", la edición sigue abierta con lo escrito y la fila no cambia [ancla: `software/api/app/Http/Requests/Catalog/UpdateProductRequest.php:25` y catalog «Modificación de productos: Código de otro producto»]

#### Scenario: Producto que ya no existe
- **WHEN** la API responde a la edición con `code` `not_found`
- **THEN** se ve "El recurso solicitado no existe." en una región de alerta y la edición sigue abierta [ancla: catalog «Modificación de productos: Producto inexistente»]

#### Scenario: Doble clic al guardar el producto
- **WHEN** el `admin` pulsa "Guardar cambios" dos veces seguidas
- **THEN** se envía una sola petición `PATCH` y el botón muestra "Guardando…" deshabilitado hasta la respuesta
