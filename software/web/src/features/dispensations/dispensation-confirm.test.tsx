import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { PreviewRequest } from '@/lib/api-types'
import { strings } from '@/lib/strings'
import {
  confirmButton,
  controlledPrescription,
  controlledPreview,
  dispensationFrom,
  dispensationRoutes,
  expectNoRequest,
  fefoPreview,
  item,
  openPatient,
  plainPrescription,
  preview,
  previewDispensation,
  previewItem,
  prescription,
  record,
  searchInput,
} from '@/test/dispensation-fixtures'
import { deferred, json, networkError, type RecordedRequest } from '@/test/http'
import { renderAs } from '@/test/render'

// Tareas 2.4–2.6 — dispensation-screen › "Vista previa de lotes FEFO", "Coautorización de control
// especial", "Confirmación idempotente"; operator-workspace › «Almacenamiento del navegador vacío de
// pacientes». La red se simula en el borde HTTP; las claves se leen de cada petición registrada.

const s = strings.dispensation
const PREVIEW = 'POST /api/dispensations/preview'
const DISPENSE = 'POST /api/dispensations'

const created = (body = fefoPreview, headers: Record<string, string> = {}) =>
  json(201, { data: dispensationFrom(body) }, headers)
const rejected = (status: number, code: string, extra: Record<string, unknown> = {}) =>
  json(status, { code, message: 'mensaje del servidor', ...extra })

const keyOf = (request: RecordedRequest) => request.headers['idempotency-key']
const quantityField = (product = 'Acetaminofén 500 mg') =>
  screen.getByLabelText(s.form.quantity.replace('{product}', product))
const authorizerEmail = () => screen.getByLabelText(s.authorizer.email)
const authorizerPassword = () => screen.getByLabelText(s.authorizer.password)

async function ready(routes: Parameters<typeof dispensationRoutes>[1], source = plainPrescription) {
  const view = renderAs('auxiliar_farmacia', '/dispensations', dispensationRoutes(record([source]), routes))
  await openPatient()
  return view
}

function fillAuthorizer(email = 'regente@dispensart.test', password = 'secreto-1') {
  fireEvent.change(authorizerEmail(), { target: { value: email } })
  fireEvent.change(authorizerPassword(), { target: { value: password } })
}

