import { CircleAlertIcon } from 'lucide-react'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { describeError, hasCode, type DescribeOptions } from '@/lib/api-errors'
import { strings } from '@/lib/strings'

type ErrorMessageProps = {
  error: unknown
  // Repite la operación fallida; sin él no se ofrece "Reintentar".
  onRetry?: () => void
  retrying?: boolean
  describe?: DescribeOptions
}

// Error anunciado como alerta (design D5): siempre el texto del catálogo, nunca `error.message`.
// `forbidden` no ofrece reintentar: repetir no cambia la respuesta.
export function ErrorMessage({ error, onRetry, retrying = false, describe }: ErrorMessageProps) {
  const canRetry = onRetry !== undefined && !hasCode(error, 'forbidden')
  return (
    <Alert variant="destructive">
      <CircleAlertIcon aria-hidden="true" />
      <AlertDescription className="flex flex-col items-start gap-3">
        <p className="whitespace-pre-line">{describeError(error, describe)}</p>
        {canRetry && (
          <Button variant="outline" size="sm" disabled={retrying} onClick={onRetry}>
            {strings.common.retry}
          </Button>
        )}
      </AlertDescription>
    </Alert>
  )
}
