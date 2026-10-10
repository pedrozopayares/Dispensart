# delivery-pipeline Specification

## Purpose
Lleva cada commit de `dev` o `main` que pasó las compuertas de calidad a imágenes publicadas e inmutables,
las prueba en un staging simulado y solo las promueve a producción con aprobación humana, con permisos
mínimos por trabajo y sin secretos guardados (parte D).

## Requirements

### Requirement: Entrega solo tras las compuertas de calidad
La entrega SHALL ejecutarse en push a `dev` o `main` que modifique `software/**` o `.github/workflows/**`, y
SHALL comenzar solo cuando los trabajos de backend y frontend del mismo commit terminaron en éxito. SHALL NOT
ejecutarse en pull requests ni en push a otras ramas. Protege RN-01..RN-11: ningún cambio que rompa sus
pruebas llega a una imagen.

#### Scenario: Push a dev con compuertas verdes
- **WHEN** se hace push a `dev` de un commit que modifica `software/` y backend y frontend pasan
- **THEN** el trabajo de construcción de imágenes comienza para ese mismo SHA

#### Scenario: Compuerta de calidad fallida
- **WHEN** en un push a `dev` o `main` el trabajo de backend o el de frontend termina en fallo
- **THEN** construcción, staging y producción no se ejecutan y el run queda en fallo

#### Scenario: Pull request
- **WHEN** llega un pull request, propio o desde un fork, que modifica `software/`
- **THEN** corren solo las compuertas de calidad y ningún trabajo de construcción, staging ni producción se ejecuta

#### Scenario: Push a una rama de trabajo
- **WHEN** se hace push a `feat/<id>` de un commit que modifica `software/`
- **THEN** corren las compuertas de calidad y ningún trabajo de entrega se ejecuta

#### Scenario: Cambio solo de documentación de proceso
- **WHEN** se hace push a `dev` de un commit que modifica solo `openspec/` o `docs/`
- **THEN** no se ejecuta ningún trabajo de entrega

### Requirement: Imágenes publicadas e inmutables por commit
La construcción SHALL producir las imágenes `api` (etapa de producción) y `web` desde los Dockerfiles de
`software/docker/` y publicarlas en GHCR como `dispensart-api` y `dispensart-web`, etiquetadas con el SHA
completo del commit y vinculadas al repositorio. SHALL NOT publicar etiquetas móviles. Los digests SHALL
pasar a los trabajos siguientes.

#### Scenario: Publicación de ambas imágenes
- **WHEN** el trabajo de construcción termina en éxito para el commit `<sha>`
- **THEN** existen `ghcr.io/<dueño>/dispensart-api:<sha>` y `ghcr.io/<dueño>/dispensart-web:<sha>`, y los digests de ambas quedan como salidas del trabajo

#### Scenario: Construcción fallida
- **WHEN** la construcción de cualquiera de las dos imágenes falla
- **THEN** el trabajo de construcción termina en fallo, staging y producción no se ejecutan y el run queda en fallo

#### Scenario: Sin etiquetas móviles
- **WHEN** se listan las etiquetas publicadas de ambos paquetes tras varios runs
- **THEN** toda etiqueta es un SHA de 40 caracteres hexadecimales y no aparece `latest`, `dev` ni `main`

### Requirement: Staging simulado con prueba de humo
El trabajo de staging SHALL levantar en el runner `db`, `api` y `web` con las imágenes publicadas,
referidas por digest y sin reconstruir; SHALL esperar `healthy` y comprobar `/health`, `/ready`, `/` y que
`api` y `web` no corren como root. Cualquier fallo SHALL terminar el trabajo en fallo, mostrar los logs del
stack y bajarlo.

#### Scenario: Humo verde
- **WHEN** staging levanta el stack con los digests del trabajo de construcción
- **THEN** los tres servicios llegan a `healthy`, `GET /health` y `GET /ready` vía `web` responden HTTP 200 y `GET /` responde HTTP 200 con el documento de la SPA, y `id -u` en `api` y `web` no es `0` [ancla: SH › API viva con dependencias sanas; SH › API lista; RE › Shell en la raíz — live specs]

