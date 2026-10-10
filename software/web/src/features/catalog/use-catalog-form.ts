import { useState } from 'react'
import { fieldErrors } from '@/lib/api-errors'
import { strings } from '@/lib/strings'
import { useSubmitGuard } from '@/lib/use-submit-guard'

export type CatalogDraft = Record<string, string | boolean>

// Cierre de la escritura que el formulario entrega a `mutate`: guarda el rechazo y suelta el candado.
export type WriteCallbacks = { onError: (error: unknown) => void; onSettled: () => void }

// Estado de un formulario del catálogo, de alta o de edición: obligatorios locales sin espacios de los
// extremos, candado síncrono contra el doble envío (design D4) y errores de campo del servidor junto
// al campo. Ante cualquier rechazo el borrador se conserva.
export function useCatalogForm<D extends CatalogDraft>(initial: D, required: readonly (keyof D & string)[]) {
  const guard = useSubmitGuard()
  const [draft, setDraft] = useState(initial)
  const [localErrors, setLocalErrors] = useState<Partial<Record<string, string>>>({})
  const [submitError, setSubmitError] = useState<unknown>(null)
  const serverErrors = fieldErrors(submitError)
  const fields = Object.keys(initial)

  const submit = (write: (draft: D, callbacks: WriteCallbacks) => void) => {
    const found: Partial<Record<string, string>> = Object.fromEntries(
      required.filter((key) => String(draft[key]).trim() === '').map((key) => [key, strings.common.required]),
    )
    setLocalErrors(found)
    if (Object.keys(found).length > 0) return
    guard((release) => {
      setSubmitError(null)
      write(draft, { onError: (error) => setSubmitError(error), onSettled: release })
    })
  }

  return {
    draft,
    set: <K extends keyof D>(key: K, value: D[K]) => setDraft((current) => ({ ...current, [key]: value })),
    errorOf: (key: keyof D & string): string | undefined => localErrors[key] ?? serverErrors[key],
    submitError,
    // El rechazo general se muestra si ningún campo lleva su propio mensaje.
    showGeneralError: submitError !== null && !fields.some((key) => serverErrors[key] !== undefined),
    reset: () => {
      setDraft(initial)
      setLocalErrors({})
      setSubmitError(null)
    },
    submit,
  }
}

// Texto obligatorio sin espacios de los extremos; opcional en blanco → `null`.
export const trimmed = (value: string) => value.trim()
export const optional = (value: string) => (value.trim() === '' ? null : value.trim())
