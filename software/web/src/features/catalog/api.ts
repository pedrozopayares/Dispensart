import { apiRequest } from '@/lib/api'
import type { Lot, LotQuery, Product, ResponseOf, Warehouse } from '@/lib/api-types'

// Catálogo de lectura que las pantallas usan en sus filtros (catalog.view).

export async function listWarehouses(): Promise<Warehouse[]> {
  const { data } = await apiRequest<ResponseOf<'/warehouses', 'get'>>('GET', '/warehouses')
  return data
}

export async function listProducts(): Promise<Product[]> {
  const { data } = await apiRequest<ResponseOf<'/products', 'get'>>('GET', '/products')
  return data
}

export async function listLots(query: LotQuery): Promise<Lot[]> {
  const { data } = await apiRequest<ResponseOf<'/lots', 'get'>>('GET', '/lots', undefined, { query })
  return data
}
