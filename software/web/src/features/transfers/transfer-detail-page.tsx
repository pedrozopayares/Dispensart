import { useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useOutletContext, useParams } from 'react-router'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import { SubmitButton } from '@/components/submit-button'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import type { TransferAction, TransferActionInput } from '@/features/transfers/api'
import { useTransfer, useTransferAction } from '@/features/transfers/queries'
import { ReceiveForm } from '@/features/transfers/receive-form'
import { transferActions, transferStatusLabel } from '@/features/transfers/transfer-rules'
import type { AuthenticatedUser } from '@/lib/api'
import { fieldErrors, hasCode } from '@/lib/api-errors'
import type { Transfer } from '@/lib/api-types'
import { formatDateTimeBogota } from '@/lib/format'
import { queryKeys } from '@/lib/query-keys'
import { format, strings } from '@/lib/strings'
import { useSubmitGuard } from '@/lib/use-submit-guard'

const s = strings.transfers
const REASON_MAX = 500
// Ids de la API: enteros positivos de PostgreSQL (`bigint`); otro valor no se consulta.
const TRANSFER_ID = /^[1-9]\d{0,17}$/

// Detalle de un traslado (/transfers/:id): estado, actores, líneas, discrepancias y acciones según
// estado y rol (RN-07, RN-08).
export function TransferDetailPage() {
  const user = useOutletContext<AuthenticatedUser>()
  const { id = '' } = useParams()
  const transferId = TRANSFER_ID.test(id) ? Number(id) : null
  const transfer = useTransfer(transferId)

  let content
  if (transferId === null || hasCode(transfer.error, 'not_found')) content = <TransferNotFound />
  else if (transfer.isPending) content = <LoadingState label={s.detail.loading} />
  else if (transfer.isError)
    content = (
      <ErrorMessage
        error={transfer.error}
        onRetry={() => void transfer.refetch()}
        retrying={transfer.isFetching}
      />
    )
  else content = <TransferDetail transfer={transfer.data} user={user} />

  return <section className="flex w-full max-w-5xl flex-col gap-6">{content}</section>
}

function TransferNotFound() {
  return (
    <Empty className="max-w-xl border">
      <EmptyHeader>
        <EmptyTitle>{s.detail.notFound}</EmptyTitle>
      </EmptyHeader>
      <EmptyContent>
        <Button asChild variant="outline">
          <Link to="/transfers">{s.detail.back}</Link>
        </Button>
      </EmptyContent>
    </Empty>
  )
}

type Dialog = 'dispatch' | 'void' | null

