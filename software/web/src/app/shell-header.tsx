import { useRef } from 'react'
import { useNavigate } from 'react-router'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { useLogout } from '@/features/session/session'
import { ApiError, errorMessage, type AuthenticatedUser } from '@/lib/api'
import { roleLabel, strings } from '@/lib/strings'

// Encabezado del shell: producto, usuario, etiqueta de rol en español y cierre de sesión.
export function ShellHeader({ user }: { user: AuthenticatedUser }) {
  const navigate = useNavigate()
  const logout = useLogout()
  // Guardia síncrona: dos clics en el mismo ciclo no alcanzan a ver el botón deshabilitado.
  const inFlight = useRef(false)

  const handleLogout = () => {
    if (inFlight.current) return
    inFlight.current = true
    logout.mutate(undefined, {
      onSuccess: () => void navigate('/login', { replace: true }),
      onSettled: () => {
        inFlight.current = false
      },
    })
  }

  return (
    <header className="flex flex-col gap-3 border-b px-6 py-4">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-xl font-semibold">{strings.app.name}</h1>
        <div className="flex items-center gap-3">
          <span className="text-sm font-medium">{user.name}</span>
          <Badge variant="secondary">{roleLabel(user.role)}</Badge>
          <Button variant="outline" size="sm" disabled={logout.isPending} onClick={handleLogout}>
            {logout.isPending && <Spinner aria-hidden="true" />}
            {logout.isPending ? strings.shell.loggingOut : strings.shell.logout}
          </Button>
        </div>
      </div>
      {logout.isError && (
        <Alert variant="destructive">
          <AlertDescription>
            {logout.error instanceof ApiError && logout.error.code === 'csrf_token_mismatch'
              ? errorMessage(logout.error)
              : strings.shell.logoutFailed}
          </AlertDescription>
        </Alert>
      )}
    </header>
  )
}
