import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import { installFakeApi } from '@/test/fake-api'
import { apiError, json } from '@/test/http'
import { renderAs } from '@/test/render'
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
