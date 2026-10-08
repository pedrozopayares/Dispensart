import { useId, useRef, useState } from 'react'
import { useNavigate } from 'react-router'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import { SubmitButton } from '@/components/submit-button'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel, FieldLegend, FieldSet } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import { Textarea } from '@/components/ui/textarea'
import { useWarehouses } from '@/features/catalog/queries'
import { useCreateTransfer, useOriginStock } from '@/features/transfers/queries'
import {
  buildNewTransfer,
  eligibleStock,
  validateDraft,
  type DraftErrors,
  type DraftLine,
  type TransferDraft,
} from '@/features/transfers/transfer-draft'
import { fieldErrors } from '@/lib/api-errors'
import type { StockRow } from '@/lib/api-types'
import { format, strings } from '@/lib/strings'
import { useSubmitGuard } from '@/lib/use-submit-guard'

const labels = strings.transfers.form
const NOTES_MAX = 1000

const lotLabel = (row: StockRow) =>
  format(labels.lotOption, {
    product: row.product.name,
    lot: row.lot.lot_code,
    expiresOn: row.lot.expires_on,
    available: String(row.quantity),
  })

// Formulario "Nuevo traslado": origen, destino, observaciones y líneas de lote + cantidad. Al crear
// abre el detalle del borrador. Sin doble envío (design D4).
export function TransferForm({ onCancel }: { onCancel: () => void }) {
  const ids = useId()
  const navigate = useNavigate()
  const warehouses = useWarehouses()
  const create = useCreateTransfer()
  const guard = useSubmitGuard()
  const nextKey = useRef(1)
  const [draft, setDraft] = useState<TransferDraft>({ notes: '', lines: [] })
  const [errors, setErrors] = useState<DraftErrors | null>(null)
  const [submitError, setSubmitError] = useState<unknown>(null)
  const stock = useOriginStock(draft.originId)
  const eligible = eligibleStock(stock.data)
  const serverErrors = fieldErrors(submitError)

  const setLine = (key: number, change: Partial<DraftLine>) =>
    setDraft((current) => ({
      ...current,
      lines: current.lines.map((line) => (line.key === key ? { ...line, ...change } : line)),
    }))

  const addLine = () => {
    const key = nextKey.current++
    setDraft((current) => ({ ...current, lines: [...current.lines, { key, quantity: '' }] }))
  }

  const submit = () => {
    const found = validateDraft(draft)
    setErrors(found)
    if (found !== null) return
    guard((release) => {
      setSubmitError(null)
      create.mutate(buildNewTransfer(draft), {
        onSuccess: (transfer) => void navigate(`/transfers/${transfer.id}`),
        // El formulario conserva lo escrito ante cualquier rechazo.
        onError: (error) => setSubmitError(error),
        onSettled: release,
      })
    })
  }

  const warehouseSelect = (
    field: 'originId' | 'destinationId',
    label: string,
    error: string | undefined,
  ) => (
    <Field className="max-w-sm" data-invalid={error !== undefined || undefined}>
      <FieldLabel htmlFor={`${ids}-${field}`}>{label}</FieldLabel>
      <NativeSelect
        id={`${ids}-${field}`}
        className="w-full"
        aria-invalid={error !== undefined || undefined}
        value={draft[field] === undefined ? '' : String(draft[field])}
        onChange={(event) => {
          const value = event.target.value === '' ? undefined : Number(event.target.value)
          // Los lotes pertenecen al origen: cambiarlo descarta los elegidos.
          setDraft((current) =>
            field === 'originId'
              ? { ...current, originId: value, lines: current.lines.map((line) => ({ ...line, lotId: undefined })) }
              : { ...current, destinationId: value },
          )
        }}
      >
        <NativeSelectOption value="">{labels.chooseWarehouse}</NativeSelectOption>
        {(warehouses.data ?? []).map((warehouse) => (
          <NativeSelectOption key={warehouse.id} value={warehouse.id}>
            {warehouse.name}
          </NativeSelectOption>
        ))}
      </NativeSelect>
      {error !== undefined && <FieldError>{error}</FieldError>}
    </Field>
  )

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <h3>{labels.title}</h3>
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form
          noValidate
          aria-label={labels.title}
          className="flex flex-col gap-6"
          onSubmit={(event) => {
            event.preventDefault()
            submit()
          }}
        >
          <FieldGroup className="flex-row flex-wrap">
            {warehouseSelect(
              'originId',
              labels.origin,
              errors?.origin ?? serverErrors.origin_warehouse_id,
            )}
            {warehouseSelect(
              'destinationId',
              labels.destination,
              errors?.destination ?? serverErrors.destination_warehouse_id,
            )}
          </FieldGroup>
          <Field data-invalid={serverErrors.notes !== undefined || undefined}>
            <FieldLabel htmlFor={`${ids}-notes`}>{labels.notes}</FieldLabel>
            <Textarea
              id={`${ids}-notes`}
              maxLength={NOTES_MAX}
              value={draft.notes}
              onChange={(event) => setDraft((current) => ({ ...current, notes: event.target.value }))}
            />
            {serverErrors.notes !== undefined && <FieldError>{serverErrors.notes}</FieldError>}
          </Field>
          <FieldSet>
            <FieldLegend>{labels.lines}</FieldLegend>
            {draft.originId !== undefined && stock.isPending && <LoadingState label={labels.loadingLots} />}
            {stock.isError && (
              <ErrorMessage error={stock.error} onRetry={() => void stock.refetch()} retrying={stock.isFetching} />
            )}
            {stock.isSuccess && eligible.length === 0 && <FieldDescription>{labels.noLots}</FieldDescription>}
            {draft.lines.map((line, index) => {
              const n = String(index + 1)
              const lineErrors = errors?.byLine[line.key]
              const lotError = lineErrors?.lot ?? serverErrors[`lines.${index}.lot_id`]
              const quantityError = lineErrors?.quantity ?? serverErrors[`lines.${index}.quantity`]
              // Un lote solo puede ir en una línea: los elegidos en otras no se ofrecen.
              const taken = new Set(draft.lines.filter((other) => other.key !== line.key).map((other) => other.lotId))
              return (
                <div key={line.key} className="flex flex-wrap items-start gap-4">
                  <Field className="min-w-80 flex-1" data-invalid={lotError !== undefined || undefined}>
                    <FieldLabel htmlFor={`${ids}-lot-${line.key}`}>{format(labels.lot, { n })}</FieldLabel>
                    <NativeSelect
                      id={`${ids}-lot-${line.key}`}
                      className="w-full"
                      disabled={draft.originId === undefined}
                      aria-invalid={lotError !== undefined || undefined}
                      value={line.lotId === undefined ? '' : String(line.lotId)}
                      onChange={(event) =>
                        setLine(line.key, {
                          lotId: event.target.value === '' ? undefined : Number(event.target.value),
                        })
                      }
                    >
                      <NativeSelectOption value="">
                        {draft.originId === undefined ? labels.chooseOriginFirst : labels.chooseLot}
                      </NativeSelectOption>
                      {eligible
                        .filter((row) => !taken.has(row.lot.id))
                        .map((row) => (
                          <NativeSelectOption key={row.lot.id} value={row.lot.id}>
                            {lotLabel(row)}
                          </NativeSelectOption>
                        ))}
                    </NativeSelect>
                    {lotError !== undefined && <FieldError>{lotError}</FieldError>}
                  </Field>
                  <Field className="w-36" data-invalid={quantityError !== undefined || undefined}>
                    <FieldLabel htmlFor={`${ids}-qty-${line.key}`}>{format(labels.quantity, { n })}</FieldLabel>
                    <Input
                      id={`${ids}-qty-${line.key}`}
                      type="number"
                      inputMode="numeric"
                      min={1}
                      aria-invalid={quantityError !== undefined || undefined}
                      value={line.quantity}
                      onChange={(event) => setLine(line.key, { quantity: event.target.value })}
                    />
                    {quantityError !== undefined && <FieldError>{quantityError}</FieldError>}
                  </Field>
                  <Button
                    type="button"
                    variant="ghost"
                    className="mt-6"
                    aria-label={format(labels.removeLine, { n })}
                    onClick={() =>
                      setDraft((current) => ({
                        ...current,
                        lines: current.lines.filter((other) => other.key !== line.key),
                      }))
                    }
                  >
                    {strings.common.remove}
                  </Button>
                </div>
              )
            })}
            {errors?.lines !== undefined && <FieldError>{errors.lines}</FieldError>}
            <div>
              <Button type="button" variant="outline" onClick={addLine}>
                {labels.addLine}
              </Button>
            </div>
          </FieldSet>
          {submitError !== null && <ErrorMessage error={submitError} />}
          <div className="flex flex-wrap gap-2">
            <SubmitButton pending={create.isPending} pendingLabel={labels.submitting}>
              {labels.submit}
            </SubmitButton>
            <Button type="button" variant="ghost" onClick={onCancel}>
              {labels.cancel}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
