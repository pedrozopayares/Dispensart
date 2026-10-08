import { fireEvent, screen, waitFor } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import {
  apiError,
  csrfCookieHandler,
  deferred,
  installFakeApi,
  json,
  user,
} from '@/test/fake-api'
import { renderApp } from '@/test/render-app'

// Tarea 6.4 — app-shell › "Pantalla de inicio de sesión".

const noSession = () => apiError(401, 'unauthenticated')

function fillAndSubmit(email: string, password: string) {
  fireEvent.change(screen.getByLabelText(strings.login.email), { target: { value: email } })
  fireEvent.change(screen.getByLabelText(strings.login.password), { target: { value: password } })
  fireEvent.click(screen.getByRole('button', { name: strings.login.submit }))
}

async function openLogin(loginHandler: Parameters<typeof installFakeApi>[0][string]) {
  const api = installFakeApi({
    'GET /api/auth/me': noSession,
    'GET /sanctum/csrf-cookie': csrfCookieHandler(),
    'POST /api/auth/login': loginHandler,
  })
  const view = renderApp('/login')
  await screen.findByRole('button', { name: strings.login.submit })
  return { api, ...view }
}

describe('pantalla /login', () => {
  it('Ingreso exitoso: navega a / y el encabezado muestra nombre y rol', async () => {
    const regente = user({ name: 'Rita Regente', role: 'regente_farmacia' })
    const { api, router } = await openLogin(() => json(200, { data: regente }))

    fillAndSubmit('  Regente@Dispensart.test ', 'secreto')

    expect(await screen.findByText('Rita Regente')).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
    expect(screen.getByText(strings.roles.regente_farmacia)).toBeInTheDocument()
    expect(api.callsTo('POST', '/api/auth/login')[0].body).toEqual({
      email: 'Regente@Dispensart.test',
      password: 'secreto',
    })
  })

  it('Doble clic: una sola petición y botón "Ingresando…" deshabilitado hasta la respuesta', async () => {
    const pending = deferred<Response>()
    const { api } = await openLogin(() => pending.promise)

    const button = screen.getByRole('button', { name: strings.login.submit })
    fillAndSubmit('admin@dispensart.test', 'secreto')
    fireEvent.click(button)

    const busy = await screen.findByRole('button', { name: strings.login.submitting })
    expect(busy).toBeDisabled()
    fireEvent.click(busy)
    expect(api.callsTo('POST', '/api/auth/login')).toHaveLength(1)

    pending.resolve(json(200, { data: user() }))
    expect(await screen.findByText(strings.shell.logout)).toBeInTheDocument()
    expect(api.callsTo('POST', '/api/auth/login')).toHaveLength(1)
  })

  it('Doble envío por teclado (dos submit seguidos): una sola petición', async () => {
    const pending = deferred<Response>()
    const { api } = await openLogin(() => pending.promise)
    fireEvent.change(screen.getByLabelText(strings.login.email), {
      target: { value: 'admin@dispensart.test' },
    })
    fireEvent.change(screen.getByLabelText(strings.login.password), { target: { value: 'secreto' } })

    const form = screen.getByRole('button', { name: strings.login.submit }).closest('form')!
    fireEvent.submit(form)
    fireEvent.submit(form)
    await screen.findByRole('button', { name: strings.login.submitting })

    expect(api.callsTo('POST', '/api/auth/login')).toHaveLength(1)
    pending.resolve(json(200, { data: user() }))
    await screen.findByText(strings.shell.logout)
  })

  it('Credenciales inválidas: mensaje, conserva el correo, vacía la contraseña y rehabilita', async () => {
    await openLogin(() => json(422, { code: 'invalid_credentials', message: 'x' }))

    fillAndSubmit('admin@dispensart.test', 'mala')

    expect(await screen.findByText(strings.errors.invalidCredentials)).toBeInTheDocument()
    expect(screen.getByLabelText(strings.login.email)).toHaveValue('admin@dispensart.test')
    expect(screen.getByLabelText(strings.login.password)).toHaveValue('')
    expect(screen.getByRole('button', { name: strings.login.submit })).toBeEnabled()
  })

  it('Campos vacíos: "Este campo es obligatorio." y ninguna petición', async () => {
    const { api } = await openLogin(() => json(200, { data: user() }))

    fireEvent.click(screen.getByRole('button', { name: strings.login.submit }))

    expect(await screen.findAllByText(strings.login.required)).toHaveLength(2)
    fillAndSubmit('admin@dispensart.test', '')
    expect(screen.getAllByText(strings.login.required)).toHaveLength(1)
    expect(api.callsTo('POST', '/api/auth/login')).toHaveLength(0)
    expect(api.callsTo('GET', '/sanctum/csrf-cookie')).toHaveLength(0)
  })

  it('Demasiados intentos: mensaje de espera', async () => {
    await openLogin(() =>
      json(429, { code: 'too_many_attempts', message: 'x' }, { 'Retry-After': '60' }),
    )

    fillAndSubmit('admin@dispensart.test', 'mala')

    expect(await screen.findByText(strings.errors.tooManyAttempts)).toBeInTheDocument()
  })

  it.each([
    ['fallo de red', () => Promise.reject(new TypeError('Failed to fetch'))],
    ['error de servidor', () => json(500, { code: 'server_error', message: 'x' })],
  ])('Servidor inalcanzable (%s): mensaje y botón habilitado', async (_caso, handler) => {
    await openLogin(handler)

    fillAndSubmit('admin@dispensart.test', 'secreto')

    expect(await screen.findByText(strings.errors.network)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: strings.login.submit })).toBeEnabled()
  })

  it('Token CSRF vencido dos veces en el login: dos envíos y mensaje de recargar', async () => {
    const { api } = await openLogin(() => apiError(419, 'csrf_token_mismatch'))

    fillAndSubmit('admin@dispensart.test', 'secreto')

    expect(await screen.findByText(strings.errors.csrfExpired)).toBeInTheDocument()
    expect(api.callsTo('POST', '/api/auth/login')).toHaveLength(2)
  })

  it('Usuario ya autenticado: /login lleva a / sin mostrar el formulario', async () => {
    installFakeApi({ 'GET /api/auth/me': () => json(200, { data: user() }) })
    const { router } = renderApp('/login')

    expect(await screen.findByText(strings.shell.logout)).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
    expect(screen.queryByLabelText(strings.login.password)).not.toBeInTheDocument()
  })
})

describe('shell en la raíz sin sesión (runtime-environment › Shell en la raíz)', () => {
  it('muestra el nombre del producto y el mensaje de bienvenida en español', async () => {
    installFakeApi({ 'GET /api/auth/me': noSession })
    renderApp('/')

    expect(
      await screen.findByRole('heading', { level: 1, name: strings.app.name }),
    ).toBeInTheDocument()
    expect(screen.getByText(strings.shell.welcomeMessage)).toBeInTheDocument()
    await waitFor(() => expect(screen.getByLabelText(strings.login.email)).toBeInTheDocument())
  })
})
