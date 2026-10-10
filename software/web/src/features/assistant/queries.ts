import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { askAssistant, listAssistantModels } from '@/features/assistant/api'
import { fieldErrors } from '@/lib/api-errors'
import { queryKeys } from '@/lib/query-keys'

// Igual al TTL de la caché del catálogo en el servidor (design D3 de S15).
const MODELS_STALE_MS = 30_000

// Pregunta como mutación sin reintento automático (lo decide el usuario). Solo lectura en el
// servidor: no invalida datos de stock. `gcTime: 0`: la pregunta no sobrevive en la caché de
// mutaciones al salir de la pantalla (RN-10, historial solo en memoria de la pantalla).
// Un 422 con `errors.model` significa que la lista quedó vieja: se pide de nuevo (design D8).
export function useAskAssistant() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: askAssistant,
    retry: false,
    gcTime: 0,
    onError: (error) => {
      if (fieldErrors(error).model !== undefined) {
        void client.invalidateQueries({ queryKey: queryKeys.assistantModels() })
      }
    },
  })
}

// Lista de modelos sin reintento: si falla, la pantalla cae a `mock` de inmediato (design D8).
export function useAssistantModels() {
  return useQuery({
    queryKey: queryKeys.assistantModels(),
    queryFn: listAssistantModels,
    retry: false,
    staleTime: MODELS_STALE_MS,
  })
}
