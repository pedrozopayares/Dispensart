import { useRef, useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router'
import { SessionLoading } from '@/app/protected-layout'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Spinner } from '@/components/ui/spinner'
import { useLogin, useSession } from '@/features/session/session'
import { ApiError } from '@/lib/api'
import { describeError } from '@/lib/api-errors'
import { strings } from '@/lib/strings'

type FieldErrors = { email?: string; password?: string }

// Pantalla /login: campos obligatorios, un solo envío a la vez, errores por `code` en español.
export function LoginPage() {
  const session = useSession()
  const location = useLocation()
  const navigate = useNavigate()
  const loginMutation = useLogin()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const passwordRef = useRef<HTMLInputElement>(null)
  // Guardia síncrona contra el doble envío: el botón deshabilitado llega un render después.
  const inFlight = useRef(false)

  if (session.isPending) return <SessionLoading />
  if (session.data) return <Navigate to="/" replace />

  const expired = (location.state as { expired?: boolean } | null)?.expired === true

  const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    if (inFlight.current) return
    const errors: FieldErrors = {
      email: email.trim() === '' ? strings.login.required : undefined,
      password: password === '' ? strings.login.required : undefined,
    }
    setFieldErrors(errors)
    if (errors.email || errors.password) return

    inFlight.current = true
    loginMutation.mutate(
      { email: email.trim(), password },
      {
        onSuccess: () => void navigate('/', { replace: true }),
        onError: (error) => {
          if (error instanceof ApiError && error.code === 'invalid_credentials') {
            setPassword('')
            passwordRef.current?.focus()
          }
        },
        onSettled: () => {
          inFlight.current = false
        },
      },
    )
  }

  const pending = loginMutation.isPending

  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-foreground">
      <div className="flex flex-col items-center gap-2 text-center">
        <h1 className="text-2xl font-semibold">{strings.app.name}</h1>
        <p className="max-w-sm text-sm text-muted-foreground">{strings.shell.welcomeMessage}</p>
      </div>
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle>
            <h2>{strings.login.title}</h2>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <form noValidate onSubmit={handleSubmit}>
            <FieldGroup>
              {expired && !loginMutation.isError && (
                <Alert>
                  <AlertDescription>{strings.session.expired}</AlertDescription>
                </Alert>
              )}
              {loginMutation.isError && (
                <Alert variant="destructive">
                  <AlertDescription>{describeError(loginMutation.error)}</AlertDescription>
                </Alert>
              )}
              <Field data-invalid={fieldErrors.email ? true : undefined}>
                <FieldLabel htmlFor="login-email">{strings.login.email}</FieldLabel>
                <Input
                  id="login-email"
                  type="email"
                  autoComplete="username"
                  autoFocus
                  value={email}
                  aria-invalid={fieldErrors.email ? true : undefined}
                  onChange={(event) => setEmail(event.target.value)}
                />
                {fieldErrors.email && <FieldError>{fieldErrors.email}</FieldError>}
              </Field>
              <Field data-invalid={fieldErrors.password ? true : undefined}>
                <FieldLabel htmlFor="login-password">{strings.login.password}</FieldLabel>
                <Input
                  id="login-password"
                  ref={passwordRef}
                  type="password"
                  autoComplete="current-password"
                  value={password}
                  aria-invalid={fieldErrors.password ? true : undefined}
                  onChange={(event) => setPassword(event.target.value)}
                />
                {fieldErrors.password && <FieldError>{fieldErrors.password}</FieldError>}
              </Field>
              <Button type="submit" disabled={pending}>
                {pending && <Spinner aria-hidden="true" />}
                {pending ? strings.login.submitting : strings.login.submit}
              </Button>
            </FieldGroup>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
