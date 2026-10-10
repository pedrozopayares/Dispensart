import { PageHeader } from '@/components/page-header'
import { ProductsSection } from '@/features/catalog/products-section'
import { WarehousesSection } from '@/features/catalog/warehouses-section'
import { strings } from '@/lib/strings'

// Pantalla Catálogo (/catalog, solo catalog.manage): bodegas y productos, cada sección con su alta,
// su lista, su edición en contexto y sus propios estados de carga, vacío y error.
export function CatalogPage() {
  return (
    <section className="flex w-full max-w-5xl flex-col gap-10">
      <PageHeader title={strings.catalog.title} description={strings.catalog.description} />
      <WarehousesSection />
      <ProductsSection />
    </section>
  )
}
