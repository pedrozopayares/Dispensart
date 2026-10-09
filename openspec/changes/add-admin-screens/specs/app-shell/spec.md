# app-shell — delta

## MODIFIED Requirements

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
- **THEN** ve "Bienvenido, {nombre}" y los accesos "Usuarios" y "Catálogo", sin el acceso "Asistente" ni los textos "Tu rol no tiene pantallas de operación en esta versión." y "Las pantallas de operación aparecerán aquí."

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
