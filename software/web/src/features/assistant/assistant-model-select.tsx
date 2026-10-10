import { useId } from 'react'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import { modelLabel } from '@/features/assistant/assistant-labels'
import { strings } from '@/lib/strings'

const labels = strings.assistant.model

type AssistantModelSelectProps = {
  // `id` de los modelos ofrecidos, en el orden de la API (`mock` primero).
  options: readonly string[]
  value: string
  loading: boolean
  disabled: boolean
  // Aviso de caída a `mock` (lista fallida o preferencia ya no disponible).
  notice?: string
  // `errors.model` del servidor.
  error?: string
  onChange: (id: string) => void
}

// Selector "Modelo" de la pantalla Asistente (S15, design D8): selector nativo del kit, accesible por
// teclado; cada opción con su etiqueta en español, nunca el `id` crudo.
export function AssistantModelSelect({
  options,
  value,
  loading,
  disabled,
  notice,
  error,
  onChange,
}: AssistantModelSelectProps) {
  const ids = useId()
  const selectId = `${ids}-model`
  const describedBy = [
    loading ? `${ids}-loading` : null,
    notice !== undefined ? `${ids}-notice` : null,
    error !== undefined ? `${ids}-error` : null,
  ]
    .filter((id) => id !== null)
    .join(' ')

  return (
    <Field
      className="max-w-sm"
      data-invalid={error !== undefined || undefined}
      data-disabled={disabled || undefined}
    >
      <FieldLabel htmlFor={selectId}>{labels.label}</FieldLabel>
      <NativeSelect
        id={selectId}
        value={value}
        disabled={disabled}
        aria-invalid={error !== undefined || undefined}
        aria-describedby={describedBy === '' ? undefined : describedBy}
        onChange={(event) => onChange(event.target.value)}
      >
        {options.map((id) => (
          <NativeSelectOption key={id} value={id}>
            {modelLabel(id)}
          </NativeSelectOption>
        ))}
      </NativeSelect>
      {loading && (
        <FieldDescription id={`${ids}-loading`} role="status" aria-live="polite">
          {labels.loading}
        </FieldDescription>
      )}
      {notice !== undefined && <FieldDescription id={`${ids}-notice`}>{notice}</FieldDescription>}
      {error !== undefined && <FieldError id={`${ids}-error`}>{error}</FieldError>}
    </Field>
  )
}
