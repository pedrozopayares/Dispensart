import { LayoutGridIcon } from 'lucide-react'
import { useOutletContext } from 'react-router'
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from '@/components/ui/empty'
import type { AuthenticatedUser } from '@/lib/api'
import { format, strings } from '@/lib/strings'

// Inicio: saludo y estado vacío mientras no existan pantallas de operación.
export function HomePage() {
  const user = useOutletContext<AuthenticatedUser>()
  return (
    <section className="flex w-full max-w-xl flex-col gap-6">
      <h2 className="text-2xl font-semibold">{format(strings.home.greeting, { name: user.name })}</h2>
      <Empty className="border">
        <EmptyHeader>
          <EmptyMedia variant="icon">
            <LayoutGridIcon />
          </EmptyMedia>
          <EmptyTitle>{strings.home.emptyTitle}</EmptyTitle>
          <EmptyDescription>{strings.home.emptyMessage}</EmptyDescription>
        </EmptyHeader>
      </Empty>
    </section>
  )
}
