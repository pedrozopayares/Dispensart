import { fireEvent, screen, within } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { strings } from '@/lib/strings'
import { json, networkError, type RecordedRequest } from '@/test/http'
import { catalogRoutes, kardexPage, lots, movement, warehouses } from '@/test/fixtures'
import { renderAs } from '@/test/render'

// Tareas 5.1 y 5.2 — kardex-screen › "Historial de movimientos filtrable", "Paginación y filtros en
// la URL", "Movimientos inmutables en la interfaz" (RN-06).

const bodyRows = () => within(screen.getByRole('table')).getAllByRole('row').slice(1)
const lastKardexQuery = (api: { requestsTo: (m: string, p: string) => RecordedRequest[] }) =>
  api.requestsTo('GET', '/api/kardex').at(-1)?.query

function openKardex(path: string, kardex: (request: RecordedRequest) => Response, role = 'regente_farmacia' as const) {
  return renderAs(role, path, { ...catalogRoutes(), 'GET /api/kardex': kardex })
}

describe('pantalla Kardex', () => {
  it('Tipos en español: los cinco tipos con su etiqueta, nunca el literal de la API', async () => {
    const types = ['entrada', 'salida_dispensacion', 'salida_traslado', 'entrada_traslado', 'ajuste'] as const
    openKardex('/kardex', () =>
      json(200, kardexPage(types.map((type, index) => movement(index + 1, { type })))),
    )
    await screen.findByRole('table')

    const typeCells = bodyRows().map((row) => within(row).getAllByRole('cell')[1].textContent)
    expect(typeCells).toEqual([
      'Entrada',
      'Salida por dispensación',
      'Salida por traslado',
      'Entrada por traslado',
      'Ajuste',
    ])
    for (const literal of types.filter((type) => type.includes('_'))) {
      expect(document.body.textContent).not.toContain(literal)
    }
  })

  it('Cantidad con signo y usuario del sistema: "-3", "+12" y "Sistema"', async () => {
    openKardex('/kardex', () =>
      json(
        200,
        kardexPage([
          movement(1, { type: 'salida_dispensacion', quantity: -3, balance_after: 9 }),
          movement(2, { type: 'entrada', quantity: 12, balance_after: 12, user: null }),
        ]),
      ),
    )
    await screen.findByRole('table')

    const [salida, entrada] = bodyRows().map((row) => within(row).getAllByRole('cell'))
    expect(salida[5]).toHaveTextContent(/^-3$/)
    expect(salida[7]).toHaveTextContent('Regente Demo')
    expect(entrada[5]).toHaveTextContent(/^\+12$/)
    expect(entrada[7]).toHaveTextContent(strings.kardex.system)
  })

  it('fecha y hora en America/Bogota, aunque el navegador esté en otra zona', async () => {
    vi.stubEnv('TZ', 'Asia/Tokyo')
    openKardex('/kardex', () =>
      json(200, kardexPage([movement(1, { created_at: '2026-10-08T03:30:00+00:00' })])),
    )
    await screen.findByRole('table')

    expect(within(bodyRows()[0]).getAllByRole('cell')[0]).toHaveTextContent('2026-10-07 22:30')
  })

  it('Filtro por producto y lote: solo movimientos del lote y vuelta a la página 1', async () => {
    const all = [movement(1, { lot: lots[0] }), movement(2, { lot: lots[1] }), movement(3, { lot: lots[2] })]
    const { api, router } = openKardex('/kardex?page=2', ({ query }) =>
      json(
        200,
        kardexPage(
          all.filter((item) => query.lot_id === undefined || String(item.lot.id) === query.lot_id),
          Number(query.page),
          2,
        ),
      ),
    )
    await screen.findByRole('table')
    await screen.findByRole('option', { name: /Acetaminofén/ })

    fireEvent.change(screen.getByLabelText(strings.filters.product), { target: { value: '10' } })
    await screen.findByRole('option', { name: 'ACE-B2' })
    fireEvent.change(screen.getByLabelText(strings.filters.lot), { target: { value: '101' } })

    await screen.findByRole('table')
    expect(bodyRows()).toHaveLength(1)
    expect(bodyRows()[0]).toHaveTextContent('ACE-B2')
    expect(lastKardexQuery(api)).toEqual({ product_id: '10', lot_id: '101', page: '1' })
    expect(api.requestsTo('GET', '/api/lots').at(-1)?.query).toEqual({ product_id: '10' })
    expect(router.state.location.search).toBe('?product_id=10&lot_id=101')
  })

  it('Lote deshabilitado sin producto: "Elige un producto primero"', async () => {
    openKardex('/kardex', () => json(200, kardexPage([movement(1)])))
    await screen.findByRole('table')

    const lot = screen.getByLabelText(strings.filters.lot)
    expect(lot).toBeDisabled()
    expect(lot).toHaveDisplayValue(strings.filters.lotNeedsProduct)
  })

  it('Sin movimientos: mensaje de vacío', async () => {
    openKardex('/kardex', () => json(200, kardexPage([])))

    expect(await screen.findByText(strings.kardex.empty)).toBeInTheDocument()
  })

  it('Fallo de la consulta: mensaje de red con "Reintentar" que repite la consulta', async () => {
    let attempts = 0
    const { api } = openKardex('/kardex', () =>
      attempts++ === 0 ? networkError() : json(200, kardexPage([movement(1)])),
    )

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent(strings.errors.network)
    fireEvent.click(within(alert).getByRole('button', { name: strings.common.retry }))

    expect(await screen.findByRole('table')).toBeInTheDocument()
    expect(api.requestsTo('GET', '/api/kardex')).toHaveLength(2)
  })

  it('Siguiente página: página 2, "Página 2 de 3" y la URL incluye la página', async () => {
    const { api, router } = openKardex('/kardex?warehouse_id=1', ({ query }) =>
      json(200, kardexPage([movement(Number(query.page))], Number(query.page), 3)),
    )
    await screen.findByText(fmtPage(1, 3))

    fireEvent.click(screen.getByRole('button', { name: strings.common.next }))

    expect(await screen.findByText(fmtPage(2, 3))).toBeInTheDocument()
    expect(lastKardexQuery(api)).toEqual({ warehouse_id: '1', page: '2' })
    expect(router.state.location.search).toBe('?warehouse_id=1&page=2')
  })

  it('Recarga conserva los filtros: la URL con bodega y producto los aplica', async () => {
    const { api } = openKardex('/kardex?warehouse_id=2&product_id=10', () =>
      json(200, kardexPage([movement(1, { warehouse: warehouses[1] })])),
    )
    await screen.findByRole('table')
    await screen.findByRole('option', { name: /Acetaminofén/ })

    expect(lastKardexQuery(api)).toEqual({ warehouse_id: '2', product_id: '10', page: '1' })
    expect(screen.getByLabelText(strings.filters.warehouse)).toHaveValue('2')
    expect(screen.getByLabelText(strings.filters.product)).toHaveValue('10')
    expect(screen.getByLabelText(strings.filters.lot)).toBeEnabled()
  })

  it('Parámetro inválido en la URL: sin filtro de lote y la consulta no lleva lot_id', async () => {
    const { api } = openKardex('/kardex?lot_id=abc&product_id=10&page=x', () =>
      json(200, kardexPage([movement(1)])),
    )
    await screen.findByRole('table')

    expect(lastKardexQuery(api)).toEqual({ product_id: '10', page: '1' })
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('Primera y última página: "Anterior" y luego "Siguiente" deshabilitados', async () => {
    openKardex('/kardex', ({ query }) =>
      json(200, kardexPage([movement(Number(query.page))], Number(query.page), 2)),
    )
    await screen.findByText(fmtPage(1, 2))
    expect(screen.getByRole('button', { name: strings.common.previous })).toBeDisabled()
    expect(screen.getByRole('button', { name: strings.common.next })).toBeEnabled()

    fireEvent.click(screen.getByRole('button', { name: strings.common.next }))

    await screen.findByText(fmtPage(2, 2))
    expect(screen.getByRole('button', { name: strings.common.next })).toBeDisabled()
    expect(screen.getByRole('button', { name: strings.common.previous })).toBeEnabled()
  })

  it('Regente sin edición: la pantalla solo ofrece paginar', async () => {
    openKardex('/kardex', () => json(200, kardexPage([movement(1), movement(2)])))
    await screen.findByRole('table')

    const buttons = within(screen.getByRole('main')).getAllByRole('button')
    expect(buttons.map((button) => button.textContent)).toEqual([
      strings.common.previous,
      strings.common.next,
    ])
    expect(within(screen.getByRole('table')).queryAllByRole('button')).toEqual([])
  })
})

function fmtPage(page: number, total: number) {
  return strings.common.page.replace('{page}', String(page)).replace('{total}', String(total))
}
