import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { getCurrentUser, login, logout, type AuthenticatedUser } from '@/lib/api'

export const sessionQueryKey = ['session', 'me'] as const

// Sesión actual. `null` = sin sesión. Solo cambia por login, logout o un 401: no envejece sola,
// y un fallo se reintenta a mano con "Reintentar".
export function useSession() {
  return useQuery({
    queryKey: sessionQueryKey,
    queryFn: getCurrentUser,
    staleTime: Infinity,
    retry: false,
  })
}

export function useLogin() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: login,
    onSuccess: (user: AuthenticatedUser) => {
      // Ningún dato en caché de un usuario anterior sobrevive al nuevo inicio de sesión.
      queryClient.removeQueries({ predicate: (query) => query.queryKey[0] !== sessionQueryKey[0] })
      queryClient.setQueryData(sessionQueryKey, user)
    },
  })
}

export function useLogout() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: logout,
    onSuccess: () => {
      // Descarta todo lo del usuario y deja la sesión en `null` para que /login no consulte de nuevo.
      queryClient.clear()
      queryClient.setQueryData(sessionQueryKey, null)
    },
  })
}