function TransferDetail({ transfer, user }: { transfer: Transfer; user: AuthenticatedUser }) {
  const client = useQueryClient()
  const mutation = useTransferAction(transfer.id)
  const guard = useSubmitGuard()
  const { actions, requesterNotice } = transferActions(transfer, user)
  const [dialog, setDialog] = useState<Dialog>(null)
  const [receiving, setReceiving] = useState(false)
  const [pageError, setPageError] = useState<unknown>(null)
  const [dialogError, setDialogError] = useState<unknown>(null)
  const [receiveError, setReceiveError] = useState<unknown>(null)
  const [reason, setReason] = useState('')
  const [reasonError, setReasonError] = useState<string | undefined>(undefined)

  const pendingAction = mutation.isPending ? mutation.variables.action : null
  const closeDialog = () => {
    setDialog(null)
    setDialogError(null)
  }

  // Ejecuta una acción con el candado de doble envío (design D4). Un cambio de estado concurrente
  // cierra lo abierto, avisa y recarga el detalle con el estado real.
  const run = (input: TransferActionInput, onError: (error: unknown) => void, onSuccess?: () => void) =>
    guard((release) => {
      setPageError(null)
      mutation.mutate(input, {
        onSuccess: () => onSuccess?.(),
        onError: (error) => {
          if (hasCode(error, 'invalid_transfer_transition')) {
            closeDialog()
            setReceiving(false)
            setPageError(error)
            void client.invalidateQueries({ queryKey: queryKeys.transfer(transfer.id) })
            return
          }
          onError(error)
        },
        onSettled: release,
      })
    })

  const confirmVoid = () => {
    const trimmed = reason.trim()
    if (trimmed === '') {
      setReasonError(s.void.reasonRequired)
      return
    }
    setReasonError(undefined)
    setDialogError(null)
    run(
      { action: 'void', body: { reason: trimmed } },
      (error) => {
        setReasonError(fieldErrors(error).reason)
        setDialogError(error)
      },
      closeDialog,
    )
  }

  const button = (action: TransferAction) => {
    const label = s.actions[action]
    const busy = mutation.isPending
    switch (action) {
      case 'request':
      case 'approve':
        return (
          <SubmitButton
            key={action}
            type="button"
            pending={pendingAction === action}
            pendingLabel={s.actions.working}
            disabled={busy}
            onClick={() => run({ action }, setPageError)}
          >
            {label}
          </SubmitButton>
        )
      case 'dispatch':
      case 'void':
        return (
          <Button
            key={action}
            variant={action === 'void' ? 'outline' : 'default'}
            disabled={busy}
            onClick={() => {
              setDialogError(null)
              setReason('')
              setReasonError(undefined)
              setDialog(action)
            }}
          >
            {label}
          </Button>
        )
      case 'receive':
        return receiving ? null : (
          <Button key={action} disabled={busy} onClick={() => setReceiving(true)}>
            {label}
          </Button>
        )
    }
  }

  return (
    <>
      <div>
        <Button asChild variant="link" className="px-0">
          <Link to="/transfers">{s.detail.back}</Link>
        </Button>
      </div>
      <header className="flex flex-wrap items-center gap-3">
        <h2 className="text-2xl font-semibold">{format(s.number, { id: String(transfer.id) })}</h2>
        <Badge variant="outline">{transferStatusLabel(transfer.status)}</Badge>
      </header>

      {pageError !== null && <ErrorMessage error={pageError} />}
      {requesterNotice && <p className="text-sm text-muted-foreground">{s.detail.requesterNotice}</p>}
      {actions.length > 0 && (
        <div role="group" aria-label={s.actions.label} className="flex flex-wrap gap-2">
          {actions.map(button)}
        </div>
      )}
      {receiving && (
        <ReceiveForm
          transfer={transfer}
          pending={pendingAction === 'receive'}
          error={receiveError}
          onCancel={() => {
            setReceiving(false)
            setReceiveError(null)
          }}
          onSubmit={(body) => {
            setReceiveError(null)
            run({ action: 'receive', body }, setReceiveError, () => setReceiving(false))
          }}
        />
      )}

      <TransferSummaryCard transfer={transfer} />
      <LinesTable transfer={transfer} />
      {transfer.status === 'RECIBIDO_PARCIAL' && <DiscrepanciesTable transfer={transfer} />}

      <ConfirmDialog
        open={dialog === 'dispatch'}
        onOpenChange={(open) => !open && closeDialog()}
        title={s.dispatch.title}
        description={format(s.dispatch.description, { origin: transfer.origin_warehouse.name })}
        confirmLabel={s.dispatch.confirm}
        pendingLabel={s.actions.working}
        pending={pendingAction === 'dispatch'}
        onConfirm={() => {
          setDialogError(null)
          run({ action: 'dispatch' }, setDialogError, closeDialog)
        }}
      >
        {dialogError !== null && (
          <ErrorMessage
            error={dialogError}
            describe={{ overrides: { insufficient_stock: s.dispatch.insufficientStock } }}
          />
        )}
      </ConfirmDialog>

      <ConfirmDialog
        open={dialog === 'void'}
        onOpenChange={(open) => !open && closeDialog()}
        title={s.void.title}
        description={s.void.description}
        confirmLabel={s.void.confirm}
        pendingLabel={s.actions.working}
        pending={pendingAction === 'void'}
        onConfirm={confirmVoid}
      >
        <Field data-invalid={reasonError !== undefined || undefined}>
          <FieldLabel htmlFor="transfer-void-reason">{s.void.reason}</FieldLabel>
          <Textarea
            id="transfer-void-reason"
            maxLength={REASON_MAX}
            aria-invalid={reasonError !== undefined || undefined}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
          />
          {reasonError !== undefined && <FieldError>{reasonError}</FieldError>}
        </Field>
        {dialogError !== null && reasonError === undefined && <ErrorMessage error={dialogError} />}
      </ConfirmDialog>
    </>
  )
}

function actorText(actor: { name: string } | null, at: string | null): string | null {
  if (actor === null && at === null) return null
  return format(s.detail.actor, {
    name: actor?.name ?? s.detail.unknownActor,
    date: at === null ? '' : formatDateTimeBogota(at),
  })
}

