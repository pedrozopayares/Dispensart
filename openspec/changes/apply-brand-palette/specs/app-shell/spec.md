# Spec Delta — app-shell

## ADDED Requirements

### Requirement: Paleta de la IPS en el tema claro
El tema claro de la SPA SHALL usar la paleta pública de la IPS: fondo `#f8f9fa`, texto `#212b51`, primario
azul marino `#232955` con texto claro y secundario verde lima `#a9cd43` con texto azul marino. El verde lima
SHALL NOT llevar texto sobre fondos claros ni ser el color del foco. La SPA SHALL NOT mostrar logo ni nombre
comercial de la IPS, ni cambiar textos o estructura de componentes.

#### Scenario: Pantalla de inicio de sesión con la paleta
- **WHEN** un visitante abre `/login` en tema claro
- **THEN** el fondo de la página es `#f8f9fa`, el texto es `#212b51` y el botón "Iniciar sesión" es azul marino `#232955` con texto claro

#### Scenario: Insignia secundaria en verde lima
- **WHEN** un usuario con sesión ve su rol en el encabezado
- **THEN** la insignia del rol tiene fondo verde lima `#a9cd43` y texto azul marino, nunca texto blanco

#### Scenario: Verde lima sin texto sobre fondo claro
- **WHEN** se leen los tokens del tema claro
- **THEN** ningún token de texto (`*-foreground` o `destructive`) ni el token `ring` vale `#a9cd43`

#### Scenario: Sin elementos de marca
- **WHEN** un usuario recorre inicio de sesión, inicio, dispensación, inventario y traslados
- **THEN** ninguna pantalla muestra logo ni nombre comercial de la IPS y los textos visibles son los del módulo central de textos sin cambios

### Requirement: Tema oscuro derivado de la paleta
El bloque de tema oscuro SHALL derivarse de la misma paleta: fondo y superficies en tonos oscuros del azul
marino, texto claro, primario verde lima con texto azul marino y destructivo en rojo. No se agrega selector de
tema.

#### Scenario: Tokens oscuros derivados
- **WHEN** se aplica la clase `dark` a la raíz del documento
- **THEN** el fondo es un azul marino oscuro, el texto es claro y el primario es verde lima `#a9cd43` con texto azul marino

#### Scenario: Sin azul marino sobre azul marino
- **WHEN** se leen los tokens del tema oscuro
- **THEN** ningún par texto/fondo combina dos tonos de azul marino por debajo de 4,5:1

### Requirement: Contraste AA de los pares de tokens
Cada par texto/fondo definido por los tokens de tema SHALL alcanzar contraste ≥ 4,5:1 en tema claro y oscuro,
y el token de foco SHALL alcanzar ≥ 3:1 sobre el fondo. Una prueba automática de la suite web SHALL leer los
tokens del archivo de tema y fallar si un par queda por debajo. El color destructivo SHALL conservar su
significado (rojo) en ambos temas.

#### Scenario: Todos los pares cumplen AA
- **WHEN** corre la suite de pruebas de la SPA
- **THEN** la prueba de contraste evalúa cada par en tema claro y oscuro, todos quedan ≥ 4,5:1 (foco ≥ 3:1) y la prueba pasa

#### Scenario: Par bajo AA hace fallar la suite
- **WHEN** un token se cambia de modo que un par queda bajo 4,5:1, por ejemplo texto blanco sobre el secundario verde lima
- **THEN** la prueba de contraste falla y su mensaje nombra el par, el tema y la razón obtenida

#### Scenario: Prueba de contraste sin tokens no pasa en vacío
- **WHEN** la prueba de contraste no encuentra tokens en el archivo de tema, o falta en un tema algún token de un par esperado
- **THEN** la prueba falla y su mensaje nombra el tema y el token ausente, en lugar de pasar sin evaluar pares

#### Scenario: Destructivo conserva color y contraste
- **WHEN** la SPA muestra un aviso de error o la insignia "Vencido" en tema claro u oscuro
- **THEN** se ven en rojo destructivo y el par del destructivo con su fondo alcanza ≥ 4,5:1
