import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import { json } from '@/test/http'
import { catalogRoutes, kardexPage, lots, noAlertsRoute, stockRow, warehouses } from '@/test/fixtures'
import { renderAs } from '@/test/render'

// Tarea 1.7 — operator-workspace › "Guarda de ruta por capacidad" (design D6).
// Tarea 6.1 — operator-workspace › «Navegación por rol» e «Inicio con accesos del rol»; app-shell ›
// «Página de inicio sin pantallas aún» y «Página de inicio con saludo».

const stockRoutes = {
  ...catalogRoutes(),
  ...noAlertsRoute(),
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
      ...noAlertsRoute(),
      'GET /api/stock': () => json(403, { code: 'forbidden', message: 'x' }),
    })

    expect(await screen.findByRole('alert')).toHaveTextContent(strings.errors.forbidden)
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(document.body.textContent).not.toContain('forbidden')
  })
})

const ALL_FOUR = [strings.nav.dispensations, strings.nav.transfers, strings.nav.inventory, strings.nav.kardex]

describe('menú por rol', () => {
  const menuOf = async () => {
    const header = await screen.findByRole('banner')
    await within(header).findByRole('button', { name: strings.shell.logout })
    const nav = within(header).queryByRole('navigation', { name: strings.nav.label })
    return nav === null ? [] : within(nav).getAllByRole('link').map((link) => link.textContent)
  }

  it('Auxiliar ve sus cuatro pantallas, en ese orden', async () => {
    renderAs('auxiliar_farmacia', '/')
    expect(await menuOf()).toEqual(ALL_FOUR)
  })

  it('Auditor ve las cuatro en lectura', async () => {
    renderAs('auditor', '/')
    expect(await menuOf()).toEqual(ALL_FOUR)
  })

  it('el regente ve las cuatro pantallas', async () => {
    renderAs('regente_farmacia', '/')
    expect(await menuOf()).toEqual(ALL_FOUR)
  })

  it('Médico solo ve Dispensación', async () => {
    renderAs('medico', '/')
    expect(await menuOf()).toEqual([strings.nav.dispensations])
  })

  it('Admin sin pantallas de operación', async () => {
    renderAs('admin', '/')
    expect(await menuOf()).toEqual([])
  })

  it('abrir Kardex desde el menú lo marca como página actual', async () => {
    const { router } = renderAs('regente_farmacia', '/', {
      ...catalogRoutes(),
      'GET /api/kardex': () => json(200, kardexPage([])),
    })
    // En el inicio el acceso-tarjeta también se llama "Kardex": se toma el del menú.
    const menu = await screen.findByRole('navigation', { name: strings.nav.label })
    const link = await within(menu).findByRole('link', { name: strings.nav.kardex })
    link.focus()
    expect(link).toHaveFocus()

    fireEvent.click(link)

    expect(await screen.findByRole('heading', { name: strings.kardex.title })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/kardex')
    expect(screen.getByRole('link', { name: strings.nav.kardex })).toHaveAttribute('aria-current', 'page')
    expect(screen.getByRole('link', { name: strings.nav.inventory })).not.toHaveAttribute('aria-current')
  })
})

describe('inicio con accesos del rol', () => {
  const shortcuts = async () => {
    await screen.findByRole('heading', { name: /^Bienvenido, / })
    const nav = screen.queryByRole('navigation', { name: strings.home.shortcuts })
    return nav === null ? [] : within(nav).getAllByRole('link')
  }

  it('Accesos del regente: saludo y cuatro accesos que abren su pantalla', async () => {
    const { router } = renderAs('regente_farmacia', '/', {
      'GET /api/transfers': () => json(200, { data: [], links: {}, meta: { current_page: 1, last_page: 1 } }),
    })

    expect(await screen.findByRole('heading', { name: 'Bienvenido, Regente Demo' })).toBeInTheDocument()
    const links = await shortcuts()
    expect(links.map((link) => link.getAttribute('href'))).toEqual([
      '/dispensations',
      '/transfers',
      '/inventory',
      '/kardex',
    ])
    expect(links[1]).toHaveTextContent(strings.nav.transfers)
    expect(screen.queryByText(strings.home.emptyMessage)).not.toBeInTheDocument()

    fireEvent.click(links[1])
    expect(await screen.findByRole('heading', { name: strings.transfers.title })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/transfers')
  })

  // Hallazgo del recorrido 7.1: el lector de pantalla anunciaba vacíos los enlaces-tarjeta. Cada uno
  // se nombra con el título de su pantalla (exacto, sin la descripción) y la describe aparte.
  it('cada acceso del inicio se nombra con el título de su pantalla', async () => {
    renderAs('auxiliar_farmacia', '/')
    await shortcuts()
    const nav = screen.getByRole('navigation', { name: strings.home.shortcuts })

    for (const [name, description] of [
      [strings.nav.dispensations, strings.dispensation.description],
      [strings.nav.transfers, strings.transfers.description],
      [strings.nav.inventory, strings.inventory.description],
      [strings.nav.kardex, strings.kardex.description],
    ]) {
      expect(within(nav).getByRole('link', { name })).toHaveAccessibleDescription(description)
    }
  })

  it('Admin sin accesos: saludo y "Tu rol no tiene pantallas de operación en esta versión."', async () => {
    renderAs('admin', '/')

    expect(await screen.findByRole('heading', { name: 'Bienvenido, Administrador Demo' })).toBeInTheDocument()
    expect(screen.getByText(strings.home.emptyMessage)).toBeInTheDocument()
    expect(await shortcuts()).toEqual([])
    expect(document.body.textContent).not.toContain('Las pantallas de operación aparecerán aquí.')
  })

  it('Página de inicio con saludo: el auxiliar no ve el estado vacío', async () => {
    renderAs('auxiliar_farmacia', '/')

    expect(await screen.findByRole('heading', { name: 'Bienvenido, Auxiliar Demo' })).toBeInTheDocument()
    expect(await shortcuts()).toHaveLength(4)
    expect(screen.queryByText(strings.home.emptyMessage)).not.toBeInTheDocument()
  })

  it('el médico ve solo el acceso a Dispensación', async () => {
    renderAs('medico', '/')

    expect((await shortcuts()).map((link) => link.getAttribute('href'))).toEqual(['/dispensations'])
  })
})
