import { CircleCheckIcon } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'

// Confirmación de una escritura, anunciada sin interrumpir (región de estado, no de alerta).
export function SuccessNotice({ message }: { message: string }) {
  return (
    <Alert role="status" aria-live="polite">
      <CircleCheckIcon aria-hidden="true" />
      <AlertDescription>{message}</AlertDescription>
    </Alert>
  )
}
