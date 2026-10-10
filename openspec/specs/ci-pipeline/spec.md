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

### Requirement: Suite del backend aislada del entorno del anfitrión
Dentro de la suite de Pest, el proveedor del asistente SHALL ser `mock` y el modelo de Ollama configurado SHALL ser
`qwen2.5:3b`, sea cual sea `AI_PROVIDER` u `OLLAMA_MODEL` en el proceso (contenedor, `software/.env`, CI). Una
prueba que necesite otro proveedor o modelo SHALL fijarlo en su propia configuración. `php artisan assistant:eval`,
que corre fuera de la suite, SHALL seguir leyendo `AI_PROVIDER` del proceso (parte E; sin RN propia: protege la
verificación de RN-01..RN-11).

#### Scenario: Proveedor ollama en el entorno del proceso
- **WHEN** la suite corre en `api-tools` con `AI_PROVIDER=ollama` en el entorno del proceso
- **THEN** dentro de cada prueba el proveedor configurado es `mock`, el modelo por defecto resuelto es `mock` y ninguna prueba que no fije su proveedor envía peticiones a Ollama

#### Scenario: Modelo de Ollama en el entorno del proceso
- **WHEN** la suite corre con `OLLAMA_MODEL=gemma4:e2b-mlx` en el entorno del proceso
- **THEN** dentro de cada prueba el modelo de Ollama configurado es `qwen2.5:3b`

#### Scenario: Prueba que fija su propio proveedor
- **WHEN** una prueba fija en su configuración el proveedor `ollama` y un modelo, con Ollama simulado en el borde HTTP
- **THEN** esa prueba usa `ollama` con ese modelo, y la prueba siguiente vuelve a ver `mock` y `qwen2.5:3b`

#### Scenario: Pin retirado de la configuración de la suite
- **WHEN** se retira de la configuración de la suite el pin de `AI_PROVIDER` y la suite corre, con o sin `AI_PROVIDER=ollama` en el proceso
- **THEN** la prueba de aislamiento falla y su mensaje nombra `AI_PROVIDER`

#### Scenario: Evaluación fuera de la suite sigue al entorno
- **WHEN** se ejecuta `php artisan assistant:eval` en `api-tools` con `AI_PROVIDER=ollama` y el servidor Ollama no responde
- **THEN** el comando se comporta como en assistant-evaluation › «Proveedor no disponible»: las filas que necesitan al proveedor dicen fallo con `asistente no disponible` y la salida es 1, prueba de que leyó `ollama` y no `mock`
