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
import { CheckboxField, FormFooter, TextField } from '@/features/catalog/catalog-fields'
import { useCreateProduct, useProducts, useUpdateProduct } from '@/features/catalog/queries'
import { optional, trimmed, useCatalogForm } from '@/features/catalog/use-catalog-form'
import type { Product } from '@/lib/api-types'
import { format, strings } from '@/lib/strings'

const labels = strings.catalog
const p = strings.catalog.products
// Límites de `StoreProductRequest` en la API.
const CODE_MAX = 30
const TEXT_MAX = 150

type ProductDraft = { code: string; name: string; presentation: string; is_controlled: boolean }
const EMPTY: ProductDraft = { code: '', name: '', presentation: '', is_controlled: false }
const REQUIRED = ['code', 'name'] as const
// Los cuatro campos siempre: presentación en blanco → `null`; la casilla es la marca de RN-05.
const toBody = (draft: ProductDraft) => ({
  code: trimmed(draft.code),
  name: trimmed(draft.name),
  presentation: optional(draft.presentation),
  is_controlled: draft.is_controlled,
})
const toDraft = (product: Product): ProductDraft => ({
  code: product.code,
  name: product.name,
  presentation: product.presentation ?? '',
  is_controlled: product.is_controlled,
})

// Sección "Productos" de la pantalla Catálogo: alta, lista y edición en contexto, con carga, vacío y
// error propios (el fallo de bodegas no la oculta). La marca de control especial se edita aquí y la
// sigue aplicando el servidor al dispensar (RN-05).
export function ProductsSection() {
  const ids = useId()
  const products = useProducts()
  const [notice, setNotice] = useState<string | null>(null)
  const [editingId, setEditingId] = useState<number | null>(null)
  // Botón "Editar" de cada fila, para devolverle el foco al cerrar la edición.
  const editButtons = useRef(new Map<number, HTMLButtonElement>())

  const closeEdit = (productId: number) => {
    setEditingId(null)
    editButtons.current.get(productId)?.focus()
  }

  return (
    <section aria-labelledby={`${ids}-title`} className="flex flex-col gap-4">
      <h3 id={`${ids}-title`} className="text-xl font-semibold">
        {p.title}
      </h3>
      <NewProductForm onCreated={setNotice} />
      {notice !== null && <SuccessNotice message={notice} />}
      {products.isPending ? (
        <LoadingState label={p.loading} />
      ) : products.isError ? (
        <ErrorMessage error={products.error} onRetry={() => void products.refetch()} retrying={products.isFetching} />
      ) : products.data.length === 0 ? (
        <EmptyState message={p.empty} />
      ) : (
        <Table>
          <TableCaption className="sr-only">{p.caption}</TableCaption>
          <TableHeader>
            <TableRow>
              <TableHead>{p.columns.code}</TableHead>
              <TableHead>{p.columns.name}</TableHead>
              <TableHead>{p.columns.presentation}</TableHead>
              <TableHead>{p.columns.controlled}</TableHead>
              <TableHead>
                <span className="sr-only">{labels.actions}</span>
              </TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {products.data.map((product) => (
              <Fragment key={product.id}>
                <TableRow>
                  <TableCell>{product.code}</TableCell>
                  <TableCell>{product.name}</TableCell>
                  <TableCell className={product.presentation === null ? 'text-muted-foreground' : undefined}>
                    {product.presentation ?? p.noPresentation}
                  </TableCell>
                  <TableCell>{product.is_controlled ? labels.yes : labels.no}</TableCell>
                  <TableCell className="text-right">
                    <Button
                      ref={(button) => {
                        if (button === null) editButtons.current.delete(product.id)
                        else editButtons.current.set(product.id, button)
                      }}
                      type="button"
                      variant="outline"
                      size="sm"
                      aria-label={format(labels.editLabel, { name: product.name })}
                      aria-expanded={editingId === product.id}
                      onClick={() => setEditingId(product.id)}
                    >
                      {labels.edit}
                    </Button>
                  </TableCell>
                </TableRow>
                {editingId === product.id && (
                  <TableRow className="hover:bg-transparent">
                    <TableCell colSpan={5} className="whitespace-normal">
                      <EditProductForm
                        product={product}
                        onCancel={() => closeEdit(product.id)}
                        onSaved={(message) => {
                          setNotice(message)
                          closeEdit(product.id)
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

type Form = ReturnType<typeof useCatalogForm<ProductDraft>>

// Los cuatro campos del producto, iguales en el alta y en la edición.
function ProductFields({ ids, form, autoFocus = false }: { ids: string; form: Form; autoFocus?: boolean }) {
  return (
    <FieldGroup className="flex flex-col gap-4">
      <div className="grid gap-4 sm:grid-cols-[12rem_1fr_1fr]">
        <TextField
          id={`${ids}-code`}
          label={p.form.code}
          maxLength={CODE_MAX}
          autoFocus={autoFocus}
          value={form.draft.code}
          error={form.errorOf('code')}
          onChange={(value) => form.set('code', value)}
        />
        <TextField
          id={`${ids}-name`}
          label={p.form.name}
          maxLength={TEXT_MAX}
          value={form.draft.name}
          error={form.errorOf('name')}
          onChange={(value) => form.set('name', value)}
        />
        <TextField
          id={`${ids}-presentation`}
          label={p.form.presentation}
          maxLength={TEXT_MAX}
          value={form.draft.presentation}
          error={form.errorOf('presentation')}
          onChange={(value) => form.set('presentation', value)}
        />
      </div>
      <CheckboxField
        id={`${ids}-controlled`}
        label={p.form.controlled}
        checked={form.draft.is_controlled}
        onChange={(checked) => form.set('is_controlled', checked)}
      />
    </FieldGroup>
  )
}

function NewProductForm({ onCreated }: { onCreated: (message: string) => void }) {
  const ids = useId()
  const create = useCreateProduct()
  const form = useCatalogForm(EMPTY, REQUIRED)

  const submit = () =>
    form.submit((draft, callbacks) =>
      create.mutate(toBody(draft), {
        ...callbacks,
        onSuccess: (product) => {
          form.reset()
          onCreated(format(p.created, { name: product.name }))
          document.getElementById(`${ids}-code`)?.focus()
        },
      }),
    )

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          <h4 id={`${ids}-title`}>{p.form.title}</h4>
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
          <ProductFields ids={ids} form={form} />
          <FormFooter
            error={form.showGeneralError ? form.submitError : null}
            pending={create.isPending}
            submitLabel={p.form.submit}
            pendingLabel={p.form.submitting}
          />
        </form>
      </CardContent>
    </Card>
  )
}

function EditProductForm({
  product,
  onCancel,
  onSaved,
}: {
  product: Product
  onCancel: () => void
  onSaved: (message: string) => void
}) {
  const ids = useId()
  const update = useUpdateProduct()
  const form = useCatalogForm(toDraft(product), REQUIRED)

  const submit = () =>
    form.submit((draft, callbacks) =>
      update.mutate(
        { id: product.id, changes: toBody(draft) },
        { ...callbacks, onSuccess: (saved) => onSaved(format(p.updated, { name: saved.name })) },
      ),
    )

  return (
    <form
      noValidate
      aria-label={format(p.form.editTitle, { name: product.name })}
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
      <ProductFields ids={ids} form={form} autoFocus />
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
