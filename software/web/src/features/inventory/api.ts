import { apiRequest } from '@/lib/api'
import type { ResponseOf, StockQuery, StockRow } from '@/lib/api-types'

// Existencias con cantidad mayor que 0, filtrables por bodega, producto y lote (inventory.view).
export async function listStock(query: StockQuery): Promise<StockRow[]> {
  const { data } = await apiRequest<ResponseOf<'/stock', 'get'>>('GET', '/stock', undefined, {
    query,
  })
  return data
}
