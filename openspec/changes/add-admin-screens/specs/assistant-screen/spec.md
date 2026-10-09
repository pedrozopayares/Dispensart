# assistant-screen — delta

## RENAMED Requirements

- FROM: `### Requirement: Pantalla Asistente para toda sesión`
- TO: `### Requirement: Pantalla Asistente para los roles de operación`

## MODIFIED Requirements

### Requirement: Pantalla Asistente para los roles de operación
La SPA SHALL ofrecer la pantalla "Asistente" en `/assistant`, dentro del shell, para toda sesión salvo el rol
`admin` (§ 3), que ve el aviso de permiso de operator-workspace; los permisos los aplica el servidor en cada
herramienta.
SHALL seguir la disposición común de operator-workspace: título, caja de pregunta arriba, resultados
debajo, componentes del kit (ADR-0004) y textos del módulo central. Abrirla SHALL NOT enviar ninguna
pregunta (RN-10, parte C).

#### Scenario: Médico abre el asistente desde el menú
- **WHEN** un `medico` con sesión pulsa "Asistente" en el menú
- **THEN** la SPA abre `/assistant` con el título "Asistente de inventario", la caja "Tu pregunta" con el foco, el botón "Preguntar" y el enlace "Asistente" marcado como página actual

#### Scenario: Apertura sin preguntas enviadas
- **WHEN** un `auxiliar_farmacia` abre `/assistant`
- **THEN** se ve "Aún no has hecho preguntas. Prueba con uno de los ejemplos." y no se envía ninguna petición al asistente

#### Scenario: Acceso directo sin sesión
- **WHEN** un visitante sin sesión abre `/assistant` escribiendo la dirección
- **THEN** la SPA lo lleva a `/login` sin mostrar la pantalla ni enviar ninguna pregunta

#### Scenario: Admin escribe la dirección del asistente
- **WHEN** un `admin` con sesión abre `/assistant` escribiendo la dirección
- **THEN** ve "No tienes permiso para ver esta pantalla." y el enlace "Volver al inicio", sin la caja "Tu pregunta", y no se envía ninguna petición al asistente
