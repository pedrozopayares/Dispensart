# Proposal

## Why

La suite de Pest depende de la máquina que la corre (deuda D-auv-9, fila S17 de `ROADMAP.md`, parte E).
`software/api/phpunit.xml` no fija `AI_PROVIDER` ni `OLLAMA_MODEL`: el contenedor `api-tools` las hereda de
`software/.env` vía `compose.yaml`, y en la máquina del autor valen `ollama` y `gemma4:e2b-mlx`. Hoy cada comando de
prueba debe pasar `-e AI_PROVIDER=mock` a mano; sin él, las pruebas que confían en el proveedor por defecto
cambian de resultado según el anfitrión. Un jurado que clone el repositorio y ajuste su `.env` para probar Ollama
vería la suite fallar sin que el código haya cambiado.

## What Changes

- `phpunit.xml` fija `AI_PROVIDER=mock` y `OLLAMA_MODEL=qwen2.5:3b` (el valor por defecto de `config/assistant.php`)
  por encima del entorno del proceso, con el mismo patrón que ya fija la base de pruebas (`DB_*`).
- Prueba nueva de aislamiento: dentro de la suite el proveedor configurado y el modelo por defecto resuelto son
  `mock`, y el modelo de Ollama configurado es `qwen2.5:3b`, aunque el proceso diga `ollama` y otro modelo; la prueba
  falla si el pin desaparece de la configuración de la suite.
- Las pruebas que necesitan Ollama siguen fijándolo ellas mismas en su configuración, con el borde HTTP simulado.
- `php artisan assistant:eval` no pasa por `phpunit.xml`: con `-e AI_PROVIDER=ollama` sigue midiendo Ollama.
- Salda D-auv-9.

Fuera de alcance: `ci.yml`, `compose.yaml`, `software/.env.example`, documentación y comportamiento del asistente.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `ci-pipeline`: requisito nuevo «Suite del backend aislada del entorno del anfitrión».

## Impact

- `software/api`: `phpunit.xml` y una prueba Pest nueva. Sin código de producción, sin rutas, sin migraciones.
- CI: sin pasos nuevos; la prueba de aislamiento corre dentro de `vendor/bin/pest`.
- Reglas de negocio: ninguna se toca. Protege la verificación de todas (RN-01..RN-11): un verde o rojo que dependa
  del anfitrión no prueba nada.

## Assumptions

1. Modelo fijado: `qwen2.5:3b`, el valor por defecto de `config/assistant.php`, `compose.yaml` y `.env.example`.
2. El pin se declara como variable de entorno y como variable de servidor, igual que `DB_*`: Laravel lee
   `$_SERVER` antes que `$_ENV` y el entorno del contenedor ganaría con una sola de las dos.
3. `OLLAMA_BASE_URL` y `OLLAMA_TIMEOUT` no se fijan: ninguna prueba sale a la red (`Http::preventStrayRequests()`
   en `tests/Feature/Assistant`) y las que las usan las fijan en su configuración.
4. La prueba de aislamiento debe fallar al retirar el pin aun si el proceso no define `AI_PROVIDER` (así protege
   también en CI, donde el paso de Pest no la define); el mecanismo lo elige el implementador.
5. Tier A por decisión del usuario del 2026-10-10 (aislamiento del arnés de pruebas; ver `journal.md`).
