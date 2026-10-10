import { act, fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { Product, Warehouse } from '@/lib/api-types'
import { strings } from '@/lib/strings'
import { installFakeApi } from '@/test/fake-api'
import { apiError, deferred, json, networkError, setXsrfCookie } from '@/test/http'
import { renderAs } from '@/test/render'
import { renderApp } from '@/test/render-app'

// add-admin-screens — admin-screens (pantalla Catálogo: bodegas y productos). Red simulada en el borde HTTP.

const c = strings.catalog
const WAREHOUSES = 'GET /api/warehouses'
const PRODUCTS = 'GET /api/products'

// Catálogo semilla sintético: 3 bodegas y 6 productos, uno de control especial.
const seedWarehouses: Warehouse[] = [
  { id: 1, code: 'FC', name: 'Farmacia Central' },
  { id: 2, code: 'FU', name: 'Farmacia Urgencias' },
  { id: 3, code: 'FH', name: 'Farmacia Hospitalización' },
]
const seedProducts: Product[] = [
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

type Routes = Parameters<typeof renderAs>[2]
type Api = ReturnType<typeof renderAs>['api']

const w = c.warehouses
const p = c.products
const REQUIRED = 'Este campo es obligatorio.'
const NETWORK = 'No pudimos conectar con el servidor. Intenta de nuevo.'
const CODE_TAKEN = 'El valor de código ya está en uso.'

async function openCatalog(routes: Routes = {}) {
  setXsrfCookie('token-1')
  const view = renderAs('admin', '/catalog', { ...seedRoutes(), ...routes })
  await screen.findByRole('table', { name: w.caption })
  await screen.findByRole('table', { name: p.caption })
  return view
}

const region = (name: string) => screen.getByRole('region', { name })
const form = (name: string) => screen.getByRole('form', { name })
const control = (container: HTMLElement, label: string) => within(container).getByLabelText(label) as HTMLInputElement
// Mensaje junto al campo: dentro del mismo `Field` del kit.
const fieldOf = (container: HTMLElement, label: string) =>
  control(container, label).closest('[data-slot="field"]') as HTMLElement
const dataRows = (caption: string) =>
  within(screen.getByRole('table', { name: caption })).getAllByRole('row').slice(1)
const cellsOf = (row: HTMLElement) => within(row).getAllByRole('cell').map((cell) => cell.textContent)
const rowOf = (caption: string, name: string) =>
  dataRows(caption).find((row) => within(row).queryByRole('cell', { name }) !== null) as HTMLElement
const type = (container: HTMLElement, label: string, value: string) =>
  fireEvent.change(control(container, label), { target: { value } })
const writes = (api: Api, method: string, path: string) => api.requestsTo(method, path)
// Peticiones de escritura a cualquier ruta del catálogo.
const anyWrite = (api: Api) => api.requests.filter((request) => request.method !== 'GET')

// Dos clics en el mismo ciclo, antes del re-render que deshabilita el botón (design D4).
function doubleClick(button: HTMLElement) {
  act(() => {
    button.click()
    button.click()
  })
}

async function openEdit(caption: string, name: string, title: string) {
  fireEvent.click(within(rowOf(caption, name)).getByRole('button', { name: `Editar ${name}` }))
  return screen.findByRole('form', { name: title })
}

describe('Lista de bodegas y productos', () => {
  it('Catálogo con los datos semilla: 3 bodegas con código y nombre, 6 productos y uno solo con "Sí"', async () => {
    await openCatalog()

    expect(dataRows(w.caption).map((row) => cellsOf(row).slice(0, 2))).toEqual([
      ['FC', 'Farmacia Central'],
      ['FU', 'Farmacia Urgencias'],
      ['FH', 'Farmacia Hospitalización'],
    ])
    const products = dataRows(p.caption)
    expect(products).toHaveLength(6)
    const headers = within(screen.getByRole('table', { name: p.caption })).getAllByRole('columnheader')
    expect(headers.slice(0, 4).map((cell) => cell.textContent)).toEqual([
      'Código',
      'Nombre',
      'Presentación',
      'Control especial',
    ])
    expect(products.map((row) => cellsOf(row)[3])).toEqual(['No', 'No', 'No', 'No', 'Sí', 'No'])
  })

  it('Producto sin presentación: la fila muestra "Sin presentación"', async () => {
    await openCatalog({
      [PRODUCTS]: listOf([{ id: 30, code: 'MED-030', name: 'Suero oral', presentation: null, is_controlled: false }]),
    })

    expect(cellsOf(rowOf(p.caption, 'Suero oral')).slice(0, 4)).toEqual(['MED-030', 'Suero oral', 'Sin presentación', 'No'])
  })

  it('Cargas anunciadas: "Cargando bodegas…" y "Cargando productos…" en regiones de estado', async () => {
    const warehousesGate = deferred<Response>()
    const productsGate = deferred<Response>()
    renderAs('admin', '/catalog', {
      [WAREHOUSES]: () => warehousesGate.promise,
      [PRODUCTS]: () => productsGate.promise,
    })

    const statuses = await screen.findAllByRole('status')
    expect(statuses.map((status) => status.textContent)).toEqual(['Cargando bodegas…', 'Cargando productos…'])
    warehousesGate.resolve(json(200, { data: seedWarehouses }))
    productsGate.resolve(json(200, { data: seedProducts }))
    expect(await screen.findByRole('cell', { name: 'Farmacia Urgencias' })).toBeInTheDocument()
  })

  it('Secciones vacías: "No hay bodegas registradas." y "No hay productos registrados."', async () => {
    renderAs('admin', '/catalog', { [WAREHOUSES]: listOf([]), [PRODUCTS]: listOf([]) })

    expect(await screen.findByText('No hay bodegas registradas.')).toBeInTheDocument()
    expect(await screen.findByText('No hay productos registrados.')).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('Falla solo una sección: productos con alerta de red y "Reintentar", bodegas con sus 3 filas', async () => {
    const { api } = renderAs('admin', '/catalog', {
      [WAREHOUSES]: listOf(seedWarehouses),
      [PRODUCTS]: [networkError, listOf(seedProducts)],
    })

    const alert = await within(await screen.findByRole('region', { name: p.title })).findByRole('alert')
    expect(alert).toHaveTextContent(NETWORK)
    expect(within(region(w.title)).queryByRole('alert')).not.toBeInTheDocument()
    expect(await screen.findByRole('table', { name: w.caption })).toBeInTheDocument()
    expect(dataRows(w.caption)).toHaveLength(3)

    fireEvent.click(within(alert).getByRole('button', { name: 'Reintentar' }))
    await screen.findByRole('table', { name: p.caption })
    expect(dataRows(p.caption)).toHaveLength(6)
    expect(api.requestsTo('GET', '/api/products')).toHaveLength(2)
  })
})

describe('Alta de bodega', () => {
  const created = { id: 4, code: 'FC2', name: 'Farmacia Consulta Externa' }
  const afterCreate = { [WAREHOUSES]: [listOf(seedWarehouses), listOf([...seedWarehouses, created])] }

  it('Alta exitosa de bodega: un solo POST con el cuerpo, confirmación, formulario vacío y la bodega en la lista', async () => {
    const { api } = await openCatalog({ ...afterCreate, 'POST /api/warehouses': () => json(201, { data: created }) })
    const newForm = form(w.form.title)

    type(newForm, w.form.code, 'FC2')
    type(newForm, w.form.name, 'Farmacia Consulta Externa')
    fireEvent.click(within(newForm).getByRole('button', { name: 'Crear bodega' }))

    expect(await screen.findByText('Bodega Farmacia Consulta Externa creada.')).toBeInTheDocument()
    expect(writes(api, 'POST', '/api/warehouses')).toHaveLength(1)
    expect(writes(api, 'POST', '/api/warehouses')[0].body).toEqual({ code: 'FC2', name: 'Farmacia Consulta Externa' })
    expect(writes(api, 'POST', '/api/warehouses')[0].headers['x-xsrf-token']).toBe('token-1')
    expect(control(newForm, w.form.code)).toHaveValue('')
    expect(control(newForm, w.form.name)).toHaveValue('')
    expect(cellsOf(rowOf(w.caption, 'Farmacia Consulta Externa')).slice(0, 2)).toEqual(['FC2', 'Farmacia Consulta Externa'])
  })

  it.each([
    ['sin código', '', 'Farmacia Consulta Externa', w.form.code],
    ['sin nombre', 'FC2', '  ', w.form.name],
  ])('Bodega con campos vacíos (%s): "Este campo es obligatorio." y ninguna petición', async (_, code, name, invalid) => {
    const { api, client } = await openCatalog({ 'POST /api/warehouses': () => json(201, { data: created }) })
    const newForm = form(w.form.title)

    type(newForm, w.form.code, code)
    type(newForm, w.form.name, name)
    fireEvent.click(within(newForm).getByRole('button', { name: 'Crear bodega' }))

    expect(within(fieldOf(newForm, invalid)).getByText(REQUIRED)).toBeInTheDocument()
    expect(control(newForm, invalid)).toHaveAttribute('aria-invalid', 'true')
    expect(within(newForm).getAllByText(REQUIRED)).toHaveLength(1)
    await waitFor(() => expect(client.isMutating()).toBe(0))
    expect(anyWrite(api)).toHaveLength(0)
  })

  it('Código de bodega en uso: mensaje junto a "Código", formulario conservado y lista sin filas nuevas', async () => {
    const { api } = await openCatalog({
      'POST /api/warehouses': () => json(422, { code: 'validation_failed', message: 'x', errors: { code: [CODE_TAKEN] } }),
    })
    const newForm = form(w.form.title)

    type(newForm, w.form.code, 'FC')
    type(newForm, w.form.name, 'Farmacia Consulta Externa')
    fireEvent.click(within(newForm).getByRole('button', { name: 'Crear bodega' }))

    expect(await within(fieldOf(newForm, w.form.code)).findByText(CODE_TAKEN)).toBeInTheDocument()
    expect(control(newForm, w.form.code)).toHaveValue('FC')
    expect(control(newForm, w.form.name)).toHaveValue('Farmacia Consulta Externa')
    expect(dataRows(w.caption)).toHaveLength(3)
    expect(api.requestsTo('GET', '/api/warehouses')).toHaveLength(1)
  })

  it('Doble clic produce una sola bodega: una sola petición y "Creando bodega…" deshabilitado hasta la respuesta', async () => {
    const gate = deferred<void>()
    const { api } = await openCatalog({
      ...afterCreate,
      'POST /api/warehouses': () => gate.promise.then(() => json(201, { data: created })),
    })
    const newForm = form(w.form.title)
    type(newForm, w.form.code, 'FC2')
    type(newForm, w.form.name, 'Farmacia Consulta Externa')

    doubleClick(within(newForm).getByRole('button', { name: 'Crear bodega' }))

    expect(await within(newForm).findByRole('button', { name: 'Creando bodega…' })).toBeDisabled()
    await waitFor(() => expect(writes(api, 'POST', '/api/warehouses').length).toBeGreaterThan(0))
    gate.resolve()
    expect(await screen.findByText('Bodega Farmacia Consulta Externa creada.')).toBeInTheDocument()
    expect(writes(api, 'POST', '/api/warehouses')).toHaveLength(1)
  })

  it('Fallo de red al crear la bodega: texto de red, formulario conservado y "Crear bodega" habilitado', async () => {
    await openCatalog({ 'POST /api/warehouses': networkError })
    const newForm = form(w.form.title)

    type(newForm, w.form.code, 'FC2')
    type(newForm, w.form.name, 'Farmacia Consulta Externa')
    fireEvent.click(within(newForm).getByRole('button', { name: 'Crear bodega' }))

    expect(await within(newForm).findByRole('alert')).toHaveTextContent(NETWORK)
    expect(control(newForm, w.form.code)).toHaveValue('FC2')
    expect(control(newForm, w.form.name)).toHaveValue('Farmacia Consulta Externa')
    expect(within(newForm).getByRole('button', { name: 'Crear bodega' })).toBeEnabled()
  })
})

describe('Edición de bodega', () => {
  const EDIT = 'Editar bodega Farmacia Central'
  const renamed: Warehouse = { id: 1, code: 'FC', name: 'Farmacia Central Norte' }
  const afterEdit = {
    [WAREHOUSES]: [listOf(seedWarehouses), listOf([renamed, ...seedWarehouses.slice(1)])],
  }

  it('Cambio de nombre de bodega: un solo PATCH con el código actual y el nombre nuevo, confirmación y fila nueva', async () => {
    const { api } = await openCatalog({ ...afterEdit, 'PATCH /api/warehouses/1': () => json(200, { data: renamed }) })

    const editForm = await openEdit(w.caption, 'Farmacia Central', EDIT)
    expect(control(editForm, w.form.code)).toHaveValue('FC')
    expect(control(editForm, w.form.name)).toHaveValue('Farmacia Central')
    type(editForm, w.form.name, 'Farmacia Central Norte')
    fireEvent.click(within(editForm).getByRole('button', { name: 'Guardar cambios' }))

    expect(await screen.findByText('Bodega Farmacia Central Norte actualizada.')).toBeInTheDocument()
    expect(writes(api, 'PATCH', '/api/warehouses/1')).toHaveLength(1)
    expect(writes(api, 'PATCH', '/api/warehouses/1')[0].body).toEqual({ code: 'FC', name: 'Farmacia Central Norte' })
    expect(anyWrite(api)).toHaveLength(1)
    expect(screen.queryByRole('form', { name: EDIT })).not.toBeInTheDocument()
    expect(cellsOf(dataRows(w.caption)[0]).slice(0, 2)).toEqual(['FC', 'Farmacia Central Norte'])
  })

  it('Cancelar la edición de bodega: se cierra, la fila conserva el nombre y no se envía ninguna petición', async () => {
    const { api, client } = await openCatalog({ 'PATCH /api/warehouses/1': () => json(200, { data: renamed }) })

    const editForm = await openEdit(w.caption, 'Farmacia Central', EDIT)
    type(editForm, w.form.name, 'Farmacia Central Norte')
    fireEvent.click(within(editForm).getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('form', { name: EDIT })).not.toBeInTheDocument()
    expect(cellsOf(dataRows(w.caption)[0]).slice(0, 2)).toEqual(['FC', 'Farmacia Central'])
    await waitFor(() => expect(client.isMutating()).toBe(0))
    expect(anyWrite(api)).toHaveLength(0)
  })

  it('Nombre de bodega vaciado: "Este campo es obligatorio." y ninguna petición', async () => {
    const { api, client } = await openCatalog({ 'PATCH /api/warehouses/1': () => json(200, { data: renamed }) })

    const editForm = await openEdit(w.caption, 'Farmacia Central', EDIT)
    type(editForm, w.form.name, '')
    fireEvent.click(within(editForm).getByRole('button', { name: 'Guardar cambios' }))

    expect(within(fieldOf(editForm, w.form.name)).getByText(REQUIRED)).toBeInTheDocument()
    expect(control(editForm, w.form.name)).toHaveAttribute('aria-invalid', 'true')
    await waitFor(() => expect(client.isMutating()).toBe(0))
    expect(anyWrite(api)).toHaveLength(0)
  })

  it('Código de otra bodega: mensaje junto a "Código", la edición sigue abierta con lo escrito y la fila no cambia', async () => {
    const { api } = await openCatalog({
      'PATCH /api/warehouses/1': () => json(422, { code: 'validation_failed', message: 'x', errors: { code: [CODE_TAKEN] } }),
    })

    const editForm = await openEdit(w.caption, 'Farmacia Central', EDIT)
    type(editForm, w.form.code, 'FU')
    fireEvent.click(within(editForm).getByRole('button', { name: 'Guardar cambios' }))

    expect(await within(fieldOf(editForm, w.form.code)).findByText(CODE_TAKEN)).toBeInTheDocument()
    expect(form(EDIT)).toBe(editForm)
    expect(control(editForm, w.form.code)).toHaveValue('FU')
    expect(cellsOf(dataRows(w.caption)[0]).slice(0, 2)).toEqual(['FC', 'Farmacia Central'])
    expect(api.requestsTo('GET', '/api/warehouses')).toHaveLength(1)
  })

  it('Bodega que ya no existe: "El recurso solicitado no existe." en alerta y la edición sigue abierta', async () => {
    await openCatalog({ 'PATCH /api/warehouses/1': () => apiError(404, 'not_found') })

    const editForm = await openEdit(w.caption, 'Farmacia Central', EDIT)
    type(editForm, w.form.name, 'Farmacia Central Norte')
    fireEvent.click(within(editForm).getByRole('button', { name: 'Guardar cambios' }))

    expect(await within(editForm).findByRole('alert')).toHaveTextContent('El recurso solicitado no existe.')
    expect(form(EDIT)).toBe(editForm)
    expect(control(editForm, w.form.name)).toHaveValue('Farmacia Central Norte')
  })

  it('Doble clic al guardar la bodega: una sola petición PATCH y "Guardando…" deshabilitado hasta la respuesta', async () => {
    const gate = deferred<void>()
    const { api } = await openCatalog({
      ...afterEdit,
      'PATCH /api/warehouses/1': () => gate.promise.then(() => json(200, { data: renamed })),
    })

    const editForm = await openEdit(w.caption, 'Farmacia Central', EDIT)
    type(editForm, w.form.name, 'Farmacia Central Norte')
    doubleClick(within(editForm).getByRole('button', { name: 'Guardar cambios' }))

    expect(await within(editForm).findByRole('button', { name: 'Guardando…' })).toBeDisabled()
    await waitFor(() => expect(writes(api, 'PATCH', '/api/warehouses/1').length).toBeGreaterThan(0))
    gate.resolve()
    expect(await screen.findByText('Bodega Farmacia Central Norte actualizada.')).toBeInTheDocument()
    expect(writes(api, 'PATCH', '/api/warehouses/1')).toHaveLength(1)
  })
})

describe('Alta de producto', () => {
  const NEW = { code: 'MED-099', name: 'Hidromorfona 2 mg/mL', presentation: 'Ampolla 1 mL' }
  const created: Product = { id: 40, ...NEW, is_controlled: true }
  const create = () => within(form(p.form.title)).getByRole('button', { name: 'Crear producto' })
  const checkbox = () => within(form(p.form.title)).getByRole('checkbox', { name: p.form.controlled })

  function fillNew(values: Partial<typeof NEW> = NEW) {
    const newForm = form(p.form.title)
    if (values.code !== undefined) type(newForm, p.form.code, values.code)
    if (values.name !== undefined) type(newForm, p.form.name, values.name)
    if (values.presentation !== undefined) type(newForm, p.form.presentation, values.presentation)
  }

  it('Alta de producto de control especial: un solo POST con is_controlled true, confirmación y fila con "Sí"', async () => {
    const { api } = await openCatalog({
      [PRODUCTS]: [listOf(seedProducts), listOf([...seedProducts, created])],
      'POST /api/products': () => json(201, { data: created }),
    })
    expect(checkbox()).not.toBeChecked()

    fillNew()
    fireEvent.click(checkbox())
    fireEvent.click(create())

    expect(await screen.findByText('Producto Hidromorfona 2 mg/mL creado.')).toBeInTheDocument()
    expect(writes(api, 'POST', '/api/products')).toHaveLength(1)
    expect(writes(api, 'POST', '/api/products')[0].body).toEqual({ ...NEW, is_controlled: true })
    expect(writes(api, 'POST', '/api/products')[0].headers['x-xsrf-token']).toBe('token-1')
    expect(cellsOf(rowOf(p.caption, NEW.name)).slice(0, 4)).toEqual([NEW.code, NEW.name, NEW.presentation, 'Sí'])
    expect(control(form(p.form.title), p.form.code)).toHaveValue('')
    expect(checkbox()).not.toBeChecked()
  })

  it('Producto sin campos opcionales: presentation null e is_controlled false; la fila muestra "Sin presentación" y "No"', async () => {
    const plain: Product = { id: 41, code: 'MED-100', name: 'Suero oral', presentation: null, is_controlled: false }
    const { api } = await openCatalog({
      [PRODUCTS]: [listOf(seedProducts), listOf([...seedProducts, plain])],
      'POST /api/products': () => json(201, { data: plain }),
    })

    fillNew({ code: 'MED-100', name: 'Suero oral' })
    fireEvent.click(create())

    expect(await screen.findByText('Producto Suero oral creado.')).toBeInTheDocument()
    expect(writes(api, 'POST', '/api/products')[0].body).toEqual({
      code: 'MED-100',
      name: 'Suero oral',
      presentation: null,
      is_controlled: false,
    })
    expect(cellsOf(rowOf(p.caption, 'Suero oral')).slice(0, 4)).toEqual(['MED-100', 'Suero oral', 'Sin presentación', 'No'])
  })

  it.each([
    ['sin código', { ...NEW, code: '' }, p.form.code],
    ['nombre solo de espacios', { ...NEW, name: '   ' }, p.form.name],
  ])('Producto con campos obligatorios vacíos (%s): "Este campo es obligatorio." y ninguna petición', async (_, values, invalid) => {
    const { api, client } = await openCatalog({ 'POST /api/products': () => json(201, { data: created }) })

    fillNew(values)
    fireEvent.click(create())

    const newForm = form(p.form.title)
    expect(within(fieldOf(newForm, invalid)).getByText(REQUIRED)).toBeInTheDocument()
    expect(within(newForm).getAllByText(REQUIRED)).toHaveLength(1)
    await waitFor(() => expect(client.isMutating()).toBe(0))
    expect(anyWrite(api)).toHaveLength(0)
  })

  it('Código de producto en uso: mensaje junto a "Código", formulario conservado con la casilla y lista sin filas nuevas', async () => {
    const { api } = await openCatalog({
      'POST /api/products': () => json(422, { code: 'validation_failed', message: 'x', errors: { code: [CODE_TAKEN] } }),
    })

    fillNew()
    fireEvent.click(checkbox())
    fireEvent.click(create())

    const newForm = form(p.form.title)
    expect(await within(fieldOf(newForm, p.form.code)).findByText(CODE_TAKEN)).toBeInTheDocument()
    expect(control(newForm, p.form.code)).toHaveValue(NEW.code)
    expect(control(newForm, p.form.name)).toHaveValue(NEW.name)
    expect(control(newForm, p.form.presentation)).toHaveValue(NEW.presentation)
    expect(checkbox()).toBeChecked()
    expect(dataRows(p.caption)).toHaveLength(6)
    expect(api.requestsTo('GET', '/api/products')).toHaveLength(1)
  })

  it('Doble clic produce un solo producto: una sola petición y "Creando producto…" deshabilitado hasta la respuesta', async () => {
    const gate = deferred<void>()
    const { api } = await openCatalog({
      [PRODUCTS]: [listOf(seedProducts), listOf([...seedProducts, created])],
      'POST /api/products': () => gate.promise.then(() => json(201, { data: created })),
    })
    fillNew()

    doubleClick(create())

    expect(await within(form(p.form.title)).findByRole('button', { name: 'Creando producto…' })).toBeDisabled()
    await waitFor(() => expect(writes(api, 'POST', '/api/products').length).toBeGreaterThan(0))
    gate.resolve()
    expect(await screen.findByText('Producto Hidromorfona 2 mg/mL creado.')).toBeInTheDocument()
    expect(writes(api, 'POST', '/api/products')).toHaveLength(1)
  })

  it('El servidor niega el alta de producto: texto de permiso, formulario conservado y lista sin cambios', async () => {
    const { api } = await openCatalog({ 'POST /api/products': () => apiError(403, 'forbidden') })

    fillNew()
    fireEvent.click(create())

    const newForm = form(p.form.title)
    expect(await within(newForm).findByRole('alert')).toHaveTextContent('No tienes permiso para realizar esta acción.')
    expect(control(newForm, p.form.code)).toHaveValue(NEW.code)
    expect(control(newForm, p.form.name)).toHaveValue(NEW.name)
    expect(dataRows(p.caption)).toHaveLength(6)
    expect(api.requestsTo('GET', '/api/products')).toHaveLength(1)
  })
})

describe('Edición de producto', () => {
  const omeprazol = seedProducts[5]
  const EDIT = `Editar producto ${omeprazol.name}`
  const controlled: Product = { ...omeprazol, is_controlled: true }
  const editCheckbox = (editForm: HTMLElement) => within(editForm).getByRole('checkbox', { name: p.form.controlled })
  const save = (editForm: HTMLElement) => within(editForm).getByRole('button', { name: 'Guardar cambios' })
  const replaced = (product: Product) => listOf(seedProducts.map((item) => (item.id === product.id ? product : item)))

  it('Marcar un producto como control especial: un solo PATCH con is_controlled true y los otros tres campos, fila con "Sí"', async () => {
    const { api } = await openCatalog({
      [PRODUCTS]: [listOf(seedProducts), replaced(controlled)],
      'PATCH /api/products/15': () => json(200, { data: controlled }),
    })

    const editForm = await openEdit(p.caption, omeprazol.name, EDIT)
    expect(control(editForm, p.form.code)).toHaveValue('MED-006')
    expect(control(editForm, p.form.presentation)).toHaveValue('Cápsula')
    expect(editCheckbox(editForm)).not.toBeChecked()
    fireEvent.click(editCheckbox(editForm))
    fireEvent.click(save(editForm))

    expect(await screen.findByText('Producto Omeprazol 20 mg actualizado.')).toBeInTheDocument()
    expect(writes(api, 'PATCH', '/api/products/15')).toHaveLength(1)
    expect(writes(api, 'PATCH', '/api/products/15')[0].body).toEqual({
      code: 'MED-006',
      name: 'Omeprazol 20 mg',
      presentation: 'Cápsula',
      is_controlled: true,
    })
    expect(cellsOf(rowOf(p.caption, omeprazol.name))[3]).toBe('Sí')
  })

  it('Quitar la presentación: el PATCH lleva presentation null y la fila muestra "Sin presentación"', async () => {
    const acetaminofen = seedProducts[0]
    const bare: Product = { ...acetaminofen, presentation: null }
    const { api } = await openCatalog({
      [PRODUCTS]: [listOf(seedProducts), replaced(bare)],
      'PATCH /api/products/10': () => json(200, { data: bare }),
    })

    const editForm = await openEdit(p.caption, acetaminofen.name, `Editar producto ${acetaminofen.name}`)
    type(editForm, p.form.presentation, '  ')
    fireEvent.click(save(editForm))

    expect(await screen.findByText('Producto Acetaminofén 500 mg actualizado.')).toBeInTheDocument()
    expect((writes(api, 'PATCH', '/api/products/10')[0].body as { presentation: unknown }).presentation).toBeNull()
    expect(cellsOf(rowOf(p.caption, acetaminofen.name))[2]).toBe('Sin presentación')
  })

  it('Cancelar la edición de producto: se cierra, la fila conserva "Sí" y no se envía ninguna petición', async () => {
    const morfina = seedProducts[4]
    const { api, client } = await openCatalog({ 'PATCH /api/products/14': () => json(200, { data: morfina }) })
    const title = `Editar producto ${morfina.name}`

    const editForm = await openEdit(p.caption, morfina.name, title)
    expect(editCheckbox(editForm)).toBeChecked()
    fireEvent.click(editCheckbox(editForm))
    expect(editCheckbox(editForm)).not.toBeChecked()
    fireEvent.click(within(editForm).getByRole('button', { name: 'Cancelar' }))

    expect(screen.queryByRole('form', { name: title })).not.toBeInTheDocument()
    expect(cellsOf(rowOf(p.caption, morfina.name))[3]).toBe('Sí')
    await waitFor(() => expect(client.isMutating()).toBe(0))
    expect(anyWrite(api)).toHaveLength(0)
  })

  it('Código de otro producto: mensaje junto a "Código", la edición sigue abierta con lo escrito y la fila no cambia', async () => {
    const { api } = await openCatalog({
      'PATCH /api/products/15': () => json(422, { code: 'validation_failed', message: 'x', errors: { code: [CODE_TAKEN] } }),
    })

    const editForm = await openEdit(p.caption, omeprazol.name, EDIT)
    type(editForm, p.form.code, 'MED-001')
    fireEvent.click(save(editForm))

    expect(await within(fieldOf(editForm, p.form.code)).findByText(CODE_TAKEN)).toBeInTheDocument()
    expect(form(EDIT)).toBe(editForm)
    expect(control(editForm, p.form.code)).toHaveValue('MED-001')
    expect(cellsOf(rowOf(p.caption, omeprazol.name)).slice(0, 4)).toEqual(['MED-006', 'Omeprazol 20 mg', 'Cápsula', 'No'])
    expect(api.requestsTo('GET', '/api/products')).toHaveLength(1)
  })

  it('Producto que ya no existe: "El recurso solicitado no existe." en alerta y la edición sigue abierta', async () => {
    await openCatalog({ 'PATCH /api/products/15': () => apiError(404, 'not_found') })

    const editForm = await openEdit(p.caption, omeprazol.name, EDIT)
    fireEvent.click(editCheckbox(editForm))
    fireEvent.click(save(editForm))

    expect(await within(editForm).findByRole('alert')).toHaveTextContent('El recurso solicitado no existe.')
    expect(form(EDIT)).toBe(editForm)
    expect(editCheckbox(editForm)).toBeChecked()
  })

  it('Doble clic al guardar el producto: una sola petición PATCH y "Guardando…" deshabilitado hasta la respuesta', async () => {
    const gate = deferred<void>()
    const { api } = await openCatalog({
      [PRODUCTS]: [listOf(seedProducts), replaced(controlled)],
      'PATCH /api/products/15': () => gate.promise.then(() => json(200, { data: controlled })),
    })

    const editForm = await openEdit(p.caption, omeprazol.name, EDIT)
    fireEvent.click(editCheckbox(editForm))
    doubleClick(save(editForm))

    expect(await within(editForm).findByRole('button', { name: 'Guardando…' })).toBeDisabled()
    await waitFor(() => expect(writes(api, 'PATCH', '/api/products/15').length).toBeGreaterThan(0))
    gate.resolve()
    expect(await screen.findByText('Producto Omeprazol 20 mg actualizado.')).toBeInTheDocument()
    expect(writes(api, 'PATCH', '/api/products/15')).toHaveLength(1)
  })
})
