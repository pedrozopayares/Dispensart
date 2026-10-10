import { apiRequest } from '@/lib/api'
import type {
  Lot,
  LotQuery,
  NewProduct,
  NewWarehouse,
  Product,
  ProductChanges,
  ResponseOf,
  Warehouse,
  WarehouseChanges,
} from '@/lib/api-types'

// Catálogo de lectura que las pantallas usan en sus filtros (catalog.view) y sus escrituras
// (catalog.manage, pantalla Catálogo del admin).

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

export async function createWarehouse(body: NewWarehouse): Promise<Warehouse> {
  const { data } = await apiRequest<ResponseOf<'/warehouses', 'post'>>('POST', '/warehouses', body)
  return data
}

export async function updateWarehouse(warehouseId: number, body: WarehouseChanges): Promise<Warehouse> {
  const { data } = await apiRequest<ResponseOf<'/warehouses/{warehouse}', 'patch'>>(
    'PATCH',
    `/warehouses/${warehouseId}`,
    body,
  )
  return data
}

export async function createProduct(body: NewProduct): Promise<Product> {
  const { data } = await apiRequest<ResponseOf<'/products', 'post'>>('POST', '/products', body)
  return data
}

export async function updateProduct(productId: number, body: ProductChanges): Promise<Product> {
  const { data } = await apiRequest<ResponseOf<'/products/{product}', 'patch'>>(
    'PATCH',
    `/products/${productId}`,
    body,
  )
  return data
}
