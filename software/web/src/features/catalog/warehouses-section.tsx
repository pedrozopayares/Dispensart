import { Fragment, useId, useRef, useState } from 'react'
import { EmptyState } from '@/components/empty-state'
import { ErrorMessage } from '@/components/error-message'
import { LoadingState } from '@/components/loading-state'
import { SuccessNotice } from '@/components/success-notice'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { FieldGroup } from '@/components/ui/field'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { FormFooter, TextField } from '@/features/catalog/catalog-fields'
import { useCreateWarehouse, useUpdateWarehouse, useWarehouses } from '@/features/catalog/queries'
import { trimmed, useCatalogForm } from '@/features/catalog/use-catalog-form'
import type { Warehouse } from '@/lib/api-types'
import { format, strings } from '@/lib/strings'

const labels = strings.catalog
const w = strings.catalog.warehouses
// Límites de `StoreWarehouseRequest` en la API.
const CODE_MAX = 20
const NAME_MAX = 120

type WarehouseDraft = { code: string; name: string }
const EMPTY: WarehouseDraft = { code: '', name: '' }
const REQUIRED = ['code', 'name'] as const
const toBody = (draft: WarehouseDraft) => ({ code: trimmed(draft.code), name: trimmed(draft.name) })

// Sección "Bodegas" de la pantalla Catálogo: alta, lista y edición en contexto, con carga, vacío y
// error propios (el fallo de productos no la oculta).
export function WarehousesSection() {
  const ids = useId()
  const warehouses = useWarehouses()
  const [notice, setNotice] = useState<string | null>(null)
  const [editingId, setEditingId] = useState<number | null>(null)
  // Botón "Editar" de cada fila, para devolverle el foco al cerrar la edición.
  const editButtons = useRef(new Map<number, HTMLButtonElement>())

  const closeEdit = (warehouseId: number) => {
    setEditingId(null)
    editButtons.current.get(warehouseId)?.focus()
  }

  return (
    <section aria-labelledby={`${ids}-title`} className="flex flex-col gap-4">
      <h3 id={`${ids}-title`} className="text-xl font-semibold">
        {w.title}
      </h3>
      <NewWarehouseForm onCreated={setNotice} />
      {notice !== null && <SuccessNotice message={notice} />}
      {warehouses.isPending ? (
        <LoadingState label={w.loading} />
      ) : warehouses.isError ? (
        <ErrorMessage
          error={warehouses.error}
          onRetry={() => void warehouses.refetch()}
          retrying={warehouses.isFetching}
        />
      ) : warehouses.data.length === 0 ? (
        <EmptyState message={w.empty} />
      ) : (
        <Table>
          <TableCaption className="sr-only">{w.caption}</TableCaption>
          <TableHeader>
            <TableRow>
              <TableHead>{w.columns.code}</TableHead>
              <TableHead>{w.columns.name}</TableHead>
              <TableHead>
                <span className="sr-only">{labels.actions}</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {warehouses.data.map((warehouse) => (
              <Fragment key={warehouse.id}>
                <TableRow>
                  <TableCell>{warehouse.code}</TableCell>
                  <TableCell>{warehouse.name}</TableCell>
                  <TableCell className="text-right">
                    <Button
                      ref={(button) => {
                        if (button === null) editButtons.current.delete(warehouse.id)
                        else editButtons.current.set(warehouse.id, button)
                      }}
                      type="button"
                      variant="outline"
                      size="sm"
                      aria-label={format(labels.editLabel, { name: warehouse.name })}
                      aria-expanded={editingId === warehouse.id}
                      onClick={() => setEditingId(warehouse.id)}
                    >
                      {labels.edit}
                    </Button>
                  </TableCell>
                </TableRow>
                {editingId === warehouse.id && (
                  <TableRow className="hover:bg-transparent">
                    <TableCell colSpan={3} className="whitespace-normal">
                      <EditWarehouseForm
                        warehouse={warehouse}
                        onCancel={() => closeEdit(warehouse.id)}
                        onSaved={(message) => {
                          setNotice(message)
                          closeEdit(warehouse.id)
                        }}
                      />
                    </TableCell>
                  </TableRow>
                )}
              </Fragment>
            ))}
          </TableBody>
        </Table>
      )}
    </section>
  )
}

