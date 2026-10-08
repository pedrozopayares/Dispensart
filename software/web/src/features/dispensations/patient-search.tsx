import { useId, useState, type KeyboardEvent } from 'react'
import { EmptyState } from '@/components/empty-state'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { usePatientSearch } from '@/features/dispensations/queries'
import type { PatientSummary } from '@/lib/api-types'
import { strings } from '@/lib/strings'
import { cn } from '@/lib/utils'

const MIN_TERM = 3
const MAX_TERM = 50

// Búsqueda de paciente con teclado (dispensation-screen "Búsqueda de paciente"): Enter busca, las
// flechas recorren la lista (`aria-activedescendant`, el foco no deja el campo) y Enter abre la ficha.
// El término vive solo en memoria: nunca en la URL ni en almacenamiento del navegador (RN-10).
export function PatientSearch({ onSelect }: { onSelect: (patientId: number) => void }) {
  const ids = useId()
  const [text, setText] = useState('')
  const [term, setTerm] = useState<string | null>(null)
  const [tooShort, setTooShort] = useState(false)
  const [active, setActive] = useState(-1)
  const [open, setOpen] = useState(false)
  const search = usePatientSearch(term)
  const results = open && search.isSuccess ? search.data : []

  const optionId = (index: number) => `${ids}-option-${index}`

  const choose = (patient: PatientSummary) => {
    setOpen(false)
    setActive(-1)
    onSelect(patient.id)
  }

  const submit = () => {
    if (active >= 0 && results[active] !== undefined) {
      choose(results[active])
      return
    }
    const next = text.trim()
    if (next.length < MIN_TERM || next.length > MAX_TERM) {
      setTooShort(true)
      return
    }
    setTooShort(false)
    setOpen(true)
    if (next === term) void search.refetch()
    else setTerm(next)
  }

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (results.length === 0) return
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActive((index) => Math.min(index + 1, results.length - 1))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActive((index) => Math.max(index - 1, 0))
    } else if (event.key === 'Escape') {
      setActive(-1)
    }
  }

  return (
    <div className="flex flex-col gap-3">
      <form
        noValidate
        className="flex flex-wrap items-end gap-3"
        onSubmit={(event) => {
          event.preventDefault()
          submit()
        }}
      >
        <Field className="w-full max-w-md" data-invalid={tooShort || undefined}>
          <FieldLabel htmlFor={`${ids}-term`}>{strings.dispensation.search.label}</FieldLabel>
          <Input
            id={`${ids}-term`}
            autoFocus
            autoComplete="off"
            maxLength={MAX_TERM}
            role="combobox"
            aria-autocomplete="list"
            aria-expanded={results.length > 0}
            aria-controls={`${ids}-results`}
            aria-activedescendant={active >= 0 ? optionId(active) : undefined}
            aria-invalid={tooShort || undefined}
            value={text}
            onChange={(event) => {
              setText(event.target.value)
              setActive(-1)
            }}
            onKeyDown={onKeyDown}
          />
          {tooShort ? (
            <FieldError>{strings.dispensation.search.tooShort}</FieldError>
          ) : (
            <FieldDescription>{strings.dispensation.search.hint}</FieldDescription>
          )}
        </Field>
        <Button type="submit" variant="secondary">
          {strings.dispensation.search.submit}
        </Button>
      </form>

      {open && search.isPending && search.isFetching && (
        <LoadingState label={strings.dispensation.search.loading} />
      )}
      {open && search.isError && (
        <ErrorMessage
          error={search.error}
          retrying={search.isFetching}
          onRetry={() => void search.refetch()}
        />
      )}
      {open && search.isSuccess && search.data.length === 0 && (
        <EmptyState message={strings.dispensation.search.empty} />
      )}
      <ul
        id={`${ids}-results`}
        role="listbox"
        aria-label={strings.dispensation.search.results}
        className={cn('flex flex-col rounded-md border', results.length === 0 && 'hidden')}
      >
        {results.map((patient, index) => (
          <li
            key={patient.id}
            id={optionId(index)}
            role="option"
            aria-selected={index === active}
            className={cn(
              'flex cursor-pointer gap-3 px-3 py-2 text-sm',
              index === active && 'bg-accent text-accent-foreground',
            )}
            onMouseDown={(event) => event.preventDefault()}
            onClick={() => choose(patient)}
          >
            <span className="font-mono">
              {patient.document_type} {patient.document_number}
            </span>
            <span>{patient.full_name}</span>
          </li>
        ))}
      </ul>
    </div>
  )
}
