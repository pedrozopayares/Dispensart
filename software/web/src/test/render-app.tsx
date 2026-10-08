import { render } from '@testing-library/react'
import { createMemoryRouter } from 'react-router'
import App from '@/App'
import { createAppQueryClient } from '@/app/app-query-client'
import { routes } from '@/app/routes'

// Monta la SPA completa (rutas reales, cliente real) en memoria, empezando en `path`.
export function renderApp(path = '/') {
  const router = createMemoryRouter(routes, { initialEntries: [path] })
  const client = createAppQueryClient(router)
  const view = render(<App router={router} client={client} />)
  return { ...view, router, client }
}
