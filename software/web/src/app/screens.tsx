import type { ComponentType } from 'react'
import { DispensationPage } from '@/features/dispensations/dispensation-page'
import { InventoryPage } from '@/features/inventory/inventory-page'
import { KardexPage } from '@/features/kardex/kardex-page'
import { canAny, type Ability } from '@/lib/abilities'
import type { AuthenticatedUser } from '@/lib/api'
import { strings } from '@/lib/strings'

// Tabla única de pantallas de operación (design D6): el menú y la guarda leen la misma fila, así no
// hay ruta sin enlace ni enlace sin guarda. Orden = orden del menú. Traslados se suma cuando su API
// exista (S4).
export type Screen = {
  path: string
  Component: ComponentType
  // Basta una de ellas.
  abilities: readonly Ability[]
  navLabel: string
}

export const screens: readonly Screen[] = [
  // Modo consulta para quien solo ve pacientes (auditor, médico).
  {
    path: '/dispensations',
    Component: DispensationPage,
    abilities: ['dispensations.create', 'patients.view'],
    navLabel: strings.nav.dispensations,
  },
  {
    path: '/inventory',
    Component: InventoryPage,
    abilities: ['inventory.view'],
    navLabel: strings.nav.inventory,
  },
  { path: '/kardex', Component: KardexPage, abilities: ['inventory.view'], navLabel: strings.nav.kardex },
]

export function screensFor(user: AuthenticatedUser): Screen[] {
  return screens.filter((screen) => canAny(user, screen.abilities))
}
