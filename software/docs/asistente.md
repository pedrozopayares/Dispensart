# Asistente de inventario

`POST /api/assistant/ask` responde preguntas de inventario en español (parte C); en la SPA lo usa la pantalla
Asistente (`/assistant`), visible para los cinco roles. El modelo **solo elige**
herramienta y argumentos; el servidor ejecuta, decide `outcome` y redacta `answer` con los datos. El texto del
modelo nunca llega al usuario: no puede inventar cifras.

## Proveedor: `mock` por defecto, `ollama` local, ningún servicio de pago

Todo el asistente depende de la interfaz `LlmProvider`; `AI_PROVIDER` elige la implementación:

| `AI_PROVIDER` | Qué hace | Costo y red |
|---|---|---|
| vacío o `mock` | Reglas deterministas sobre la pregunta (intención, producto, bodega, plazo) | Cero; sin red ni llave |
| `ollama` | `POST {OLLAMA_BASE_URL}/api/chat`, `OLLAMA_MODEL` (por defecto `qwen2.5:3b`), temperatura 0 | Cero; modelo local |
| otro valor | El asistente responde 503 `assistant_unavailable`; el resto de la API sigue igual | — |

`mock` es el valor por defecto: evaluación y CI corren sin llaves, sin red y con resultado estable.
`AI_PROVIDER` decide solo las preguntas que llegan sin `model`: `assistant:eval`, CI y clientes que no eligen.

## Selector de modelo en la pantalla Asistente

La pantalla ofrece el selector "Modelo" con lo que devuelve `GET /api/assistant/models`: "Simulado (sin red)"
(`mock`) siempre y primero, y "Ollama · {nombre}" por cada modelo disponible. La pregunta viaja con
`model` = `mock` u `ollama:<nombre>`; la API lo valida contra esa misma lista (422 «El modelo elegido no está
disponible.» si no figura) y la respuesta trae `data.model`, que la pantalla muestra en cada entrada del
historial ("Respondió: …").

Un modelo de Ollama está disponible si está descargado (`/api/tags`) y su ficha (`/api/show`) declara la
capacidad `tools`. El descubrimiento tiene un plazo total de 2 s y la lista de nombres se guarda 30 s en caché
de archivos (nunca en la base). Ollama caído, lento o con error → solo `mock`, HTTP 200, sin aviso de error; un
modelo borrado puede seguir hasta 30 s en la lista y su pregunta responde 503.

La primera visita arranca en `mock`, aunque `AI_PROVIDER=ollama`. La elección se guarda en el navegador
(`localStorage`, clave `dispensart.assistant.model`, solo el `id`); al volver se restaura si sigue en la lista
y, si no, la pantalla usa `mock` con el aviso «El modelo que elegiste ya no está disponible. Se usa Simulado
(sin red).». La pregunta y el historial nunca se guardan.

Para ofrecer un modelo local: descargarlo con `ollama pull <modelo>` y comprobar que `ollama show <modelo>`
lista `tools` en `Capabilities` (p. ej. `gemma4:e2b-mlx`). Aparece en el selector sin reiniciar nada, al
vencer la caché de 30 s del servidor y la de 30 s de la pantalla. La URL de Ollama sale solo de `OLLAMA_BASE_URL`.

**Compromiso del modelo local.** `qwen2.5:3b` (unos 2 GB) entiende español y herramientas, pero en CPU tarda
segundos por ronda y puede elegir mal o enviar enteros como texto (`invalid_arguments`). `qwen2.5:7b` o
`llama3.1:8b` aciertan más con más memoria y latencia. A cambio, ningún dato sale de la máquina. Plazo total
por pregunta: 50 s; agotado, o con Ollama caído, la API responde 503.

**Medición real.** Con `gemma4:e2b-mlx` en Ollama sobre el equipo Apple Silicon del autor. El 2026-10-09, con el
comparador que usa bodega y producto resueltos, `assistant:eval` dio 22/24. Los dos fallos son del modelo: el
caso 8 (`stock-auditor-hospitalizacion`) lo dio por fuera de alcance y el 10 (`low-stock-equal-minimum`) respondió
«desconocido» en vez de «sin resultados». La corrida previa (2026-10-08, comparador literal) dio 20/24, con entre
7 y 19 s por pregunta; su caso 4 falló y ahora pasó: el modelo local no es determinista entre corridas.

## Herramientas y roles

Cuatro herramientas de solo lectura, con esquema cerrado y la Policy de su ruta REST:

| Herramienta | Responde | Capacidad |
|---|---|---|
| `find_expiring_lots` | Lotes con existencia que vencen en `days` días (1–365, por defecto 90), vencidos marcados | `inventory.view` |
| `get_stock` | Existencias por bodega y producto; total disponible sin lotes vencidos | `inventory.view` |
| `get_low_stock_alerts` | Productos bajo su mínimo | `inventory.view` |
| `get_transfer_status` | Estado de un traslado o conteo por estado | `transfers.view` |

Cada llamada corre en una transacción `transaction_read_only` que siempre se revierte. El rol sale de la
sesión, nunca de un argumento: `medico` y `admin` reciben `not_permitted`.

## Defensa contra inyección

- Instrucciones fijas en el código; la pregunta viaja solo como mensaje de usuario.
- Resultados y observaciones de traslados viajan entre delimitadores `trust="untrusted"`, con `<` y `>`
  escapados: ningún texto cierra el sobre.
- Herramienta fuera del catálogo → fin (`unknown`); argumentos fuera de esquema no se ejecutan. Máximo 4
  llamadas y 5 rondas.
- `answer` nunca muestra las observaciones.

## Pacientes

Ninguna herramienta lee pacientes, prescripciones ni dispensaciones (prueba de arquitectura). Una pregunta que
los menciona, o con 7 o más dígitos seguidos, responde `out_of_scope` sin llamar al modelo. El log guarda
resultado, herramientas, estados, proveedor y modelo, nunca pregunta ni respuesta. **Riesgo:** las observaciones de un traslado
son texto libre y podrían traer un dato personal tecleado; con Ollama no sale de la máquina, pero un proveedor
externo futuro debe excluirlas.

## Fuera de alcance y "no sé"

Pregunta ajena, pedido de escritura o SQL → `out_of_scope` con mensaje fijo. Sin datos → `no_results`. Rol
sin permiso → `not_permitted`. Argumentos inválidos o límite alcanzado → `unknown` ("No sé responder…").

## Evaluación

`software/api/resources/assistant/evaluation-set.json`: 24 preguntas con rol, herramienta, argumentos y
fragmentos esperados. El comando las responde con el servicio de la ruta sobre una base desechable
(`<base>_assistant_eval`, creada y borrada por corrida) e imprime una fila por pregunta y `Aciertos: N/T`. Sale
con 0 solo si todas aciertan; CI lo corre con `mock`.
El comparador usa la bodega y el producto resueltos en el catálogo, no el texto literal de los argumentos.

```sh
docker compose -f software/compose.yaml --profile tools run --rm api-tools php artisan assistant:eval
```

Con Ollama en el anfitrión (`ollama pull qwen2.5:3b`), el mismo informe mide el modelo local; `-e OLLAMA_MODEL=<modelo>`
mide otro (p. ej. `gemma4:e2b-mlx`):

```sh
docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=ollama api-tools php artisan assistant:eval
```
