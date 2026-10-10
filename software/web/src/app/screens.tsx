import type { ComponentType } from 'react'
import { AssistantPage } from '@/features/assistant/assistant-page'
import { CatalogPage } from '@/features/catalog/catalog-page'
import { DispensationPage } from '@/features/dispensations/dispensation-page'
import { InventoryPage } from '@/features/inventory/inventory-page'
import { KardexPage } from '@/features/kardex/kardex-page'
import { TransferDetailPage } from '@/features/transfers/transfer-detail-page'
import { TransfersPage } from '@/features/transfers/transfers-page'
import { UsersPage } from '@/features/users/users-page'
import { canAny, type Ability } from '@/lib/abilities'
import type { AuthenticatedUser } from '@/lib/api'
import { strings } from '@/lib/strings'

// Tabla única de pantallas (design D6): el menú, el inicio y la guarda leen la misma fila, así no hay
// ruta sin enlace ni enlace sin guarda. Orden = orden del menú.
export type Screen = {
  path: string
  Component: ComponentType
  // Basta una de ellas; sin ninguna, la guarda muestra el aviso de permiso.
  abilities: readonly Ability[]
  navLabel: string
  // Texto del acceso en el inicio.
  description: string
  // Subrutas bajo la misma guarda y el mismo enlace de menú (p. ej. el detalle de un traslado).
  children?: ReadonlyArray<{ path: string; Component: ComponentType }>
}

// Las cuatro pantallas de operación (parte B).
const operationScreens: readonly Screen[] = [
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
]

export const screens: readonly Screen[] = [
  ...operationScreens,
  // Gestión de usuarios y catálogos del admin (§ 3, admin-screens).
  {
    path: '/users',
    Component: UsersPage,
    abilities: ['users.manage'],
    navLabel: strings.nav.users,
    description: strings.users.description,
  },
  {
    path: '/catalog',
    Component: CatalogPage,
    abilities: ['catalog.manage'],
    navLabel: strings.nav.catalog,
    description: strings.catalog.description,
  },
  // Al final, para los roles de operación: quien abre al menos una pantalla de operación. El admin no
  // la ve (assistant-screen «Pantalla Asistente para los roles de operación»); cada herramienta sigue
  // autorizando en el servidor.
  {
    path: '/assistant',
    Component: AssistantPage,
    abilities: operationScreens.flatMap((screen) => screen.abilities),
    navLabel: strings.nav.assistant,
    description: strings.assistant.description,
  },
]

export function screensFor(user: AuthenticatedUser): Screen[] {
  return screens.filter((screen) => canAny(user, screen.abilities))
}