describe('Dispensación › vista previa FEFO', () => {
  it('Lotes en orden FEFO: L1 con 3 y su vencimiento, luego L2 con 2; Confirmar habilitado', async () => {
    const { api } = await ready({ [PREVIEW]: () => json(200, { data: fefoPreview }) })

    await previewDispensation(7)

    const group = screen.getByRole('group', { name: 'Acetaminofén 500 mg' })
    const rows = within(group).getAllByRole('row').slice(1)
    expect(rows.map((row) => within(row).getAllByRole('cell').map((cell) => cell.textContent))).toEqual([
      ['L1', '2027-01-31', '3'],
      ['L2', '2027-06-30', '2'],
    ])
    expect(confirmButton()).toBeEnabled()
    expect(api.requestsTo('POST', '/api/dispensations/preview')[0].body).toEqual({
      prescription_id: 7,
      warehouse_id: 1,
      items: [{ prescription_item_id: 70, quantity: 5 }],
    })
  })

  it('Unidades vencidas excluidas: aviso con la cantidad', async () => {
    const withExpired = preview(plainPrescription, [
      previewItem(plainPrescription.items[0], 5, [{ lot_id: 100, lot_code: 'L1', expires_on: '2027-01-31', quantity: 5 }], {
        expired_excluded_quantity: 4,
      }),
    ])
    await ready({ [PREVIEW]: () => json(200, { data: withExpired }) })

    await previewDispensation(7)

    expect(screen.getByText('4 unidades en lotes vencidos no se usan.')).toBeInTheDocument()
  })

  it('Faltante en la vista previa: mensaje por producto y Confirmar deshabilitado', async () => {
    const short = preview(plainPrescription, [
      previewItem(plainPrescription.items[0], 5, [{ lot_id: 100, lot_code: 'L1', expires_on: '2027-01-31', quantity: 2 }]),
    ])
    await ready({ [PREVIEW]: () => json(200, { data: short }) })

    await previewDispensation(7)

    expect(
      screen.getByText('Stock insuficiente: Acetaminofén 500 mg necesita 5 y hay 2 disponibles.'),
    ).toBeInTheDocument()
    expect(confirmButton()).toBeDisabled()
  })

  it('Cantidad mayor que el pendiente: error junto al campo y ninguna petición', async () => {
    const { api } = await ready({ [PREVIEW]: () => json(200, { data: fefoPreview }) })
    fireEvent.click(screen.getByRole('button', { name: s.prescription.dispense }))
    const warehouse = await screen.findByLabelText(s.form.warehouse)
    await within(warehouse).findByRole('option', { name: 'Farmacia Central' })
    fireEvent.change(warehouse, { target: { value: '1' } })

    fireEvent.change(quantityField(), { target: { value: '8' } })
    fireEvent.click(screen.getByRole('button', { name: s.form.preview }))

    expect(await screen.findByText('La cantidad no puede superar lo pendiente (5).')).toBeInTheDocument()
    expect(quantityField()).toHaveAttribute('aria-invalid', 'true')
    expectNoRequest(api.requests, 'POST', '/api/dispensations/preview')
  })

  it('Sin bodega o sin cantidades: aviso y ninguna petición', async () => {
    const { api } = await ready({ [PREVIEW]: () => json(200, { data: fefoPreview }) })
    fireEvent.click(screen.getByRole('button', { name: s.prescription.dispense }))
    await screen.findByLabelText(s.form.warehouse)

    fireEvent.click(screen.getByRole('button', { name: s.form.preview }))
    expect(await screen.findByText(s.form.warehouseRequired)).toBeInTheDocument()

    const warehouse = screen.getByLabelText(s.form.warehouse)
    await within(warehouse).findByRole('option', { name: 'Farmacia Central' })
    fireEvent.change(warehouse, { target: { value: '1' } })
    fireEvent.change(quantityField(), { target: { value: '0' } })
    fireEvent.click(screen.getByRole('button', { name: s.form.preview }))

    expect(await screen.findByText(s.form.quantitiesRequired)).toBeInTheDocument()
    expect(screen.queryByText(s.form.warehouseRequired)).not.toBeInTheDocument()
    expectNoRequest(api.requests, 'POST', '/api/dispensations/preview')
  })

  it('Vista previa desactualizada: cambiar la cantidad borra la asignación y deshabilita Confirmar', async () => {
    await ready({ [PREVIEW]: () => json(200, { data: fefoPreview }) })
    await previewDispensation(7)
    expect(confirmButton()).toBeEnabled()

    fireEvent.change(quantityField(), { target: { value: '4' } })

    expect(screen.queryByRole('heading', { name: s.preview.title })).not.toBeInTheDocument()
    expect(screen.queryByText('L1')).not.toBeInTheDocument()
    expect(confirmButton()).toBeDisabled()
    expect(screen.getByText(s.form.previewNeeded)).toBeInTheDocument()
  })

  it.each([
    ['prescription_expired', strings.errors.prescriptionExpired, 'vencida'],
    ['prescription_exhausted', strings.errors.prescriptionExhausted, 'agotada'],
    ['exceeds_prescription', strings.errors.exceedsPrescription, 'vigente'],
  ] as const)('Prescripción que cambió de estado (%s): mensaje y ficha recargada', async (code, message, status) => {
    const before = record([plainPrescription])
    const after = record([prescription(7, status, [item(70, false, 5, status === 'vigente' ? 3 : 0)])])
    const { api } = renderAs(
      'auxiliar_farmacia',
      '/dispensations',
      dispensationRoutes(before, {
        'GET /api/patients/1': [() => json(200, { data: before }), () => json(200, { data: after })],
        [PREVIEW]: () => rejected(422, code),
      }),
    )
    await openPatient()
    fireEvent.click(screen.getByRole('button', { name: s.prescription.dispense }))
    const warehouse = await screen.findByLabelText(s.form.warehouse)
    await within(warehouse).findByRole('option', { name: 'Farmacia Central' })
    fireEvent.change(warehouse, { target: { value: '1' } })
    fireEvent.click(screen.getByRole('button', { name: s.form.preview }))

    expect(await screen.findByText(message)).toBeInTheDocument()
    await waitFor(() => expect(api.requestsTo('GET', '/api/patients/1')).toHaveLength(2))
    expect(document.body.textContent).not.toContain(code)
  })
})

