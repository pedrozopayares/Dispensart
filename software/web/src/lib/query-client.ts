import { QueryClient } from '@tanstack/react-query'

// Estado del servidor vía TanStack Query (ADR-0003). Las escrituras no se reintentan solas:
// un reintento automático podría duplicar una dispensación; el reintento lo decide el usuario
// reutilizando la misma Idempotency-Key.
export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: 1, refetchOnWindowFocus: false },
      mutations: { retry: false },
    },
  })
}
