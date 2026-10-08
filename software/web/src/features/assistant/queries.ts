import { useMutation } from '@tanstack/react-query'
import { askAssistant } from '@/features/assistant/api'

// Pregunta como mutación sin reintento automático (lo decide el usuario). Solo lectura en el
// servidor: no invalida nada. `gcTime: 0`: la pregunta no sobrevive en la caché de mutaciones al
// salir de la pantalla (RN-10, historial solo en memoria de la pantalla).
export function useAskAssistant() {
  return useMutation({ mutationFn: askAssistant, retry: false, gcTime: 0 })
}
