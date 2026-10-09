import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { format, strings } from '@/lib/strings'
import { json, networkError, type RecordedRequest } from '@/test/http'
import {
  alertsBody,
  catalogRoutes,
  expiringLot,
  lots,
  lowStock,
  products,
  stockRow,
  warehouses,
} from '@/test/fixtures'
import { renderAs } from '@/test/render'

// Tarea 4.2 — inventory-screen › "Alertas de vencimiento y stock mínimo" (RN-11). El resaltado sale
// solo de `GET /api/alerts`; la red se simula en el borde HTTP y la pantalla se monta completa.

const s = strings.inventory.alerts
const [central, urgencias] = warehouses
const [acetaminofen, morfina] = products
const [aceA1, aceB2, morC3] = lots

const rows = [
  stockRow(1, central, aceA1, 40),
  stockRow(2, urgencias, aceA1, 12),
  stockRow(3, urgencias, morC3, 3),
]

const stockServer = ({ query }: RecordedRequest) =>
  json(200, {
    data: rows.filter(
      (row) => query.warehouse_id === undefined || String(row.warehouse.id) === query.warehouse_id,
    ),
  })

// Servidor de alertas: filtra ambas listas por bodega, como la API.
const alertsServer =
  (alerts: Parameters<typeof alertsBody>[0]) =>
  ({ query }: RecordedRequest) => {
    const { data } = alertsBody(alerts)
    const inWarehouse = (alert: { warehouse: { id: number } }) =>
      query.warehouse_id === undefined || String(alert.warehouse.id) === query.warehouse_id
    return json(200, {
      data: {
        expiring_lots: data.expiring_lots.filter(inWarehouse),
        low_stock: data.low_stock.filter(inWarehouse),
      },
    })
  }

const stockTable = () => screen.getByRole('table', { name: strings.inventory.caption })
const stockRows = () => within(stockTable()).getAllByRole('row').slice(1)
// Fila de existencias por bodega y código de lote.
const rowOf = (warehouseName: string, lotCode: string) => {
  const row = stockRows().find(
    (candidate) =>
      within(candidate).queryByRole('cell', { name: warehouseName }) !== null &&
      candidate.textContent?.includes(lotCode),
  )
  expect(row, `${warehouseName} ${lotCode}`).toBeDefined()
  return row!
}
const lowStockPanel = () => screen.getByRole('region', { name: s.lowStockTitle })

type Resolver = (request: RecordedRequest) => Response | Promise<Response>

function renderInventory(alertsRoute: Resolver | Resolver[]) {
  return renderAs('auxiliar_farmacia', '/inventory', {
    ...catalogRoutes(),
    'GET /api/stock': stockServer,
    'GET /api/alerts': alertsRoute,
  })
}

afterEach(() => {
  vi.useRealTimers()
})

