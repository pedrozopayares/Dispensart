import { useId, useState } from 'react'
import { ErrorMessage } from '@/components/error-message'
import { SubmitButton } from '@/components/submit-button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
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
import { useWarehouses } from '@/features/catalog/queries'
import {
  buildDispensationIntent,
  defaultQuantities,
  dispensableItems,
  validateIntent,
  type IntentErrors,
  type Quantities,
} from '@/features/dispensations/dispensation-intent'
import { useDispense, usePreviewDispensation } from '@/features/dispensations/queries'
import type {
  Dispensation,
  DispensationPreview,
  DispensationRequest,
  Prescription,
  PreviewItem,
  PreviewRequest,
} from '@/lib/api-types'
import { hasCode } from '@/lib/api-errors'
import { format, strings } from '@/lib/strings'
import type { IdempotentIntent } from '@/lib/use-idempotent-intent'
import { useSubmitGuard } from '@/lib/use-submit-guard'

// Rechazos que significan que la prescripción cambió: la ficha se recarga (dispensation-screen
// "Vista previa de lotes FEFO: Prescripción que cambió de estado").
const PRESCRIPTION_CHANGED = ['prescription_expired', 'prescription_exhausted', 'exceeds_prescription']
// Rechazos que un reintento idéntico puede resolver: se ofrece "Reintentar" con la misma clave.
const RETRYABLE = ['network_error', 'server_error']

const changedPrescription = (error: unknown) => PRESCRIPTION_CHANGED.some((code) => hasCode(error, code))

export type CompletedDispensation = { dispensation: Dispensation; prescription: Prescription }

export type DispensationFormProps = {
  prescription: Prescription
  // Clave por intención (design D3), del nivel de la pantalla: sobrevive a cerrar y reabrir el formulario.
  intent: IdempotentIntent<PreviewRequest>
  onCompleted: (completed: CompletedDispensation) => void
  onPrescriptionChanged: (error: unknown) => void
  onCancel: () => void
}

type Previewed = { intent: PreviewRequest; data: DispensationPreview }
type AuthorizerErrors = { email?: string; password?: string }

