import type { QueryClient } from '@tanstack/react-query'
import type { AlertsQuery, KardexQuery, LotQuery, StockQuery, TransferQuery } from '@/lib/api-types'

// Claves de consulta de TanStack Query (ADR-0003, design D7). Cada raíz agrupa un recurso para
// invalidarlo completo tras una escritura.
export const queryKeys = {
  stock: (filters: StockQuery) => ['stock', filters] as const,
  kardex: (filters: KardexQuery) => ['kardex', filters] as const,
  alerts: (filters: AlertsQuery) => ['alerts', filters] as const,
  patient: (patientId: number) => ['patient', patientId] as const,
  // El término vive solo en memoria (nunca en la URL ni en almacenamiento del navegador).
  patientSearch: (term: string) => ['patient-search', term] as const,
  warehouses: () => ['catalog', 'warehouses'] as const,
  products: () => ['catalog', 'products'] as const,
  lots: (filters: LotQuery) => ['catalog', 'lots', filters] as const,
  // Raíz `transfers`: listado y detalle se invalidan juntos tras cada acción de traslado.
  transfers: (filters: TransferQuery) => ['transfers', 'list', filters] as const,
  transfer: (transferId: number) => ['transfers', 'detail', transferId] as const,
}

// Raíces que cambian con toda escritura de stock (dispensación, ajuste, despacho, recepción).
export const stockWriteRoots = ['stock', 'kardex', 'alerts', 'patient'] as const

export async function invalidateAfterStockWrite(client: QueryClient): Promise<void> {
  await Promise.all(stockWriteRoots.map((root) => client.invalidateQueries({ queryKey: [root] })))
}