function NewWarehouseForm({ onCreated }: { onCreated: (message: string) => void }) {
  const ids = useId()
  const create = useCreateWarehouse()
  const form = useCatalogForm(EMPTY, REQUIRED)
  const codeInput = useRef<HTMLInputElement>(null)

  const submit = () =>
    form.submit((draft, callbacks) =>
      create.mutate(toBody(draft), {
        ...callbacks,
        onSuccess: (warehouse) => {
          form.reset()
          onCreated(format(w.created, { name: warehouse.name }))
          codeInput.current?.focus()
        },
      }),
    )

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <h4 id={`${ids}-title`}>{w.form.title}</h4>
        </CardTitle>
      </CardHeader>
      <CardContent>
        <form
          noValidate
          aria-labelledby={`${ids}-title`}
          className="flex flex-col gap-4"
          onSubmit={(event) => {
            event.preventDefault()
            submit()
          }}
        >
          <FieldGroup className="grid gap-4 sm:grid-cols-[12rem_1fr]">
            <TextField
              id={`${ids}-code`}
              inputRef={codeInput}
              label={w.form.code}
              maxLength={CODE_MAX}
              value={form.draft.code}
              error={form.errorOf('code')}
              onChange={(value) => form.set('code', value)}
            />
            <TextField
              id={`${ids}-name`}
              label={w.form.name}
              maxLength={NAME_MAX}
              value={form.draft.name}
              error={form.errorOf('name')}
              onChange={(value) => form.set('name', value)}
            />
          </FieldGroup>
          <FormFooter
            error={form.showGeneralError ? form.submitError : null}
            pending={create.isPending}
            submitLabel={w.form.submit}
            pendingLabel={w.form.submitting}
          />
        </form>
      </CardContent>
    </Card>
  )
}

function EditWarehouseForm({
  warehouse,
  onCancel,
  onSaved,
}: {
  warehouse: Warehouse
  onCancel: () => void
  onSaved: (message: string) => void
}) {
  const ids = useId()
  const update = useUpdateWarehouse()
  const form = useCatalogForm({ code: warehouse.code, name: warehouse.name }, REQUIRED)

  const submit = () =>
    form.submit((draft, callbacks) =>
      update.mutate(
        { id: warehouse.id, changes: toBody(draft) },
        { ...callbacks, onSuccess: (saved) => onSaved(format(w.updated, { name: saved.name })) },
      ),
    )

  return (
    <form
      noValidate
      aria-label={format(w.form.editTitle, { name: warehouse.name })}
      className="flex flex-col gap-4 py-2"
      onSubmit={(event) => {
        event.preventDefault()
        submit()
      }}
      // Escape cierra la edición sin enviar, como "Cancelar".
      onKeyDown={(event) => {
        if (event.key === 'Escape') onCancel()
      }}
    >
      <FieldGroup className="grid gap-4 sm:grid-cols-[12rem_1fr]">
        <TextField
          id={`${ids}-code`}
          label={w.form.code}
          maxLength={CODE_MAX}
          autoFocus
          value={form.draft.code}
          error={form.errorOf('code')}
          onChange={(value) => form.set('code', value)}
        />
        <TextField
          id={`${ids}-name`}
          label={w.form.name}
          maxLength={NAME_MAX}
          value={form.draft.name}
          error={form.errorOf('name')}
          onChange={(value) => form.set('name', value)}
        />
      </FieldGroup>
      <FormFooter
        error={form.showGeneralError ? form.submitError : null}
        pending={update.isPending}
        submitLabel={labels.save}
        pendingLabel={labels.saving}
        onCancel={onCancel}
      />
    </form>
  )
}
