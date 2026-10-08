import type { QueryClient } from '@tanstack/react-query'
import { RouterProvider, type DataRouter } from 'react-router'
import { AppProviders } from '@/app/providers'

// Raíz de la SPA: TanStack Query (ADR-0003) + router en modo datos (design D7).
export default function App({ router, client }: { router: DataRouter; client: QueryClient }) {
  return (
    <AppProviders client={client}>
      <RouterProvider router={router} />
    </AppProviders>
  )
}
