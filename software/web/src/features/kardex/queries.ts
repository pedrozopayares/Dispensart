import { useQuery } from '@tanstack/react-query'
import { listKardex } from '@/features/kardex/api'
import type { KardexQuery } from '@/lib/api-types'
import { queryKeys } from '@/lib/query-keys'

export function useKardex(filters: KardexQuery) {
  return useQuery({ queryKey: queryKeys.kardex(filters), queryFn: () => listKardex(filters) })
}
