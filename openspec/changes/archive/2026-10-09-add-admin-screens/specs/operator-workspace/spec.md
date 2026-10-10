# operator-workspace — delta

## MODIFIED Requirements

### Requirement: Navegación por rol
El shell SHALL mostrar un menú con solo las pantallas que el rol puede abrir, según `abilities` del usuario
actual: "Dispensación" con `dispensations.create` o `patients.view`; "Traslados" con `transfers.view`;
"Inventario" y "Kardex" con `inventory.view`; "Usuarios" con `users.manage`; "Catálogo" con `catalog.manage`;
"Asistente", al final, para todo rol salvo `admin`. El enlace activo SHALL marcarse como página actual y el menú
SHALL recorrerse con teclado (RN-10, § 3).

#### Scenario: Auxiliar ve sus cuatro pantallas
- **WHEN** inicia sesión un `auxiliar_farmacia`
- **THEN** el menú muestra las cuatro pantallas de operación "Dispensación", "Traslados", "Inventario" y "Kardex", y al final "Asistente", en ese orden, sin "Usuarios" ni "Catálogo"

#### Scenario: Auditor ve las cuatro en lectura
- **WHEN** inicia sesión un `auditor`
- **THEN** el menú muestra "Dispensación", "Traslados", "Inventario" y "Kardex", y al final "Asistente"

#### Scenario: Médico solo ve Dispensación
- **WHEN** inicia sesión un `medico`
- **THEN** de las pantallas de operación el menú muestra solo "Dispensación", seguida de "Asistente", y no muestra "Traslados", "Inventario" ni "Kardex"

#### Scenario: Admin sin pantallas de operación
- **WHEN** inicia sesión un `admin`
- **THEN** el menú muestra "Usuarios" y "Catálogo", en ese orden, y no muestra ninguna de las cuatro pantallas de operación ni "Asistente"

#### Scenario: Navegación con teclado
- **WHEN** el usuario recorre el encabezado con Tab y pulsa Enter sobre "Kardex"
- **THEN** la SPA abre la pantalla Kardex y el enlace "Kardex" queda marcado como página actual

### Requirement: Inicio con accesos del rol
La página de inicio SHALL saludar al usuario y mostrar un acceso por cada pantalla de su menú, en el mismo
orden. Todo rol tiene al menos una pantalla en su menú ("Asistente" los roles de operación; "Usuarios" y
"Catálogo" el `admin`), así que el estado vacío "Tu rol no tiene pantallas de operación en esta versión." SHALL
NOT mostrarse.

#### Scenario: Accesos del regente
- **WHEN** un `regente_farmacia` abre `/`
- **THEN** ve "Bienvenido, {nombre}" y cuatro accesos de operación, Dispensación, Traslados, Inventario y Kardex, seguidos del acceso Asistente

#### Scenario: Admin sin accesos
- **WHEN** un `admin` abre `/`
- **THEN** ve "Bienvenido, {nombre}" y exactamente dos accesos, Usuarios y Catálogo, sin el acceso Asistente ni el texto "Tu rol no tiene pantallas de operación en esta versión."

#### Scenario: Médico sin accesos de inventario
- **WHEN** un `medico` abre `/`
- **THEN** ve los accesos Dispensación y Asistente, y ningún acceso a Traslados, Inventario, Kardex, Usuarios ni Catálogo
