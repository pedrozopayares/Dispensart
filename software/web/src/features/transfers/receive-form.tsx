import { useId, useState } from 'react'
import { ErrorMessage } from '@/components/error-message'
import { SubmitButton } from '@/components/submit-button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { fieldErrors } from '@/lib/api-errors'
import type { Transfer, TransferReceipt } from '@/lib/api-types'
import { format, strings } from '@/lib/strings'

const labels = strings.transfers.receive
const WHOLE = /^\d+$/

type ReceiveFormProps = {
  transfer: Transfer
  pending: boolean
  error: unknown
  onSubmit: (body: TransferReceipt) => void
  onCancel: () => void
}

// Error local de una cantidad recibida: entero de 0 a lo enviado (RN-06).
function quantityError(value: string, sent: number): string | undefined {
  const trimmed = value.trim()
  if (!WHOLE.test(trimmed)) return format(labels.invalid, { sent: String(sent) })
  if (Number(trimmed) > sent) return format(labels.tooHigh, { sent: String(sent) })
  return undefined
}

// Recepción por línea: cantidad recibida por defecto igual a la enviada; recibir menos avisa antes
// de confirmar que lo no recibido queda como discrepancia pendiente (RN-07).
export function ReceiveForm({ transfer, pending, error, onSubmit, onCancel }: ReceiveFormProps) {
  const ids = useId()
  const [quantities, setQuantities] = useState<Record<number, string>>(() =>
    Object.fromEntries(transfer.lines.map((line) => [line.id, String(line.quantity)])),
  )
  const [showErrors, setShowErrors] = useState(false)
  const serverErrors = fieldErrors(error)

  const localErrors = Object.fromEntries(
    transfer.lines.map((line) => [line.id, quantityError(quantities[line.id] ?? '', line.quantity)]),
  )
  const valid = Object.values(localErrors).every((message) => message === undefined)
  const short = transfer.lines.some((line) => {
    const value = quantities[line.id] ?? ''
    return WHOLE.test(value.trim()) && Number(value) < line.quantity
  })

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
          className="flex flex-col gap-4"
          onSubmit={(event) => {
            event.preventDefault()
            setShowErrors(true)
            if (!valid) return
            onSubmit({
              lines: transfer.lines.map((line) => ({
                line_id: line.id,
                received_quantity: Number(quantities[line.id]),
              })),
            })
          }}
        >
          {transfer.lines.map((line, index) => {
            const message =
              (showErrors ? localErrors[line.id] : undefined) ??
              serverErrors[`lines.${index}.received_quantity`]
            return (
              <Field key={line.id} className="max-w-md" data-invalid={message !== undefined || undefined}>
                <FieldLabel htmlFor={`${ids}-${line.id}`}>
                  {format(labels.quantity, { product: line.product.name, lot: line.lot.lot_code })}
                </FieldLabel>
                <Input
                  id={`${ids}-${line.id}`}
                  type="number"
                  inputMode="numeric"
                  min={0}
                  max={line.quantity}
                  aria-invalid={message !== undefined || undefined}
                  value={quantities[line.id] ?? ''}
                  onChange={(event) => {
                    setShowErrors(true)
                    setQuantities((current) => ({ ...current, [line.id]: event.target.value }))
                  }}
                />
                {message !== undefined ? (
                  <FieldError>{message}</FieldError>
                ) : (
                  <FieldDescription>{format(labels.sent, { sent: String(line.quantity) })}</FieldDescription>
                )}
              </Field>
            )
          })}
          {short && (
            <Alert>
              <AlertDescription>{labels.discrepancyNotice}</AlertDescription>
            </Alert>
          )}
          {error !== null && <ErrorMessage error={error} />}
          <div className="flex flex-wrap gap-2">
            <SubmitButton pending={pending} pendingLabel={strings.transfers.actions.working}>
              {labels.confirm}
            </SubmitButton>
            <Button type="button" variant="ghost" disabled={pending} onClick={onCancel}>
              {labels.cancel}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
