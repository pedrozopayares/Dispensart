import { useState } from 'react'
import { Link, useOutletContext, useSearchParams } from 'react-router'
import { EmptyState } from '@/components/empty-state'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import { PageHeader } from '@/components/page-header'
import { Pagination } from '@/components/pagination'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Field, FieldLabel } from '@/components/ui/field'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useTransfers } from '@/features/transfers/queries'
import { TransferForm } from '@/features/transfers/transfer-form'
import {
  isTransferStatus,
  TRANSFER_STATUSES,
  transferStatusLabel,
} from '@/features/transfers/transfer-rules'
import { can } from '@/lib/abilities'
import type { AuthenticatedUser } from '@/lib/api'
import type { TransferStatus, TransferSummary } from '@/lib/api-types'
import { formatDateTimeBogota } from '@/lib/format'
import { format, strings } from '@/lib/strings'

const s = strings.transfers

// Estado y página en la URL; valores inválidos se ignoran (sin filtro, página 1).
function readListParams(params: URLSearchParams): { status?: TransferStatus; page: number } {
  const status = params.get('status')
  const page = Number(params.get('page'))
  return {
    status: isTransferStatus(status) ? status : undefined,
    page: Number.isInteger(page) && page >= 1 ? page : 1,
  }
}

// Pantalla Traslados (/transfers): listado por estado y creación de borradores (RN-07).
export function TransfersPage() {
  const user = useOutletContext<AuthenticatedUser>()
  const [params, setParams] = useSearchParams()
  const { status, page } = readListParams(params)
  const transfers = useTransfers({ status, page })
  const [creating, setCreating] = useState(false)

  const update = (next: { status?: TransferStatus; page: number }) => {
    const query = new URLSearchParams()
    if (next.status !== undefined) query.set('status', next.status)
    if (next.page > 1) query.set('page', String(next.page))
    setParams(query)
  }

  return (
    <section className="flex w-full max-w-5xl flex-col gap-6">
      <PageHeader title={s.title} description={s.description} />
      {can(user, 'transfers.create') &&
        (creating ? (
          <TransferForm onCancel={() => setCreating(false)} />
        ) : (
          <div>
            <Button onClick={() => setCreating(true)}>{s.newTransfer}</Button>
          </div>
        ))}
      <Field className="w-auto max-w-60">
        <FieldLabel htmlFor="transfers-status">{s.filters.status}</FieldLabel>
        <NativeSelect
          id="transfers-status"
          className="w-full"
          value={status ?? ''}
          onChange={(event) => {
            const value = event.target.value
            // Todo cambio de filtro vuelve a la página 1.
            update({ status: isTransferStatus(value) ? value : undefined, page: 1 })
          }}
        >
          <NativeSelectOption value="">{s.filters.allStatuses}</NativeSelectOption>
          {TRANSFER_STATUSES.map((value) => (
            <NativeSelectOption key={value} value={value}>
              {s.status[value]}
            </NativeSelectOption>
          ))}
        </NativeSelect>
      </Field>
      {transfers.isPending ? (
        <LoadingState label={s.loading} />
      ) : transfers.isError ? (
        <ErrorMessage
          error={transfers.error}
          onRetry={() => void transfers.refetch()}
          retrying={transfers.isFetching}
        />
      ) : transfers.data.data.length === 0 ? (
        <EmptyState message={s.empty} />
      ) : (
        <>
          <TransfersTable transfers={transfers.data.data} />
          <Pagination
            page={transfers.data.meta.current_page}
            lastPage={transfers.data.meta.last_page}
            label={s.pagination}
            onPage={(next) => update({ status, page: next })}
          />
        </>
      )}
    </section>
  )
}

function TransfersTable({ transfers }: { transfers: readonly TransferSummary[] }) {
  const { columns } = s
  return (
    <Table>
      <TableCaption className="sr-only">{s.caption}</TableCaption>
      <TableHeader>
        <TableRow>
          <TableHead>{columns.number}</TableHead>
          <TableHead>{columns.origin}</TableHead>
          <TableHead>{columns.destination}</TableHead>
          <TableHead>{columns.status}</TableHead>
          <TableHead>{columns.date}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {transfers.map((transfer) => (
          <TableRow key={transfer.id}>
            <TableCell>
              <Link className="font-medium underline-offset-4 hover:underline" to={`/transfers/${transfer.id}`}>
                {format(s.number, { id: String(transfer.id) })}
              </Link>
            </TableCell>
            <TableCell>{transfer.origin_warehouse.name}</TableCell>
            <TableCell>{transfer.destination_warehouse.name}</TableCell>
            <TableCell>
              <Badge variant="outline">{transferStatusLabel(transfer.status)}</Badge>
            </TableCell>
            <TableCell className="whitespace-nowrap tabular-nums">
              {formatDateTimeBogota(transfer.created_at)}
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
