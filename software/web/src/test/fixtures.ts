import type { KardexMovement, Lot, Product, StockRow, Warehouse } from '@/lib/api-types'
import { json } from '@/test/http'

// Datos sintéticos con la forma del contrato (tipos derivados del OpenAPI): nunca datos reales.

export const warehouses: Warehouse[] = [
  { id: 1, code: 'FC', name: 'Farmacia Central' },
  { id: 2, code: 'FU', name: 'Farmacia Urgencias' },
]

export const products: Product[] = [
  { id: 10, code: 'ACE500', name: 'Acetaminofén 500 mg', presentation: 'Tableta', is_controlled: false },
  { id: 11, code: 'MOR10', name: 'Morfina 10 mg/ml', presentation: 'Ampolla', is_controlled: true },
]

export const lots: Lot[] = [
  { id: 100, product_id: 10, lot_code: 'ACE-A1', expires_on: '2027-06-30', is_expired: false },
  { id: 101, product_id: 10, lot_code: 'ACE-B2', expires_on: '2026-01-31', is_expired: true },
  { id: 110, product_id: 11, lot_code: 'MOR-C3', expires_on: '2027-03-31', is_expired: false },
]

const productSummary = (product: Product) => ({
  id: product.id,
  code: product.code,
  name: product.name,
  is_controlled: product.is_controlled,
})

const lotSummary = (lot: Lot) => ({
  id: lot.id,
  lot_code: lot.lot_code,
  expires_on: lot.expires_on,
  is_expired: lot.is_expired,
})

export function stockRow(id: number, warehouse: Warehouse, lot: Lot, quantity: number): StockRow {
  const product = products.find((candidate) => candidate.id === lot.product_id)!
  return { id, quantity, warehouse, product: productSummary(product), lot: lotSummary(lot) }
}

export function movement(
  id: number,
  overrides: Partial<Pick<KardexMovement, 'type' | 'quantity' | 'balance_after' | 'reason' | 'created_at' | 'user'>> & {
    warehouse?: Warehouse
    lot?: Lot
  } = {},
): KardexMovement {
  const { warehouse = warehouses[0], lot = lots[0], ...rest } = overrides
  const product = products.find((candidate) => candidate.id === lot.product_id)!
  return {
    id,
    type: 'entrada',
    quantity: 5,
    balance_after: 5,
    reason: null,
    created_at: '2026-10-08T15:00:00+00:00',
    user: { id: 1, name: 'Regente Demo' },
    warehouse,
    product: productSummary(product),
    lot: lotSummary(lot),
    ...rest,
  }
}

// Página del kardex con la forma de Laravel (`data`, `links`, `meta`).
export function kardexPage(data: KardexMovement[], page = 1, lastPage = 1) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: page,
      from: data.length > 0 ? 1 : null,
      last_page: lastPage,
      links: [],
      path: '/api/kardex',
      per_page: 25,
      to: data.length > 0 ? data.length : null,
      total: data.length,
    },
  }
}

// Manejadores del catálogo que usan los filtros de Inventario y Kardex.
export function catalogRoutes() {
  return {
    'GET /api/warehouses': () => json(200, { data: warehouses }),
    'GET /api/products': () => json(200, { data: products }),
    'GET /api/lots': ({ query }: { query: Record<string, string> }) =>
      json(200, { data: lots.filter((lot) => String(lot.product_id) === query.product_id) }),
  }
}
