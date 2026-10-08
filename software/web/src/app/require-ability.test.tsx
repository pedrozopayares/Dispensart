import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import { json } from '@/test/http'
import { catalogRoutes, kardexPage, lots, stockRow, warehouses } from '@/test/fixtures'
import { renderAs } from '@/test/render'

// Tarea 1.7 — operator-workspace › "Guarda de ruta por capacidad" (design D6).
// Tarea 6.1 (parcial: Inventario y Kardex) — «Navegación por rol».

const stockRoutes = {
  ...catalogRoutes(),
  'GET /api/stock': () => json(200, { data: [stockRow(1, warehouses[0], lots[0], 8)] }),
}

describe('guarda de ruta por capacidad', () => {
  it('Acceso directo sin capacidad: médico en /inventory ve el aviso y no se envía ninguna consulta', async () => {
    const { api, client } = renderAs('medico', '/inventory', stockRoutes)

    expect(await screen.findByText(strings.guard.forbidden)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: strings.guard.backHome })).toHaveAttribute('href', '/')
    // Si la pantalla hubiera creado consultas, se esperan hasta que lleguen al borde HTTP.
    await waitFor(() => expect(client.isFetching()).toBe(0))
    expect(api.requests.map((request) => request.path)).toEqual(['/api/auth/me'])
  })

  it('control positivo: la misma pantalla con capacidad envía su consulta de existencias', async () => {
    const { api } = renderAs('auditor', '/inventory', stockRoutes)

    expect(await screen.findByRole('cell', { name: 'Farmacia Central' })).toBeInTheDocument()
    expect(api.requestsTo('GET', '/api/stock')).toHaveLength(1)
    expect(screen.queryByText(strings.guard.forbidden)).not.toBeInTheDocument()
  })

  it('Acceso directo con capacidad: auditor en /kardex ve la pantalla con sus filtros', async () => {
    renderAs('auditor', '/kardex', {
      ...catalogRoutes(),
      'GET /api/kardex': () => json(200, kardexPage([])),
    })

    expect(await screen.findByRole('heading', { name: strings.kardex.title })).toBeInTheDocument()
    expect(screen.getByLabelText(strings.filters.warehouse)).toBeInTheDocument()
    expect(screen.getByLabelText(strings.filters.product)).toBeInTheDocument()
    expect(screen.getByLabelText(strings.filters.lot)).toBeInTheDocument()
  })

  it('El servidor niega aunque la guarda permita: mensaje en lugar de los datos, sin el código', async () => {
    renderAs('auxiliar_farmacia', '/inventory', {
      ...catalogRoutes(),
      'GET /api/stock': () => json(403, { code: 'forbidden', message: 'x' }),
    })

    expect(await screen.findByRole('alert')).toHaveTextContent(strings.errors.forbidden)
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(document.body.textContent).not.toContain('forbidden')
  })
})

describe('menú por rol (Inventario y Kardex)', () => {
  const menuOf = async () => {
    const header = await screen.findByRole('banner')
    await within(header).findByRole('button', { name: strings.shell.logout })
    const nav = within(header).queryByRole('navigation', { name: strings.nav.label })
    return nav === null ? [] : within(nav).getAllByRole('link').map((link) => link.textContent)
  }

  it('el auxiliar ve Inventario y Kardex, en ese orden', async () => {
    renderAs('auxiliar_farmacia', '/')
    expect(await menuOf()).toEqual([strings.nav.inventory, strings.nav.kardex])
  })

  it('el auditor ve Inventario y Kardex', async () => {
    renderAs('auditor', '/')
    expect(await menuOf()).toEqual([strings.nav.inventory, strings.nav.kardex])
  })

  it.each(['medico', 'admin'] as const)('%s no ve Inventario ni Kardex', async (role) => {
    renderAs(role, '/')
    expect(await menuOf()).toEqual([])
  })

  it('abrir Kardex desde el menú lo marca como página actual', async () => {
    const { router } = renderAs('regente_farmacia', '/', {
      ...catalogRoutes(),
      'GET /api/kardex': () => json(200, kardexPage([])),
    })
    const link = await screen.findByRole('link', { name: strings.nav.kardex })
    link.focus()
    expect(link).toHaveFocus()

    fireEvent.click(link)

    expect(await screen.findByRole('heading', { name: strings.kardex.title })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/kardex')
    expect(screen.getByRole('link', { name: strings.nav.kardex })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: strings.nav.inventory })).not.toHaveAttribute('aria-current')
  })
})
