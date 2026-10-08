import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  dispense,
  getPatient,
  previewDispensation,
  searchPatients,
} from '@/features/dispensations/api'
import type { DispensationRequest } from '@/lib/api-types'
import { invalidateAfterStockWrite, queryKeys } from '@/lib/query-keys'

// Búsqueda solo tras Enter con un término válido; `null` = sin búsqueda.
export function usePatientSearch(term: string | null) {
  return useQuery({
    queryKey: queryKeys.patientSearch(term ?? ''),
    queryFn: () => searchPatients(term ?? ''),
    enabled: term !== null,
  })
}

export function usePatient(patientId: number) {
  return useQuery({ queryKey: queryKeys.patient(patientId), queryFn: () => getPatient(patientId) })
}

export function usePreviewDispensation() {
  return useMutation({ mutationFn: previewDispensation })
}

// Tras dispensar, inventario, kardex, alertas y ficha quedan obsoletos (ADR-0003).
export function useDispense() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: ({ body, key }: { body: DispensationRequest; key: string }) => dispense(body, key),
    // Sin esperar la recarga: la confirmación no queda pendiente por una consulta de lectura.
    onSuccess: () => void invalidateAfterStockWrite(client),
  })
}
