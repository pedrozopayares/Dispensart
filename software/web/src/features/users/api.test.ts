import { describe, expect, it } from 'vitest'
import { createUser, listUsers } from '@/features/users/api'
import { ApiError } from '@/lib/api'
import { json, serveApi, setXsrfCookie } from '@/test/http'

// add-admin-screens 1.2 — usuarios (identity-access) contra la red simulada.
const anaUser = { id: 7, name: 'Ana Sintética', email: 'ana.s13@dispensart.test', role: 'auditor' }

describe('API de usuarios', () => {
  it('lista: GET /api/users devuelve `data` en el orden recibido', async () => {
    const api = serveApi({ 'GET /api/users': () => json(200, { data: [anaUser] }) })

    expect(await listUsers()).toEqual([anaUser])
    expect(api.requestsTo('GET', '/api/users')).toHaveLength(1)
  })

  it('alta: POST /api/users con el cuerpo y la cabecera X-XSRF-TOKEN, devuelve el usuario sin contraseña', async () => {
    setXsrfCookie('token-1')
    const api = serveApi({ 'POST /api/users': () => json(201, { data: anaUser }) })
    const body = {
      name: 'Ana Sintética',
      email: 'ana.s13@dispensart.test',
      password: 'Clave-Sintetica-2026',
      role: 'auditor' as const,
    }

    expect(await createUser(body)).toEqual(anaUser)
    const [sent] = api.requestsTo('POST', '/api/users')
    expect(sent.body).toEqual(body)
    expect(sent.headers['x-xsrf-token']).toBe('token-1')
  })

  it('alta rechazada: `ApiError` con los mensajes por campo', async () => {
    setXsrfCookie('token-1')
    serveApi({
      'POST /api/users': () =>
        json(422, { code: 'validation_failed', message: 'x', errors: { email: ['El valor de correo electrónico ya está en uso.'] } }),
    })

    const error = await createUser({ name: 'A', email: 'a@b.co', password: '12345678', role: 'medico' }).catch(
      (rejection: unknown) => rejection,
    )
    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).errors.email).toEqual(['El valor de correo electrónico ya está en uso.'])
  })
})
