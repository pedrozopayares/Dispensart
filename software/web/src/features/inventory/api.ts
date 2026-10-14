import { apiRequest } from '@/lib/api'
import type {
  Alerts,
  AlertsQuery,
  KardexMovement,
  NewStockEntry,
  ResponseOf,
  StockQuery,
  StockRow,
} from '@/lib/api-types'

// Existencias con cantidad mayor que 0, filtrables por bodega, producto y lote (inventory.view).
export async function listStock(query: StockQuery): Promise<StockRow[]> {
  const { data } = await apiRequest<ResponseOf<'/stock', 'get'>>('GET', '/stock', undefined, {
    query,
  })
  return data
}

// Alertas de vencimiento y de stock mínimo, calculadas por la API al consultar (RN-11).
export async function listAlerts(query: AlertsQuery): Promise<Alerts> {
  const { data } = await apiRequest<ResponseOf<'/alerts', 'get'>>('GET', '/alerts', undefined, {
    query,
  })
  return data
}

// Ingreso de lote (S18): crea el lote si no existe y devuelve el movimiento `entrada` creado.
export async function createStockEntry(body: NewStockEntry): Promise<KardexMovement> {
  const { data } = await apiRequest<ResponseOf<'/stock-entries', 'post'>>('POST', '/stock-entries', body)
  return data
}
