import { useState } from 'react'
import { EmptyState } from '@/components/empty-state'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import { PageHeader } from '@/components/page-header'
import { SelectFilter } from '@/components/select-filter'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { productOptions, warehouseOptions } from '@/features/catalog/options'
import { useProducts, useWarehouses } from '@/features/catalog/queries'
import { indexAlerts, expiresInLabel, type AlertIndex } from '@/features/inventory/alert-index'
import { InventoryAlerts } from '@/features/inventory/inventory-alerts'
import { useAlerts, useStock } from '@/features/inventory/queries'
import type { StockRow } from '@/lib/api-types'
import { strings } from '@/lib/strings'
import { cn } from '@/lib/utils'

// Pantalla Inventario (/inventory): existencias por bodega, producto y lote, con las alertas de
// vencimiento y stock mínimo de la misma bodega (RN-11). Solo lectura (RN-01).
export function InventoryPage() {
  const [warehouseId, setWarehouseId] = useState<number | undefined>()
  const [productId, setProductId] = useState<number | undefined>()
  const warehouses = useWarehouses()
  const products = useProducts()
  const stock = useStock({ warehouse_id: warehouseId, product_id: productId })
  const alerts = useAlerts({ warehouse_id: warehouseId })

  return (
    <section className="flex w-full max-w-6xl flex-col gap-6">
      <PageHeader title={strings.inventory.title} description={strings.inventory.description} />
      <div className="flex flex-wrap gap-4">
        <SelectFilter
          id="inventory-warehouse"
          label={strings.filters.warehouse}
          allLabel={strings.filters.allWarehouses}
          options={warehouseOptions(warehouses.data)}
          value={warehouseId}
          onChange={setWarehouseId}
        />
        <SelectFilter
          id="inventory-product"
          label={strings.filters.product}
          allLabel={strings.filters.allProducts}
          options={productOptions(products.data)}
          value={productId}
          onChange={setProductId}
        />
      </div>
      <InventoryAlerts alerts={alerts} />
      {stock.isPending ? (
        <LoadingState label={strings.inventory.loading} />
      ) : stock.isError ? (
        <ErrorMessage
          error={stock.error}
          onRetry={() => void stock.refetch()}
          retrying={stock.isFetching}
        />
      ) : stock.data.length === 0 ? (
        <EmptyState message={strings.inventory.empty} />
      ) : (
        <StockTable rows={stock.data} alertsFor={indexAlerts(alerts.data)} />
      )}
    </section>
  )
}

function StockTable({ rows, alertsFor }: { rows: readonly StockRow[]; alertsFor: AlertIndex }) {
  const { columns } = strings.inventory
  return (
    <Table>
      <TableCaption className="sr-only">{strings.inventory.caption}</TableCaption>
      <TableHeader>
        <TableRow>
          <TableHead>{columns.warehouse}</TableHead>
          <TableHead>{columns.product}</TableHead>
          <TableHead>{columns.lot}</TableHead>
          <TableHead>{columns.expiresOn}</TableHead>
          <TableHead className="text-right">{columns.quantity}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {rows.map((row) => {
          const { expiring, lowStock } = alertsFor(row)
          const expired = row.lot.is_expired || expiring?.lot.is_expired === true
          // "Vence en" solo para un lote vigente de `expiring_lots`; el vencido lleva "Vencido".
          const expiringSoon = expiring !== undefined && !expired
          return (
            <TableRow
              key={row.id}
              data-expired={expired || undefined}
              data-expiring={expiringSoon || undefined}
              data-low-stock={lowStock || undefined}
              className={cn(
                expired && 'bg-destructive/10 hover:bg-destructive/15',
                expiringSoon && 'bg-amber-100 hover:bg-amber-200/70 dark:bg-amber-950/40',
              )}
            >
              <TableCell>{row.warehouse.name}</TableCell>
              <TableCell>
                <div className="flex flex-wrap items-center gap-2">
                  <span className="font-mono text-xs text-muted-foreground">{row.product.code}</span>
                  <span>{row.product.name}</span>
                  {row.product.is_controlled && (
                    <Badge variant="outline">{strings.inventory.controlled}</Badge>
                  )}
                  {lowStock && (
                    <Badge variant="secondary">{strings.inventory.alerts.lowStockBadge}</Badge>
                  )}
                </div>
              </TableCell>
              <TableCell>
                <div className="flex items-center gap-2">
                  <span className="font-mono">{row.lot.lot_code}</span>
                  {expired && <Badge variant="destructive">{strings.inventory.expired}</Badge>}
                  {expiringSoon && (
                    <Badge className="bg-warning text-warning-foreground">
                      {expiresInLabel(expiring.days_to_expiry)}
                    </Badge>
                  )}
                </div>
              </TableCell>
              <TableCell>{row.lot.expires_on}</TableCell>
              <TableCell className="text-right tabular-nums">{row.quantity}</TableCell>
            </TableRow>
          )
        })}
      </TableBody>
    </Table>
  )
}
