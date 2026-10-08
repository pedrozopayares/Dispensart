import { useSearchParams } from 'react-router'
import { EmptyState } from '@/components/empty-state'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import { PageHeader } from '@/components/page-header'
import { SelectFilter } from '@/components/select-filter'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { lotOptions, productOptions, warehouseOptions } from '@/features/catalog/options'
import { useLots, useProducts, useWarehouses } from '@/features/catalog/queries'
import {
  readKardexFilters,
  toKardexQuery,
  writeKardexFilters,
  type KardexFilters,
} from '@/features/kardex/kardex-filters'
import { useKardex } from '@/features/kardex/queries'
import type { KardexMovement, KardexPage as KardexResult } from '@/lib/api-types'
import { formatDateTimeBogota, formatSignedQuantity } from '@/lib/format'
import { format, strings } from '@/lib/strings'

// Pantalla Kardex (/kardex): historial inmutable de movimientos (RN-06). Filtros y página viven en la
// URL para volver o compartir la vista; ninguna fila ofrece editar ni borrar.
export function KardexPage() {
  const [params, setParams] = useSearchParams()
  const filters = readKardexFilters(params)
  const warehouses = useWarehouses()
  const products = useProducts()
  const lots = useLots(filters.product_id)
  const kardex = useKardex(toKardexQuery(filters))

  // Todo cambio de filtro vuelve a la página 1; cambiar de producto descarta el lote.
  const update = (next: Partial<KardexFilters>) =>
    setParams(writeKardexFilters({ ...filters, page: 1, ...next }))

  return (
    <section className="flex w-full max-w-6xl flex-col gap-6">
      <PageHeader title={strings.kardex.title} description={strings.kardex.description} />
      <div className="flex flex-wrap gap-4">
        <SelectFilter
          id="kardex-warehouse"
          label={strings.filters.warehouse}
          allLabel={strings.filters.allWarehouses}
          options={warehouseOptions(warehouses.data)}
          value={filters.warehouse_id}
          onChange={(warehouseId) => update({ warehouse_id: warehouseId })}
        />
        <SelectFilter
          id="kardex-product"
          label={strings.filters.product}
          allLabel={strings.filters.allProducts}
          options={productOptions(products.data)}
          value={filters.product_id}
          onChange={(productId) => update({ product_id: productId, lot_id: undefined })}
        />
        <SelectFilter
          id="kardex-lot"
          label={strings.filters.lot}
          allLabel={strings.filters.allLots}
          options={lotOptions(lots.data)}
          value={filters.lot_id}
          onChange={(lotId) => update({ lot_id: lotId })}
          disabled={filters.product_id === undefined}
          disabledLabel={strings.filters.lotNeedsProduct}
        />
      </div>
      {kardex.isPending ? (
        <LoadingState label={strings.kardex.loading} />
      ) : kardex.isError ? (
        <ErrorMessage
          error={kardex.error}
          onRetry={() => void kardex.refetch()}
          retrying={kardex.isFetching}
        />
      ) : kardex.data.data.length === 0 ? (
        <EmptyState message={strings.kardex.empty} />
      ) : (
        <>
          <MovementsTable movements={kardex.data.data} />
          <Pagination
            meta={kardex.data.meta}
            onPage={(page) => setParams(writeKardexFilters({ ...filters, page }))}
          />
        </>
      )}
    </section>
  )
}

function movementTypeLabel(type: string): string {
  const { types } = strings.kardex
  return Object.hasOwn(types, type) ? types[type as keyof typeof types] : strings.kardex.unknownType
}

function MovementsTable({ movements }: { movements: readonly KardexMovement[] }) {
  const { columns } = strings.kardex
  return (
    <Table>
      <TableCaption className="sr-only">{strings.kardex.caption}</TableCaption>
      <TableHeader>
        <TableRow>
          <TableHead>{columns.date}</TableHead>
          <TableHead>{columns.type}</TableHead>
          <TableHead>{columns.warehouse}</TableHead>
          <TableHead>{columns.product}</TableHead>
          <TableHead>{columns.lot}</TableHead>
          <TableHead className="text-right">{columns.quantity}</TableHead>
          <TableHead className="text-right">{columns.balance}</TableHead>
          <TableHead>{columns.user}</TableHead>
          <TableHead>{columns.reason}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {movements.map((movement) => (
          <TableRow key={movement.id}>
            <TableCell className="whitespace-nowrap tabular-nums">
              {formatDateTimeBogota(movement.created_at)}
            </TableCell>
            <TableCell>{movementTypeLabel(movement.type)}</TableCell>
            <TableCell>{movement.warehouse.name}</TableCell>
            <TableCell>{movement.product.name}</TableCell>
            <TableCell className="font-mono">{movement.lot.lot_code}</TableCell>
            <TableCell className="text-right tabular-nums">
              {formatSignedQuantity(movement.quantity)}
            </TableCell>
            <TableCell className="text-right tabular-nums">{movement.balance_after}</TableCell>
            <TableCell>{movement.user?.name ?? strings.kardex.system}</TableCell>
            <TableCell className="text-muted-foreground">{movement.reason}</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}

function Pagination({
  meta,
  onPage,
}: {
  meta: KardexResult['meta']
  onPage: (page: number) => void
}) {
  const page = meta.current_page
  return (
    <nav className="flex items-center justify-end gap-3" aria-label={strings.kardex.pagination}>
      <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>
        {strings.common.previous}
      </Button>
      <span className="text-sm text-muted-foreground tabular-nums">
        {format(strings.common.page, { page: String(page), total: String(meta.last_page) })}
      </span>
      <Button
        variant="outline"
        size="sm"
        disabled={page >= meta.last_page}
        onClick={() => onPage(page + 1)}
      >
        {strings.common.next}
      </Button>
    </nav>
  )
}
