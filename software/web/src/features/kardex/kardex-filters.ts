import type { KardexQuery } from '@/lib/api-types'

// Filtros del kardex leídos de la URL (kardex-screen "Paginación y filtros en la URL"). Un valor que no
// es un entero positivo se ignora: nunca llega a la consulta ni produce error.
export type KardexFilters = {
  warehouse_id?: number
  product_id?: number
  lot_id?: number
  page: number
}

const POSITIVE_INTEGER = /^[1-9]\d*$/

function positiveInt(value: string | null): number | undefined {
  return value !== null && POSITIVE_INTEGER.test(value) ? Number(value) : undefined
}

export function readKardexFilters(params: URLSearchParams): KardexFilters {
  const productId = positiveInt(params.get('product_id'))
  return {
    warehouse_id: positiveInt(params.get('warehouse_id')),
    product_id: productId,
    // El lote depende del producto: sin producto el filtro de lote está deshabilitado y no aplica.
    lot_id: productId === undefined ? undefined : positiveInt(params.get('lot_id')),
    page: positiveInt(params.get('page')) ?? 1,
  }
}

export function writeKardexFilters(filters: KardexFilters): URLSearchParams {
  const params = new URLSearchParams()
  if (filters.warehouse_id !== undefined) params.set('warehouse_id', String(filters.warehouse_id))
  if (filters.product_id !== undefined) params.set('product_id', String(filters.product_id))
  if (filters.lot_id !== undefined) params.set('lot_id', String(filters.lot_id))
  if (filters.page > 1) params.set('page', String(filters.page))
  return params
}

export function toKardexQuery(filters: KardexFilters): KardexQuery {
  return {
    warehouse_id: filters.warehouse_id,
    product_id: filters.product_id,
    lot_id: filters.lot_id,
    page: filters.page,
  }
}
