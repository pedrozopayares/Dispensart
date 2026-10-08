import { describe, expect, it } from 'vitest'
import { askAssistant } from '@/features/assistant/api'
import { ApiError } from '@/lib/api'
import { apiError, json, noContent, serveApi, setXsrfCookie } from '@/test/http'

// add-assistant-screen 1.2 — assistant-screen «Envío de una pregunta: Pregunta enviada» y «Errores de
// la pregunta: Sesión expirada al preguntar» a nivel del cliente.
const answer = { outcome: 'answered', answer: 'Hay 30 unidades disponibles.', tool_calls: [] }

describe('API del asistente', () => {
  it('envía POST /api/assistant/ask con X-XSRF-TOKEN y {"question": …}, y devuelve `data`', async () => {
    setXsrfCookie('token-1')
    const api = serveApi({ 'POST /api/assistant/ask': () => json(200, { data: answer }) })

    const result = await askAssistant('¿Cuánto acetaminofén hay?')

    expect(result).toEqual(answer)
    const sent = api.requestsTo('POST', '/api/assistant/ask')
    expect(sent).toHaveLength(1)
    expect(sent[0].headers['x-xsrf-token']).toBe('token-1')
    expect(sent[0].body).toEqual({ question: '¿Cuánto acetaminofén hay?' })
  })

  it('csrf_token_mismatch: renueva la cookie y reintenta una sola vez', async () => {
    setXsrfCookie('vieja')
    const api = serveApi({
      'GET /sanctum/csrf-cookie': () => {
        setXsrfCookie('nueva')
        return noContent()
      },
      'POST /api/assistant/ask': [() => apiError(419, 'csrf_token_mismatch'), () => json(200, { data: answer })],
    })

    await askAssistant('¿Cuánto acetaminofén hay?')

    const sent = api.requestsTo('POST', '/api/assistant/ask')
    expect(sent.map((request) => request.headers['x-xsrf-token'])).toEqual(['vieja', 'nueva'])
  })

  it('unauthenticated: rechaza con el código para el manejo de app-shell', async () => {
    setXsrfCookie('token-1')
    serveApi({ 'POST /api/assistant/ask': () => apiError(401, 'unauthenticated') })

    const error = await askAssistant('¿Cuánto acetaminofén hay?').catch((rejection: unknown) => rejection)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).code).toBe('unauthenticated')
  })
})
