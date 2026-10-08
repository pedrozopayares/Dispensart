import { NavLink } from 'react-router'
import { buttonVariants } from '@/components/ui/button'
import { screensFor } from '@/app/screens'
import type { AuthenticatedUser } from '@/lib/api'
import { strings } from '@/lib/strings'
import { cn } from '@/lib/utils'

// Menú de pantallas según las capacidades del rol. Enlaces nativos: Tab y Enter los recorren, y
// NavLink marca la página actual con `aria-current="page"`.
export function MainNav({ user }: { user: AuthenticatedUser }) {
  const items = screensFor(user)
  if (items.length === 0) return null
  return (
    <nav aria-label={strings.nav.label}>
      <ul className="flex flex-wrap gap-1">
        {items.map((screen) => (
          <li key={screen.path}>
            <NavLink
              to={screen.path}
              className={({ isActive }) =>
                cn(buttonVariants({ variant: isActive ? 'secondary' : 'ghost', size: 'sm' }))
              }
            >
              {screen.navLabel}
            </NavLink>
          </li>
        ))}
      </ul>
    </nav>
  )
}
