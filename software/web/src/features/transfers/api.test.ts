import { describe, expect, it } from 'vitest'
import {
  createTransfer,
  getTransfer,
  listTransfers,
  runTransferAction,
  type TransferActionInput,
} from '@/features/transfers/api'
import { ApiError } from '@/lib/api'
import { json, serveApi } from '@/test/http'
import { summary, transfer, transferPage, withXsrf } from '@/test/transfer-fixtures'

// Tarea 1.3 — recursos de traslados (S4) contra la red simulada.
describe('API de traslados', () => {
  it('listado: envía estado y página, y devuelve la página con `meta`', async () => {
    const api = serveApi({ 'GET /api/transfers': () => json(200, transferPage([summary(3, 'EN_TRANSITO')], 2, 4)) })

    const result = await listTransfers({ status: 'EN_TRANSITO', page: 2 })

    expect(result.meta.last_page).toBe(4)
    expect(result.data[0].status).toBe('EN_TRANSITO')
    expect(api.requestsTo('GET', '/api/transfers')[0].query).toEqual({ status: 'EN_TRANSITO', page: '2' })
  })

  it('detalle: devuelve `data` del traslado pedido', async () => {
    const api = serveApi({ 'GET /api/transfers/12': () => json(200, { data: transfer() }) })

    expect((await getTransfer(12)).lines[0].quantity).toBe(3)
    expect(api.requestsTo('GET', '/api/transfers/12')).toHaveLength(1)
  })

  it('creación: envía el cuerpo con cookie XSRF y devuelve el borrador', async () => {
    const api = serveApi(withXsrf({ 'POST /api/transfers': () => json(201, { data: transfer() }) }))
    const body = { origin_warehouse_id: 1, destination_warehouse_id: 2, lines: [{ lot_id: 100, quantity: 3 }] }

    expect((await createTransfer(body)).status).toBe('BORRADOR')
    const [sent] = api.requestsTo('POST', '/api/transfers')
    expect(sent.body).toEqual(body)
    expect(sent.headers['x-xsrf-token']).toBe('token-1')
  })

  it.each<[TransferActionInput, unknown]>([
    [{ action: 'request' }, undefined],
    [{ action: 'approve' }, undefined],
    [{ action: 'dispatch' }, undefined],
    [{ action: 'receive', body: { lines: [{ line_id: 500, received_quantity: 2 }] } }, { lines: [{ line_id: 500, received_quantity: 2 }] }],
    [{ action: 'void', body: { reason: 'Error de digitación' } }, { reason: 'Error de digitación' }],
  ])('acción %o: POST a su ruta con su cuerpo', async (input, body) => {
    const path = `/api/transfers/12/${input.action}`
    const api = serveApi(withXsrf({ [`POST ${path}`]: () => json(200, { data: transfer({ status: 'SOLICITADO' }) }) }))

    expect((await runTransferAction(12, input)).status).toBe('SOLICITADO')
    expect(api.requestsTo('POST', path)[0].body).toEqual(body)
  })

  it('rechazo de una acción: `ApiError` con el código de la API', async () => {
    serveApi(
      withXsrf({
        'POST /api/transfers/12/approve': () => json(403, { code: 'segregation_of_duties', message: 'x' }),
      }),
    )

    const error = await runTransferAction(12, { action: 'approve' }).catch((caught: unknown) => caught)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).code).toBe('segregation_of_duties')
  })
})
