## ADDED Requirements

### Requirement: Contrato OpenAPI de salud y disponibilidad
`software/api/openapi.json` SHALL documentar `GET /health` y `GET /ready` en la raíz del mismo origen, no bajo
`/api`, como operaciones públicas sin cookie ni CSRF, con sus códigos, el esquema de su cuerpo y la cabecera
`X-Correlation-Id`, sin claves internas (RN-10; parte D). Una prueba de contrato SHALL fallar si falta alguna de
las dos. El contrato SHALL seguir saliendo de la exportación del código, sin deriva.

#### Scenario: Vivacidad documentada
- **WHEN** se lee la operación `GET /health` de `openapi.json`
- **THEN** declara la respuesta 200 con un objeto cuya única clave es `status` con valor `ok`, y la cabecera `X-Correlation-Id` [ancla: SH › API viva con dependencias sanas — live spec `service-health`; ruta `software/api/routes/health.php:8`]

#### Scenario: Disponibilidad documentada con su fallo
- **WHEN** se lee la operación `GET /ready` de `openapi.json`
- **THEN** declara las respuestas 200 y 503, ambas con la cabecera `X-Correlation-Id` y un objeto con `status` (`ready` o `not_ready`) y `checks` con `database` (`ok` o `fail`) y `migrations` (`ok`, `pending`, `skipped` o `fail`) [ancla: SH › API lista y SH › Base de datos inalcanzable — live spec `service-health`; `software/api/app/Http/Controllers/Health/ReadyController.php:17`]

#### Scenario: URL en la raíz del origen
- **WHEN** se resuelve la URL de `GET /health` y de `GET /ready` combinando su ruta con el servidor que les aplica en el contrato
- **THEN** las URLs son `/health` y `/ready`, no `/api/health` ni `/api/ready`, y las demás operaciones siguen resolviendo bajo `/api`

#### Scenario: Operaciones públicas sin seguridad de sesión
- **WHEN** se leen los requisitos de seguridad y las respuestas de `GET /health` y `GET /ready`
- **THEN** ninguna exige la cookie de sesión ni `X-XSRF-TOKEN`, y ninguna declara 401 ni 419 [ancla: rutas sin grupo de middleware, `software/api/routes/health.php:7-9`]

#### Scenario: Contrato sin claves internas
- **WHEN** se recorren las propiedades de los esquemas de respuesta de `GET /health` y `GET /ready`
- **THEN** no aparece ninguna clave fuera de `status`, `checks`, `database` y `migrations`, ni ejemplo alguno con host, usuario, versión o mensaje de excepción

#### Scenario: Salud ausente del contrato
- **WHEN** se quita `GET /health` o `GET /ready` de `openapi.json`
- **THEN** la prueba de contrato falla nombrando la operación ausente

#### Scenario: Contrato derivado del código
- **WHEN** se ejecutan `composer openapi:check` en `software/api`, `npm run openapi:lint` en la raíz y `npm run api:types:check` en `software/web`
- **THEN** los tres terminan con código 0

#### Scenario: Edición manual del contrato
- **WHEN** se agregan a mano a `openapi.json` las operaciones de salud sin que la exportación del código las produzca
- **THEN** `composer openapi:check` termina con código distinto de 0
