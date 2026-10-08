import { useQuery } from '@tanstack/react-query'
import { listAlerts, listStock } from '@/features/inventory/api'
import type { AlertsQuery, StockQuery } from '@/lib/api-types'
import { queryKeys } from '@/lib/query-keys'

export function useStock(filters: StockQuery) {
  return useQuery({ queryKey: queryKeys.stock(filters), queryFn: () => listStock(filters) })
}

// Misma bodega que la tabla; el producto no filtra las alertas (la API solo admite bodega).
export function useAlerts(filters: AlertsQuery) {
  return useQuery({ queryKey: queryKeys.alerts(filters), queryFn: () => listAlerts(filters) })
}
