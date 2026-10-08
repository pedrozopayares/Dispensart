import { describe, expect, it } from 'vitest'
import {
  createPrescription,
  dispense,
  getPatient,
  previewDispensation,
  searchPatients,
} from '@/features/dispensations/api'
import {
  clearPatient,
  dispensationFrom,
  fefoPreview,
  plainPrescription,
  record,
} from '@/test/dispensation-fixtures'
import { json, serveApi, setXsrfCookie } from '@/test/http'

// Tarea 1.3 — pacientes, prescripciones, vista previa y dispensación contra la red simulada.
const body = { prescription_id: 7, warehouse_id: 1, items: [{ prescription_item_id: 70, quantity: 5 }] }

describe('API de dispensación', () => {
  it('busca pacientes con `q` y devuelve `data`', async () => {
    const api = serveApi({ 'GET /api/patients': () => json(200, { data: [clearPatient] }) })

    expect(await searchPatients('SINT')).toEqual([clearPatient])
    expect(api.requestsTo('GET', '/api/patients')[0].query).toEqual({ q: 'SINT' })
  })

  it('lee la ficha por id con sus prescripciones', async () => {
    const patient = record([plainPrescription])
    serveApi({ 'GET /api/patients/1': () => json(200, { data: patient }) })

    expect(await getPatient(1)).toEqual(patient)
  })

  it('crea una prescripción con el cuerpo dado y cabecera XSRF', async () => {
    setXsrfCookie('token-1')
    const api = serveApi({ 'POST /api/prescriptions': () => json(201, { data: plainPrescription }) })
    const payload = { patient_id: 1, valid_until: '2026-12-31', items: [{ product_id: 10, quantity: 5 }] }

    expect(await createPrescription(payload)).toEqual(plainPrescription)
    const [request] = api.requestsTo('POST', '/api/prescriptions')
    expect(request.body).toEqual(payload)
    expect(request.headers['x-xsrf-token']).toBe('token-1')
  })

  it('pide la vista previa y devuelve la asignación', async () => {
    setXsrfCookie('token-1')
    const api = serveApi({ 'POST /api/dispensations/preview': () => json(200, { data: fefoPreview }) })

    expect(await previewDispensation(body)).toEqual(fefoPreview)
    expect(api.requestsTo('POST', '/api/dispensations/preview')[0].body).toEqual(body)
  })

  it('dispensa con `Idempotency-Key` y lee `Idempotent-Replayed`', async () => {
    setXsrfCookie('token-1')
    const created = dispensationFrom(fefoPreview)
    const api = serveApi({
      'POST /api/dispensations': [
        () => json(201, { data: created }),
        () => json(201, { data: created }, { 'Idempotent-Replayed': 'true' }),
      ],
    })

    expect(await dispense(body, 'clave-de-prueba-0001')).toEqual({ dispensation: created, replayed: false })
    expect(await dispense(body, 'clave-de-prueba-0001')).toEqual({ dispensation: created, replayed: true })
    const requests = api.requestsTo('POST', '/api/dispensations')
    expect(requests.map((request) => request.headers['idempotency-key'])).toEqual([
      'clave-de-prueba-0001',
      'clave-de-prueba-0001',
    ])
    expect(requests[0].body).toEqual(body)
  })

  it('un 409 `insufficient_stock` llega como ApiError con sus `shortages`', async () => {
    setXsrfCookie('token-1')
    const shortages = [{ prescription_item_id: 70, product_id: 10, requested: 5, available: 1 }]
    serveApi({
      'POST /api/dispensations': () => json(409, { code: 'insufficient_stock', message: 'x', shortages }),
    })

    await expect(dispense(body, 'clave-de-prueba-0002')).rejects.toMatchObject({
      name: 'ApiError',
      code: 'insufficient_stock',
      shortages,
    })
  })
})
