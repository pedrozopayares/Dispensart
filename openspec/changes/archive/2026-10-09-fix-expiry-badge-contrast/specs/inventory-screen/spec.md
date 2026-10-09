## ADDED Requirements

### Requirement: Contraste AA de la insignia de vencimiento próximo
La insignia "Vence en {n} días" de `/inventory` SHALL tomar texto y fondo de un par de tokens de tema de
advertencia, definido en tema claro y oscuro, con contraste ≥ 4,5:1 en ambos. SHALL conservar el significado de
advertencia en ámbar, distinto del rojo de "Vencido" y del verde lima de "Bajo mínimo". La prueba de contraste de
la SPA SHALL evaluar ese par y fallar si queda por debajo. Textos y resaltado de fila no cambian.

#### Scenario: Insignia por vencer legible en ambos temas
- **WHEN** corre la suite de pruebas de la SPA
- **THEN** la prueba de contraste evalúa el par texto/fondo de la insignia de advertencia en tema claro y oscuro, ambos quedan ≥ 4,5:1 y la prueba pasa

#### Scenario: Insignia por vencer con colores de tema
- **WHEN** las alertas incluyen un lote de Farmacia Central con `days_to_expiry` 20
- **THEN** la fila muestra "Vence en 20 días" en una insignia cuyo texto y fondo vienen del par de advertencia del tema, no de colores fijos con texto blanco

#### Scenario: Advertencia distinta de vencido y bajo mínimo
- **WHEN** se leen los tokens de tema claro y oscuro
- **THEN** el fondo de advertencia es un ámbar, distinto del token destructivo (rojo) y del secundario (verde lima `#a9cd43`)

#### Scenario: Par de advertencia bajo AA hace fallar la suite
- **WHEN** el texto de advertencia se cambia a blanco sobre ámbar `#f59e0b` (≈ 2,15:1) en un tema
- **THEN** la prueba de contraste falla y su mensaje nombra el par de advertencia, el tema y la razón obtenida

#### Scenario: Token de advertencia ausente no pasa en vacío
- **WHEN** falta en un tema el token de texto o de fondo de advertencia
- **THEN** la prueba de contraste falla y su mensaje nombra el tema y el token ausente
