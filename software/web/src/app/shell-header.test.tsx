import { fireEvent, screen, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import {
  apiError,
  csrfCookieHandler,
  deferred,
  installFakeApi,
  json,
  noContent,
  setXsrfCookie,
  user,
} from '@/test/fake-api'
import { renderApp } from '@/test/render-app'

// Tarea 6.5 — app-shell › "Encabezado con sesión y cierre".

const admin = user({ id: 1, name: 'Ana Admin', role: 'admin' })
const auditor = user({ id: 4, name: 'Aurelio Auditor', role: 'auditor', email: 'auditor@dispensart.test' })

async function openShell(routes: Parameters<typeof installFakeApi>[0], who = admin) {
  setXsrfCookie('token-1')
  const api = installFakeApi({ 'GET /api/auth/me': () => json(200, { data: who }), ...routes })
  const view = renderApp('/')
  await screen.findByRole('button', { name: strings.shell.logout })
  return { api, ...view }
}

describe('encabezado del shell', () => {
  it('Etiqueta de rol en español: muestra "Regente de farmacia", nunca el código', async () => {
    await openShell({}, user({ name: 'Rita Regente', role: 'regente_farmacia' }))

    const header = screen.getByRole('banner')
    expect(within(header).getByText('Rita Regente')).toBeInTheDocument()
    expect(within(header).getByText(strings.roles.regente_farmacia)).toBeInTheDocument()
    expect(document.body.textContent).not.toContain('regente_farmacia')
  })

  it('Página de inicio sin pantallas aún: saludo y estado vacío', async () => {
    await openShell({})

    expect(screen.getByRole('heading', { name: 'Bienvenido, Ana Admin' })).toBeInTheDocument()
    expect(screen.getByText(strings.home.emptyMessage)).toBeInTheDocument()
  })

  it('Cierre exitoso: descarta la caché y navega a /login', async () => {
    const { api, router, client } = await openShell({ 'POST /api/auth/logout': () => noContent() })
    client.setQueryData(['warehouses'], [{ id: 1, name: 'Bodega del admin' }])

    fireEvent.click(screen.getByRole('button', { name: strings.shell.logout }))

    expect(await screen.findByLabelText(strings.login.email)).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(client.getQueryData(['warehouses'])).toBeUndefined()
    expect(api.callsTo('POST', '/api/auth/logout')[0].headers['X-XSRF-TOKEN']).toBe('token-1')
    expect(api.callsTo('GET', '/api/auth/me')).toHaveLength(1)
  })

  it('Doble clic en cerrar sesión: una sola petición y botón deshabilitado en curso', async () => {
    const pending = deferred<Response>()
    const { api } = await openShell({ 'POST /api/auth/logout': () => pending.promise })

    const button = screen.getByRole('button', { name: strings.shell.logout })
    fireEvent.click(button)
    fireEvent.click(button)

    const busy = await screen.findByRole('button', { name: strings.shell.loggingOut })
    expect(busy).toBeDisabled()
    expect(api.callsTo('POST', '/api/auth/logout')).toHaveLength(1)

    pending.resolve(noContent())
    expect(await screen.findByLabelText(strings.login.email)).toBeInTheDocument()
    expect(api.callsTo('POST', '/api/auth/logout')).toHaveLength(1)
  })

  it.each([
    ['fallo de red', () => Promise.reject(new TypeError('Failed to fetch'))],
    ['error de servidor', () => json(500, { code: 'server_error', message: 'x' })],
  ])('Cierre fallido (%s): mensaje, sigue en el shell y rehabilita el botón', async (_caso, handler) => {
    const { router } = await openShell({ 'POST /api/auth/logout': handler })

    fireEvent.click(screen.getByRole('button', { name: strings.shell.logout }))

    expect(await screen.findByText(strings.shell.logoutFailed)).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
    expect(screen.getByRole('button', { name: strings.shell.logout })).toBeEnabled()
  })

  it('Token CSRF vencido y reintento exitoso al cerrar: sin error y dos peticiones', async () => {
    const { api, router } = await openShell({
      'GET /sanctum/csrf-cookie': csrfCookieHandler('token-2'),
      'POST /api/auth/logout': [() => apiError(419, 'csrf_token_mismatch'), () => noContent()],
    })

    fireEvent.click(screen.getByRole('button', { name: strings.shell.logout }))

    expect(await screen.findByLabelText(strings.login.email)).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(screen.queryByText(strings.errors.csrfExpired)).not.toBeInTheDocument()
    expect(api.callsTo('POST', '/api/auth/logout')).toHaveLength(2)
  })

  it('Otro usuario no ve datos del anterior: tras cerrar el admin, el auditor ve solo lo suyo', async () => {
    const { client } = await openShell({
      'POST /api/auth/logout': () => noContent(),
      'GET /sanctum/csrf-cookie': csrfCookieHandler('token-2'),
      'POST /api/auth/login': () => json(200, { data: auditor }),
    })
    client.setQueryData(['warehouses'], [{ id: 1, name: 'Bodega del admin' }])

    fireEvent.click(screen.getByRole('button', { name: strings.shell.logout }))
    await screen.findByLabelText(strings.login.email)
    expect(client.getQueryData(['warehouses'])).toBeUndefined()
    fireEvent.change(screen.getByLabelText(strings.login.email), {
      target: { value: 'auditor@dispensart.test' },
    })
    fireEvent.change(screen.getByLabelText(strings.login.password), { target: { value: 'secreto' } })
    fireEvent.click(screen.getByRole('button', { name: strings.login.submit }))

    const header = await screen.findByRole('banner')
    expect(await within(header).findByText('Aurelio Auditor')).toBeInTheDocument()
    expect(within(header).getByText(strings.roles.auditor)).toBeInTheDocument()
    expect(screen.queryByText('Ana Admin')).not.toBeInTheDocument()
    expect(screen.queryByText(/Ana Admin/)).not.toBeInTheDocument()
  })
})
