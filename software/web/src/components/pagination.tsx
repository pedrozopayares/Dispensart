import { Button } from '@/components/ui/button'
import { format, strings } from '@/lib/strings'

type PaginationProps = {
  page: number
  lastPage: number
  // Nombre accesible de la región de paginación.
  label: string
  onPage: (page: number) => void
}

// "Anterior" / "Siguiente" con el número de página; los extremos deshabilitan su botón.
export function Pagination({ page, lastPage, label, onPage }: PaginationProps) {
  return (
    <nav className="flex items-center justify-end gap-3" aria-label={label}>
      <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>
        {strings.common.previous}
      </Button>
      <span className="text-sm text-muted-foreground tabular-nums">
        {format(strings.common.page, { page: String(page), total: String(lastPage) })}
      </span>
      <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => onPage(page + 1)}>
        {strings.common.next}
      </Button>
    </nav>
  )
}