describe('Dispensación › coautorización de control especial', () => {
  async function controlledReady(routes: Parameters<typeof dispensationRoutes>[1]) {
    const view = await ready({ [PREVIEW]: () => json(200, { data: controlledPreview }), ...routes }, controlledPrescription)
    await previewDispensation(8)
    return view
  }

  it('Campos de autorizador visibles con el aviso de control especial', async () => {
    await controlledReady({})

    expect(screen.getByText(s.authorizer.notice)).toBeInTheDocument()
    expect(authorizerEmail()).toBeInTheDocument()
    expect(authorizerPassword()).toHaveAttribute('type', 'password')
  })

  // Hallazgo del recorrido 7.1: el navegador rellenó el correo del autorizador con el del usuario en
  // sesión. Los campos llegan vacíos y piden al navegador no autocompletarlos con credenciales guardadas.
  it('Campos del autorizador sin autorrelleno del navegador: vacíos, correo "off" y contraseña "new-password"', async () => {
    await controlledReady({})

    expect(authorizerEmail()).toHaveValue('')
    expect(authorizerPassword()).toHaveValue('')
    expect(authorizerEmail()).toHaveAttribute('autocomplete', 'off')
    expect(authorizerPassword()).toHaveAttribute('autocomplete', 'new-password')
  })

  it('Sin control especial no se piden y la confirmación no lleva credenciales', async () => {
    const { api } = await ready({
      [PREVIEW]: () => json(200, { data: fefoPreview }),
      [DISPENSE]: () => created(),
    })
    await previewDispensation(7)

    expect(screen.queryByLabelText(s.authorizer.email)).not.toBeInTheDocument()
    fireEvent.click(confirmButton())

    await screen.findByText(s.success.title)
    const body = api.requestsTo('POST', '/api/dispensations')[0].body as Record<string, unknown>
    expect(body).not.toHaveProperty('authorizer_email')
    expect(body).not.toHaveProperty('authorizer_password')
  })

  it('Campos del autorizador vacíos: "Este campo es obligatorio." y ninguna petición', async () => {
    const { api } = await controlledReady({ [DISPENSE]: () => created(controlledPreview) })

    fireEvent.change(authorizerEmail(), { target: { value: 'regente@dispensart.test' } })
    fireEvent.click(confirmButton())

    expect(await screen.findByText(strings.common.required)).toBeInTheDocument()
    expect(authorizerPassword()).toHaveAttribute('aria-invalid', 'true')
    expect(authorizerEmail()).not.toHaveAttribute('aria-invalid')
    expectNoRequest(api.requests, 'POST', '/api/dispensations')
  })

  it('Autorizador inválido: mensaje, conserva el correo y la vista previa, vacía la contraseña', async () => {
    const { api } = await controlledReady({ [DISPENSE]: () => rejected(422, 'invalid_authorizer') })
    fillAuthorizer()

    fireEvent.click(confirmButton())

    expect(await screen.findByText(strings.errors.invalidAuthorizer)).toBeInTheDocument()
    expect(authorizerEmail()).toHaveValue('regente@dispensart.test')
    expect(authorizerPassword()).toHaveValue('')
    expect(screen.getByText('MOR-C3')).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/dispensations')[0].body).toMatchObject({
      authorizer_email: 'regente@dispensart.test',
      authorizer_password: 'secreto-1',
    })
  })

  it('Autorizador igual al dispensador: mensaje y contraseña vaciada', async () => {
    await controlledReady({ [DISPENSE]: () => rejected(422, 'authorizer_must_differ') })
    fillAuthorizer('auxiliar_farmacia@dispensart.test')

    fireEvent.click(confirmButton())

    expect(await screen.findByText(strings.errors.authorizerMustDiffer)).toBeInTheDocument()
    expect(authorizerPassword()).toHaveValue('')
  })

  it('Autorización requerida por el servidor: aparecen los campos con su mensaje', async () => {
    const { api } = await ready({
      [PREVIEW]: () => json(200, { data: fefoPreview }),
      [DISPENSE]: () => rejected(422, 'authorization_required'),
    })
    await previewDispensation(7)
    expect(screen.queryByLabelText(s.authorizer.email)).not.toBeInTheDocument()

    fireEvent.click(confirmButton())

    expect(await screen.findByText(strings.errors.authorizationRequired)).toBeInTheDocument()
    expect(authorizerEmail()).toBeInTheDocument()
    expect(authorizerPassword()).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/dispensations')).toHaveLength(1)
  })

  it('Demasiados intentos del autorizador: mensaje y contraseña vaciada', async () => {
    await controlledReady({
      [DISPENSE]: () => json(429, { code: 'too_many_attempts', message: 'x' }, { 'Retry-After': '60' }),
    })
    fillAuthorizer()

    fireEvent.click(confirmButton())

    expect(await screen.findByText(strings.errors.tooManyAttempts)).toBeInTheDocument()
    expect(authorizerPassword()).toHaveValue('')
  })
})

