import type { FilterOption } from '@/components/select-filter'
import type { Lot, Product, Warehouse } from '@/lib/api-types'

// Opciones de filtro a partir del catálogo; etiquetas con datos de la API, no textos de interfaz.
export const warehouseOptions = (warehouses: readonly Warehouse[] = []): FilterOption[] =>
  warehouses.map((warehouse) => ({ value: warehouse.id, label: warehouse.name }))

export const productOptions = (products: readonly Product[] = []): FilterOption[] =>
  products.map((product) => ({ value: product.id, label: `${product.code} · ${product.name}` }))

export const lotOptions = (lots: readonly Lot[] = []): FilterOption[] =>
  lots.map((lot) => ({ value: lot.id, label: lot.lot_code }))
