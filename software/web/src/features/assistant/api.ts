import { apiRequest } from '@/lib/api'
import type { AskAssistantRequest, AssistantAnswer, AssistantModel, ResponseOf } from '@/lib/api-types'

// Pregunta al asistente (toda sesión). Escritura para el cliente: lleva X-XSRF-TOKEN y hereda el
// reintento único ante `csrf_token_mismatch`. El servidor decide `outcome`, `answer` y valida `model`.
export async function askAssistant(body: AskAssistantRequest): Promise<AssistantAnswer> {
  const { data } = await apiRequest<ResponseOf<'/assistant/ask', 'post'>>('POST', '/assistant/ask', body)
  return data
}

// Modelos elegibles (S15): `mock` siempre y primero; los de Ollama solo si el servidor los ve disponibles.
export async function listAssistantModels(): Promise<AssistantModel[]> {
  const { data } = await apiRequest<ResponseOf<'/assistant/models', 'get'>>('GET', '/assistant/models')
  return data
}
