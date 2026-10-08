import type { ComponentType } from 'react'
import { AssistantPage } from '@/features/assistant/assistant-page'
import { DispensationPage } from '@/features/dispensations/dispensation-page'
import { InventoryPage } from '@/features/inventory/inventory-page'
import { KardexPage } from '@/features/kardex/kardex-page'
import { TransferDetailPage } from '@/features/transfers/transfer-detail-page'
import { TransfersPage } from '@/features/transfers/transfers-page'
import { canAny, type Ability } from '@/lib/abilities'
import type { AuthenticatedUser } from '@/lib/api'
import { strings } from '@/lib/strings'

// Tabla única de pantallas de operación (design D6): el menú y la guarda leen la misma fila, así no
// hay ruta sin enlace ni enlace sin guarda. Orden = orden del menú.
export type Screen = {
  path: string
  Component: ComponentType
  // Basta una de ellas. `'session'`: toda sesión, sin capacidad propia (la ruta del asistente solo
  // exige `auth:sanctum`; cada herramienta autoriza en el servidor).
  abilities: readonly Ability[] | 'session'
  navLabel: string
  // Texto del acceso en el inicio.
  description: string
  // Subrutas bajo la misma guarda y el mismo enlace de menú (p. ej. el detalle de un traslado).
  children?: ReadonlyArray<{ path: string; Component: ComponentType }>
}

export const screens: readonly Screen[] = [
  // Modo consulta para quien solo ve pacientes (auditor, médico).
  {
    path: '/dispensations',
    Component: DispensationPage,
    abilities: ['dispensations.create', 'patients.view'],
    navLabel: strings.nav.dispensations,
    description: strings.dispensation.description,
  },
  {
    path: '/transfers',
    Component: TransfersPage,
    abilities: ['transfers.view'],
    navLabel: strings.nav.transfers,
    description: strings.transfers.description,
    children: [{ path: '/transfers/:id', Component: TransferDetailPage }],
  },
  {
    path: '/inventory',
    Component: InventoryPage,
    abilities: ['inventory.view'],
    navLabel: strings.nav.inventory,
    description: strings.inventory.description,
  },
  {
    path: '/kardex',
    Component: KardexPage,
    abilities: ['inventory.view'],
    navLabel: strings.nav.kardex,
    description: strings.kardex.description,
  },
  // Al final, para toda sesión (operator-workspace «Navegación por rol»).
  {
    path: '/assistant',
    Component: AssistantPage,
    abilities: 'session',
    navLabel: strings.nav.assistant,
    description: strings.assistant.description,
  },
]

export function screensFor(user: AuthenticatedUser): Screen[] {
  return screens.filter((screen) => screen.abilities === 'session' || canAny(user, screen.abilities))
}
