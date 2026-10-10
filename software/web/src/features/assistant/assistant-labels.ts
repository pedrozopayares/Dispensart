import type { AssistantToolCall } from '@/lib/api-types'
import { format, strings } from '@/lib/strings'

const labels = strings.assistant

// Etiqueta de un literal de la API en un mapa del módulo de textos; fuera del mapa, el texto genérico.
// Nunca el literal crudo: puede venir del modelo (nombre de herramienta pedido) o de una versión nueva.
function labelOf(map: Record<string, string>, key: string, fallback: string): string {
  return Object.hasOwn(map, key) ? map[key] : fallback
}

// La etiqueta sale solo de `outcome`, nunca del texto de la respuesta (RN-10, parte C).
export function outcomeLabel(outcome: string): string {
  return labelOf(labels.outcomes, outcome, labels.outcomes.unknown)
}

// Una herramienta fuera del catálogo se muestra genérica: el nombre lo pidió el modelo.
export function toolLabel(tool: string): string {
  return labelOf(labels.tools, tool, labels.unknownTool)
}

export function callStatusLabel(status: string): string {
  return labelOf(labels.callStatus, status, labels.unknownCallStatus)
}

const OLLAMA_PREFIX = 'ollama:'

// Etiqueta de un `id` de modelo (S15): `mock` y `ollama:<name>`; cualquier otro, el texto genérico,
// nunca el `id` recibido.
export function modelLabel(id: unknown): string {
  if (id === 'mock') return labels.model.mock
  if (typeof id === 'string' && id.startsWith(OLLAMA_PREFIX) && id.length > OLLAMA_PREFIX.length) {
    return format(labels.model.ollama, { name: id.slice(OLLAMA_PREFIX.length) })
  }
  return labels.model.unknown
}

// Una línea "Etiqueta: valor" por argumento. El `status` de un traslado usa las etiquetas de la
// pantalla Traslados (RN-07), nunca el literal `EN_TRANSITO`.
export function argumentLines(args: AssistantToolCall['arguments']): string[] {
  return Object.entries(args).map(([key, raw]) => {
    const value =
      key === 'status'
        ? labelOf(strings.transfers.status, String(raw), strings.transfers.unknownStatus)
        : String(raw)
    return format(labels.argument, { label: labelOf(labels.arguments, key, labels.unknownArgument), value })
  })
}
