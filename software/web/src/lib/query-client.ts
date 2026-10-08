import { MutationCache, QueryCache, QueryClient } from '@tanstack/react-query'
import { ApiError } from '@/lib/api'

type QueryClientOptions = {
  // Reacción global a `unauthenticated` (sesión expirada con el shell abierto, design D8).
  onUnauthenticated?: (client: QueryClient) => void
}

// Estado del servidor vía TanStack Query (ADR-0003). Las escrituras no se reintentan solas:
// un reintento automático podría duplicar una dispensación; el reintento lo decide el usuario
// reutilizando la misma Idempotency-Key.
export function createQueryClient({ onUnauthenticated }: QueryClientOptions = {}): QueryClient {
  const handleError = (error: unknown) => {
    if (error instanceof ApiError && error.code === 'unauthenticated') onUnauthenticated?.(client)
  }
  const client: QueryClient = new QueryClient({
    queryCache: new QueryCache({ onError: handleError }),
    mutationCache: new MutationCache({ onError: handleError }),
    defaultOptions: {
      queries: { retry: 1, refetchOnWindowFocus: false },
      mutations: { retry: false },
    },
  })
  return client
}
