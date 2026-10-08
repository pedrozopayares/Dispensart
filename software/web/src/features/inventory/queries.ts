import { useQuery } from '@tanstack/react-query'
import { listStock } from '@/features/inventory/api'
import type { StockQuery } from '@/lib/api-types'
import { queryKeys } from '@/lib/query-keys'

export function useStock(filters: StockQuery) {
  return useQuery({ queryKey: queryKeys.stock(filters), queryFn: () => listStock(filters) })
}
