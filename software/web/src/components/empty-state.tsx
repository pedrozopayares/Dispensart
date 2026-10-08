import { InboxIcon } from 'lucide-react'
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia } from '@/components/ui/empty'

// Resultado vacío de una consulta con los filtros elegidos.
export function EmptyState({ message }: { message: string }) {
  return (
    <Empty className="border">
      <EmptyHeader>
        <EmptyMedia variant="icon">
          <InboxIcon />
        </EmptyMedia>
        <EmptyDescription>{message}</EmptyDescription>
      </EmptyHeader>
    </Empty>
  )
}
