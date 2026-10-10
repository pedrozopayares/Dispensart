# Colección de Postman

Colección ejecutable de la API de Dispensart, con cuerpos de ejemplo y aserciones (`pm.test`) sobre los flujos de
la prueba. Complementa el contrato OpenAPI (`software/api/openapi.json`): cubre cada una de sus operaciones.

| Archivo | Uso |
|---|---|
| `software/docs/postman/dispensart.postman_collection.json` | colección Postman v2.1 |
| `software/docs/postman/local.postman_environment.json` | entorno local: `baseUrl` `http://localhost:8090`, correos de los 5 usuarios semilla, `password` |
| `software/docs/postman/check-coverage.sh` | guarda: cada operación del contrato tiene petición en la colección (corre en CI) |

## Ejecutar con newman (sin cuenta de Postman)

Con el stack arriba (`docker compose -f software/compose.yaml up --build`), desde la raíz del repo:

```sh
npx --yes newman@6.2.3 run software/docs/postman/dispensart.postman_collection.json -e software/docs/postman/local.postman_environment.json
```

Sale con 0 solo si todas las aserciones pasan e imprime el resumen de peticiones, aserciones y fallas. Una carpeta
sola: agregar `--folder "02 Dispensación FEFO e idempotencia"`. El staging simulado del CI ejecuta este mismo
comando después de los humos.

## Importar en Postman

1. *Import* de los dos archivos JSON.
2. Elegir el entorno «Dispensart local».
3. Ejecutar la colección (*Run collection*) o una petición. Cada carpeta abre y cierra su propia sesión y crea sus
   datos de la corrida (prescripción, códigos, claves de idempotencia), así que conviene ejecutarla completa.

## Sesión Sanctum SPA

La API usa la sesión por cookie de Sanctum, como la SPA. El script previo de la colección la resuelve en cada
petición: envía `Origin` y `Referer` de `baseUrl`; en las escrituras envía `X-XSRF-TOKEN` con el valor decodificado
de la cookie `XSRF-TOKEN` y, si aún no la hay, la pide antes a `GET /sanctum/csrf-cookie`. No hace falta copiar
tokens ni cookies a mano. Las peticiones que documentan rechazos (carpeta `08 Permisos denegados`) excluyen
cabeceras con `X-Omit-Headers`, que el script quita antes de enviar.

## Contraseña

`password` = `dispensart-dev-only`: valor por defecto SOLO de desarrollo local de los usuarios sintéticos de la
semilla; no es secreto y la API nunca lo aplica con `APP_ENV=production`. Si el stack sembró otra contraseña
(`SEED_USER_PASSWORD`), se sobrescribe sin editar archivos:

```sh
npx --yes newman@6.2.3 run software/docs/postman/dispensart.postman_collection.json -e software/docs/postman/local.postman_environment.json --env-var password=<contraseña>
```

En Postman, editar el valor actual de `password` en el entorno.

## Carpetas

| Carpeta | Flujo |
|---|---|
| `00 Salud` | `/health` y `/ready` en la raíz del origen, sin sesión |
| `01 Sesión por rol` | login, usuario actual y cierre de sesión de los 5 roles |
| `02 Dispensación FEFO e idempotencia` | prescripción de la corrida, vista previa FEFO sin el lote vencido, dispensación con `Idempotency-Key`, repetición sin movimiento nuevo en el kardex, envío sin clave (RN-01, RN-02, RN-06, RN-09) |
| `03 Control especial` | MED-006: sin autorizador 422, coautorizado por el regente 201 (RN-05) |
| `04 Traslado con discrepancia` | recorrido completo con recepción parcial, transición prohibida, resolución, autoaprobación rechazada y anulación (RN-07, RN-08) |
| `05 Alertas, existencias y kardex` | alertas, ajuste de -1 con su movimiento, ajuste mayor que la existencia (RN-11) |
| `06 Asistente` | lista de modelos y pregunta con `mock`; modelo fuera de la lista 422 |
| `07 Catálogo y administración` | usuarios, bodegas, productos y lotes como admin |
| `08 Permisos denegados` | 401 sin sesión, 403 cliente ajeno y rol sin la capacidad, 419 CSRF antes que permisos |

Repetible sobre la misma base: cada corrida usa su propio sufijo. Consume stock semilla finito (como los humos);
`docker compose -f software/compose.yaml down -v` lo repone. La carrera por la última unidad (RN-03) se prueba en
Pest, no aquí.

## Guarda de cobertura

```sh
bash software/docs/postman/check-coverage.sh software/docs/postman/dispensart.postman_collection.json
```

Sale distinto de 0 nombrando cada operación de `openapi.json` sin petición y cada petición fuera del contrato
(`GET /sanctum/csrf-cookie` se admite por lista explícita). Una operación nueva en la API exige su petición aquí.
