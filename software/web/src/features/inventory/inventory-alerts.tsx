import type { UseQueryResult } from '@tanstack/react-query'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { Alerts } from '@/lib/api-types'
import { format, strings } from '@/lib/strings'

const s = strings.inventory.alerts

// Zona de alertas: carga, fallo propio (no oculta las existencias), sin alertas, resumen de lotes
// resaltados y panel "Productos bajo mínimo".
export function InventoryAlerts({ alerts }: { alerts: UseQueryResult<Alerts> }) {
  return (
    <section aria-labelledby="inventory-alerts-title" className="flex flex-col gap-3">
      <h3 id="inventory-alerts-title" className="text-lg font-semibold">
        {s.title}
      </h3>
      {alerts.isPending ? (
        <LoadingState label={s.loading} />
      ) : alerts.isError ? (
        <ErrorMessage
          error={alerts.error}
          message={s.failed}
          onRetry={() => void alerts.refetch()}
          retrying={alerts.isFetching}
        />
      ) : alerts.data.expiring_lots.length === 0 && alerts.data.low_stock.length === 0 ? (
        <p className="text-sm text-muted-foreground">{s.none}</p>
      ) : (
        <>
          {alerts.data.expiring_lots.length > 0 && (
            <p className="text-sm">
              {format(s.expiringSummary, { count: String(alerts.data.expiring_lots.length) })}
            </p>
          )}
          {alerts.data.low_stock.length > 0 && <LowStockPanel rows={alerts.data.low_stock} />}
        </>
      )}
    </section>
  )
}

function LowStockPanel({ rows }: { rows: Alerts['low_stock'] }) {
  const { columns } = s
  return (
    <section aria-labelledby="low-stock-title" className="flex flex-col gap-2">
      <h4 id="low-stock-title" className="font-medium">
        {s.lowStockTitle}
      </h4>
      <Table>
        <TableCaption className="sr-only">{s.lowStockCaption}</TableCaption>
        <TableHeader>
          <TableRow>
            <TableHead>{columns.warehouse}</TableHead>
            <TableHead>{columns.product}</TableHead>
            <TableHead className="text-right">{columns.minimum}</TableHead>
            <TableHead className="text-right">{columns.available}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.map((row) => (
            <TableRow key={`${row.warehouse.id}:${row.product.id}`}>
              <TableCell>{row.warehouse.name}</TableCell>
              <TableCell>
                <span className="font-mono text-xs text-muted-foreground">{row.product.code}</span>{' '}
                {row.product.name}
              </TableCell>
              <TableCell className="text-right tabular-nums">{row.minimum_quantity}</TableCell>
              <TableCell className="text-right tabular-nums">{row.available_quantity}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </section>
  )
}