// Formulario de dispensación: bodega y cantidades → vista previa FEFO → coautorización si hay control
// especial (RN-05) → confirmación idempotente sin doble envío (RN-09, design D3/D4).
export function DispensationForm({
  prescription,
  intent,
  onCompleted,
  onPrescriptionChanged,
  onCancel,
}: DispensationFormProps) {
  const ids = useId()
  const warehouses = useWarehouses()
  const previewMutation = usePreviewDispensation()
  const dispenseMutation = useDispense()
  const guard = useSubmitGuard()

  const [warehouseId, setWarehouseId] = useState<number | undefined>(undefined)
  const [quantities, setQuantities] = useState<Quantities>(() => defaultQuantities(prescription))
  const [errors, setErrors] = useState<IntentErrors | null>(null)
  const [preview, setPreview] = useState<Previewed | null>(null)
  const [previewError, setPreviewError] = useState<unknown>(null)
  const [confirmError, setConfirmError] = useState<unknown>(null)
  // `insufficient_stock` al confirmar: la asignación mostrada ya no vale hasta recalcularla.
  const [stale, setStale] = useState(false)
  // `authorization_required` del servidor aunque la vista previa no lo pidiera.
  const [forceAuthorization, setForceAuthorization] = useState(false)
  const [authorizerEmail, setAuthorizerEmail] = useState('')
  const [authorizerPassword, setAuthorizerPassword] = useState('')
  const [authorizerErrors, setAuthorizerErrors] = useState<AuthorizerErrors>({})

  const productName = (productId: number) =>
    prescription.items.find((item) => item.product.id === productId)?.product.name
  const requiresAuthorization =
    preview !== null && (preview.data.requires_authorization || forceAuthorization)
  const canConfirm = preview !== null && preview.data.fulfillable && !stale

  // Cualquier cambio de bodega o cantidad deja la asignación mostrada sin valor.
  const discardPreview = () => {
    setPreview(null)
    setPreviewError(null)
    setConfirmError(null)
    setStale(false)
    setForceAuthorization(false)
  }

  const runPreview = (body: PreviewRequest) => {
    setPreviewError(null)
    previewMutation.mutate(body, {
      onSuccess: (data) => {
        setPreview({ intent: body, data })
        setConfirmError(null)
        setStale(false)
      },
      onError: (error) => {
        if (changedPrescription(error)) onPrescriptionChanged(error)
        else setPreviewError(error)
      },
    })
  }

  const requestPreview = () => {
    const found = validateIntent(prescription, warehouseId, quantities)
    setErrors(found)
    if (found !== null || warehouseId === undefined) return
    runPreview(buildDispensationIntent(prescription, warehouseId, quantities))
  }

  const confirm = () => {
    if (preview === null || !canConfirm) return
    if (requiresAuthorization) {
      const missing: AuthorizerErrors = {
        email: authorizerEmail.trim() === '' ? strings.common.required : undefined,
        password: authorizerPassword === '' ? strings.common.required : undefined,
      }
      setAuthorizerErrors(missing)
      if (missing.email !== undefined || missing.password !== undefined) return
    }
    guard((release) => {
      const key = intent.keyFor(preview.intent)
      const body: DispensationRequest = requiresAuthorization
        ? {
            ...preview.intent,
            authorizer_email: authorizerEmail.trim(),
            authorizer_password: authorizerPassword,
          }
        : preview.intent
      setConfirmError(null)
      dispenseMutation.mutate(
        { body, key },
        {
          onSuccess: ({ dispensation }) => {
            intent.settleSuccess()
            onCompleted({ dispensation, prescription })
          },
          onError: (error) => {
            intent.settleError(error)
            if (changedPrescription(error)) {
              onPrescriptionChanged(error)
              return
            }
            setConfirmError(error)
            if (hasCode(error, 'idempotency_key_reused')) setPreview(null)
            if (hasCode(error, 'insufficient_stock')) setStale(true)
            if (hasCode(error, 'authorization_required')) setForceAuthorization(true)
          },
          onSettled: () => {
            // La contraseña del regente nunca sobrevive a una respuesta (RN-05).
            setAuthorizerPassword('')
            release()
          },
        },
      )
    })
  }

  const labels = strings.dispensation.form
  const retryable = RETRYABLE.some((code) => hasCode(confirmError, code))

  return (
    <section
      aria-label={format(labels.title, { id: String(prescription.id) })}
      className="flex flex-col gap-4 rounded-md border p-4"
    >
      <h4 className="font-semibold">{format(labels.title, { id: String(prescription.id) })}</h4>
      <form
        noValidate
        className="flex flex-col gap-4"
        onSubmit={(event) => {
          event.preventDefault()
          requestPreview()
        }}
      >
        <Field className="max-w-sm" data-invalid={errors?.warehouse !== undefined || undefined}>
          <FieldLabel htmlFor={`${ids}-warehouse`}>{labels.warehouse}</FieldLabel>
          <NativeSelect
            id={`${ids}-warehouse`}
            className="w-full"
            value={warehouseId === undefined ? '' : String(warehouseId)}
            aria-invalid={errors?.warehouse !== undefined || undefined}
            onChange={(event) => {
              setWarehouseId(event.target.value === '' ? undefined : Number(event.target.value))
              discardPreview()
            }}
          >
            <NativeSelectOption value="">{labels.chooseWarehouse}</NativeSelectOption>
            {(warehouses.data ?? []).map((warehouse) => (
              <NativeSelectOption key={warehouse.id} value={warehouse.id}>
                {warehouse.name}
              </NativeSelectOption>
            ))}
          </NativeSelect>
          {errors?.warehouse !== undefined && <FieldError>{errors.warehouse}</FieldError>}
        </Field>
        <div className="flex flex-wrap gap-4">
          {dispensableItems(prescription).map((item) => {
            const error = errors?.items[item.id]
            return (
              <Field key={item.id} className="w-56" data-invalid={error !== undefined || undefined}>
                <FieldLabel htmlFor={`${ids}-qty-${item.id}`}>
                  {format(labels.quantity, { product: item.product.name })}
                </FieldLabel>
                <Input
                  id={`${ids}-qty-${item.id}`}
                  type="number"
                  inputMode="numeric"
                  min={0}
                  max={item.pending_quantity}
                  aria-invalid={error !== undefined || undefined}
                  value={quantities[item.id] ?? ''}
                  onChange={(event) => {
                    setQuantities((current) => ({ ...current, [item.id]: event.target.value }))
                    discardPreview()
                  }}
                />
                {error !== undefined ? (
                  <FieldError>{error}</FieldError>
                ) : (
                  <FieldDescription>
                    {format(labels.pending, { pending: String(item.pending_quantity) })}
                  </FieldDescription>
                )}
              </Field>
            )
          })}
        </div>
        {errors?.quantities !== undefined && <FieldError>{errors.quantities}</FieldError>}
        <div className="flex flex-wrap gap-2">
          <SubmitButton variant="secondary" pending={previewMutation.isPending} pendingLabel={labels.previewing}>
            {labels.preview}
          </SubmitButton>
          <Button type="button" variant="ghost" onClick={onCancel}>
            {labels.cancel}
          </Button>
        </div>
      </form>

      {previewError !== null && <ErrorMessage error={previewError} />}
      {preview === null ? (
        <p className="text-sm text-muted-foreground">{labels.previewNeeded}</p>
      ) : (
        <PreviewAllocation preview={preview.data} productName={productName} />
      )}

      {requiresAuthorization && (
        <div className="flex flex-col gap-3">
          <Alert>
            <AlertDescription>{strings.dispensation.authorizer.notice}</AlertDescription>
          </Alert>
          <div className="flex flex-wrap gap-4">
            <Field className="w-72" data-invalid={authorizerErrors.email !== undefined || undefined}>
              <FieldLabel htmlFor={`${ids}-authorizer-email`}>{strings.dispensation.authorizer.email}</FieldLabel>
              <Input
                id={`${ids}-authorizer-email`}
                type="email"
                autoComplete="off"
                aria-invalid={authorizerErrors.email !== undefined || undefined}
                value={authorizerEmail}
                onChange={(event) => setAuthorizerEmail(event.target.value)}
              />
              {authorizerErrors.email !== undefined && <FieldError>{authorizerErrors.email}</FieldError>}
            </Field>
            <Field className="w-72" data-invalid={authorizerErrors.password !== undefined || undefined}>
              <FieldLabel htmlFor={`${ids}-authorizer-password`}>
                {strings.dispensation.authorizer.password}
              </FieldLabel>
              <Input
                id={`${ids}-authorizer-password`}
                type="password"
                // "new-password": el navegador no trata el par como inicio de sesión ni rellena el
                // correo y la clave guardados del usuario en sesión.
                autoComplete="new-password"
                aria-invalid={authorizerErrors.password !== undefined || undefined}
                value={authorizerPassword}
                onChange={(event) => setAuthorizerPassword(event.target.value)}
              />
              {authorizerErrors.password !== undefined && (
                <FieldError>{authorizerErrors.password}</FieldError>
              )}
            </Field>
          </div>
        </div>
      )}

      {confirmError !== null && (
        <ErrorMessage
          error={confirmError}
          describe={{ productName }}
          onRetry={retryable ? confirm : undefined}
          retrying={dispenseMutation.isPending}
        />
      )}
      <div className="flex flex-wrap gap-2">
        <SubmitButton
          type="button"
          pending={dispenseMutation.isPending}
          pendingLabel={strings.dispensation.confirming}
          disabled={!canConfirm}
          onClick={confirm}
        >
          {strings.dispensation.confirm}
        </SubmitButton>
        {stale && preview !== null && (
          <SubmitButton
            type="button"
            variant="outline"
            pending={previewMutation.isPending}
            pendingLabel={labels.previewing}
            onClick={() => runPreview(preview.intent)}
          >
            {strings.dispensation.recalculate}
          </SubmitButton>
        )}
      </div>
    </section>
  )
}

