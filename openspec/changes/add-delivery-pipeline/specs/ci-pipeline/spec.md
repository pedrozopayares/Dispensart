# Spec Delta — ci-pipeline

## MODIFIED Requirements

### Requirement: Permisos mínimos y sin secretos
Los trabajos de calidad (backend y frontend) SHALL ejecutarse con permiso de solo lectura sobre el contenido
del repositorio, SHALL NOT leer secretos y SHALL NOT construir ni publicar imágenes ni desplegar. La
publicación y el despliegue SHALL vivir en trabajos distintos, regidos por la capacidad `delivery-pipeline`.

#### Scenario: Permisos de solo lectura
- **WHEN** se leen los permisos efectivos de los trabajos de backend y frontend
- **THEN** cada uno se ejecuta con `contents: read` y ninguno tiene `packages`, `id-token` ni otro permiso de escritura

#### Scenario: Sin secretos ni publicación
- **WHEN** se buscan en los pasos de los trabajos de backend y frontend referencias a `secrets.`, `docker push`, registros de imágenes o entornos de despliegue
- **THEN** no aparece ninguna

#### Scenario: Pull request desde un fork
- **WHEN** un pull request llega desde un fork
- **THEN** el CI corre completo con las mismas compuertas, porque no depende de secretos, y ningún trabajo de entrega se ejecuta
