import { screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { apiRequest } from '@/lib/api'
import { roleLabel } from '@/lib/strings'
import { json, serveApi, setXsrfCookie, unhandledRequests } from '@/test/http'
import { renderAs } from '@/test/render'

// Tarea 1.2 — cimiento: red simulada en el borde HTTP (design D2).
describe('red simulada y render por rol', () => {
  it('renderiza el shell como auxiliar_farmacia y registra la petición de sesión', async () => {
    const { api } = renderAs('auxiliar_farmacia', '/')

    const header = await screen.findByRole('banner')
    expect(await within(header).findByText('Auxiliar Demo')).toBeInTheDocument()
    expect(within(header).getByText(roleLabel('auxiliar_farmacia'))).toBeInTheDocument()
    const [me] = api.requestsTo('GET', '/api/auth/me')
    expect(me.headers.accept).toBe('application/json')
  })

  it('registra método, ruta, consulta, cabeceras y cuerpo de cada petición', async () => {
    setXsrfCookie('token-1')
    const api = serveApi({ 'POST /api/stock-adjustments': () => json(201, { data: { id: 1 } }) })

    await apiRequest('POST', '/stock-adjustments', { quantity: -1 }, { query: { dry: 1 } })

    expect(api.requests).toEqual([
      expect.objectContaining({
        method: 'POST',
        path: '/api/stock-adjustments',
        query: { dry: '1' },
        body: { quantity: -1 },
        headers: expect.objectContaining({ 'x-xsrf-token': 'token-1' }),
      }),
    ])
  })

  it('control positivo: sin manejador la petición falla y queda como no atendida', async () => {
    await expect(apiRequest('GET', '/sin-manejador')).rejects.toMatchObject({ code: 'network_error' })

    // La guarda de `setup.ts` haría fallar esta prueba; se retira tras comprobarla.
    expect(unhandledRequests.splice(0)).toEqual(['GET /api/sin-manejador'])
  })
})
