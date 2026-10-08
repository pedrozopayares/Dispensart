import { describe, expect, it } from 'vitest'
import { listKardex } from '@/features/kardex/api'
import { json, serveApi } from '@/test/http'
import { kardexPage, movement } from '@/test/fixtures'

// Tarea 1.3 — kardex paginado contra la red simulada.
describe('API del kardex', () => {
  it('envía filtros y página, y devuelve la página completa con `meta`', async () => {
    const page = kardexPage([movement(1)], 2, 3)
    const api = serveApi({ 'GET /api/kardex': () => json(200, page) })

    const result = await listKardex({ warehouse_id: 1, lot_id: undefined, page: 2 })

    expect(result.meta.last_page).toBe(3)
    expect(result.data).toHaveLength(1)
    expect(api.requestsTo('GET', '/api/kardex')[0].query).toEqual({ warehouse_id: '1', page: '2' })
  })
})
