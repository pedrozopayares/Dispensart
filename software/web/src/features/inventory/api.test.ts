import { describe, expect, it } from 'vitest'
import { listAlerts, listStock } from '@/features/inventory/api'
import { json, serveApi } from '@/test/http'
import { alertsBody, expiringLot, lots, lowStock, products, stockRow, warehouses } from '@/test/fixtures'

// Tarea 1.3 — existencias contra la red simulada.
describe('API de existencias', () => {
  it('envía solo los filtros definidos y devuelve `data`', async () => {
    const row = stockRow(1, warehouses[1], lots[0], 8)
    const api = serveApi({ 'GET /api/stock': () => json(200, { data: [row] }) })

    expect(await listStock({ warehouse_id: 2, product_id: undefined })).toEqual([row])
    expect(api.requestsTo('GET', '/api/stock')[0].query).toEqual({ warehouse_id: '2' })
  })

  it('un rechazo llega como ApiError con su código', async () => {
    serveApi({ 'GET /api/stock': () => json(403, { code: 'forbidden', message: 'x' }) })

    await expect(listStock({})).rejects.toMatchObject({ name: 'ApiError', code: 'forbidden' })
  })
})

// Tarea 1.3 — alertas (S5) contra la red simulada.
describe('API de alertas', () => {
  it('envía la bodega y devuelve ambas listas de `data`', async () => {
    const body = alertsBody({
      expiring_lots: [expiringLot(warehouses[1], lots[0], 12, 20)],
      low_stock: [lowStock(warehouses[1], products[0], 10, 4)],
    })
    const api = serveApi({ 'GET /api/alerts': () => json(200, body) })

    expect(await listAlerts({ warehouse_id: 2 })).toEqual(body.data)
    expect(api.requestsTo('GET', '/api/alerts')[0].query).toEqual({ warehouse_id: '2' })
  })

  it('sin bodega no envía filtro', async () => {
    const api = serveApi({ 'GET /api/alerts': () => json(200, alertsBody()) })

    expect(await listAlerts({})).toEqual({ expiring_lots: [], low_stock: [] })
    expect(api.requestsTo('GET', '/api/alerts')[0].query).toEqual({})
  })
})
