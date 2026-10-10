import { describe, expect, it } from 'vitest'
import {
  createProduct,
  createWarehouse,
  listLots,
  listProducts,
  listWarehouses,
  updateProduct,
  updateWarehouse,
} from '@/features/catalog/api'
import { json, serveApi, setXsrfCookie } from '@/test/http'
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

// add-admin-screens 1.2 — escrituras del catálogo (catalog.manage): método, ruta, cabecera y cuerpo.
describe('API de catálogo: escrituras', () => {
  it('alta de bodega: POST /api/warehouses con cuerpo y X-XSRF-TOKEN', async () => {
    setXsrfCookie('token-1')
    const created = { id: 3, code: 'FC2', name: 'Farmacia Consulta Externa' }
    const api = serveApi({ 'POST /api/warehouses': () => json(201, { data: created }) })

    expect(await createWarehouse({ code: 'FC2', name: 'Farmacia Consulta Externa' })).toEqual(created)
    const [sent] = api.requestsTo('POST', '/api/warehouses')
    expect(sent.body).toEqual({ code: 'FC2', name: 'Farmacia Consulta Externa' })
    expect(sent.headers['x-xsrf-token']).toBe('token-1')
  })

  it('edición de bodega: PATCH /api/warehouses/{id} con cuerpo y X-XSRF-TOKEN', async () => {
    setXsrfCookie('token-1')
    const updated = { id: 1, code: 'FC', name: 'Farmacia Central Norte' }
    const api = serveApi({ 'PATCH /api/warehouses/1': () => json(200, { data: updated }) })

    expect(await updateWarehouse(1, { code: 'FC', name: 'Farmacia Central Norte' })).toEqual(updated)
    const [sent] = api.requestsTo('PATCH', '/api/warehouses/1')
    expect(sent.body).toEqual({ code: 'FC', name: 'Farmacia Central Norte' })
    expect(sent.headers['x-xsrf-token']).toBe('token-1')
  })

  it('alta de producto: POST /api/products con cuerpo y X-XSRF-TOKEN', async () => {
    setXsrfCookie('token-1')
    const body = { code: 'MED-099', name: 'Hidromorfona 2 mg/mL', presentation: 'Ampolla 1 mL', is_controlled: true }
    const api = serveApi({ 'POST /api/products': () => json(201, { data: { id: 20, ...body } }) })

    expect(await createProduct(body)).toEqual({ id: 20, ...body })
    const [sent] = api.requestsTo('POST', '/api/products')
    expect(sent.body).toEqual(body)
    expect(sent.headers['x-xsrf-token']).toBe('token-1')
  })

  it('edición de producto: PATCH /api/products/{id} con los cuatro campos y X-XSRF-TOKEN', async () => {
    setXsrfCookie('token-1')
    const body = { code: 'ACE500', name: 'Acetaminofén 500 mg', presentation: null, is_controlled: true }
    const api = serveApi({ 'PATCH /api/products/10': () => json(200, { data: { id: 10, ...body } }) })

    expect(await updateProduct(10, body)).toEqual({ id: 10, ...body })
    const [sent] = api.requestsTo('PATCH', '/api/products/10')
    expect(sent.body).toEqual(body)
    expect(sent.headers['x-xsrf-token']).toBe('token-1')
  })
})
