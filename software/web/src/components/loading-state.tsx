import { Spinner } from '@/components/ui/spinner'

// Carga anunciada a lectores de pantalla (operator-workspace "Disposición común de las pantallas").
export function LoadingState({ label }: { label: string }) {
  return (
    <p role="status" aria-live="polite" className="flex items-center gap-2 text-muted-foreground">
      <Spinner aria-hidden="true" />
      {label}
    </p>
  )
}