describe('Dispensación › confirmación idempotente', () => {
  it('Dispensación exitosa: resumen con lote, vencimiento y cantidad; inventario y kardex invalidados; almacenamiento sin datos del paciente', async () => {
    const { api, client } = await ready({
      [PREVIEW]: () => json(200, { data: fefoPreview }),
      [DISPENSE]: () => created(),
    })
    client.setQueryData(['stock', {}], [])
    client.setQueryData(['kardex', {}], [])
    await previewDispensation(7)

    fireEvent.click(confirmButton())

    const title = await screen.findByText(s.success.title)
    const summary = title.closest<HTMLElement>('[role="status"]')!
    const rows = within(summary).getAllByRole('row').slice(1)
    expect(rows.map((row) => within(row).getAllByRole('cell').map((cell) => cell.textContent))).toEqual([
      ['Acetaminofén 500 mg', 'L1', '2027-01-31', '3'],
      ['Acetaminofén 500 mg', 'L2', '2027-06-30', '2'],
    ])
    expect(within(summary).getByRole('button', { name: s.success.newDispensation })).toBeInTheDocument()
    expect(client.getQueryState(['stock', {}])?.isInvalidated).toBe(true)
    expect(client.getQueryState(['kardex', {}])?.isInvalidated).toBe(true)
    expect(keyOf(api.requestsTo('POST', '/api/dispensations')[0])).toMatch(/^[A-Za-z0-9_-]{16,128}$/)
    const stored = JSON.stringify({ ...localStorage }) + JSON.stringify({ ...sessionStorage })
    for (const value of ['1000000001', 'Sintética', '3000000001', '1980-05-01']) {
      expect(stored).not.toContain(value)
    }
  })

  it('Doble clic en Confirmar: una sola petición y "Confirmando…" deshabilitado hasta la respuesta', async () => {
    const pending = deferred<Response>()
    const { api } = await ready({
      [PREVIEW]: () => json(200, { data: fefoPreview }),
      [DISPENSE]: () => pending.promise,
    })
    await previewDispensation(7)

    const button = confirmButton()
    fireEvent.click(button)
    fireEvent.click(button)

    const busy = await screen.findByRole('button', { name: s.confirming })
    expect(busy).toBeDisabled()
    expect(api.requestsTo('POST', '/api/dispensations')).toHaveLength(1)
    pending.resolve(created())
    expect(await screen.findByText(s.success.title)).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/dispensations')).toHaveLength(1)
  })

  it('Reintento tras fallo de red reutiliza la clave y el cuerpo', async () => {
    const { api } = await ready({
      [PREVIEW]: () => json(200, { data: fefoPreview }),
      [DISPENSE]: [networkError, () => created()],
    })
    await previewDispensation(7)
    fireEvent.click(confirmButton())

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent(strings.errors.network)
    fireEvent.click(within(alert).getByRole('button', { name: strings.common.retry }))

    expect(await screen.findByText(s.success.title)).toBeInTheDocument()
    const [first, second] = api.requestsTo('POST', '/api/dispensations')
    expect(keyOf(first)).toBeDefined()
    expect(keyOf(second)).toBe(keyOf(first))
    expect(second.body).toEqual(first.body)
  })

  it('Respuesta repetida tratada como éxito: un solo "Dispensación registrada", sin aviso de duplicado', async () => {
    await ready({
      [PREVIEW]: () => json(200, { data: fefoPreview }),
      [DISPENSE]: [networkError, () => created(fefoPreview, { 'Idempotent-Replayed': 'true' })],
    })
    await previewDispensation(7)
    fireEvent.click(confirmButton())
    fireEvent.click(within(await screen.findByRole('alert')).getByRole('button', { name: strings.common.retry }))

    expect(await screen.findAllByText(s.success.title)).toHaveLength(1)
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('Reintento tras autorizador corregido reutiliza la clave', async () => {
    const { api } = await ready(
      {
        [PREVIEW]: () => json(200, { data: controlledPreview }),
        [DISPENSE]: [() => rejected(422, 'invalid_authorizer'), () => created(controlledPreview)],
      },
      controlledPrescription,
    )
    await previewDispensation(8)
    fillAuthorizer('regente@dispensart.test', 'equivocada')
    fireEvent.click(confirmButton())
    await screen.findByText(strings.errors.invalidAuthorizer)

    fillAuthorizer('regente@dispensart.test', 'correcta')
    fireEvent.click(confirmButton())

    expect(await screen.findByText(s.success.title)).toBeInTheDocument()
    const [first, second] = api.requestsTo('POST', '/api/dispensations')
    expect(keyOf(second)).toBe(keyOf(first))
    expect((second.body as { authorizer_password: string }).authorizer_password).toBe('correcta')
  })

  it('Cambio de cantidad genera clave nueva tras un fallo de red', async () => {
    const reduced = preview(plainPrescription, [
      previewItem(plainPrescription.items[0], 3, [{ lot_id: 100, lot_code: 'L1', expires_on: '2027-01-31', quantity: 3 }]),
    ])
    const { api } = await ready({
      [PREVIEW]: ({ body }) =>
        json(200, { data: (body as PreviewRequest).items[0].quantity === 3 ? reduced : fefoPreview }),
      [DISPENSE]: [networkError, () => created(reduced)],
    })
    await previewDispensation(7)
    fireEvent.click(confirmButton())
    await screen.findByRole('alert')

    fireEvent.change(quantityField(), { target: { value: '3' } })
    fireEvent.click(screen.getByRole('button', { name: s.form.preview }))
    await screen.findByRole('heading', { name: s.preview.title })
    fireEvent.click(confirmButton())

    expect(await screen.findByText(s.success.title)).toBeInTheDocument()
    const [first, second] = api.requestsTo('POST', '/api/dispensations')
    expect(keyOf(second)).toBeDefined()
    expect(keyOf(second)).not.toBe(keyOf(first))
    expect(second.body).toEqual({ prescription_id: 7, warehouse_id: 1, items: [{ prescription_item_id: 70, quantity: 3 }] })
  })

  it('Nueva dispensación genera clave nueva y devuelve el foco a la búsqueda', async () => {
    const { api } = await ready({
      [PREVIEW]: () => json(200, { data: fefoPreview }),
      [DISPENSE]: () => created(),
    })
    await previewDispensation(7)
    fireEvent.click(confirmButton())
    fireEvent.click(await screen.findByRole('button', { name: s.success.newDispensation }))

    await waitFor(() => expect(searchInput()).toHaveFocus())
    expect(searchInput()).toHaveValue('')
    await openPatient()
    await previewDispensation(7)
    fireEvent.click(confirmButton())

    await screen.findByText(s.success.title)
    const [first, second] = api.requestsTo('POST', '/api/dispensations')
    expect(second.body).toEqual(first.body)
    expect(keyOf(second)).not.toBe(keyOf(first))
  })

  it('Stock agotado entre la vista previa y la confirmación: mensaje, Confirmar deshabilitado y "Recalcular asignación"', async () => {
    const { api } = await ready({
      [PREVIEW]: () => json(200, { data: fefoPreview }),
      [DISPENSE]: () =>
        rejected(409, 'insufficient_stock', {
          shortages: [{ prescription_item_id: 70, product_id: 10, requested: 5, available: 1 }],
        }),
    })
    await previewDispensation(7)
    fireEvent.click(confirmButton())

    expect(
      await screen.findByText('Stock insuficiente: Acetaminofén 500 mg necesita 5 y hay 1 disponibles.'),
    ).toBeInTheDocument()
    expect(confirmButton()).toBeDisabled()
    fireEvent.click(screen.getByRole('button', { name: s.recalculate }))

    await waitFor(() => expect(api.requestsTo('POST', '/api/dispensations/preview')).toHaveLength(2))
    await waitFor(() => expect(confirmButton()).toBeEnabled())
  })

  it('Clave reutilizada con otros datos: mensaje, clave descartada y vista previa nueva exigida', async () => {
    const { api } = await ready({
      [PREVIEW]: () => json(200, { data: fefoPreview }),
      [DISPENSE]: [() => rejected(422, 'idempotency_key_reused'), () => created()],
    })
    await previewDispensation(7)
    fireEvent.click(confirmButton())

    expect(await screen.findByText(strings.errors.idempotencyKeyReused)).toBeInTheDocument()
    expect(confirmButton()).toBeDisabled()
    expect(screen.getByText(s.form.previewNeeded)).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: s.form.preview }))
    await screen.findByRole('heading', { name: s.preview.title })
    fireEvent.click(confirmButton())

    await screen.findByText(s.success.title)
    const [first, second] = api.requestsTo('POST', '/api/dispensations')
    expect(keyOf(second)).not.toBe(keyOf(first))
  })

  it('Sesión del dispensador sin permiso: mensaje sin "Reintentar"', async () => {
    await ready({
      [PREVIEW]: () => json(200, { data: fefoPreview }),
      [DISPENSE]: () => rejected(403, 'forbidden'),
    })
    await previewDispensation(7)
    fireEvent.click(confirmButton())

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent(strings.errors.forbidden)
    expect(within(alert).queryByRole('button', { name: strings.common.retry })).not.toBeInTheDocument()
    expect(document.body.textContent).not.toContain('forbidden')
  })
})
