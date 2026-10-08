import { fireEvent, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import { apiError, deferred, installFakeApi, json, setXsrfCookie, user } from '@/test/fake-api'
import { renderApp } from '@/test/render-app'

// Tarea 6.3 — app-shell › "Rutas protegidas por sesión" y "Sesión expirada y token CSRF vencido".
describe('ruta de diseño protegida', () => {
  it('Carga con sesión vigente: "Cargando sesión…" y luego el inicio dentro del shell', async () => {
    const me = deferred<Response>()
    installFakeApi({ 'GET /api/auth/me': () => me.promise })
    renderApp('/')

    expect(screen.getByText(strings.session.loading)).toBeInTheDocument()
    expect(screen.queryByText(strings.shell.logout)).not.toBeInTheDocument()

    me.resolve(json(200, { data: user() }))
    expect(await screen.findByText(strings.home.emptyMessage)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: strings.shell.logout })).toBeInTheDocument()
  })

  it.each(['/', '/inventario'])(
    'Ruta protegida sin sesión (%s): lleva a /login sin contenido del shell',
    async (path) => {
      installFakeApi({ 'GET /api/auth/me': () => apiError(401, 'unauthenticated') })
      const { router } = renderApp(path)

      expect(await screen.findByLabelText(strings.login.email)).toBeInTheDocument()
      expect(router.state.location.pathname).toBe('/login')
      expect(screen.queryByText(strings.shell.logout)).not.toBeInTheDocument()
      expect(screen.queryByText(strings.home.emptyMessage)).not.toBeInTheDocument()
    },
  )

  it('Fallo al consultar la sesión: error con "Reintentar" que repite la consulta', async () => {
    const api = installFakeApi({
      'GET /api/auth/me': [
        () => json(500, { code: 'server_error', message: 'x' }),
        () => json(200, { data: user() }),
      ],
    })
    renderApp('/')

    expect(await screen.findByText(strings.session.loadFailed)).toBeInTheDocument()
    expect(screen.queryByText(strings.shell.logout)).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: strings.session.retry }))

    expect(await screen.findByText(strings.home.emptyMessage)).toBeInTheDocument()
    expect(api.callsTo('GET', '/api/auth/me')).toHaveLength(2)
  })

  it('Sesión expirada durante el uso: un 401 lleva a /login con el aviso', async () => {
    setXsrfCookie('token-1')
    installFakeApi({
      'GET /api/auth/me': [() => json(200, { data: user() }), () => apiError(401, 'unauthenticated')],
      'POST /api/auth/logout': () => apiError(401, 'unauthenticated'),
    })
    const { router, client } = renderApp('/')
    await screen.findByText(strings.home.emptyMessage)
    client.setQueryData(['warehouses'], [{ id: 1, name: 'Bodega del admin' }])

    fireEvent.click(screen.getByRole('button', { name: strings.shell.logout }))

    expect(await screen.findByText(strings.session.expired)).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(client.getQueryData(['warehouses'])).toBeUndefined()
  })
})
