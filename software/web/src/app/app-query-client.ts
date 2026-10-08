import type { QueryClient } from '@tanstack/react-query'
import type { DataRouter } from 'react-router'
import { createQueryClient } from '@/lib/query-client'

// Cliente de la SPA: ante `unauthenticated` con el shell abierto descarta la caché y vuelve a
// /login con el aviso de sesión expirada (design D8).
export function createAppQueryClient(router: DataRouter): QueryClient {
  return createQueryClient({
    onUnauthenticated: (client) => {
      client.clear()
      void router.navigate('/login', { replace: true, state: { expired: true } })
    },
  })
}
