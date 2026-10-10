# Spec Delta — delivery-pipeline

## ADDED Requirements

### Requirement: Colección de Postman en el staging simulado
El trabajo de staging SHALL ejecutar la colección de `software/docs/postman/` con `newman` en versión exacta,
sin rango, contra el stack levantado por digest y después del paso de humos, con el entorno local y la URL del
stack. Una aserción fallida SHALL terminar staging en fallo. SHALL NOT usar servicios de pago, cuentas de
Postman ni secretos.

#### Scenario: Colección verde en staging
- **WHEN** un push a `dev` llega a staging y los humos terminan en éxito
- **THEN** el paso de `newman` se ejecuta después de ellos, termina con código 0 y su resumen queda en el log del trabajo

#### Scenario: Aserción fallida en staging
- **WHEN** en staging una aserción de la colección no se cumple
- **THEN** el paso de `newman` termina en fallo, se imprimen los logs del stack, el stack se baja con sus volúmenes, staging queda en fallo y producción no se ejecuta [ancla: delivery-pipeline › Humo fallido — live spec]

#### Scenario: Humos fallidos
- **WHEN** el paso de humos termina en fallo
- **THEN** el paso de `newman` no se ejecuta y staging queda en fallo por los humos

#### Scenario: Fallo no silenciado
- **WHEN** se lee el paso de `newman` en `.github/workflows/ci.yml`
- **THEN** no declara `continue-on-error` ni enmascara el código de salida con `|| true` o equivalente

#### Scenario: Versión fijada y sin secretos
- **WHEN** se leen el paso de `newman` y su entorno
- **THEN** la versión es exacta, igual a la documentada en el README y en la guía de la colección, y el paso no contiene `secrets.` ni llaves de API de Postman
