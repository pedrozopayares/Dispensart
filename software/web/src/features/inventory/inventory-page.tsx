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
import { useStock } from '@/features/inventory/queries'
import type { StockRow } from '@/lib/api-types'
import { strings } from '@/lib/strings'
import { cn } from '@/lib/utils'

// Pantalla Inventario (/inventory): existencias por bodega, producto y lote. Solo lectura (RN-01).
export function InventoryPage() {
  const [warehouseId, setWarehouseId] = useState<number | undefined>()
  const [productId, setProductId] = useState<number | undefined>()
  const warehouses = useWarehouses()
  const products = useProducts()
  const stock = useStock({ warehouse_id: warehouseId, product_id: productId })

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
        <StockTable rows={stock.data} />
      )}
    </section>
  )
}

function StockTable({ rows }: { rows: readonly StockRow[] }) {
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
        {rows.map((row) => (
          <TableRow
            key={row.id}
            data-expired={row.lot.is_expired || undefined}
            className={cn(row.lot.is_expired && 'bg-destructive/10 hover:bg-destructive/15')}
          >
            <TableCell>{row.warehouse.name}</TableCell>
            <TableCell>
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-mono text-xs text-muted-foreground">{row.product.code}</span>
                <span>{row.product.name}</span>
                {row.product.is_controlled && (
                  <Badge variant="outline">{strings.inventory.controlled}</Badge>
                )}
              </div>
            </TableCell>
            <TableCell>
              <div className="flex items-center gap-2">
                <span className="font-mono">{row.lot.lot_code}</span>
                {row.lot.is_expired && <Badge variant="destructive">{strings.inventory.expired}</Badge>}
              </div>
            </TableCell>
            <TableCell>{row.lot.expires_on}</TableCell>
            <TableCell className="text-right tabular-nums">{row.quantity}</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
