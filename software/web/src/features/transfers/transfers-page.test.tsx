import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import { lots, stockRow, warehouses } from '@/test/fixtures'
import { deferred, json, networkError } from '@/test/http'
import { renderAs } from '@/test/render'
import { summary, transfer, transferPage, withXsrf } from '@/test/transfer-fixtures'

// Tareas 3.1 y 3.3 — transfers-screen › "Listado de traslados" y "Creación de traslado".

type Routes = NonNullable<Parameters<typeof renderAs>[2]>

const s = strings.transfers
const f = s.form
const CREATE = 'POST /api/transfers'

const listOf = (...items: Parameters<typeof summary>[]) => () =>
  json(200, transferPage(items.map((item) => summary(...item))))

// Formulario de creación con catálogo y existencias del origen: L vigente con 8, L vencido con 4.
function formRoutes(extra: Routes = {}) {
  return withXsrf({
    'GET /api/transfers': listOf(),
    'GET /api/warehouses': () => json(200, { data: warehouses }),
    'GET /api/stock': () =>
      json(200, { data: [stockRow(1, warehouses[0], lots[0], 8), stockRow(2, warehouses[0], lots[1], 4)] }),
    'GET /api/transfers/12': () => json(200, { data: transfer() }),
    ...extra,
  })
}

async function openForm(routes = formRoutes()) {
  const view = renderAs('auxiliar_farmacia', '/transfers', routes)
  fireEvent.click(await screen.findByRole('button', { name: s.newTransfer }))
  // Bodegas cargadas: placeholder + 2 opciones.
  await waitFor(() => expect(within(screen.getByLabelText(f.origin)).getAllByRole('option')).toHaveLength(3))
  return view
}

const select = (label: string, value: string) =>
  fireEvent.change(screen.getByLabelText(label), { target: { value } })

async function fillValidDraft(quantity = '3') {
  select(f.origin, '1')
  select(f.destination, '2')
  fireEvent.click(screen.getByRole('button', { name: f.addLine }))
  const lot = screen.getByLabelText(f.lot.replace('{n}', '1'))
  await waitFor(() => expect(within(lot).getAllByRole('option')).toHaveLength(2))
  fireEvent.change(lot, { target: { value: '100' } })
  fireEvent.change(screen.getByLabelText(f.quantity.replace('{n}', '1')), { target: { value: quantity } })
}

const createButton = () => screen.getByRole('button', { name: f.submit })

describe('Traslados › listado', () => {
  it('Listado con estados en español: "Cargando traslados…" y luego cada estado en español', async () => {
    renderAs('auxiliar_farmacia', '/transfers', {
      'GET /api/transfers': listOf([3, 'EN_TRANSITO'], [2, 'RECIBIDO_PARCIAL'], [1, 'BORRADOR']),
    })

    expect(await screen.findByText(s.loading)).toBeInTheDocument()
    const table = await screen.findByRole('table')
    const statuses = within(table)
      .getAllByRole('row')
      .slice(1)
      .map((row) => within(row).getAllByRole('cell')[3].textContent)
    expect(statuses).toEqual(['En tránsito', 'Recibido parcial', 'Borrador'])
    expect(within(table).getByRole('link', { name: 'Traslado #3' })).toHaveAttribute('href', '/transfers/3')
    expect(document.body.textContent).not.toMatch(/EN_TRANSITO|RECIBIDO_PARCIAL|BORRADOR/)
  })

  it('Filtro por estado: pide solo "En tránsito" y vuelve a la página 1', async () => {
    const { api, router } = renderAs('auxiliar_farmacia', '/transfers?page=2', {
      'GET /api/transfers': ({ query }) =>
        json(200, transferPage(query.status === 'EN_TRANSITO' ? [summary(3, 'EN_TRANSITO')] : [summary(1, 'BORRADOR')], Number(query.page ?? 1), 2)),
    })
    await screen.findByRole('table')

    select(s.filters.status, 'EN_TRANSITO')

    expect(await screen.findByText('En tránsito', { selector: 'td *' })).toBeInTheDocument()
    expect(api.requestsTo('GET', '/api/transfers').map((request) => request.query)).toEqual([
      { page: '2' },
      { status: 'EN_TRANSITO', page: '1' },
    ])
    expect(router.state.location.search).toBe('?status=EN_TRANSITO')
  })

  it('Sin traslados: "No hay traslados para este filtro."', async () => {
    renderAs('auditor', '/transfers?status=ANULADO', { 'GET /api/transfers': listOf() })

    expect(await screen.findByText(s.empty)).toBeInTheDocument()
  })

  it('Fallo del listado: mensaje de red con "Reintentar" que repite la consulta', async () => {
    const { api } = renderAs('auxiliar_farmacia', '/transfers', {
      'GET /api/transfers': [networkError, listOf([1, 'BORRADOR'])],
    })

    expect(await screen.findByRole('alert')).toHaveTextContent(strings.errors.network)
    fireEvent.click(screen.getByRole('button', { name: strings.common.retry }))

    expect(await screen.findByRole('table')).toBeInTheDocument()
    expect(api.requestsTo('GET', '/api/transfers')).toHaveLength(2)
  })
})