describe('Inventario › alertas de vencimiento y stock mínimo', () => {
  it('Lote por vencer resaltado: "Vence en 20 días" solo en la fila de esa bodega', async () => {
    renderInventory(alertsServer({ expiring_lots: [expiringLot(central, aceA1, 40, 20)] }))
    await screen.findByText(format(s.expiringSummary, { count: '1' }))

    const highlighted = rowOf('Farmacia Central', 'ACE-A1')
    const badge = within(highlighted).getByText(format(s.expiresIn, { days: '20' }))
    expect(badge).toBeInTheDocument()
    // S14: colores del par de advertencia del tema, no ámbar fijo con texto blanco.
    expect(badge).toHaveClass('bg-warning', 'text-warning-foreground')
    expect(badge).not.toHaveClass('text-white')
    expect(highlighted).toHaveAttribute('data-expiring', 'true')
    // El mismo lote en otra bodega no figura en la alerta: sin resaltado.
    const sameLotElsewhere = rowOf('Farmacia Urgencias', 'ACE-A1')
    expect(sameLotElsewhere).not.toHaveTextContent(/Vence en/)
    expect(sameLotElsewhere).not.toHaveAttribute('data-expiring')
    expect(highlighted.className).not.toBe(sameLotElsewhere.className)
  })

  it('lote ya vencido en la alerta: "Vencido" y no "Vence en"', async () => {
    renderAs('auxiliar_farmacia', '/inventory', {
      ...catalogRoutes(),
      'GET /api/stock': () => json(200, { data: [stockRow(1, central, aceB2, 2)] }),
      'GET /api/alerts': alertsServer({ expiring_lots: [expiringLot(central, aceB2, 2, -1)] }),
    })
    await screen.findByText(format(s.expiringSummary, { count: '1' }))

    const [row] = stockRows()
    expect(within(row).getByText(strings.inventory.expired)).toBeInTheDocument()
    expect(row).not.toHaveTextContent(/Vence en/)
    expect(row).toHaveAttribute('data-expired', 'true')
  })

  it('Lote fuera de la ventana sin resaltar, aunque el reloj del navegador lo haga parecer cercano', async () => {
    // Reloj del navegador fijado a 10 días del vencimiento de ACE-A1 (2027-06-30).
    vi.useFakeTimers({ toFake: ['Date'] })
    vi.setSystemTime(new Date('2027-06-20T12:00:00-05:00'))
    // La API solo alerta MOR-C3 en Urgencias: control positivo dentro de la misma pantalla.
    renderInventory(alertsServer({ expiring_lots: [expiringLot(urgencias, morC3, 3, 5)] }))
    await screen.findByText(format(s.expiringSummary, { count: '1' }))

    expect(rowOf('Farmacia Urgencias', 'MOR-C3')).toHaveTextContent(format(s.expiresIn, { days: '5' }))
    for (const row of [rowOf('Farmacia Central', 'ACE-A1'), rowOf('Farmacia Urgencias', 'ACE-A1')]) {
      expect(row).not.toHaveTextContent(/Vence en/)
      expect(row).not.toHaveAttribute('data-expiring')
      expect(row).not.toHaveAttribute('data-expired')
      expect(row.className).toBe(stockRows()[0].className)
    }
  })

  it('Producto bajo mínimo: el panel lo lista con 10 y 4 y sus filas de esa bodega llevan "Bajo mínimo"; la consulta usa la bodega elegida', async () => {
    const { api } = renderInventory(
      alertsServer({ low_stock: [lowStock(urgencias, acetaminofen, 10, 4)] }),
    )
    await screen.findByRole('region', { name: s.lowStockTitle })

    const [panelRow] = within(lowStockPanel()).getAllByRole('row').slice(1)
    expect(within(panelRow).getAllByRole('cell').map((cell) => cell.textContent)).toEqual([
      'Farmacia Urgencias',
      'ACE500 Acetaminofén 500 mg',
      '10',
      '4',
    ])
    expect(within(rowOf('Farmacia Urgencias', 'ACE-A1')).getByText(s.lowStockBadge)).toBeInTheDocument()
    // Mismo producto en otra bodega y otro producto en la misma bodega: sin marca.
    expect(rowOf('Farmacia Central', 'ACE-A1')).not.toHaveTextContent(s.lowStockBadge)
    expect(rowOf('Farmacia Urgencias', 'MOR-C3')).not.toHaveTextContent(s.lowStockBadge)

    await screen.findByRole('option', { name: 'Farmacia Urgencias' })
    fireEvent.change(screen.getByLabelText(strings.filters.warehouse), { target: { value: '2' } })

    await waitFor(() =>
      expect(api.requestsTo('GET', '/api/alerts').at(-1)?.query).toEqual({ warehouse_id: '2' }),
    )
    expect(await screen.findByRole('region', { name: s.lowStockTitle })).toHaveTextContent('Farmacia Urgencias')
    expect(api.requestsTo('GET', '/api/alerts')[0].query).toEqual({})
  })

  it('Producto bajo mínimo sin existencias: el panel lo lista con disponible 0', async () => {
    renderInventory(alertsServer({ low_stock: [lowStock(central, morfina, 5, 0)] }))
    await screen.findByRole('region', { name: s.lowStockTitle })

    const [panelRow] = within(lowStockPanel()).getAllByRole('row').slice(1)
    expect(panelRow).toHaveTextContent('Farmacia Central')
    expect(panelRow).toHaveTextContent('Morfina 10 mg/ml')
    expect(within(panelRow).getAllByRole('cell').at(-1)).toHaveTextContent(/^0$/)
    // No hay fila de Morfina en Central: ninguna fila de existencias lleva la marca.
    for (const row of stockRows()) expect(row).not.toHaveTextContent(s.lowStockBadge)
  })

  it('Sin alertas: mensaje propio y ninguna fila resaltada', async () => {
    renderInventory(alertsServer({}))

    expect(await screen.findByText(s.none)).toBeInTheDocument()
    await screen.findByRole('table', { name: strings.inventory.caption })
    expect(screen.queryByRole('region', { name: s.lowStockTitle })).not.toBeInTheDocument()
    for (const row of stockRows()) {
      expect(row).not.toHaveAttribute('data-expiring')
      expect(row).not.toHaveAttribute('data-low-stock')
      expect(row).not.toHaveTextContent(/Vence en|Bajo mínimo/)
    }
  })

  it('Alertas fallan y existencias no: tabla completa, "No pudimos cargar las alertas." y "Reintentar" repite la consulta', async () => {
    // La segunda respuesta ya trae una alerta: el reintento la muestra.
    const { api } = renderInventory([
      networkError,
      alertsServer({ expiring_lots: [expiringLot(central, aceA1, 40, 20)] }),
    ])
    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent(s.failed)
    await screen.findByRole('table', { name: strings.inventory.caption })
    expect(stockRows()).toHaveLength(3)
    expect(screen.getAllByRole('alert')).toHaveLength(1)
    for (const row of stockRows()) expect(row).not.toHaveAttribute('data-expiring')

    fireEvent.click(within(alert).getByRole('button', { name: strings.common.retry }))

    expect(await screen.findByText(format(s.expiresIn, { days: '20' }))).toBeInTheDocument()
    expect(screen.queryByText(s.failed)).not.toBeInTheDocument()
    expect(api.requestsTo('GET', '/api/alerts').map((request) => request.query)).toEqual([{}, {}])
    expect(api.requestsTo('GET', '/api/stock')).toHaveLength(1)
  })
})
