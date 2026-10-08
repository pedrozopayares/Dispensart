import { useMutation } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import { AppProviders } from '@/app/providers'
import { ErrorMessage } from '@/components/error-message'
import { SubmitButton } from '@/components/submit-button'
import { Field, FieldError, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { apiRequest } from '@/lib/api'
import { fieldErrors } from '@/lib/api-errors'
import { strings } from '@/lib/strings'
import { useSubmitGuard } from '@/lib/use-submit-guard'
import { deferred, json, serveApi, setXsrfCookie } from '@/test/http'

// Tarea 1.5 — operator-workspace › "Bloqueo del doble envío" y «Mensajes de error por código: Error de
// campo junto al campo» (design D4, D5). Formulario de prueba sobre una escritura real del contrato
// (POST /api/stock-adjustments); la red se simula en el borde HTTP.

const SUBMIT = 'Registrar ajuste'
const SUBMITTING = 'Registrando…'

function AdjustmentProbe() {
  const [reason, setReason] = useState('')
  const guard = useSubmitGuard()
  const mutation = useMutation({
    mutationFn: (body: { reason: string }) => apiRequest('POST', '/stock-adjustments', body),
  })
  const errors = fieldErrors(mutation.error)

  return (
    <form
      onSubmit={(event) => {
        event.preventDefault()
        guard((release) => mutation.mutate({ reason }, { onSettled: release }))
      }}
    >
      <Field data-invalid={errors.reason !== undefined || undefined}>
        <FieldLabel htmlFor="reason">Motivo</FieldLabel>
        <Input id="reason" value={reason} onChange={(event) => setReason(event.target.value)} />
        {errors.reason !== undefined && <FieldError>{errors.reason}</FieldError>}
      </Field>
      {mutation.isError && <ErrorMessage error={mutation.error} />}
      <SubmitButton pending={mutation.isPending} pendingLabel={SUBMITTING}>
        {SUBMIT}
      </SubmitButton>
    </form>
  )
}

function renderProbe() {
  setXsrfCookie('token-1')
  render(
    <AppProviders>
      <AdjustmentProbe />
    </AppProviders>,
  )
}

describe('bloqueo del doble envío', () => {
  it('Doble clic produce una sola petición y el botón queda deshabilitado con su progreso', async () => {
    const pending = deferred<Response>()
    const api = serveApi({ 'POST /api/stock-adjustments': () => pending.promise })
    renderProbe()

    const button = screen.getByRole('button', { name: SUBMIT })
    fireEvent.click(button)
    fireEvent.click(button)

    const busy = await screen.findByRole('button', { name: SUBMITTING })
    expect(busy).toBeDisabled()
    expect(api.requestsTo('POST', '/api/stock-adjustments')).toHaveLength(1)

    pending.resolve(json(201, { data: { id: 1 } }))
    expect(await screen.findByRole('button', { name: SUBMIT })).toBeEnabled()
    expect(api.requestsTo('POST', '/api/stock-adjustments')).toHaveLength(1)
  })

  it('Enter repetido en un formulario envía una sola petición', async () => {
    const pending = deferred<Response>()
    const api = serveApi({ 'POST /api/stock-adjustments': () => pending.promise })
    renderProbe()

    const reason = screen.getByLabelText('Motivo')
    fireEvent.change(reason, { target: { value: 'Rotura' } })
    fireEvent.submit(reason.closest('form')!)
    fireEvent.submit(reason.closest('form')!)

    await screen.findByRole('button', { name: SUBMITTING })
    expect(api.requestsTo('POST', '/api/stock-adjustments')).toHaveLength(1)
    pending.resolve(json(201, { data: { id: 1 } }))
    await screen.findByRole('button', { name: SUBMIT })
  })

  it('Botón rehabilitado tras un rechazo: conserva lo escrito y muestra el mensaje del código', async () => {
    const api = serveApi({
      'POST /api/stock-adjustments': [
        () => json(409, { code: 'insufficient_stock', message: 'x' }),
        () => json(201, { data: { id: 1 } }),
      ],
    })
    renderProbe()
    fireEvent.change(screen.getByLabelText('Motivo'), { target: { value: 'Rotura' } })

    fireEvent.click(screen.getByRole('button', { name: SUBMIT }))

    expect(await screen.findByRole('alert')).toHaveTextContent(strings.errors.insufficientStockGeneric)
    expect(screen.getByRole('button', { name: SUBMIT })).toBeEnabled()
    expect(screen.getByLabelText('Motivo')).toHaveValue('Rotura')
    // El candado se liberó: un segundo envío sí sale.
    fireEvent.click(screen.getByRole('button', { name: SUBMIT }))
    await waitFor(() => expect(api.requestsTo('POST', '/api/stock-adjustments')).toHaveLength(2))
  })

  it('Error de campo junto al campo: el mensaje de errors.reason junto a "Motivo"', async () => {
    serveApi({
      'POST /api/stock-adjustments': () =>
        json(422, {
          code: 'validation_failed',
          message: 'x',
          errors: { reason: ['El motivo no puede estar vacío.'] },
        }),
    })
    renderProbe()
    fireEvent.change(screen.getByLabelText('Motivo'), { target: { value: '   ' } })

    fireEvent.click(screen.getByRole('button', { name: SUBMIT }))

    const message = await screen.findByText('El motivo no puede estar vacío.')
    expect(message.closest('[data-slot="field"]')).toContainElement(screen.getByLabelText('Motivo'))
    expect(screen.getByLabelText('Motivo')).toHaveValue('   ')
  })
})
