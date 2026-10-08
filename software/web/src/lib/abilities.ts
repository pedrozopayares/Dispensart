import type { AuthenticatedUser } from '@/lib/api'

// Capacidades del sistema (S1 identity-access, enum `Ability` de la API). La SPA solo oculta lo que
// el rol no puede hacer; el servidor sigue siendo la autoridad (design D6).
export const ABILITIES = [
  'catalog.view',
  'catalog.manage',
  'users.manage',
  'inventory.view',
  'inventory.adjust',
  'dispensations.create',
  'controlled_drugs.authorize',
  'transfers.view',
  'transfers.create',
  'transfers.receive',
  'transfers.approve',
  'prescriptions.create',
  'patients.view',
] as const

export type Ability = (typeof ABILITIES)[number]

type WithAbilities = Pick<AuthenticatedUser, 'abilities'> | null | undefined

export function can(user: WithAbilities, ability: Ability): boolean {
  return user != null && user.abilities.includes(ability)
}

// `true` si el usuario tiene al menos una de las capacidades.
export function canAny(user: WithAbilities, abilities: readonly Ability[]): boolean {
  return abilities.some((ability) => can(user, ability))
}