function TransferSummaryCard({ transfer }: { transfer: Transfer }) {
  const { detail } = s
  const history: Array<[string, string | null]> = [
    [detail.events.created, actorText(transfer.created_by, transfer.created_at)],
    [detail.events.requested, actorText(transfer.requested_by, transfer.requested_at)],
    [detail.events.approved, actorText(transfer.approved_by, transfer.approved_at)],
    [detail.events.dispatched, actorText(transfer.dispatched_by, transfer.dispatched_at)],
    [detail.events.received, actorText(transfer.received_by, transfer.received_at)],
    [detail.events.voided, actorText(transfer.voided_by, transfer.voided_at)],
    [detail.events.voidReason, transfer.void_reason],
  ]
  return (
    <div className="grid gap-4 md:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>
            <h3>{detail.summary}</h3>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
            <dt className="text-muted-foreground">{detail.origin}</dt>
            <dd>{transfer.origin_warehouse.name}</dd>
            <dt className="text-muted-foreground">{detail.destination}</dt>
            <dd>{transfer.destination_warehouse.name}</dd>
            <dt className="text-muted-foreground">{detail.notes}</dt>
            <dd className="whitespace-pre-line">{transfer.notes ?? detail.noNotes}</dd>
          </dl>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle>
            <h3>{detail.history}</h3>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <dl aria-label={detail.history} className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
            {history
              .filter((entry): entry is [string, string] => entry[1] !== null)
              .map(([label, value]) => (
                <div key={label} className="contents">
                  <dt className="text-muted-foreground">{label}</dt>
                  <dd>{value}</dd>
                </div>
              ))}
          </dl>
        </CardContent>
      </Card>
    </div>
  )
}

function LinesTable({ transfer }: { transfer: Transfer }) {
  const { columns } = s.detail
  return (
    <section className="flex flex-col gap-2">
      <h3 className="font-semibold">{s.detail.lines}</h3>
      <Table>
        <TableCaption className="sr-only">{s.detail.linesCaption}</TableCaption>
        <TableHeader>
          <TableRow>
            <TableHead>{columns.product}</TableHead>
            <TableHead>{columns.lot}</TableHead>
            <TableHead>{columns.expiresOn}</TableHead>
            <TableHead className="text-right">{columns.sent}</TableHead>
            <TableHead className="text-right">{columns.received}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {transfer.lines.map((line) => (
            <TableRow key={line.id}>
              <TableCell>{line.product.name}</TableCell>
              <TableCell className="font-mono">{line.lot.lot_code}</TableCell>
              <TableCell className="tabular-nums">{line.lot.expires_on}</TableCell>
              <TableCell className="text-right tabular-nums">{line.quantity}</TableCell>
              <TableCell className="text-right tabular-nums">
                {line.received_quantity ?? s.detail.notReceived}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </section>
  )
}

function DiscrepanciesTable({ transfer }: { transfer: Transfer }) {
  const { columns, discrepancyStatus } = s.detail
  return (
    <section aria-label={s.detail.discrepancies} className="flex flex-col gap-2">
      <h3 className="font-semibold">{s.detail.discrepancies}</h3>
      <Table>
        <TableCaption className="sr-only">{s.detail.discrepanciesCaption}</TableCaption>
        <TableHeader>
          <TableRow>
            <TableHead>{columns.product}</TableHead>
            <TableHead>{columns.lot}</TableHead>
            <TableHead className="text-right">{columns.shortage}</TableHead>
            <TableHead>{columns.status}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {transfer.discrepancies.map((discrepancy) => {
            const line = transfer.lines.find((candidate) => candidate.id === discrepancy.line_id)
            return (
              <TableRow key={discrepancy.id}>
                <TableCell>{line?.product.name}</TableCell>
                <TableCell className="font-mono">{line?.lot.lot_code}</TableCell>
                <TableCell className="text-right tabular-nums">{discrepancy.shortage}</TableCell>
                <TableCell>
                  <Badge variant={discrepancy.status === 'pending' ? 'secondary' : 'outline'}>
                    {discrepancyStatus[discrepancy.status]}
                  </Badge>
                </TableCell>
              </TableRow>
            )
          })}
        </TableBody>
      </Table>
    </section>
  )
}
