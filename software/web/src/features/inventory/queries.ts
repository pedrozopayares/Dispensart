import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createStockEntry, listAlerts, listStock } from '@/features/inventory/api'
import type { AlertsQuery, StockQuery } from '@/lib/api-types'
import { invalidateAfterStockWrite, queryKeys } from '@/lib/query-keys'

export function useStock(filters: StockQuery) {
  return useQuery({ queryKey: queryKeys.stock(filters), queryFn: () => listStock(filters) })
}

// Misma bodega que la tabla; el producto no filtra las alertas (la API solo admite bodega).
export function useAlerts(filters: AlertsQuery) {
  return useQuery({ queryKey: queryKeys.alerts(filters), queryFn: () => listAlerts(filters) })
}

// Ingreso de lote (S18), sin reintento automático. Termina cuando existencias, kardex, alertas y lotes ya se
// volvieron a pedir: la confirmación y el saldo nuevo aparecen juntos.
export function useCreateStockEntry() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: createStockEntry,
    retry: false,
    onSuccess: () =>
      Promise.all([
        invalidateAfterStockWrite(client),
        client.invalidateQueries({ queryKey: ['catalog', 'lots'] }),
      ]),
  })
}
