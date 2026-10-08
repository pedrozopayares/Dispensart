# ci-pipeline Specification

## Purpose
Impide que entre al repositorio código de `software/` que no pase lint, análisis estático y pruebas de
backend y frontend, con permisos mínimos y sin secretos. S8 extiende esta capacidad con imágenes y
despliegues.

## Requirements

### Requirement: Disparo por rutas
El workflow de CI SHALL ejecutarse en cada push y pull request que modifique archivos bajo `software/` o el
propio archivo del workflow, y SHALL NOT ejecutarse cuando el cambio solo toca otras rutas.

#### Scenario: Cambio en el código de la aplicación
- **WHEN** se hace push a `dev` de un commit que modifica un archivo bajo `software/`
- **THEN** el workflow de CI se ejecuta

#### Scenario: Cambio en el workflow
- **WHEN** un commit modifica solo `.github/workflows/ci.yml`
- **THEN** el workflow de CI se ejecuta

#### Scenario: Cambio solo de documentación de proceso
- **WHEN** un commit modifica solo archivos bajo `openspec/` o `docs/`
- **THEN** el workflow de CI no se ejecuta

### Requirement: Compuerta de calidad del backend
El CI SHALL ejecutar sobre `software/api` el formato de Pint en modo verificación, el análisis estático de
Larastan y la suite de Pest contra un servicio PostgreSQL 16. Cualquier paso fallido SHALL hacer fallar el
workflow. Las pruebas SHALL NOT recurrir a SQLite.

#### Scenario: Backend correcto
- **WHEN** el código de la API cumple el formato, no tiene hallazgos de Larastan y Pest pasa
- **THEN** el trabajo de backend termina en éxito

#### Scenario: Violación de formato
- **WHEN** un archivo PHP no cumple el formato de Pint
- **THEN** el trabajo de backend termina en fallo y el workflow queda en fallo, sin reescribir el archivo

#### Scenario: Hallazgo de análisis estático o prueba fallida
- **WHEN** Larastan reporta un hallazgo o una prueba de Pest falla
- **THEN** el trabajo de backend termina en fallo y el workflow queda en fallo

#### Scenario: Pruebas contra PostgreSQL
- **WHEN** corre la suite de Pest en CI
- **THEN** la conexión de pruebas es `pgsql` contra el servicio PostgreSQL 16 del trabajo; si el servicio no está disponible, las pruebas fallan en lugar de usar SQLite

### Requirement: Compuerta de calidad del frontend
El CI SHALL ejecutar sobre `software/web` ESLint y la suite de Vitest con dependencias instaladas desde el
lockfile. Cualquier paso fallido SHALL hacer fallar el workflow.

#### Scenario: Frontend correcto
- **WHEN** ESLint no reporta errores y Vitest pasa
- **THEN** el trabajo de frontend termina en éxito

#### Scenario: Error de lint o prueba fallida
- **WHEN** ESLint reporta un error o una prueba de Vitest falla
- **THEN** el trabajo de frontend termina en fallo y el workflow queda en fallo

#### Scenario: Lockfile desincronizado
- **WHEN** `package.json` declara una dependencia ausente del lockfile
- **THEN** la instalación falla y el trabajo de frontend termina en fallo

### Requirement: Permisos mínimos y sin secretos
El workflow SHALL declarar permisos de solo lectura sobre el contenido del repositorio, SHALL NOT leer
secretos y SHALL NOT construir ni publicar imágenes ni desplegar (alcance de S8).

#### Scenario: Permisos de solo lectura
- **WHEN** se lee `.github/workflows/ci.yml`
- **THEN** declara `permissions: contents: read` a nivel de workflow y ningún trabajo amplía permisos

#### Scenario: Sin secretos ni publicación
- **WHEN** se buscan en el workflow referencias a `secrets.`, `docker push`, registros de imágenes o entornos de despliegue
- **THEN** no aparece ninguna

#### Scenario: Pull request desde un fork
- **WHEN** un pull request llega desde un fork
- **THEN** el CI corre completo con las mismas compuertas, porque no depende de secretos
