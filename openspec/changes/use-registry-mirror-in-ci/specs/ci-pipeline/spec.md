## ADDED Requirements

### Requirement: Imágenes de Docker Hub desde el espejo en CI
Todo trabajo del workflow de CI SHALL descargar cada imagen alojada en Docker Hub desde `mirror.gcr.io`, con
el mismo repositorio y la misma etiqueta que hoy: servicio PostgreSQL del backend, imágenes base y de BuildKit
de la construcción, y `db` del staging. SHALL NOT autenticarse ante ningún registro para ello ni leer
secretos nuevos. Fuera del CI, el stack local SHALL seguir resolviendo esas imágenes en Docker Hub.

#### Scenario: Servicio PostgreSQL del backend desde el espejo
- **WHEN** arranca el trabajo de backend
- **THEN** su contenedor de servicio usa `mirror.gcr.io/library/postgres:16.15-alpine3.24` y el log del run no muestra descargas desde `docker.io` ni `registry-1.docker.io`

#### Scenario: Construcción de imágenes desde el espejo
- **WHEN** el trabajo de construcción construye `api` y `web`
- **THEN** `php:8.5.11-fpm-alpine3.24`, `composer:2.10.3`, `node:22.23.1-alpine3.23`, `nginxinc/nginx-unprivileged:1.30.3-alpine3.23`, el frontend `docker/dockerfile:1` y la imagen del constructor de BuildKit se resuelven bajo `mirror.gcr.io`, con las mismas etiquetas que los valores por defecto de los Dockerfiles

#### Scenario: Base de datos del staging desde el espejo
- **WHEN** el trabajo de staging descarga y arranca `db`
- **THEN** compose recibe `DB_IMAGE=mirror.gcr.io/library/postgres:16.15-alpine3.24` y el servicio llega a `healthy` con esa imagen

#### Scenario: Mismo contenido que Docker Hub
- **WHEN** se compara, para cada imagen redirigida, el digest del manifiesto en `mirror.gcr.io` con el de Docker Hub para la misma etiqueta
- **THEN** ambos digests son idénticos

#### Scenario: Sin credenciales para el espejo
- **WHEN** se leen los pasos de todos los trabajos del workflow
- **THEN** no hay login a `mirror.gcr.io` ni a `docker.io`, y la única referencia `secrets.` sigue siendo `secrets.GITHUB_TOKEN` fuera de los trabajos de calidad

#### Scenario: Stack local sin DB_IMAGE
- **WHEN** se ejecuta `docker compose -f software/compose.yaml config` sin la variable `DB_IMAGE` definida
- **THEN** la imagen de `db` es `postgres:16.15-alpine3.24` y los `ARG` de imagen de los Dockerfiles conservan sus valores de Docker Hub

#### Scenario: Espejo sin la etiqueta pedida
- **WHEN** `mirror.gcr.io` no tiene en caché la etiqueta pedida o no está disponible durante el run
- **THEN** el paso que descarga la imagen termina en fallo y el run queda en fallo, sin recurrir en silencio a Docker Hub

#### Scenario: Vuelta documentada a Docker Hub
- **WHEN** un operador abre `software/docs/deployment.md` ante una caída del espejo
- **THEN** encuentra qué imágenes pasan por `mirror.gcr.io` y la edición concreta que devuelve el CI a Docker Hub

### Requirement: Guarda contra descargas directas de Docker Hub en CI
Una compuerta de calidad SHALL ejecutar una guarda versionada que haga fallar el workflow cuando el CI
pueda descargar una imagen de Docker Hub sin pasar por `mirror.gcr.io`, ya sea desde el workflow, un
Dockerfile, compose o el constructor de BuildKit. La guarda SHALL nombrar el archivo y la línea de cada
hallazgo.

#### Scenario: Árbol conforme
- **WHEN** la guarda corre sobre el árbol del commit
- **THEN** termina con código 0 e imprime cuántas referencias revisó, con un total mayor que cero

#### Scenario: Imagen sin registro en el workflow
- **WHEN** el workflow declara `image: postgres:16.15-alpine3.24`, o una referencia `docker.io/...`, o una acción `docker://` hacia Docker Hub
- **THEN** la guarda termina en fallo nombrando `.github/workflows/ci.yml` y la línea, y el workflow queda en fallo

#### Scenario: Imagen base nueva en un Dockerfile sin redirigir
- **WHEN** un Dockerfile de `software/docker/` declara un `ARG` de imagen o una directiva `# syntax=` con valor de Docker Hub que el workflow no sobrescribe con una referencia de `mirror.gcr.io`
- **THEN** la guarda termina en fallo nombrando el Dockerfile, la línea y el argumento

#### Scenario: Servicio de compose descargado sin redirigir
- **WHEN** un servicio de `software/compose.yaml` sin `build` declara una imagen de Docker Hub literal, o una variable cuyo valor por defecto es de Docker Hub y que el workflow no fija bajo `mirror.gcr.io`
- **THEN** la guarda termina en fallo nombrando el servicio y la línea

#### Scenario: Constructor de BuildKit sin redirigir
- **WHEN** un paso `docker/setup-buildx-action` del workflow no declara en `driver-opts` una imagen `mirror.gcr.io/moby/buildkit` con etiqueta de versión `vX.Y.Z`
- **THEN** la guarda termina en fallo nombrando `.github/workflows/ci.yml` y la línea del paso

#### Scenario: Imágenes construidas o de otros registros
- **WHEN** el árbol declara `${API_IMAGE:-dispensart-api:local}`, `${WEB_IMAGE:-dispensart-web:local}` o referencias `ghcr.io/...`
- **THEN** la guarda no las reporta como hallazgo
