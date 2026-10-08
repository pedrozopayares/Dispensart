import { fireEvent, screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import { json, networkError, type RecordedRequest } from '@/test/http'
import { catalogRoutes, lots, noAlertsRoute, stockRow, warehouses } from '@/test/fixtures'
import { renderAs } from '@/test/render'

// Tarea 4.1 — inventory-screen › "Existencias por bodega y lote" (RN-01).

const [central, urgencias] = warehouses
const rows = [
  stockRow(1, central, lots[0], 40),
  stockRow(2, urgencias, lots[0], 12),
  stockRow(3, urgencias, lots[2], 3),
]

// Servidor simulado: filtra por bodega y producto como la API.
const stockServer = ({ query }: RecordedRequest) =>
  json(200, {
    data: rows.filter(
      (row) =>
        (query.warehouse_id === undefined || String(row.warehouse.id) === query.warehouse_id) &&
        (query.product_id === undefined || String(row.product.id) === query.product_id),
    ),
  })

const bodyRows = () => within(screen.getByRole('table')).getAllByRole('row').slice(1)

describe('pantalla Inventario', () => {
  it('Consulta por bodega: "Cargando inventario…" y luego solo existencias de Farmacia Urgencias', async () => {
    const { api } = renderAs('auxiliar_farmacia', '/inventory', {
      ...catalogRoutes(),
      ...noAlertsRoute(),
      'GET /api/stock': stockServer,
    })
    expect(await screen.findAllByRole('cell', { name: 'Farmacia Central' })).toHaveLength(1)
    await screen.findByRole('option', { name: 'Farmacia Urgencias' })

    fireEvent.change(screen.getByLabelText(strings.filters.warehouse), { target: { value: '2' } })

    expect(screen.getByText(strings.inventory.loading)).toBeInTheDocument()
    await screen.findByRole('table')
    expect(bodyRows()).toHaveLength(2)
    for (const row of bodyRows()) {
      expect(within(row).getByRole('cell', { name: 'Farmacia Urgencias' })).toBeInTheDocument()
    }
    expect(screen.queryByRole('cell', { name: 'Farmacia Central' })).not.toBeInTheDocument()
    const [lotRow] = bodyRows()
    expect(lotRow).toHaveTextContent('ACE-A1')
    expect(lotRow).toHaveTextContent('2027-06-30')
    expect(lotRow).toHaveTextContent('12')
    expect(api.requestsTo('GET', '/api/stock').at(-1)?.query).toEqual({ warehouse_id: '2' })
  })

  it('Sin existencias para el filtro: mensaje de vacío', async () => {
    renderAs('auxiliar_farmacia', '/inventory', {
      ...catalogRoutes(),
      ...noAlertsRoute(),
      'GET /api/stock': () => json(200, { data: [] }),
    })

    expect(await screen.findByText(strings.inventory.empty)).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('Fallo de la consulta: mensaje de red con "Reintentar", que repite con los mismos filtros', async () => {
    let urgenciasAttempts = 0
    const { api } = renderAs('auxiliar_farmacia', '/inventory', {
      ...catalogRoutes(),
      ...noAlertsRoute(),
      'GET /api/stock': (request) =>
        request.query.warehouse_id === '2' && urgenciasAttempts++ === 0
          ? networkError()
          : stockServer(request),
    })
    await screen.findByRole('table')
    await screen.findByRole('option', { name: 'Farmacia Urgencias' })
    fireEvent.change(screen.getByLabelText(strings.filters.warehouse), { target: { value: '2' } })

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent(strings.errors.network)
    fireEvent.click(within(alert).getByRole('button', { name: strings.common.retry }))

    await screen.findByRole('table')
    expect(bodyRows()).toHaveLength(2)
    const retried = api.requestsTo('GET', '/api/stock').slice(-2)
    expect(retried.map((request) => request.query)).toEqual([
      { warehouse_id: '2' },
      { warehouse_id: '2' },
    ])
  })

  it('Lote vencido marcado: "Vencido" con estilo distinto solo en su fila', async () => {
    renderAs('auxiliar_farmacia', '/inventory', {
      ...catalogRoutes(),
      ...noAlertsRoute(),
      'GET /api/stock': () =>
        json(200, { data: [stockRow(1, central, lots[1], 2), stockRow(2, central, lots[0], 9)] }),
    })
    await screen.findByRole('table')

    const [expired, current] = bodyRows()
    expect(within(expired).getByText(strings.inventory.expired)).toBeInTheDocument()
    expect(expired).toHaveAttribute('data-expired', 'true')
    expect(expired.className).not.toBe(current.className)
    expect(within(current).queryByText(strings.inventory.expired)).not.toBeInTheDocument()
    expect(current).not.toHaveAttribute('data-expired')
  })

  it('producto de control especial marcado en su fila', async () => {
    renderAs('auxiliar_farmacia', '/inventory', {
      ...catalogRoutes(),
      ...noAlertsRoute(),
      'GET /api/stock': () => json(200, { data: [stockRow(1, central, lots[2], 4)] }),
    })
    await screen.findByRole('table')

    expect(within(bodyRows()[0]).getByText(strings.inventory.controlled)).toBeInTheDocument()
  })

  it('filtro por producto: la consulta lleva el producto', async () => {
    const { api } = renderAs('auxiliar_farmacia', '/inventory', {
      ...catalogRoutes(),
      ...noAlertsRoute(),
      'GET /api/stock': stockServer,
    })
    await screen.findByRole('table')
    await screen.findByRole('option', { name: /Morfina/ })

    fireEvent.change(screen.getByLabelText(strings.filters.product), { target: { value: '11' } })

    await screen.findByRole('table')
    expect(bodyRows()).toHaveLength(1)
    expect(api.requestsTo('GET', '/api/stock').at(-1)?.query).toEqual({ product_id: '11' })
  })

  it('Auditor sin controles de edición: ve existencias y ningún botón', async () => {
    renderAs('auditor', '/inventory', {
      ...catalogRoutes(),
      ...noAlertsRoute(),
      'GET /api/stock': stockServer,
    })
    await screen.findByRole('table')

    expect(within(screen.getByRole('main')).queryAllByRole('button')).toEqual([])
  })
})
