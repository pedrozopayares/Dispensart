import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  createProduct,
  createWarehouse,
  listLots,
  listProducts,
  listWarehouses,
  updateProduct,
  updateWarehouse,
} from '@/features/catalog/api'
import type { ProductChanges, WarehouseChanges } from '@/lib/api-types'
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

// Escrituras del catálogo, sin reintento automático. Terminan cuando la lista afectada ya se volvió a
// pedir: la confirmación y la fila nueva o editada aparecen juntas (el `staleTime` no lo impide).
function useCatalogWrite<Input, Output>(
  mutationFn: (input: Input) => Promise<Output>,
  queryKey: readonly unknown[],
) {
  const client = useQueryClient()
  return useMutation({
    mutationFn,
    retry: false,
    onSuccess: () => client.invalidateQueries({ queryKey }),
  })
}

export const useCreateWarehouse = () => useCatalogWrite(createWarehouse, queryKeys.warehouses())

export const useUpdateWarehouse = () =>
  useCatalogWrite(
    ({ id, changes }: { id: number; changes: WarehouseChanges }) => updateWarehouse(id, changes),
    queryKeys.warehouses(),
  )

export const useCreateProduct = () => useCatalogWrite(createProduct, queryKeys.products())

export const useUpdateProduct = () =>
  useCatalogWrite(
    ({ id, changes }: { id: number; changes: ProductChanges }) => updateProduct(id, changes),
    queryKeys.products(),
  )
