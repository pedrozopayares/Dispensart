import { describe, expect, it } from 'vitest'
import { ApiError, apiRequest } from '@/lib/api'
import { describeError, fieldErrors, hasCode } from '@/lib/api-errors'
import { strings } from '@/lib/strings'
import { json, serveApi } from '@/test/http'

// Tarea 1.4 — operator-workspace › "Mensajes de error por código" (design D5).
describe('catálogo de mensajes por code', () => {
  it('Stock insuficiente con detalle: una línea por faltante con el nombre del producto', () => {
    const error = new ApiError(409, 'insufficient_stock', {}, [
      { prescription_item_id: 70, product_id: 10, requested: 5, available: 2 },
    ])

    const text = describeError(error, {
      productName: (id) => (id === 10 ? 'Acetaminofén 500 mg' : undefined),
    })

    expect(text).toBe('Stock insuficiente: Acetaminofén 500 mg necesita 5 y hay 2 disponibles.')
  })

  it('Stock insuficiente sin nombre conocido ni detalle: nunca el id crudo ni el código', () => {
    const withShortage = new ApiError(409, 'insufficient_stock', {}, [
      { prescription_item_id: 71, product_id: 77, requested: 3, available: 0 },
    ])
    expect(describeError(withShortage)).not.toContain('77')
    expect(describeError(new ApiError(409, 'insufficient_stock'))).toBe(
      strings.errors.insufficientStockGeneric,
    )
  })

  it('Lote vencido', () => {
    expect(describeError(new ApiError(422, 'lot_expired'))).toBe(
      'El lote está vencido y no puede usarse.',
    )
  })

  it('Falta de autorización', () => {
    expect(describeError(new ApiError(403, 'forbidden'))).toBe(
      'No tienes permiso para realizar esta acción.',
    )
  })

  it('Código desconocido: texto genérico, sin el código', async () => {
    serveApi({ 'GET /api/stock': () => json(429, { code: 'quota_exceeded', message: 'x' }) })
    const error = await apiRequest('GET', '/stock').catch((rejection: unknown) => rejection)

    const text = describeError(error)
    expect(text).toBe('Ocurrió un error inesperado. Intenta de nuevo.')
    expect(text).not.toContain('quota_exceeded')
  })

  it.each([
    ['network_error del cliente', new ApiError(0, 'network_error')],
    ['server_error', new ApiError(500, 'server_error')],
    ['TypeError del navegador', new TypeError('Failed to fetch')],
  ])('Fallo de red (%s): texto de red, sin el mensaje técnico', (_caso, error) => {
    const text = describeError(error)
    expect(text).toBe('No pudimos conectar con el servidor. Intenta de nuevo.')
    expect(text).not.toContain('Failed to fetch')
  })

  it('Error de campo: el primer mensaje de cada campo, solo en validation_failed', () => {
    const error = new ApiError(422, 'validation_failed', {
      reason: ['El motivo es obligatorio.', 'Otro mensaje.'],
      quantity: [],
    })
    expect(fieldErrors(error)).toEqual({ reason: 'El motivo es obligatorio.' })
    expect(fieldErrors(new ApiError(403, 'forbidden', { reason: ['x'] }))).toEqual({})
  })

  // add-assistant-screen 1.1 — assistant-screen «Errores de la pregunta» a nivel de catálogo.
  it('Demasiadas preguntas: too_many_requests con texto propio, sin el código', () => {
    const text = describeError(new ApiError(429, 'too_many_requests'))
    expect(text).toBe('Hiciste muchas preguntas seguidas. Espera un minuto e intenta de nuevo.')
  })

  it('Asistente no disponible: assistant_unavailable con texto propio, sin el código', () => {
    const text = describeError(new ApiError(503, 'assistant_unavailable'))
    expect(text).toBe('El asistente no está disponible en este momento. Intenta más tarde.')
  })

  it('hasCode distingue el código del rechazo', () => {
    expect(hasCode(new ApiError(422, 'lot_expired'), 'lot_expired')).toBe(true)
    expect(hasCode(new ApiError(422, 'lot_expired'), 'forbidden')).toBe(false)
    expect(hasCode(new Error('lot_expired'), 'lot_expired')).toBe(false)
  })
})
