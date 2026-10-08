import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { strings } from '@/lib/strings'
import { apiError, csrfCookieHandler, deferred, installFakeApi, json, user } from '@/test/fake-api'
import { renderApp } from '@/test/render-app'

// Tarea 6.2 — cada texto visible sale del módulo central (app-shell; runtime-environment ›
// "Textos del shell desde el módulo central"). Plantillas `{x}` aceptan cualquier valor de datos.

function catalogValues(node: unknown): string[] {
  if (typeof node === 'string') return [node]
  if (node && typeof node === 'object') return Object.values(node).flatMap(catalogValues)
  return []
}

function escapeRegExp(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

// Textos visibles que no salen del módulo central. `data` = valores que vienen de la API
// (nombre del usuario), no son textos de interfaz.
function textsOutsideCatalog(root: HTMLElement, data: string[] = []) {
  const patterns = catalogValues(strings).map(
    (value) =>
      new RegExp(`^${escapeRegExp(value).replace(/\\\{\w+\\\}/g, '.+')}$`),
  )
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
  const visible: string[] = []
  while (walker.nextNode()) {
    const text = walker.currentNode.textContent?.trim()
    if (text) visible.push(text)
  }
  const outside = visible.filter(
    (text) => !data.includes(text) && !patterns.some((pattern) => pattern.test(text)),
  )
  return { visible, outside }
}

describe('textos de la SPA desde el módulo central', () => {
  it('pantalla /login con errores de campo y de la API', async () => {
    installFakeApi({
      'GET /api/auth/me': () => apiError(401, 'unauthenticated'),
      'GET /sanctum/csrf-cookie': csrfCookieHandler(),
      'POST /api/auth/login': () => json(422, { code: 'invalid_credentials', message: 'x' }),
    })
    const { container } = renderApp('/login')
    fireEvent.click(await screen.findByRole('button', { name: strings.login.submit }))
    await screen.findAllByText(strings.login.required)
    fireEvent.change(screen.getByLabelText(strings.login.email), { target: { value: 'a@b.test' } })
    fireEvent.change(screen.getByLabelText(strings.login.password), { target: { value: 'x' } })
    fireEvent.click(screen.getByRole('button', { name: strings.login.submit }))
    await screen.findByText(strings.errors.invalidCredentials)

    const { visible, outside } = textsOutsideCatalog(container)
    expect(visible.length).toBeGreaterThanOrEqual(6)
    expect(outside).toEqual([])
  })

  it('carga de sesión, fallo con "Reintentar" y shell con inicio', async () => {
    const me = deferred<Response>()
    installFakeApi({
      'GET /api/auth/me': [
        () => me.promise,
        () => json(200, { data: user({ name: 'Rita Regente', role: 'regente_farmacia' }) }),
      ],
    })
    const { container } = renderApp('/')
    expect(textsOutsideCatalog(container).outside).toEqual([])

    me.resolve(json(503, { code: 'server_error', message: 'x' }))
    await screen.findByText(strings.session.loadFailed)
    expect(textsOutsideCatalog(container).outside).toEqual([])

    fireEvent.click(screen.getByRole('button', { name: strings.session.retry }))
    await screen.findByRole('heading', { name: /^Bienvenido, / })
    const { visible, outside } = textsOutsideCatalog(container, ['Rita Regente'])
    expect(visible).toContain('Bienvenido, Rita Regente')
    expect(outside).toEqual([])
  })

  it('control positivo: un texto fuera del módulo es detectado', () => {
    const { container } = render(
      <p>
        {strings.app.name} <span>Texto suelto</span>
      </p>,
    )
    expect(textsOutsideCatalog(container).outside).toEqual(['Texto suelto'])
  })

  it('control positivo: el código crudo de un rol no pasa como texto del módulo', () => {
    const { container } = render(<span>regente_farmacia</span>)
    expect(textsOutsideCatalog(container).outside).toEqual(['regente_farmacia'])
  })
})
