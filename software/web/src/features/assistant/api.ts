import { apiRequest } from '@/lib/api'
import type { AssistantAnswer, ResponseOf } from '@/lib/api-types'

// Pregunta al asistente (toda sesión). Escritura para el cliente: lleva X-XSRF-TOKEN y hereda el
// reintento único ante `csrf_token_mismatch`. El servidor decide `outcome` y `answer`.
export async function askAssistant(question: string): Promise<AssistantAnswer> {
  const { data } = await apiRequest<ResponseOf<'/assistant/ask', 'post'>>('POST', '/assistant/ask', {
    question,
  })
  return data
}
