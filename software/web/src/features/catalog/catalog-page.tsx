import { PageHeader } from '@/components/page-header'
import { strings } from '@/lib/strings'

const labels = strings.catalog

// Pantalla Catálogo (/catalog, solo catalog.manage): bodegas y productos.
export function CatalogPage() {
  return (
    <section className="flex w-full max-w-5xl flex-col gap-8">
      <PageHeader title={labels.title} description={labels.description} />
      <section className="flex flex-col gap-4">
        <h3 className="text-xl font-semibold">{labels.warehouses.title}</h3>
      </section>
      <section className="flex flex-col gap-4">
        <h3 className="text-xl font-semibold">{labels.products.title}</h3>
      </section>
    </section>
  )
}
