import type { Ref } from 'react'
import { ErrorMessage } from '@/components/error-message'
import { SubmitButton } from '@/components/submit-button'
import { Button } from '@/components/ui/button'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { strings } from '@/lib/strings'

type TextFieldProps = {
  id: string
  label: string
  value: string
  onChange: (value: string) => void
  error?: string
  maxLength?: number
  autoFocus?: boolean
  inputRef?: Ref<HTMLInputElement>
}

// Campo de texto del kit con su mensaje junto al campo, enlazado como descripción accesible.
export function TextField({ id, label, value, onChange, error, maxLength, autoFocus, inputRef }: TextFieldProps) {
  const errorId = `${id}-error`
  return (
    <Field data-invalid={error !== undefined || undefined}>
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <Input
        id={id}
        ref={inputRef}
        autoComplete="off"
        // Foco al abrir la edición en contexto (flujo de teclado); nunca al cargar la página.
        autoFocus={autoFocus}
        maxLength={maxLength}
        aria-invalid={error !== undefined || undefined}
        aria-describedby={error !== undefined ? errorId : undefined}
        value={value}
        onChange={(event) => onChange(event.target.value)}
      />
      {error !== undefined && <FieldError id={errorId}>{error}</FieldError>}
    </Field>
  )
}

// Casilla nativa con su etiqueta al lado, con los tokens del tema: el Checkbox de Radix exige
// `ResizeObserver`, ausente en el entorno de pruebas, y la casilla nativa da teclado y lector gratis.
export function CheckboxField({
  id,
  label,
  checked,
  onChange,
}: {
  id: string
  label: string
  checked: boolean
  onChange: (checked: boolean) => void
}) {
  return (
    <Field orientation="horizontal">
      <input
        id={id}
        type="checkbox"
        className="size-4 shrink-0 accent-primary outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        checked={checked}
        onChange={(event) => onChange(event.target.checked)}
      />
      <FieldLabel htmlFor={id} className="font-normal">
        {label}
      </FieldLabel>
    </Field>
  )
}

// Pie de formulario: rechazo general (si ningún campo lleva el suyo), botón de envío y, en la edición,
// "Cancelar".
export function FormFooter({
  error,
  pending,
  submitLabel,
  pendingLabel,
  onCancel,
}: {
  error: unknown
  pending: boolean
  submitLabel: string
  pendingLabel: string
  onCancel?: () => void
}) {
  return (
    <>
      {error !== null && <ErrorMessage error={error} />}
      <div className="flex flex-wrap gap-2">
        <SubmitButton pending={pending} pendingLabel={pendingLabel}>
          {submitLabel}
        </SubmitButton>
        {onCancel !== undefined && (
          <Button type="button" variant="ghost" onClick={onCancel}>
            {strings.catalog.cancel}
          </Button>
        )}
      </div>
    </>
  )
}
