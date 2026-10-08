import { useId, useRef, useState, type KeyboardEvent } from 'react'
import { EmptyState } from '@/components/empty-state'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import { PageHeader } from '@/components/page-header'
import { SubmitButton } from '@/components/submit-button'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Textarea } from '@/components/ui/textarea'
import { AssistantAnswerCard, type AssistantEntry } from '@/features/assistant/assistant-answer'
import { useAskAssistant } from '@/features/assistant/queries'
import { fieldErrors } from '@/lib/api-errors'
import { format, strings } from '@/lib/strings'
import { useSubmitGuard } from '@/lib/use-submit-guard'

const labels = strings.assistant
// Límites de `AskAssistantRequest` en la API (3 a 500).
const MIN_QUESTION = 3
const MAX_QUESTION = 500
const HISTORY_SIZE = 10

// Error local de la pregunta, sin espacios de los extremos; `null` si es válida.
function questionError(question: string): string | null {
  if (question === '') return strings.common.required
  if (question.length < MIN_QUESTION) return labels.tooShort
  return null
}

// Pantalla Asistente (/assistant, toda sesión). El historial vive solo en el estado de esta pantalla:
// se pierde al salir, recargar o cerrar sesión, y nunca va a almacenamiento, URL ni consola, porque el
// usuario puede escribir datos de un paciente (RN-10, Ley 1581).
export function AssistantPage() {
  const ids = useId()
  const [text, setText] = useState('')
  const [localError, setLocalError] = useState<string | null>(null)
  const [submitError, setSubmitError] = useState<unknown>(null)
  const [history, setHistory] = useState<AssistantEntry[]>([])
  const nextId = useRef(0)
  const box = useRef<HTMLTextAreaElement>(null)
  const guard = useSubmitGuard()
  const ask = useAskAssistant()

  const submit = () => {
    const question = text.trim()
    const error = questionError(question)
    setLocalError(error)
    if (error !== null) return
    guard((release) => {
      setSubmitError(null)
      ask.mutate(question, {
        onSuccess: (answer) => {
          const entry = { id: nextId.current++, question, answer }
          setHistory((current) => [entry, ...current].slice(0, HISTORY_SIZE))
          setText('')
          box.current?.focus()
        },
        // La pregunta escrita se conserva ante cualquier rechazo.
        onError: (rejection) => setSubmitError(rejection),
        onSettled: release,
      })
    })
  }

  // Enter envía; Shift+Enter deja el salto de línea del navegador.
  const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key !== 'Enter' || event.shiftKey || event.nativeEvent.isComposing) return
    event.preventDefault()
    submit()
  }

  const message = localError ?? fieldErrors(submitError).question
  const boxId = `${ids}-question`
  const describedBy = `${ids}-counter ${ids}-privacy`

  return (
    <section className="flex w-full max-w-4xl flex-col gap-6">
      <PageHeader title={labels.title} description={labels.description} />
      <form
        noValidate
        className="flex flex-col gap-3"
        onSubmit={(event) => {
          event.preventDefault()
          submit()
        }}
      >
        <Field data-invalid={message !== undefined || undefined}>
          <FieldLabel htmlFor={boxId}>{labels.question}</FieldLabel>
          <Textarea
            id={boxId}
            ref={box}
            autoFocus
            rows={3}
            maxLength={MAX_QUESTION}
            aria-invalid={message !== undefined || undefined}
            aria-describedby={describedBy}
            value={text}
            onChange={(event) => setText(event.target.value.slice(0, MAX_QUESTION))}
            onKeyDown={onKeyDown}
          />
          {message !== undefined && <FieldError>{message}</FieldError>}
          <FieldDescription id={`${ids}-counter`} className="flex flex-wrap justify-between gap-2">
            <span>{labels.hint}</span>
            <span>{format(labels.counter, { n: String(text.length) })}</span>
          </FieldDescription>
          <FieldDescription id={`${ids}-privacy`}>{labels.privacy}</FieldDescription>
        </Field>
        <div>
          <SubmitButton pending={ask.isPending} pendingLabel={labels.submitting}>
            {labels.submit}
          </SubmitButton>
        </div>
      </form>

      <section aria-labelledby={`${ids}-examples`} className="flex flex-col gap-2">
        <h3 id={`${ids}-examples`} className="text-sm font-medium">
          {labels.examplesTitle}
        </h3>
        <ul className="flex flex-wrap gap-2">
          {labels.examples.map((example) => (
            <li key={example}>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-auto text-left whitespace-normal"
                disabled={ask.isPending}
                onClick={() => {
                  setText(example)
                  setLocalError(null)
                  box.current?.focus()
                }}
              >
                {example}
              </Button>
            </li>
          ))}
        </ul>
      </section>

      {ask.isPending && <LoadingState label={labels.pending} />}
      {submitError !== null && <ErrorMessage error={submitError} />}

      {history.length === 0 ? (
        <EmptyState message={labels.empty} />
      ) : (
        <section aria-labelledby={`${ids}-history`} className="flex flex-col gap-3">
          <h3 id={`${ids}-history`} className="text-lg font-semibold">
            {labels.history}
          </h3>
          <ol className="flex flex-col gap-3">
            {history.map((entry) => (
              <li key={entry.id}>
                <AssistantAnswerCard entry={entry} />
              </li>
            ))}
          </ol>
        </section>
      )}
    </section>
  )
}
