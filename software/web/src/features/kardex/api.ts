import { apiRequest } from '@/lib/api'
import type { KardexPage, KardexQuery } from '@/lib/api-types'

// Movimientos del kardex, del más reciente al más antiguo, paginados (inventory.view).
export async function listKardex(query: KardexQuery): Promise<KardexPage> {
  return apiRequest<KardexPage>('GET', '/kardex', undefined, { query })
}
