import { describe, expect, it } from 'vitest'
import { listStock } from '@/features/inventory/api'
import { json, serveApi } from '@/test/http'
import { lots, stockRow, warehouses } from '@/test/fixtures'

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
