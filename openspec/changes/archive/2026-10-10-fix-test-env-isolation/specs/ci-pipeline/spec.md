## ADDED Requirements

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
