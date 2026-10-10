import { PageHeader } from '@/components/page-header'
import { strings } from '@/lib/strings'

// Pantalla Usuarios (/users, solo users.manage).
export function UsersPage() {
  return (
    <section className="flex w-full max-w-5xl flex-col gap-6">
      <PageHeader title={strings.users.title} description={strings.users.description} />
    </section>
  )
}