describe('Traslados › creación', () => {
  it('Borrador creado: abre el detalle en "Borrador" con "Solicitar"', async () => {
    const { api, router } = await openForm(
      formRoutes({ [CREATE]: () => json(201, { data: transfer() }) }),
    )
    await fillValidDraft()

    fireEvent.click(createButton())

    expect(await screen.findByRole('heading', { name: 'Traslado #12' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/transfers/12')
    expect(screen.getByText('Borrador')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: s.actions.request })).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/transfers')[0].body).toEqual({
      origin_warehouse_id: 1,
      destination_warehouse_id: 2,
      lines: [{ lot_id: 100, quantity: 3 }],
    })
    expect(api.requestsTo('GET', '/api/stock')[0].query).toEqual({ warehouse_id: '1' })
  })

  it('Lotes vencidos fuera de la lista: el lote vencido con existencia no se ofrece', async () => {
    await openForm()
    select(f.origin, '1')
    fireEvent.click(screen.getByRole('button', { name: f.addLine }))

    const lot = screen.getByLabelText(f.lot.replace('{n}', '1'))
    await waitFor(() => expect(within(lot).getAllByRole('option')).toHaveLength(2))
    const options = within(lot).getAllByRole('option').map((option) => option.textContent)
    expect(options.some((text) => text?.includes('ACE-A1') && text.includes('disponible 8'))).toBe(true)
    expect(options.some((text) => text?.includes('ACE-B2'))).toBe(false)
  })

  it('Destino igual al origen: mensaje y ninguna petición de creación', async () => {
    const { api } = await openForm()
    await fillValidDraft()
    select(f.destination, '1')

    fireEvent.click(createButton())

    expect(await screen.findByText(f.sameWarehouse)).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/transfers')).toHaveLength(0)
  })

  it('Sin líneas: "Agrega al menos un lote." y ninguna petición', async () => {
    const { api } = await openForm()
    select(f.origin, '1')
    select(f.destination, '2')

    fireEvent.click(createButton())

    expect(await screen.findByText(f.noLines)).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/transfers')).toHaveLength(0)
  })

  // Hallazgo del recorrido 7.1: el mensaje seguía visible tras "Agregar lote" hasta el siguiente envío.
  it('Sin líneas: "Agrega al menos un lote." desaparece al agregar una línea, sin otro envío', async () => {
    const { api } = await openForm()
    select(f.origin, '1')
    fireEvent.click(createButton())
    expect(await screen.findByText(f.noLines)).toBeInTheDocument()
    expect(screen.getByLabelText(f.destination)).toHaveAttribute('aria-invalid', 'true')

    fireEvent.click(screen.getByRole('button', { name: f.addLine }))

    expect(screen.getByLabelText(f.lot.replace('{n}', '1'))).toBeInTheDocument()
    expect(screen.queryByText(f.noLines)).not.toBeInTheDocument()
    // Solo se retira el error de "sin líneas": el del destino sigue hasta corregirlo y reenviar.
    expect(screen.getByLabelText(f.destination)).toHaveAttribute('aria-invalid', 'true')
    expect(api.requestsTo('POST', '/api/transfers')).toHaveLength(0)
  })

  it.each(['', '0', '1.5'])('Cantidad inválida (%j): mensaje junto a la cantidad y ninguna petición', async (quantity) => {
    const { api } = await openForm()
    await fillValidDraft(quantity)

    fireEvent.click(createButton())

    expect(await screen.findByText(f.quantityInvalid)).toBeInTheDocument()
    expect(screen.getByLabelText(f.quantity.replace('{n}', '1'))).toHaveAttribute('aria-invalid', 'true')
    expect(api.requestsTo('POST', '/api/transfers')).toHaveLength(0)
  })

  it('Doble clic en Crear traslado: una sola petición y botón en progreso hasta la respuesta', async () => {
    const pending = deferred<Response>()
    const { api } = await openForm(formRoutes({ [CREATE]: () => pending.promise }))
    await fillValidDraft()

    const button = createButton()
    fireEvent.click(button)
    fireEvent.click(button)

    expect(await screen.findByRole('button', { name: f.submitting })).toBeDisabled()
    expect(api.requestsTo('POST', '/api/transfers')).toHaveLength(1)
    pending.resolve(json(201, { data: transfer() }))
    expect(await screen.findByRole('heading', { name: 'Traslado #12' })).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/transfers')).toHaveLength(1)
  })

  it('Lote vencido al crear: mensaje del código y el formulario conserva lo escrito', async () => {
    await openForm(formRoutes({ [CREATE]: () => json(422, { code: 'lot_expired', message: 'x' }) }))
    await fillValidDraft()

    fireEvent.click(createButton())

    expect(await screen.findByRole('alert')).toHaveTextContent(strings.errors.lotExpired)
    expect(screen.getByLabelText(f.origin)).toHaveValue('1')
    expect(screen.getByLabelText(f.lot.replace('{n}', '1'))).toHaveValue('100')
    expect(screen.getByLabelText(f.quantity.replace('{n}', '1'))).toHaveValue(3)
    expect(createButton()).toBeEnabled()
  })

  it('Sin capacidad de crear: el auditor no ve "Nuevo traslado"', async () => {
    renderAs('auditor', '/transfers', { 'GET /api/transfers': listOf([1, 'BORRADOR']) })
    await screen.findByRole('table')
    expect(screen.queryByRole('button', { name: s.newTransfer })).not.toBeInTheDocument()
  })
})
