import { useQuery } from '@tanstack/react-query'
import { listLots, listProducts, listWarehouses } from '@/features/catalog/api'
import { queryKeys } from '@/lib/query-keys'

// El catálogo cambia poco: se reutiliza unos minutos entre pantallas.
const CATALOG_STALE_MS = 5 * 60 * 1000

export function useWarehouses() {
  return useQuery({
    queryKey: queryKeys.warehouses(),
    queryFn: listWarehouses,
    staleTime: CATALOG_STALE_MS,
  })
}

export function useProducts() {
  return useQuery({ queryKey: queryKeys.products(), queryFn: listProducts, staleTime: CATALOG_STALE_MS })
}

// Lotes de un producto; sin producto no se consulta.
export function useLots(productId: number | undefined) {
  return useQuery({
    queryKey: queryKeys.lots({ product_id: productId }),
    queryFn: () => listLots({ product_id: productId }),
    enabled: productId !== undefined,
    staleTime: CATALOG_STALE_MS,
  })
}
