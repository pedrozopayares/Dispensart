import type { ReactNode } from 'react'
import { Link, useOutletContext } from 'react-router'
import { Button } from '@/components/ui/button'
import { Empty, EmptyContent, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import type { AuthenticatedUser } from '@/lib/api'
import { canAny, type Ability } from '@/lib/abilities'
import { strings } from '@/lib/strings'

// Guarda de ruta por capacidad (design D6): sin la capacidad la pantalla no se monta, así que sus
// consultas nunca se crean. Solo evita pantallas rotas; el servidor sigue siendo la autoridad.
export function RequireAbility({
  anyOf,
  children,
}: {
  anyOf: readonly Ability[]
  children: ReactNode
}) {
  const user = useOutletContext<AuthenticatedUser>()
  if (!canAny(user, anyOf)) return <ForbiddenScreen />
  return children
}

function ForbiddenScreen() {
  return (
    <Empty className="max-w-xl border">
      <EmptyHeader>
        <EmptyTitle>{strings.guard.forbidden}</EmptyTitle>
      </EmptyHeader>
      <EmptyContent>
        <Button asChild variant="outline">
          <Link to="/">{strings.guard.backHome}</Link>
        </Button>
      </EmptyContent>
    </Empty>
  )
}
