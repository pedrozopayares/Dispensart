import type { ComponentProps } from 'react'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'

type SubmitButtonProps = ComponentProps<typeof Button> & {
  pending: boolean
  pendingLabel: string
}

// Botón de escritura (design D4): deshabilitado y con texto de progreso mientras la petición está en
// curso. El candado síncrono contra pulsaciones en el mismo ciclo lo pone `useSubmitGuard`.
export function SubmitButton({
  pending,
  pendingLabel,
  disabled,
  children,
  type = 'submit',
  ...props
}: SubmitButtonProps) {
  return (
    <Button type={type} disabled={pending || disabled} aria-busy={pending || undefined} {...props}>
      {pending && <Spinner data-icon="inline-start" aria-hidden="true" />}
      {pending ? pendingLabel : children}
    </Button>
  )
}
