import type { ReactNode } from 'react'
import { Navigate, Outlet } from 'react-router'
import { ShellHeader } from '@/app/shell-header'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { useSession } from '@/features/session/session'
import { strings } from '@/lib/strings'

// Pantalla completa para los estados previos al shell (carga o fallo de la sesión).
export function SessionStatus({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-svh items-center justify-center bg-background p-6 text-foreground">
      {children}
    </div>
  )
}

export function SessionLoading() {
  return (
    <SessionStatus>
      <p className="flex items-center gap-2 text-muted-foreground" aria-live="polite">
        <Spinner aria-hidden="true" />
        {strings.session.loading}
      </p>
    </SessionStatus>
  )
}

// Ruta de diseño protegida: sin sesión no se muestra nada del shell.
export function ProtectedLayout() {
  const session = useSession()

  if (session.isPending) return <SessionLoading />

  if (session.isError) {
    return (
      <SessionStatus>
        <Alert variant="destructive" className="max-w-md">
          <AlertDescription className="flex flex-col items-start gap-3">
            {strings.session.loadFailed}
            <Button
              variant="outline"
              disabled={session.isFetching}
              onClick={() => void session.refetch()}
            >
              {strings.session.retry}
            </Button>
          </AlertDescription>
        </Alert>
      </SessionStatus>
    )
  }

  if (!session.data) return <Navigate to="/login" replace />

  return (
    <div className="flex min-h-svh flex-col bg-background text-foreground">
      <ShellHeader user={session.data} />
      <main className="flex flex-1 items-start justify-center p-6">
        <Outlet context={session.data} />
      </main>
    </div>
  )
}
