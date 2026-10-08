# Asistente de inventario

`POST /api/assistant/ask` responde preguntas de inventario en español (parte C). El modelo **solo elige**
herramienta y argumentos; el servidor ejecuta, decide `outcome` y redacta `answer` con los datos. El texto del
modelo nunca llega al usuario: no puede inventar cifras.

## Proveedor: `mock` por defecto, `ollama` local, ningún servicio de pago

Todo el asistente depende de la interfaz `LlmProvider`; `AI_PROVIDER` elige la implementación:

| `AI_PROVIDER` | Qué hace | Costo y red |
|---|---|---|
| vacío o `mock` | Reglas deterministas sobre la pregunta (intención, producto, bodega, plazo) | Cero; sin red ni llave |
| `ollama` | `POST {OLLAMA_BASE_URL}/api/chat`, `OLLAMA_MODEL` (`qwen2.5:3b`), temperatura 0 | Cero; modelo local |
| otro valor | El asistente responde 503 `assistant_unavailable`; el resto de la API sigue igual | — |

`mock` es el valor por defecto: evaluación y CI corren sin llaves, sin red y con resultado estable.

**Compromiso del modelo local.** `qwen2.5:3b` (unos 2 GB) entiende español y herramientas, pero en CPU tarda
segundos por ronda y puede elegir mal o enviar enteros como texto (`invalid_arguments`). `qwen2.5:7b` o
`llama3.1:8b` aciertan más con más memoria y latencia. A cambio, ningún dato sale de la máquina. Plazo total
por pregunta: 50 s; agotado, o con Ollama caído, la API responde 503.

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
resultado, herramientas y estados, nunca pregunta ni respuesta. **Riesgo:** las observaciones de un traslado
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

```sh
docker compose -f software/compose.yaml --profile tools run --rm api-tools php artisan assistant:eval
```

Con Ollama en el anfitrión (`ollama pull qwen2.5:3b`), el mismo informe mide el modelo local:

```sh
docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=ollama api-tools php artisan assistant:eval
```
