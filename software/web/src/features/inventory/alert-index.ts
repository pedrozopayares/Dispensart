import type { Alerts, ExpiringLot, StockRow } from '@/lib/api-types'
import { format, strings } from '@/lib/strings'

// Marcas de una fila de existencias según las alertas de la API (RN-11). Nada sale del reloj del
// navegador: una fila sin alerta no se resalta aunque su vencimiento parezca cercano.
export type RowAlerts = { expiring?: ExpiringLot; lowStock: boolean }

export type AlertIndex = (row: StockRow) => RowAlerts

const noAlerts: AlertIndex = () => ({ lowStock: false })

// Índice por bodega + lote (vencimiento) y bodega + producto (mínimo).
export function indexAlerts(alerts: Alerts | undefined): AlertIndex {
  if (alerts === undefined) return noAlerts
  const expiring = new Map(
    alerts.expiring_lots.map((alert) => [`${alert.warehouse.id}:${alert.lot.id}`, alert]),
  )
  const lowStock = new Set(
    alerts.low_stock.map((alert) => `${alert.warehouse.id}:${alert.product.id}`),
  )
  return (row) => ({
    expiring: expiring.get(`${row.warehouse.id}:${row.lot.id}`),
    lowStock: lowStock.has(`${row.warehouse.id}:${row.product.id}`),
  })
}

// "Vence en {n} días" para un lote aún vigente de la lista de alertas.
export function expiresInLabel(days: number): string {
  const { alerts } = strings.inventory
  return days === 1 ? alerts.expiresInOne : format(alerts.expiresIn, { days: String(days) })
}
