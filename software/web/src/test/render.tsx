import { render } from '@testing-library/react'
import { createMemoryRouter } from 'react-router'
import App from '@/App'
import { createAppQueryClient } from '@/app/app-query-client'
import { routes } from '@/app/routes'
import type { Ability } from '@/lib/abilities'
import type { RoleCode } from '@/lib/strings'
import { json, serveApi } from '@/test/http'

// Mapa rol → capacidades de S1 (identity-access "Mapa de capacidades por rol", enum `Role` de la API).
const pharmacyBase: Ability[] = [
  'catalog.view',
  'inventory.view',
  'dispensations.create',
  'transfers.view',
  'transfers.create',
  'transfers.receive',
  'patients.view',
]

export const abilitiesByRole: Record<RoleCode, Ability[]> = {
  auxiliar_farmacia: pharmacyBase,
  regente_farmacia: [
    ...pharmacyBase,
    'transfers.approve',
    'controlled_drugs.authorize',
    'inventory.adjust',
  ],
  medico: ['catalog.view', 'prescriptions.create', 'patients.view'],
  auditor: ['catalog.view', 'inventory.view', 'transfers.view', 'patients.view'],
  admin: ['catalog.view', 'catalog.manage', 'users.manage'],
}

const namesByRole: Record<RoleCode, string> = {
  auxiliar_farmacia: 'Auxiliar Demo',
  regente_farmacia: 'Regente Demo',
  medico: 'Médico Demo',
  auditor: 'Auditor Demo',
  admin: 'Administrador Demo',
}

export function sessionUser(role: RoleCode) {
  return {
    id: Object.keys(namesByRole).indexOf(role) + 1,
    name: namesByRole[role],
    email: `${role}@dispensart.test`,
    role,
    abilities: abilitiesByRole[role],
  }
}

type Routes = Parameters<typeof serveApi>[0]

// Monta la SPA completa en `path` con la sesión de `role` y la red simulada de `routes` (design D2).
export function renderAs(role: RoleCode, path: string, routesToServe: Routes = {}) {
  const api = serveApi({
    'GET /api/auth/me': () => json(200, { data: sessionUser(role) }),
    ...routesToServe,
  })
  const router = createMemoryRouter(routes, { initialEntries: [path] })
  const client = createAppQueryClient(router)
  // Sin reintento automático de consultas en pruebas: el fallo se ve al primer intento y
  // "Reintentar" es la única repetición.
  const defaults = client.getDefaultOptions()
  client.setDefaultOptions({ ...defaults, queries: { ...defaults.queries, retry: false } })
  const view = render(<App router={router} client={client} />)
  return { ...view, router, client, api }
}
