# app-shell Specification

## Purpose
Da a la SPA una puerta de entrada única y segura: inicio de sesión, rutas que exigen sesión, un encabezado
con el usuario, su rol y el cierre de sesión, y una reacción comprensible cuando la sesión expira; toda
pantalla posterior se monta dentro de este shell.

## Requirements

### Requirement: Pantalla de inicio de sesión
La SPA SHALL ofrecer en `/login` un formulario con "Correo electrónico", "Contraseña" y el botón "Iniciar
sesión". Antes de enviar SHALL obtener la cookie CSRF. Mientras la petición está en curso el botón SHALL
quedar deshabilitado con el texto "Ingresando…". Todos los textos SHALL venir del módulo central de textos.

#### Scenario: Ingreso exitoso
- **WHEN** el usuario escribe credenciales válidas y pulsa "Iniciar sesión"
- **THEN** la SPA navega a `/` y el encabezado muestra su nombre y su rol

#### Scenario: Doble clic
- **WHEN** el usuario pulsa "Iniciar sesión" dos veces seguidas
- **THEN** se envía una sola petición de login y el botón muestra "Ingresando…" deshabilitado hasta recibir respuesta

#### Scenario: Credenciales inválidas
- **WHEN** la API rechaza el login con `code` `invalid_credentials`
- **THEN** el formulario muestra "Correo o contraseña incorrectos.", conserva el correo escrito, vacía la contraseña y vuelve a habilitar el botón

#### Scenario: Campos vacíos
- **WHEN** el usuario pulsa "Iniciar sesión" con el correo o la contraseña vacíos
- **THEN** el campo vacío muestra "Este campo es obligatorio." y no se envía petición

#### Scenario: Demasiados intentos
- **WHEN** la API rechaza el login con `code` `too_many_attempts`
- **THEN** el formulario muestra "Demasiados intentos. Espera un momento antes de volver a intentar."

#### Scenario: Servidor inalcanzable
- **WHEN** la petición de login falla por red o la API responde con error de servidor
- **THEN** el formulario muestra "No pudimos conectar con el servidor. Intenta de nuevo." y el botón vuelve a habilitarse

#### Scenario: Usuario ya autenticado
- **WHEN** un usuario con sesión abre `/login`
- **THEN** la SPA lo lleva a `/` sin mostrar el formulario

### Requirement: Rutas protegidas por sesión
Al cargar, la SPA SHALL consultar el usuario actual. Toda ruta distinta de `/login` SHALL exigir sesión.
Mientras se consulta SHALL mostrarse "Cargando sesión…"; sin sesión SHALL llevarse a `/login`; ante un fallo
del servidor SHALL mostrarse un error con el botón "Reintentar".

#### Scenario: Carga con sesión vigente
- **WHEN** un usuario con sesión abre `/`
- **THEN** ve "Cargando sesión…" mientras llega la respuesta y luego la página de inicio dentro del shell

#### Scenario: Ruta protegida sin sesión
- **WHEN** un visitante sin sesión abre `/` o cualquier ruta distinta de `/login`
- **THEN** la SPA lo lleva a `/login` sin mostrar contenido del shell

#### Scenario: Fallo al consultar la sesión
- **WHEN** la consulta del usuario actual falla por red o por error de servidor
- **THEN** la SPA muestra "No pudimos verificar tu sesión." con el botón "Reintentar", que repite la consulta

### Requirement: Encabezado con sesión y cierre
El shell SHALL mostrar en un encabezado el nombre del usuario, la etiqueta en español de su rol y el botón
"Cerrar sesión". Etiquetas: Auxiliar de farmacia, Regente de farmacia, Médico, Auditor, Administrador. La
página de inicio SHALL saludar al usuario; los accesos que muestra debajo los fija `operator-workspace`
("Inicio con accesos del rol").

#### Scenario: Etiqueta de rol en español
- **WHEN** inicia sesión un usuario con rol `regente_farmacia`
- **THEN** el encabezado muestra su nombre y "Regente de farmacia", nunca el código `regente_farmacia`

#### Scenario: Página de inicio sin pantallas aún
- **WHEN** un `admin`, rol sin pantallas de operación, está en `/`
- **THEN** ve "Bienvenido, {nombre}" y el acceso "Asistente", sin el texto "Tu rol no tiene pantallas de operación en esta versión." ni "Las pantallas de operación aparecerán aquí."

#### Scenario: Página de inicio con saludo
- **WHEN** un `auxiliar_farmacia` está en `/`
- **THEN** ve "Bienvenido, {nombre}" y no ve el estado vacío "Las pantallas de operación aparecerán aquí."

#### Scenario: Cierre exitoso
- **WHEN** el usuario pulsa "Cerrar sesión" y la API confirma el cierre
- **THEN** la SPA descarta todos los datos en caché del usuario y navega a `/login`

#### Scenario: Doble clic en cerrar sesión
- **WHEN** el usuario pulsa "Cerrar sesión" dos veces seguidas
- **THEN** se envía una sola petición y el botón queda deshabilitado mientras está en curso

#### Scenario: Cierre fallido
- **WHEN** la petición de cierre falla por red o por error de servidor
- **THEN** la SPA muestra "No pudimos cerrar la sesión. Intenta de nuevo.", mantiene al usuario en el shell y rehabilita el botón

#### Scenario: Otro usuario no ve datos del anterior
- **WHEN** un `admin` cierra sesión y en el mismo navegador inicia sesión un `auditor`
- **THEN** el encabezado muestra solo los datos del `auditor` y ninguna respuesta en caché del `admin` se reutiliza

### Requirement: Sesión expirada y token CSRF vencido
Si cualquier petición de la SPA recibe `code` `unauthenticated` con el shell abierto, la SPA SHALL
descartar la caché y llevar a `/login` con un aviso. Si recibe `csrf_token_mismatch`, SHALL renovar la
cookie CSRF y reintentar esa petición una sola vez.

#### Scenario: Sesión expirada durante el uso
- **WHEN** con el shell abierto una petición recibe `code` `unauthenticated`
- **THEN** la SPA navega a `/login` y muestra "Tu sesión expiró. Inicia sesión de nuevo."

#### Scenario: Token CSRF vencido y reintento exitoso
- **WHEN** una escritura recibe `code` `csrf_token_mismatch` y el reintento tras renovar la cookie tiene éxito
- **THEN** el usuario ve el resultado normal de la acción, sin mensaje de error, y se enviaron exactamente dos peticiones de esa escritura

#### Scenario: Token CSRF vencido dos veces
- **WHEN** el reintento también recibe `code` `csrf_token_mismatch`
- **THEN** la SPA no reintenta más y muestra "La sesión de seguridad expiró. Recarga la página e intenta de nuevo."
