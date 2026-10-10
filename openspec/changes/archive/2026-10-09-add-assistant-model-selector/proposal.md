# Proposal — add-assistant-model-selector (S15)

## Why

Hoy el modelo del asistente lo fija solo `AI_PROVIDER` al arrancar: probar el modelo local de Ollama exige cambiar
el entorno y reiniciar. El usuario pide (2026-10-09) elegirlo en la pantalla Asistente entre `mock` y los modelos de
Ollama realmente disponibles, con la elección conservada al recargar. La parte C exige proveedor configurable por
entorno, modo simulado y cambio de modelo sin tocar el resto: se mantienen los tres.

## What Changes

- API: `GET /api/assistant/models` lista los modelos elegibles: `mock` siempre; cada modelo de Ollama solo si Ollama
  responde en plazo corto, el modelo está descargado (`/api/tags`) y declara la capacidad `tools`. Ollama caído →
  solo `mock`, HTTP 200, sin error.
- API: `POST /api/assistant/ask` acepta `model` opcional, validado contra esa misma lista (422 en español si no está);
  sin `model` decide `AI_PROVIDER` como hoy. La respuesta gana `data.model` (modelo que atendió). La URL de Ollama
  sigue saliendo solo del entorno.
- Log del asistente: agrega el modelo, nunca la pregunta.
- SPA: selector "Modelo" en la pantalla Asistente con solo modelos disponibles; `mock` por defecto en la primera
  carga; elección en `localStorage` con regreso seguro a `mock` si ya no está; cada respuesta del historial muestra
  qué modelo respondió.
- Sin migración, sin base de datos, sin cambio de herramientas, catálogo, filtro de pacientes ni defensa contra
  inyección. `assistant:eval` y CI siguen con `AI_PROVIDER=mock`.

## Capabilities

### New Capabilities
- Ninguna.

### Modified Capabilities
- `inventory-assistant`: ADDED «Lista de modelos disponibles», «Disponibilidad de modelos de Ollama», «Modelo
  elegido por pregunta»; MODIFIED «Proveedor configurable por entorno», «Registro de consultas sin contenido».
- `assistant-screen`: ADDED «Selector de modelo», «Elección de modelo conservada al recargar», «Modelo que
  respondió»; MODIFIED «Envío de una pregunta», «Historial de la pantalla solo en memoria».

## Impact

- Código: `software/api` (ruta, FormRequest, servicio de disponibilidad, enlace del proveedor por petición,
  recurso, log, `openapi.json`) y `software/web` (selector, almacenamiento, tipos regenerados, textos).
- Contrato: endpoint nuevo + campo opcional de entrada + campo nuevo de salida → api antes que web.
- Parte C; RN-10 (sin datos de pacientes al modelo ni al almacenamiento del navegador; el log sin contenido).
  Sin escritura de stock: RN-03 y RN-09 no aplican.
- Tier A: superficie del asistente (qué proveedor recibe la pregunta lo decide ahora una entrada del cliente).

## Supuestos

1. "Activos" = descargados y servidos por un Ollama en ejecución (`/api/tags`), no solo los cargados en memoria
   (`/api/ps`): un modelo descargado se carga al primer uso.
2. Solo se ofrecen modelos con capacidad `tools` (`/api/show`): sin ella el asistente no puede consultar.
3. Plazo de la consulta de disponibilidad: 2 s en total; vencido → solo `mock`.
4. La lista se ofrece a toda sesión, igual que `ask` (`admin` no ve la pantalla en la SPA desde S13).
5. La SPA arranca en `mock` aunque `AI_PROVIDER` sea `ollama`; `AI_PROVIDER` solo decide las preguntas sin `model`,
   `assistant:eval` y CI.
6. Identificadores: `mock` y `ollama:<nombre en /api/tags>`; la forma exacta la confirma `design.md`.
7. Modelo elegido que deja de estar disponible entre la lista y la pregunta → 422; Ollama que falla durante la
   respuesta → 503 como hoy.
8. Pregunta respondida por el filtro previo (paciente) informa igualmente el modelo elegido en `data.model`.
