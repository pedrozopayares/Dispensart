import { Field, FieldLabel } from '@/components/ui/field'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'

export type FilterOption = { value: number; label: string }

type SelectFilterProps = {
  id: string
  label: string
  // Primera opción: sin filtro.
  allLabel: string
  options: readonly FilterOption[]
  value: number | undefined
  onChange: (value: number | undefined) => void
  disabled?: boolean
  // Texto de la primera opción cuando el filtro está deshabilitado.
  disabledLabel?: string
}

// Filtro por identificador con `select` nativo: teclado y lectores de pantalla sin trabajo extra.
export function SelectFilter({
  id,
  label,
  allLabel,
  options,
  value,
  onChange,
  disabled = false,
  disabledLabel,
}: SelectFilterProps) {
  return (
    <Field className="w-auto min-w-52" data-disabled={disabled || undefined}>
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <NativeSelect
        id={id}
        className="w-full"
        value={value === undefined ? '' : String(value)}
        disabled={disabled}
        onChange={(event) => {
          const raw = event.target.value
          onChange(raw === '' ? undefined : Number(raw))
        }}
      >
        <NativeSelectOption value="">
          {disabled && disabledLabel !== undefined ? disabledLabel : allLabel}
        </NativeSelectOption>
        {options.map((option) => (
          <NativeSelectOption key={option.value} value={option.value}>
            {option.label}
          </NativeSelectOption>
        ))}
      </NativeSelect>
    </Field>
  )
}