// Lotes asignados por ítem en el orden que entrega la API (FEFO, RN-01), sin reordenar.
function PreviewAllocation({
  preview,
  productName,
}: {
  preview: DispensationPreview
  productName: (productId: number) => string | undefined
}) {
  const labels = strings.dispensation.preview
  return (
    <div className="flex flex-col gap-4">
      <h5 className="font-medium">{labels.title}</h5>
      {preview.items.map((item) => (
        <PreviewItemBlock
          key={item.prescription_item_id}
          item={item}
          product={productName(item.product_id) ?? strings.errors.unknownProduct}
        />
      ))}
    </div>
  )
}

function PreviewItemBlock({ item, product }: { item: PreviewItem; product: string }) {
  const labels = strings.dispensation.preview
  return (
    <div role="group" aria-label={product} className="flex flex-col gap-2">
      <p className="flex items-center gap-2 text-sm font-medium">
        {product}
        {item.requires_authorization && (
          <Badge variant="outline">{strings.dispensation.prescription.controlled}</Badge>
        )}
      </p>
      {item.allocations.length > 0 && (
        <Table>
          <TableCaption className="sr-only">{format(labels.caption, { product })}</TableCaption>
          <TableHeader>
            <TableRow>
              <TableHead>{labels.columns.lot}</TableHead>
              <TableHead>{labels.columns.expiresOn}</TableHead>
              <TableHead className="text-right">{labels.columns.quantity}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {item.allocations.map((allocation) => (
              <TableRow key={allocation.lot_id}>
                <TableCell className="font-mono">{allocation.lot_code}</TableCell>
                <TableCell>{allocation.expires_on}</TableCell>
                <TableCell className="text-right">{allocation.quantity}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
      {item.expired_excluded_quantity > 0 && (
        <p className="text-sm text-muted-foreground">
          {format(labels.expiredExcluded, { count: String(item.expired_excluded_quantity) })}
        </p>
      )}
      {item.shortage > 0 && (
        <p className="text-sm text-destructive">
          {format(strings.errors.insufficientStock, {
            product,
            requested: String(item.requested),
            available: String(item.available),
          })}
        </p>
      )}
    </div>
  )
}
