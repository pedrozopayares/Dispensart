import { describe, expect, it } from 'vitest'
import { ApiError, apiRequest, errorMessage, getCurrentUser, login } from '@/lib/api'
import { strings } from '@/lib/strings'
import {
  apiError,
  csrfCookieHandler,
  installFakeApi,
  json,
  noContent,
  setXsrfCookie,
  user,
} from '@/test/fake-api'

// Tarea 6.1 — cliente HTTP (design D8). app-shell › "Sesión expirada y token CSRF vencido".
describe('cliente HTTP de la SPA', () => {
  it('pide la cookie CSRF antes del login y envía X-XSRF-TOKEN decodificado de la cookie', async () => {
    // Una cookie previa no evita la petición: el login siempre parte de una cookie CSRF fresca.
    setXsrfCookie('previa')
    const api = installFakeApi({
      'GET /sanctum/csrf-cookie': csrfCookieHandler('abc=='),
      'POST /api/auth/login': () => json(200, { data: user() }),
    })

    await login({ email: 'admin@dispensart.test', password: 'secreto' })

    expect(api.calls.map((c) => `${c.method} ${c.path}`)).toEqual([
      'GET /sanctum/csrf-cookie',
      'POST /api/auth/login',
    ])
    const [loginCall] = api.callsTo('POST', '/api/auth/login')
    expect(loginCall.headers['X-XSRF-TOKEN']).toBe('abc==')
    expect(loginCall.credentials).toBe('same-origin')
    expect(loginCall.headers.Authorization).toBeUndefined()
  })

  it('toda escritura lleva X-XSRF-TOKEN; sin cookie la pide primero', async () => {
    setXsrfCookie(null)
    const api = installFakeApi({
      'GET /sanctum/csrf-cookie': csrfCookieHandler('nuevo'),
      'POST /api/auth/logout': () => noContent(),
    })

    await apiRequest('POST', '/auth/logout')

    expect(api.calls[0].path).toBe('/sanctum/csrf-cookie')
    expect(api.callsTo('POST', '/api/auth/logout')[0].headers['X-XSRF-TOKEN']).toBe('nuevo')
  })

  it('las lecturas no envían X-XSRF-TOKEN ni piden la cookie', async () => {
    const api = installFakeApi({ 'GET /api/auth/me': () => json(200, { data: user() }) })

    await getCurrentUser()

    expect(api.calls).toHaveLength(1)
    expect(api.calls[0].headers['X-XSRF-TOKEN']).toBeUndefined()
  })

  it('Token CSRF vencido y reintento exitoso: renueva la cookie y envía exactamente dos peticiones', async () => {
    setXsrfCookie('viejo')
    const api = installFakeApi({
      'GET /sanctum/csrf-cookie': csrfCookieHandler('renovado'),
      'POST /api/warehouses': [
        () => apiError(419, 'csrf_token_mismatch'),
        () => json(201, { data: { id: 9, code: 'B9', name: 'Bodega 9' } }),
      ],
    })

    const result = await apiRequest<{ data: { id: number } }>('POST', '/warehouses', { code: 'B9' })

    expect(result.data.id).toBe(9)
    const writes = api.callsTo('POST', '/api/warehouses')
    expect(writes).toHaveLength(2)
    expect(writes.map((c) => c.headers['X-XSRF-TOKEN'])).toEqual(['viejo', 'renovado'])
    expect(api.callsTo('GET', '/sanctum/csrf-cookie')).toHaveLength(1)
  })

  it('Token CSRF vencido dos veces: no reintenta más y el mensaje pide recargar', async () => {
    setXsrfCookie('viejo')
    const api = installFakeApi({
      'GET /sanctum/csrf-cookie': csrfCookieHandler('renovado'),
      'POST /api/warehouses': () => apiError(419, 'csrf_token_mismatch'),
    })

    const failure = await apiRequest('POST', '/warehouses', {}).catch((e: unknown) => e)

    expect(failure).toBeInstanceOf(ApiError)
    expect((failure as ApiError).code).toBe('csrf_token_mismatch')
    expect(api.callsTo('POST', '/api/warehouses')).toHaveLength(2)
    expect(errorMessage(failure)).toBe(strings.errors.csrfExpired)
  })

  it('sin sesión (401 unauthenticated) el usuario actual es null, no un error', async () => {
    installFakeApi({ 'GET /api/auth/me': () => apiError(401, 'unauthenticated') })
    await expect(getCurrentUser()).resolves.toBeNull()
  })

  it('un fallo de red y un 502 sin forma de rechazo se muestran como servidor inalcanzable', async () => {
    installFakeApi({
      'GET /api/auth/me': [
        () => Promise.reject(new TypeError('Failed to fetch')),
        () => new Response('<html>Bad Gateway</html>', { status: 502 }),
      ],
    })

    const network = await getCurrentUser().catch((e: unknown) => e)
    const badGateway = await getCurrentUser().catch((e: unknown) => e)

    expect((network as ApiError).code).toBe('network_error')
    expect((badGateway as ApiError).code).toBe('server_error')
    expect(errorMessage(network)).toBe(strings.errors.network)
    expect(errorMessage(badGateway)).toBe(strings.errors.network)
  })
})
