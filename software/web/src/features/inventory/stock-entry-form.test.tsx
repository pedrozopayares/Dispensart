import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import { setXsrfCookie } from '@/test/fake-api'
import { json } from '@/test/http'
import { catalogRoutes, lots, noAlertsRoute, stockRow, warehouses } from '@/test/fixtures'
import { renderAs } from '@/test/render'

// S18 — inventory-screen › "Registro de ingreso de lote".

const s = strings.inventory.entry
const [central] = warehouses

// Escrituras con cookie XSRF ya emitida: la prueba no depende de /sanctum/csrf-cookie.
const baseRoutes = () => {
  setXsrfCookie('token-1')
  return {
    ...catalogRoutes(),
    ...noAlertsRoute(),
    'GET /api/stock': () => json(200, { data: [stockRow(1, central, lots[0], 40)] }),
  }
}

const entryForm = () => screen.getByRole('form', { name: s.title })

// Movimiento `entrada` como lo devuelve la API.
const createdMovement = {
  data: {
    id: 900,
    type: 'entrada',
    quantity: 25,
    balance_after: 25,
    reason: 'Factura FAC-1',
    created_at: '2026-10-14T10:00:00-05:00',
    warehouse: { id: 1, code: 'FC', name: 'Farmacia Central' },
    product: { id: 10, code: 'ACE500', name: 'Acetaminofén 500 mg', is_controlled: false },
    lot: { id: 555, lot_code: 'ACE-NEW', expires_on: '2028-01-31', is_expired: false },
    user: { id: 2, name: 'Regente' },
  },
}

async function fillForm() {
  const form = within(entryForm())
  await form.findByRole('option', { name: 'Farmacia Central' })
  fireEvent.change(form.getByLabelText(s.warehouse), { target: { value: '1' } })
  fireEvent.change(form.getByLabelText(s.product), { target: { value: '10' } })
  fireEvent.change(form.getByLabelText(s.lotCode), { target: { value: ' ACE-NEW ' } })
  fireEvent.change(form.getByLabelText(s.expiresOn), { target: { value: '2028-01-31' } })
  fireEvent.change(form.getByLabelText(s.quantity), { target: { value: '25' } })
  fireEvent.change(form.getByLabelText(s.reason), { target: { value: 'Factura FAC-1' } })
}

const submit = () => fireEvent.click(within(entryForm()).getByRole('button', { name: s.submit }))

describe('formulario Registrar ingreso', () => {
  it('Regente ve el formulario con sus seis campos', async () => {
    renderAs('regente_farmacia', '/inventory', baseRoutes())

    const form = within(await screen.findByRole('form', { name: s.title }))
    for (const label of [s.warehouse, s.product, s.lotCode, s.expiresOn, s.quantity, s.reason]) {
      expect(form.getByLabelText(label)).toBeInTheDocument()
    }
    expect(form.getByLabelText(s.expiresOn)).toHaveAttribute('type', 'date')
  })

  it.each(['auxiliar_farmacia', 'auditor'] as const)('Otros roles sin formulario: %s', async (role) => {
    renderAs(role, '/inventory', baseRoutes())

    await screen.findByRole('table')
    expect(screen.queryByRole('form', { name: s.title })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: s.submit })).not.toBeInTheDocument()
  })

  it('Ingreso exitoso: envía los seis campos, confirma, limpia y vuelve a pedir existencias, kardex y alertas', async () => {
    const { api } = renderAs('regente_farmacia', '/inventory', {
      ...baseRoutes(),
      'POST /api/stock-entries': () => json(201, createdMovement),
    })
    await screen.findByRole('table')
    await fillForm()
    const stockCalls = api.requestsTo('GET', '/api/stock').length
    const alertCalls = api.requestsTo('GET', '/api/alerts').length

    submit()

    expect(
      await screen.findByText('Ingreso registrado: 25 unidades del lote ACE-NEW en Farmacia Central.'),
    ).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/stock-entries')).toHaveLength(1)
    expect(api.requestsTo('POST', '/api/stock-entries')[0]?.body).toEqual({
      warehouse_id: 1,
      product_id: 10,
      lot_code: 'ACE-NEW',
      expires_on: '2028-01-31',
      quantity: 25,
      reason: 'Factura FAC-1',
    })
    expect(within(entryForm()).getByLabelText(s.lotCode)).toHaveValue('')
    expect(within(entryForm()).getByLabelText(s.warehouse)).toHaveValue('')
    await waitFor(() => {
      expect(api.requestsTo('GET', '/api/stock').length).toBeGreaterThan(stockCalls)
      expect(api.requestsTo('GET', '/api/alerts').length).toBeGreaterThan(alertCalls)
    })
  })

  it('Campos obligatorios vacíos: cada campo muestra su mensaje y no se envía nada', async () => {
    const { api } = renderAs('regente_farmacia', '/inventory', baseRoutes())
    await screen.findByRole('form', { name: s.title })

    submit()

    expect(within(entryForm()).getAllByText(strings.common.required)).toHaveLength(6)
    expect(api.requestsTo('POST', '/api/stock-entries')).toHaveLength(0)
  })

  it('Rechazo de la API lot_expired: mensaje general y borrador conservado', async () => {
    renderAs('regente_farmacia', '/inventory', {
      ...baseRoutes(),
      'POST /api/stock-entries': () => json(422, { code: 'lot_expired', message: 'x' }),
    })
    await screen.findByRole('table')
    await fillForm()

    submit()

    expect(await within(entryForm()).findByText(strings.errors.lotExpired)).toBeInTheDocument()
    expect(within(entryForm()).getByLabelText(s.lotCode)).toHaveValue(' ACE-NEW ')
    expect(within(entryForm()).getByLabelText(s.quantity)).toHaveValue('25')
  })

  it('Rechazo de la API validation_failed en expires_on: mensaje junto al vencimiento', async () => {
    const mismatch = 'El lote ya existe con otra fecha de vencimiento.'
    renderAs('regente_farmacia', '/inventory', {
      ...baseRoutes(),
      'POST /api/stock-entries': () =>
        json(422, { code: 'validation_failed', message: 'x', errors: { expires_on: [mismatch] } }),
    })
    await screen.findByRole('table')
    await fillForm()

    submit()

    const field = within(entryForm()).getByLabelText(s.expiresOn)
    expect(await within(entryForm()).findByText(mismatch)).toBeInTheDocument()
    expect(field).toHaveAttribute('aria-invalid', 'true')
    expect(field).toHaveValue('2028-01-31')
  })
})
