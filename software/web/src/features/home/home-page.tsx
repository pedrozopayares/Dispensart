import { useId } from 'react'
import { Link, useOutletContext } from 'react-router'
import { screensFor } from '@/app/screens'
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import type { AuthenticatedUser } from '@/lib/api'
import { format, strings } from '@/lib/strings'

// Inicio: saludo y un acceso por cada pantalla del menú del rol (operator-workspace "Inicio con
// accesos del rol"). Lee la misma tabla de pantallas que el menú y la guarda (design D6). Todo rol
// tiene al menos un acceso ("Asistente" los de operación; "Usuarios" y "Catálogo" el admin), así que no
// hay estado vacío.
export function HomePage() {
  const ids = useId()
  const user = useOutletContext<AuthenticatedUser>()
  const items = screensFor(user)
  return (
    <section className="flex w-full max-w-4xl flex-col gap-6">
      <h2 className="text-2xl font-semibold">{format(strings.home.greeting, { name: user.name })}</h2>
      <nav aria-label={strings.home.shortcuts}>
        <ul className="grid gap-4 sm:grid-cols-2">
          {items.map((screen) => {
            const descriptionId = `${ids}-${screen.path.slice(1)}`
            // Nombre accesible = título de la pantalla; la descripción queda como descripción, no
            // como nombre (el lector de pantalla anunciaba el enlace-tarjeta vacío).
            return (
              <li key={screen.path}>
                <Link
                  to={screen.path}
                  aria-label={screen.navLabel}
                  aria-describedby={descriptionId}
                  className="block rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                  <Card className="h-full transition-colors hover:bg-accent">
                    <CardHeader>
                      <CardTitle>{screen.navLabel}</CardTitle>
                      <CardDescription id={descriptionId}>{screen.description}</CardDescription>
                    </CardHeader>
                  </Card>
                </Link>
              </li>
            )
          })}
        </ul>
      </nav>
    </section>
  )
}
