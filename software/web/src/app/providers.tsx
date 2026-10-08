import { QueryClientProvider } from '@tanstack/react-query'
import { useState, type ReactNode } from 'react'
import { createQueryClient } from '@/lib/query-client'

// Proveedores globales de la raíz de la SPA.
export function AppProviders({ children }: { children: ReactNode }) {
  // Un cliente por montaje: las pruebas no comparten caché entre sí.
  const [queryClient] = useState(createQueryClient)
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
}
