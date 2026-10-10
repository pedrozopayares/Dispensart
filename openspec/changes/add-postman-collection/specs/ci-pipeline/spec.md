# Spec Delta — ci-pipeline

## ADDED Requirements

### Requirement: Cobertura del contrato por la colección en CI
El trabajo de backend SHALL ejecutar la guarda de cobertura de la colección de Postman contra
`software/api/openapi.json` en cada push y pull request que dispare el CI. Una operación del contrato sin
petición en la colección, o una petición fuera del contrato, SHALL hacer fallar el trabajo. La guarda SHALL NOT
requerir el stack, red ni secretos.

#### Scenario: Contrato cubierto
- **WHEN** corre el trabajo de backend con una colección que cubre cada operación del contrato
- **THEN** el paso de la guarda termina en éxito

#### Scenario: Operación nueva sin petición
- **WHEN** un commit agrega una operación a `openapi.json` sin agregar su petición a la colección
- **THEN** el paso de la guarda termina en fallo nombrando la operación y el trabajo de backend queda en fallo

#### Scenario: Pull request desde un fork con la guarda
- **WHEN** un pull request desde un fork modifica la colección
- **THEN** la guarda corre con permiso `contents: read`, sin secretos, igual que las demás compuertas [ancla: ci-pipeline › Pull request desde un fork — live spec]
