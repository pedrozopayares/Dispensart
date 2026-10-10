import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { strings } from '@/lib/strings'
import { spyOnConsole } from '@/test/console-spy'
import { installFakeApi } from '@/test/fake-api'
import { apiError, deferred, json, networkError, setXsrfCookie } from '@/test/http'
import { renderAs, sessionUser } from '@/test/render'
import { renderApp } from '@/test/render-app'

// add-admin-screens — admin-screens (pantalla Usuarios). Red simulada en el borde HTTP.

const USERS = 'GET /api/users'

// Usuarios semilla sintéticos, uno por rol (la API los ordena por nombre).
const seedUsers = [
  { id: 1, name: 'Auxiliar Demo', email: 'auxiliar@dispensart.test', role: 'auxiliar_farmacia' },
  { id: 2, name: 'Regente Demo', email: 'regente@dispensart.test', role: 'regente_farmacia' },
  { id: 3, name: 'Médico Demo', email: 'medico@dispensart.test', role: 'medico' },
  { id: 4, name: 'Auditor Demo', email: 'auditor@dispensart.test', role: 'auditor' },
  { id: 5, name: 'Administrador Demo', email: 'admin@dispensart.test', role: 'admin' },
]
const listOf = (data: unknown[]) => () => json(200, { data })

