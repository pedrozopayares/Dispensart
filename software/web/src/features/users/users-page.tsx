import { useId, useRef, useState } from 'react'
import { EmptyState } from '@/components/empty-state'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import { PageHeader } from '@/components/page-header'
import { SubmitButton } from '@/components/submit-button'
import { SuccessNotice } from '@/components/success-notice'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { useCreateUser, useUsers } from '@/features/users/queries'
import { fieldErrors } from '@/lib/api-errors'
import type { User } from '@/lib/api-types'
import { format, roleLabel, strings, type RoleCode } from '@/lib/strings'
import { useSubmitGuard } from '@/lib/use-submit-guard'

const labels = strings.users
const form = strings.users.form

// Roles en el orden del módulo de textos (el mismo de § 3).
const ROLE_OPTIONS = Object.keys(strings.roles).filter((role) => role !== 'unknown') as RoleCode[]

type Draft = { name: string; email: string; password: string; role: RoleCode | '' }
type DraftField = keyof Draft
const FIELDS: readonly DraftField[] = ['name', 'email', 'password', 'role']
const EMPTY: Draft = { name: '', email: '', password: '', role: '' }

// Obligatorios sin espacios de los extremos; formato y unicidad los valida la API.
function requiredErrors(draft: Draft): Partial<Record<DraftField, string>> {
  return Object.fromEntries(
    FIELDS.filter((key) => draft[key].trim() === '').map((key) => [key, strings.common.required]),
  )
}

// Pantalla Usuarios (/users, solo users.manage): alta con rol y lista. La contraseña solo vive en el
// estado del formulario mientras se escribe: nunca se lista, ni va a URL, almacenamiento o consola.
export function UsersPage() {
  return (
    <section className="flex w-full max-w-5xl flex-col gap-6">
      <PageHeader title={labels.title} description={labels.description} />
      <NewUserForm />
      <UsersList />
    </section>
  )
}