#### Scenario: Humo fallido
- **WHEN** `GET /ready` no responde HTTP 200 dentro del plazo del humo, o un servicio no llega a `healthy` [ancla: SH › Base de datos inalcanzable, SH › Migraciones pendientes — live spec `service-health`]
- **THEN** el trabajo de staging termina en fallo tras imprimir `docker compose logs`, el stack se baja con sus volúmenes y producción no se ejecuta

#### Scenario: Imagen ausente en el registro
- **WHEN** un digest recibido no existe en GHCR
- **THEN** staging termina en fallo al descargar la imagen, sin construirla en el runner

#### Scenario: Sin reconstrucción en staging
- **WHEN** se revisa el log del trabajo de staging de un run verde
- **THEN** no contiene pasos de construcción de imágenes y cada imagen de `api` y `web` se refiere por `@sha256:`

### Requirement: Producción con aprobación manual
El trabajo de producción SHALL ejecutarse solo desde `main`, después de staging en éxito, bajo el entorno
`production` con al menos un revisor requerido. Ningún paso SHALL correr antes de la aprobación. El
despliegue simulado SHALL usar los mismos digests que pasaron staging. Sin revisor requerido en el entorno,
SHALL fallar antes de desplegar.

#### Scenario: Espera de aprobación
- **WHEN** un run de `main` termina staging en éxito
- **THEN** el trabajo de producción queda en espera de revisión y ninguno de sus pasos se ejecuta

#### Scenario: Aprobado
- **WHEN** un revisor aprueba el despliegue en espera
- **THEN** el trabajo de producción corre y su resumen lista los digests de `api` y `web`, idénticos a los que usó staging en ese run

#### Scenario: Rechazado
- **WHEN** un revisor rechaza el despliegue en espera
- **THEN** el trabajo de producción no ejecuta ningún paso y ningún despliegue queda registrado para ese run

#### Scenario: Push a dev
- **WHEN** un run de `dev` termina staging en éxito
- **THEN** el trabajo de producción queda omitido, no fallido, y no pide aprobación

#### Scenario: Staging fallido
- **WHEN** staging termina en fallo en un run de `main`
- **THEN** el trabajo de producción no se ejecuta y no pide aprobación

#### Scenario: Entorno sin revisor requerido
- **WHEN** el entorno `production` no existe o no declara revisores requeridos y el trabajo de producción arranca
- **THEN** su primer paso termina en fallo antes del paso de despliegue y el run queda en fallo

### Requirement: Mínimo privilegio y secretos en la entrega
Cada trabajo de entrega SHALL declarar sus permisos; solo el de construcción SHALL tener `packages: write`
y los demás a lo sumo `packages: read`.
La entrega SHALL autenticarse solo con `GITHUB_TOKEN` y SHALL NOT leer secretos guardados. La contraseña de
la base de staging SHALL generarse por run, enmascararse y no aparecer en ningún log. El checkout SHALL NOT
persistir credenciales.

#### Scenario: Escritura al registro acotada
- **WHEN** se leen los permisos efectivos de cada trabajo de los workflows
- **THEN** solo el trabajo de construcción tiene `packages: write`, staging y producción tienen a lo sumo `packages: read`, ninguno tiene `contents: write` ni `id-token: write`, y el nivel de workflow no concede escritura

#### Scenario: Sin secretos guardados
- **WHEN** se buscan referencias `secrets.` en los workflows
- **THEN** la única es `secrets.GITHUB_TOKEN` y no aparece en los trabajos de calidad

#### Scenario: Contraseña de staging fuera de los logs
- **WHEN** staging termina, en éxito o en fallo, y se descarga el log completo del run
- **THEN** el valor de la contraseña generada no aparece en claro; un paso de control que la imprime muestra `***`

#### Scenario: Checkout sin credenciales persistidas
- **WHEN** se leen los pasos de checkout de los workflows
- **THEN** cada uno declara `persist-credentials: false`

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