describe('Acceso a las pantallas de administración: Usuarios', () => {
  it('Admin abre Usuarios desde el menú: título "Usuarios" y enlace marcado como página actual', async () => {
    const { router } = renderAs('admin', '/', { [USERS]: listOf(seedUsers) })
    const menu = await screen.findByRole('navigation', { name: strings.nav.label })

    fireEvent.click(within(menu).getByRole('link', { name: strings.nav.users }))

    expect(await screen.findByRole('heading', { level: 2, name: 'Usuarios' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/users')
    expect(within(menu).getByRole('link', { name: strings.nav.users })).toHaveAttribute('aria-current', 'page')
    expect(within(menu).getByRole('link', { name: strings.nav.catalog })).not.toHaveAttribute('aria-current')
  })

  it('Otro rol escribe la dirección de Usuarios: aviso de permiso y ninguna petición a /api/users', async () => {
    const { api, client } = renderAs('auxiliar_farmacia', '/users', { [USERS]: listOf(seedUsers) })

    expect(await screen.findByText(strings.guard.forbidden)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: strings.guard.backHome })).toHaveAttribute('href', '/')
    expect(screen.queryByRole('heading', { name: strings.users.title })).not.toBeInTheDocument()
    await waitFor(() => expect(client.isFetching()).toBe(0))
    expect(api.requestsTo('GET', '/api/users')).toHaveLength(0)
  })

  it('Acceso sin sesión: /users lleva a /login sin mostrar la pantalla', async () => {
    const api = installFakeApi({ 'GET /api/auth/me': () => apiError(401, 'unauthenticated') })
    const { router } = renderApp('/users')

    expect(await screen.findByLabelText(strings.login.email)).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(screen.queryByText(strings.users.title)).not.toBeInTheDocument()
    expect(api.callsTo('GET', '/api/users')).toHaveLength(0)
  })
})

const u = strings.users
const f = strings.users.form
const CREATE = 'POST /api/users'
const ROLE_CODES = ['auxiliar_farmacia', 'regente_farmacia', 'medico', 'auditor', 'admin']

type Routes = Parameters<typeof renderAs>[2]

async function openUsers(routes: Routes = {}) {
  setXsrfCookie('token-1')
  const view = renderAs('admin', '/users', { [USERS]: listOf(seedUsers), ...routes })
  await screen.findByRole('table', { name: u.caption })
  return view
}

const field = (label: string) => screen.getByLabelText(label) as HTMLInputElement | HTMLSelectElement
const createButton = () => screen.getByRole('button', { name: f.submit })
const created = (api: ReturnType<typeof renderAs>['api']) => api.requestsTo('POST', '/api/users')
// Filas de datos de la lista (sin la fila de encabezados).
const dataRows = () => within(screen.getByRole('table', { name: u.caption })).getAllByRole('row').slice(1)
// Mensaje junto al campo: dentro del mismo `Field` del kit.
const fieldMessage = (label: string) => field(label).closest('[data-slot="field"]') as HTMLElement

const NEW_USER = {
  name: 'Nueva Auxiliar',
  email: 'nueva.aux@dispensart.test',
  password: 'Clave-S13-ok',
  role: 'auxiliar_farmacia',
}
const newUserResource = { id: 6, name: NEW_USER.name, email: NEW_USER.email, role: NEW_USER.role }

function fill(values: Partial<typeof NEW_USER> = NEW_USER) {
  if (values.name !== undefined) fireEvent.change(field(f.name), { target: { value: values.name } })
  if (values.email !== undefined) fireEvent.change(field(f.email), { target: { value: values.email } })
  if (values.password !== undefined) fireEvent.change(field(f.password), { target: { value: values.password } })
  if (values.role !== undefined) fireEvent.change(field(f.role), { target: { value: values.role } })
}

function expectFormKept() {
  expect(field(f.name)).toHaveValue(NEW_USER.name)
  expect(field(f.email)).toHaveValue(NEW_USER.email)
  expect(field(f.role)).toHaveValue(NEW_USER.role)
}

describe('Lista de usuarios', () => {
  it('Lista con los usuarios semilla: 5 filas con nombre, correo y etiqueta de rol, sin códigos de rol', async () => {
    const { api } = await openUsers()

    const rows = dataRows()
    expect(rows).toHaveLength(5)
    const table = screen.getByRole('table', { name: u.caption })
    expect(within(table).getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual([
      'Nombre',
      'Correo electrónico',
      'Rol',
    ])
    expect(rows.map((row) => within(row).getAllByRole('cell').map((cell) => cell.textContent))).toEqual([
      ['Auxiliar Demo', 'auxiliar@dispensart.test', 'Auxiliar de farmacia'],
      ['Regente Demo', 'regente@dispensart.test', 'Regente de farmacia'],
      ['Médico Demo', 'medico@dispensart.test', 'Médico'],
      ['Auditor Demo', 'auditor@dispensart.test', 'Auditor'],
      ['Administrador Demo', 'admin@dispensart.test', 'Administrador'],
    ])
    // Los correos semilla pueden contener la palabra (p. ej. "medico@…"): se revisa la columna "Rol".
    const roleCells = rows.map((row) => within(row).getAllByRole('cell')[2].textContent ?? '')
    for (const code of ROLE_CODES) expect(roleCells.filter((cell) => cell.includes(code))).toEqual([])
    expect(table.textContent).not.toContain('auxiliar_farmacia')
    expect(api.requestsTo('GET', '/api/users')).toHaveLength(1)
  })

  it('Carga anunciada: "Cargando usuarios…" en una región de estado', async () => {
    const pending = deferred<Response>()
    renderAs('admin', '/users', { [USERS]: () => pending.promise })

    expect(await screen.findByRole('status')).toHaveTextContent('Cargando usuarios…')
    pending.resolve(json(200, { data: seedUsers }))
    expect(await screen.findByRole('cell', { name: 'Auditor Demo' })).toBeInTheDocument()
  })

  it('Lista vacía: "No hay usuarios registrados." y ninguna tabla con filas', async () => {
    renderAs('admin', '/users', { [USERS]: listOf([]) })

    expect(await screen.findByText('No hay usuarios registrados.')).toBeInTheDocument()
    expect(screen.queryByRole('table', { name: u.caption })).not.toBeInTheDocument()
  })

  it('Fallo de red al listar: alerta con el texto de red y "Reintentar", que repite la consulta', async () => {
    const { api } = renderAs('admin', '/users', { [USERS]: [networkError, listOf(seedUsers)] })

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('No pudimos conectar con el servidor. Intenta de nuevo.')
    fireEvent.click(within(alert).getByRole('button', { name: 'Reintentar' }))

    expect(await screen.findByRole('cell', { name: 'Regente Demo' })).toBeInTheDocument()
    expect(api.requestsTo('GET', '/api/users')).toHaveLength(2)
  })

  it('El servidor niega la lista: texto de permiso, sin filas, sin "Reintentar" y sin el código', async () => {
    renderAs('admin', '/users', { [USERS]: () => json(403, { code: 'forbidden', message: 'x' }) })

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('No tienes permiso para realizar esta acción.')
    expect(within(alert).queryByRole('button', { name: 'Reintentar' })).not.toBeInTheDocument()
    expect(screen.queryByRole('table', { name: u.caption })).not.toBeInTheDocument()
    expect(document.body.textContent).not.toContain('forbidden')
  })
})

describe('Alta de usuario', () => {
  it('Alta exitosa: un solo POST con los datos, confirmación, formulario vacío y la fila nueva en la lista', async () => {
    const { api } = await openUsers({
      [USERS]: [listOf(seedUsers), listOf([...seedUsers, newUserResource])],
      [CREATE]: () => json(201, { data: newUserResource }),
    })
    expect(screen.queryByRole('cell', { name: 'Nueva Auxiliar' })).not.toBeInTheDocument()
    expect(field(f.role)).toHaveValue('')

    fill()
    fireEvent.click(createButton())

    expect(await screen.findByText('Usuario Nueva Auxiliar creado.')).toBeInTheDocument()
    expect(created(api)).toHaveLength(1)
    expect(created(api)[0].body).toEqual({
      name: 'Nueva Auxiliar',
      email: 'nueva.aux@dispensart.test',
      password: 'Clave-S13-ok',
      role: 'auxiliar_farmacia',
    })
    expect(created(api)[0].headers['x-xsrf-token']).toBe('token-1')
    for (const label of [f.name, f.email, f.password, f.role]) expect(field(label)).toHaveValue('')
    const row = (await screen.findByRole('cell', { name: 'Nueva Auxiliar' })).closest('tr') as HTMLElement
    expect(within(row).getByRole('cell', { name: 'Auxiliar de farmacia' })).toBeInTheDocument()
    expect(dataRows()).toHaveLength(6)
  })

  it.each([
    ['todo vacío', {}, [f.name, f.email, f.password, f.role]],
    ['nombre solo de espacios', { ...NEW_USER, name: '   ' }, [f.name]],
    ['sin elegir rol', { ...NEW_USER, role: '' }, [f.role]],
  ] as const)('Campos vacíos sin envío (%s): "Este campo es obligatorio." y ninguna petición', async (_, values, invalid) => {
    const { api, client } = await openUsers({ [CREATE]: () => json(201, { data: newUserResource }) })

    fill(values)
    fireEvent.click(createButton())

    for (const label of [f.name, f.email, f.password, f.role]) {
      const expected = (invalid as readonly string[]).includes(label)
      expect(within(fieldMessage(label)).queryByText('Este campo es obligatorio.') !== null).toBe(expected)
      if (expected) expect(field(label)).toHaveAttribute('aria-invalid', 'true')
    }
    await waitFor(() => expect(client.isMutating()).toBe(0))
    expect(created(api)).toHaveLength(0)
  })

  it('Correo ya en uso: mensaje junto a "Correo electrónico", formulario conservado y lista sin filas nuevas', async () => {
    const message = 'El valor de correo electrónico ya está en uso.'
    const { api } = await openUsers({
      [CREATE]: () => json(422, { code: 'validation_failed', message: 'x', errors: { email: [message] } }),
    })

    fill()
    fireEvent.click(createButton())

    expect(await within(fieldMessage(f.email)).findByText(message)).toBeInTheDocument()
    expect(field(f.email)).toHaveAttribute('aria-invalid', 'true')
    expectFormKept()
    expect(createButton()).toBeEnabled()
    expect(dataRows()).toHaveLength(5)
    expect(api.requestsTo('GET', '/api/users')).toHaveLength(1)
  })

  it('Contraseña demasiado corta: mensaje junto a "Contraseña" y sin confirmación', async () => {
    const message = 'El campo contraseña debe tener al menos 8 caracteres.'
    await openUsers({
      [CREATE]: () => json(422, { code: 'validation_failed', message: 'x', errors: { password: [message] } }),
    })

    fill({ ...NEW_USER, password: 'corta' })
    fireEvent.click(createButton())

    expect(await within(fieldMessage(f.password)).findByText(message)).toBeInTheDocument()
    expect(screen.queryByText(/^Usuario .* creado\.$/)).not.toBeInTheDocument()
  })

  it('Doble clic produce un solo alta: una sola petición y "Creando usuario…" deshabilitado hasta la respuesta', async () => {
    const pending = deferred<Response>()
    const { api } = await openUsers({
      [USERS]: [listOf(seedUsers), listOf([...seedUsers, newUserResource])],
      [CREATE]: () => pending.promise,
    })
    fill()

    const button = createButton()
    fireEvent.click(button)
    fireEvent.click(button)

    expect(await screen.findByRole('button', { name: f.submitting })).toBeDisabled()
    await waitFor(() => expect(created(api).length).toBeGreaterThan(0))
    pending.resolve(json(201, { data: newUserResource }))
    expect(await screen.findByText('Usuario Nueva Auxiliar creado.')).toBeInTheDocument()
    expect(created(api)).toHaveLength(1)
  })

  it('Enter repetido: dos envíos seguidos del formulario producen una sola petición', async () => {
    const pending = deferred<Response>()
    const { api } = await openUsers({
      [USERS]: [listOf(seedUsers), listOf([...seedUsers, newUserResource])],
      [CREATE]: () => pending.promise,
    })
    fill()

    const form = screen.getByRole('form', { name: f.title })
    fireEvent.submit(form)
    fireEvent.submit(form)

    await waitFor(() => expect(created(api).length).toBeGreaterThan(0))
    pending.resolve(json(201, { data: newUserResource }))
    expect(await screen.findByText('Usuario Nueva Auxiliar creado.')).toBeInTheDocument()
    expect(created(api)).toHaveLength(1)
  })

  it('El servidor niega el alta: texto de permiso, formulario conservado y lista sin cambios', async () => {
    const { api } = await openUsers({ [CREATE]: () => apiError(403, 'forbidden') })

    fill()
    fireEvent.click(createButton())

    expect(await screen.findByRole('alert')).toHaveTextContent('No tienes permiso para realizar esta acción.')
    expectFormKept()
    expect(dataRows()).toHaveLength(5)
    expect(api.requestsTo('GET', '/api/users')).toHaveLength(1)
  })

  it('Fallo de red al crear: texto de red, formulario conservado y "Crear usuario" habilitado', async () => {
    await openUsers({ [CREATE]: networkError })

    fill()
    fireEvent.click(createButton())

    expect(await screen.findByRole('alert')).toHaveTextContent('No pudimos conectar con el servidor. Intenta de nuevo.')
    expectFormKept()
    expect(field(f.password)).toHaveValue(NEW_USER.password)
    expect(createButton()).toBeEnabled()
  })

  it('Sesión expirada al crear: navega a /login con el aviso de app-shell', async () => {
    const { router } = await openUsers({
      'GET /api/auth/me': [() => json(200, { data: sessionUser('admin') }), () => apiError(401, 'unauthenticated')],
      [CREATE]: () => apiError(401, 'unauthenticated'),
    })

    fill()
    fireEvent.click(createButton())

    // Primero la ruta de login: el mismo texto aparece un instante en la alerta del formulario, y
    // "Correo electrónico" también rotula el alta; se espera el botón propio del login.
    await waitFor(() => expect(router.state.location.pathname).toBe('/login'))
    expect(await screen.findByRole('button', { name: strings.login.submit })).toBeInTheDocument()
    expect(screen.getByText('Tu sesión expiró. Inicia sesión de nuevo.')).toBeInTheDocument()
    expect(screen.queryByRole('form', { name: f.title })).not.toBeInTheDocument()
  })
})

describe('Contraseña nunca visible', () => {
  const SECRET = 'Clave-Sintetica-2026'

  // Barrido: texto y valores de campo del documento, almacenamientos, URL (router y ventana) y cada
  // argumento de cada llamada a la consola.
  function sweeper(router: ReturnType<typeof renderAs>['router'], spies: ReturnType<typeof spyOnConsole>) {
    const dump = (storage: Storage) =>
      Array.from({ length: storage.length }, (_, i) => `${storage.key(i)}=${storage.getItem(storage.key(i) ?? '')}`)
    return {
      document: () =>
        [
          document.body.textContent ?? '',
          ...Array.from(document.querySelectorAll('input, textarea, select'), (control) => (control as HTMLInputElement).value),
        ].filter((value) => value.includes(SECRET)),
      outside: () =>
        [
          ...dump(localStorage),
          ...dump(sessionStorage),
          JSON.stringify(router.state.location),
          window.location.href,
          ...spies.flatMap((spy) => vi.mocked(spy).mock.calls.map((call) => JSON.stringify(call))),
        ].filter((value) => value.includes(SECRET)),
    }
  }

  it('Campo enmascarado: tipo contraseña y ningún texto visible con la contraseña', async () => {
    await openUsers()

    fireEvent.change(field(f.password), { target: { value: SECRET } })

    expect(field(f.password)).toHaveAttribute('type', 'password')
    expect(field(f.password)).toHaveValue(SECRET)
    expect(document.body.textContent).not.toContain(SECRET)
  })

  it('Contraseña fuera del documento tras el alta: ni texto, ni campos, ni almacenamiento, ni URL, ni consola', async () => {
    const spies = spyOnConsole()
    const { router } = await openUsers({
      [USERS]: [listOf(seedUsers), listOf([...seedUsers, newUserResource])],
      [CREATE]: () => json(201, { data: newUserResource }),
    })
    const sweep = sweeper(router, spies)

    fill({ ...NEW_USER, password: SECRET })
    // Control positivo: el mismo barrido encuentra la contraseña en el valor del campo antes de enviar.
    expect(sweep.document()).toHaveLength(1)

    fireEvent.click(createButton())
    expect(await screen.findByText('Usuario Nueva Auxiliar creado.')).toBeInTheDocument()

    expect(sweep.document()).toEqual([])
    expect(sweep.outside()).toEqual([])
  })

  it('Contraseña fuera de la consola tras un fallo: ni consola, ni URL, ni almacenamiento; el campo la conserva enmascarada', async () => {
    const spies = spyOnConsole()
    const { router } = await openUsers({ [CREATE]: networkError })
    const sweep = sweeper(router, spies)

    fill({ ...NEW_USER, password: SECRET })
    fireEvent.click(createButton())
    expect(await screen.findByRole('alert')).toHaveTextContent('No pudimos conectar con el servidor. Intenta de nuevo.')

    expect(sweep.outside()).toEqual([])
    expect(field(f.password)).toHaveValue(SECRET)
    expect(field(f.password)).toHaveAttribute('type', 'password')
  })
})
