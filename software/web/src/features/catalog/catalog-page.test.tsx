import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import { installFakeApi } from '@/test/fake-api'
import { apiError, json } from '@/test/http'
import { renderAs } from '@/test/render'
import { renderApp } from '@/test/render-app'

// add-admin-screens — admin-screens (pantalla Catálogo: bodegas y productos). Red simulada en el borde HTTP.

const c = strings.catalog
const WAREHOUSES = 'GET /api/warehouses'
const PRODUCTS = 'GET /api/products'

// Catálogo semilla sintético: 3 bodegas y 6 productos, uno de control especial.
const seedWarehouses = [
  { id: 1, code: 'FC', name: 'Farmacia Central' },
  { id: 2, code: 'FU', name: 'Farmacia Urgencias' },
  { id: 3, code: 'FH', name: 'Farmacia Hospitalización' },
]
const seedProducts = [
  { id: 10, code: 'MED-001', name: 'Acetaminofén 500 mg', presentation: 'Tableta', is_controlled: false },
  { id: 11, code: 'MED-002', name: 'Amoxicilina 500 mg', presentation: 'Cápsula', is_controlled: false },
  { id: 12, code: 'MED-003', name: 'Ibuprofeno 400 mg', presentation: 'Tableta', is_controlled: false },
  { id: 13, code: 'MED-004', name: 'Losartán 50 mg', presentation: 'Tableta', is_controlled: false },
  { id: 14, code: 'MED-005', name: 'Morfina 10 mg/mL', presentation: 'Ampolla', is_controlled: true },
  { id: 15, code: 'MED-006', name: 'Omeprazol 20 mg', presentation: 'Cápsula', is_controlled: false },
]
const listOf = (data: unknown[]) => () => json(200, { data })
const seedRoutes = () => ({ [WAREHOUSES]: listOf(seedWarehouses), [PRODUCTS]: listOf(seedProducts) })

describe('Acceso a las pantallas de administración: Catálogo', () => {
  it('Admin abre Catálogo desde el inicio: título "Catálogo" y secciones "Bodegas" y "Productos"', async () => {
    const { router } = renderAs('admin', '/', seedRoutes())
    const shortcuts = await screen.findByRole('navigation', { name: strings.home.shortcuts })

    fireEvent.click(within(shortcuts).getByRole('link', { name: strings.nav.catalog }))

    expect(await screen.findByRole('heading', { level: 2, name: 'Catálogo' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/catalog')
    expect(screen.getByRole('heading', { name: 'Bodegas' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Productos' })).toBeInTheDocument()
    const menu = screen.getByRole('navigation', { name: strings.nav.label })
    expect(within(menu).getByRole('link', { name: strings.nav.catalog })).toHaveAttribute('aria-current', 'page')
  })

  it('Otro rol escribe la dirección de Catálogo: aviso de permiso y ninguna petición de bodegas ni de productos', async () => {
    const { api, client } = renderAs('regente_farmacia', '/catalog', seedRoutes())

    expect(await screen.findByText(strings.guard.forbidden)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: strings.guard.backHome })).toHaveAttribute('href', '/')
    expect(screen.queryByRole('heading', { name: c.title })).not.toBeInTheDocument()
    await waitFor(() => expect(client.isFetching()).toBe(0))
    expect(api.requestsTo('GET', '/api/warehouses')).toHaveLength(0)
    expect(api.requestsTo('GET', '/api/products')).toHaveLength(0)
  })

  it('Acceso sin sesión: /catalog lleva a /login sin mostrar la pantalla', async () => {
    const api = installFakeApi({ 'GET /api/auth/me': () => apiError(401, 'unauthenticated') })
    const { router } = renderApp('/catalog')

    expect(await screen.findByLabelText(strings.login.email)).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(screen.queryByText(c.title)).not.toBeInTheDocument()
    expect(api.callsTo('GET', '/api/warehouses')).toHaveLength(0)
    expect(api.callsTo('GET', '/api/products')).toHaveLength(0)
  })
})
