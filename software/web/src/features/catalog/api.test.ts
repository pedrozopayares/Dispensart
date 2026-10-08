import { describe, expect, it } from 'vitest'
import { listLots, listProducts, listWarehouses } from '@/features/catalog/api'
import { json, serveApi } from '@/test/http'
import { lots, products, warehouses } from '@/test/fixtures'

// Tarea 1.3 — funciones de catálogo contra la red simulada.
describe('API de catálogo', () => {
  it('bodegas: GET /api/warehouses devuelve `data`', async () => {
    const api = serveApi({ 'GET /api/warehouses': () => json(200, { data: warehouses }) })

    expect(await listWarehouses()).toEqual(warehouses)
    expect(api.requestsTo('GET', '/api/warehouses')).toHaveLength(1)
  })

  it('productos: GET /api/products devuelve `data`', async () => {
    serveApi({ 'GET /api/products': () => json(200, { data: products }) })

    expect(await listProducts()).toEqual(products)
  })

  it('lotes: GET /api/lots envía el producto como consulta', async () => {
    const api = serveApi({
      'GET /api/lots': ({ query }) =>
        json(200, { data: lots.filter((lot) => String(lot.product_id) === query.product_id) }),
    })

    expect((await listLots({ product_id: 11 })).map((lot) => lot.lot_code)).toEqual(['MOR-C3'])
    expect(api.requestsTo('GET', '/api/lots')[0].query).toEqual({ product_id: '11' })
  })
})
