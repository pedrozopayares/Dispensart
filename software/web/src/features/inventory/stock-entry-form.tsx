import { useId, useState } from 'react'
import { SuccessNotice } from '@/components/success-notice'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import { FormFooter, TextField } from '@/features/catalog/catalog-fields'
import type { FilterOption } from '@/components/select-filter'
import { trimmed, useCatalogForm } from '@/features/catalog/use-catalog-form'
import { useCreateStockEntry } from '@/features/inventory/queries'
import { format, strings } from '@/lib/strings'

const s = strings.inventory.entry
// Límites de `StoreStockEntryRequest` en la API.
const LOT_CODE_MAX = 50
const REASON_MAX = 500

type EntryDraft = {
  warehouse_id: string
  product_id: string
  lot_code: string
  expires_on: string
  quantity: string
  reason: string
}
const EMPTY: EntryDraft = { warehouse_id: '', product_id: '', lot_code: '', expires_on: '', quantity: '', reason: '' }
const REQUIRED = ['warehouse_id', 'product_id', 'lot_code', 'expires_on', 'quantity', 'reason'] as const
// La cantidad viaja como número; si no es entera, la API la rechaza junto al campo.
const toBody = (draft: EntryDraft) => ({
  warehouse_id: Number(draft.warehouse_id),
  product_id: Number(draft.product_id),
  lot_code: trimmed(draft.lot_code),
  expires_on: draft.expires_on,
  quantity: Number(draft.quantity),
  reason: trimmed(draft.reason),
})

// Formulario «Registrar ingreso» de la pantalla Inventario (S18): solo se monta con `inventory.adjust`. El
// servidor decide si el lote se crea o se reutiliza y rechaza vencidos; el borrador se conserva ante cualquier
// rechazo.
export function StockEntryForm({
  warehouses,
  products,
}: {
  warehouses: readonly FilterOption[]
  products: readonly FilterOption[]
}) {
  const ids = useId()
  const create = useCreateStockEntry()
  const form = useCatalogForm(EMPTY, REQUIRED)
  const [notice, setNotice] = useState<string | null>(null)

  const submit = () => {
    setNotice(null)
    form.submit((draft, callbacks) =>
      create.mutate(toBody(draft), {
        ...callbacks,
        onSuccess: (movement) => {
          form.reset()
          setNotice(
            format(s.created, {
              quantity: String(movement.quantity),
              lot: movement.lot.lot_code,
              warehouse: movement.warehouse.name,
            }),
          )
        },
      }),
    )
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <h3 id={`${ids}-title`}>{s.title}</h3>
        </CardTitle>
        <CardDescription>{s.description}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <form
          noValidate
          aria-labelledby={`${ids}-title`}
          className="flex flex-col gap-4"
          onSubmit={(event) => {
            event.preventDefault()
            submit()
          }}
        >
          <FieldGroup className="grid gap-4 sm:grid-cols-3">
            <SelectField
              id={`${ids}-warehouse`}
              label={s.warehouse}
              options={warehouses}
              value={form.draft.warehouse_id}
              error={form.errorOf('warehouse_id')}
              onChange={(value) => form.set('warehouse_id', value)}
            />
            <SelectField
              id={`${ids}-product`}
              label={s.product}
              options={products}
              value={form.draft.product_id}
              error={form.errorOf('product_id')}
              onChange={(value) => form.set('product_id', value)}
            />
            <TextField
              id={`${ids}-lot-code`}
              label={s.lotCode}
              maxLength={LOT_CODE_MAX}
              value={form.draft.lot_code}
              error={form.errorOf('lot_code')}
              onChange={(value) => form.set('lot_code', value)}
            />
            <TextField
              id={`${ids}-expires-on`}
              label={s.expiresOn}
              type="date"
              value={form.draft.expires_on}
              error={form.errorOf('expires_on')}
              onChange={(value) => form.set('expires_on', value)}
            />
            <TextField
              id={`${ids}-quantity`}
              label={s.quantity}
              inputMode="numeric"
              value={form.draft.quantity}
              error={form.errorOf('quantity')}
              onChange={(value) => form.set('quantity', value)}
            />
            <TextField
              id={`${ids}-reason`}
              label={s.reason}
              maxLength={REASON_MAX}
              value={form.draft.reason}
              error={form.errorOf('reason')}
              onChange={(value) => form.set('reason', value)}
            />
          </FieldGroup>
          <FormFooter
            error={form.showGeneralError ? form.submitError : null}
            pending={create.isPending}
            submitLabel={s.submit}
            pendingLabel={s.submitting}
          />
        </form>
        {notice !== null && <SuccessNotice message={notice} />}
      </CardContent>
    </Card>
  )
}

// Lista nativa con su mensaje junto al campo, enlazado como descripción accesible.
function SelectField({
  id,
  label,
  options,
  value,
  error,
  onChange,
}: {
  id: string
  label: string
  options: readonly FilterOption[]
  value: string
  error?: string
  onChange: (value: string) => void
}) {
  const errorId = `${id}-error`
  return (
    <Field data-invalid={error !== undefined || undefined}>
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <NativeSelect
        id={id}
        className="w-full"
        aria-invalid={error !== undefined || undefined}
        aria-describedby={error !== undefined ? errorId : undefined}
        value={value}
        onChange={(event) => onChange(event.target.value)}
      >
        <NativeSelectOption value="">{s.choose}</NativeSelectOption>
        {options.map((option) => (
          <NativeSelectOption key={option.value} value={option.value}>
            {option.label}
          </NativeSelectOption>
        ))}
      </NativeSelect>
      {error !== undefined && <FieldError id={errorId}>{error}</FieldError>}
    </Field>
  )
}