function NewUserForm() {
  const ids = useId()
  const create = useCreateUser()
  const guard = useSubmitGuard()
  const nameInput = useRef<HTMLInputElement>(null)
  const [draft, setDraft] = useState<Draft>(EMPTY)
  const [localErrors, setLocalErrors] = useState<Partial<Record<DraftField, string>>>({})
  const [submitError, setSubmitError] = useState<unknown>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const serverErrors = fieldErrors(submitError)
  const errorOf = (key: DraftField) => localErrors[key] ?? serverErrors[key]
  // El rechazo general se muestra si ningún campo lleva su propio mensaje.
  const showGeneralError = submitError !== null && !FIELDS.some((key) => serverErrors[key] !== undefined)

  const set = (key: DraftField, value: string) => setDraft((current) => ({ ...current, [key]: value }))

  const submit = () => {
    const found = requiredErrors(draft)
    setLocalErrors(found)
    if (Object.keys(found).length > 0 || draft.role === '') return
    const role = draft.role
    guard((release) => {
      setSubmitError(null)
      setNotice(null)
      create.mutate(
        { name: draft.name.trim(), email: draft.email.trim(), password: draft.password, role },
        {
          onSuccess: (user) => {
            setDraft(EMPTY)
            setNotice(format(labels.created, { name: user.name }))
            nameInput.current?.focus()
          },
          // El formulario conserva lo escrito ante cualquier rechazo.
          onError: (error) => setSubmitError(error),
          onSettled: release,
        },
      )
    })
  }

  const errorId = (key: DraftField) => `${ids}-${key}-error`
  const fieldProps = (key: DraftField) => ({
    id: `${ids}-${key}`,
    'aria-invalid': errorOf(key) !== undefined || undefined,
    'aria-describedby': errorOf(key) !== undefined ? errorId(key) : undefined,
  })
  const message = (key: DraftField) =>
    errorOf(key) !== undefined && <FieldError id={errorId(key)}>{errorOf(key)}</FieldError>

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <h3 id={`${ids}-title`}>{form.title}</h3>
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form
          noValidate
          aria-labelledby={`${ids}-title`}
          className="flex flex-col gap-6"
          onSubmit={(event) => {
            event.preventDefault()
            submit()
          }}
        >
          <FieldGroup className="grid gap-6 sm:grid-cols-2">
            <Field data-invalid={errorOf('name') !== undefined || undefined}>
              <FieldLabel htmlFor={`${ids}-name`}>{form.name}</FieldLabel>
              <Input
                {...fieldProps('name')}
                ref={nameInput}
                autoComplete="off"
                maxLength={255}
                value={draft.name}
                onChange={(event) => set('name', event.target.value)}
              />
              {message('name')}
            </Field>
            <Field data-invalid={errorOf('email') !== undefined || undefined}>
              <FieldLabel htmlFor={`${ids}-email`}>{form.email}</FieldLabel>
              <Input
                {...fieldProps('email')}
                type="email"
                autoComplete="off"
                maxLength={255}
                value={draft.email}
                onChange={(event) => set('email', event.target.value)}
              />
              {message('email')}
            </Field>
            <Field data-invalid={errorOf('password') !== undefined || undefined}>
              <FieldLabel htmlFor={`${ids}-password`}>{form.password}</FieldLabel>
              <Input
                {...fieldProps('password')}
                type="password"
                autoComplete="new-password"
                maxLength={255}
                aria-describedby={[`${ids}-password-hint`, errorOf('password') && errorId('password')]
                  .filter(Boolean)
                  .join(' ')}
                value={draft.password}
                onChange={(event) => set('password', event.target.value)}
              />
              <FieldDescription id={`${ids}-password-hint`}>{form.passwordHint}</FieldDescription>
              {message('password')}
            </Field>
            <Field data-invalid={errorOf('role') !== undefined || undefined}>
              <FieldLabel htmlFor={`${ids}-role`}>{form.role}</FieldLabel>
              <NativeSelect
                {...fieldProps('role')}
                className="w-full"
                value={draft.role}
                onChange={(event) => set('role', event.target.value)}
              >
                <NativeSelectOption value="">{form.chooseRole}</NativeSelectOption>
                {ROLE_OPTIONS.map((role) => (
                  <NativeSelectOption key={role} value={role}>
                    {strings.roles[role]}
                  </NativeSelectOption>
                ))}
              </NativeSelect>
              {message('role')}
            </Field>
          </FieldGroup>
          {showGeneralError && <ErrorMessage error={submitError} />}
          {notice !== null && <SuccessNotice message={notice} />}
          <div>
            <SubmitButton pending={create.isPending} pendingLabel={form.submitting}>
              {form.submit}
            </SubmitButton>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

function UsersList() {
  const ids = useId()
  const users = useUsers()
  return (
    <section aria-labelledby={`${ids}-list`} className="flex flex-col gap-4">
      <h3 id={`${ids}-list`} className="text-xl font-semibold">
        {labels.caption}
      </h3>
      {users.isPending ? (
        <LoadingState label={labels.loading} />
      ) : users.isError ? (
        <ErrorMessage error={users.error} onRetry={() => void users.refetch()} retrying={users.isFetching} />
      ) : users.data.length === 0 ? (
        <EmptyState message={labels.empty} />
      ) : (
        <UsersTable users={users.data} />
      )}
    </section>
  )
}

function UsersTable({ users }: { users: readonly User[] }) {
  const columns = labels.columns
  return (
    <Table>
      <TableCaption className="sr-only">{labels.caption}</TableCaption>
      <TableHeader>
        <TableRow>
          <TableHead>{columns.name}</TableHead>
          <TableHead>{columns.email}</TableHead>
          <TableHead>{columns.role}</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {users.map((user) => (
          <TableRow key={user.id}>
            <TableCell>{user.name}</TableCell>
            <TableCell>{user.email}</TableCell>
            <TableCell>{roleLabel(user.role)}</TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}
