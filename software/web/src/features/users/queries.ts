import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createUser, listUsers } from '@/features/users/api'
import { queryKeys } from '@/lib/query-keys'

export function useUsers() {
  return useQuery({ queryKey: queryKeys.users(), queryFn: listUsers })
}

// Alta sin reintento automático. Termina cuando la lista ya se volvió a pedir, así la confirmación y
// la fila nueva aparecen juntas. `gcTime: 0`: la contraseña enviada no queda en la caché de mutaciones.
export function useCreateUser() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: createUser,
    retry: false,
    gcTime: 0,
    onSuccess: () => client.invalidateQueries({ queryKey: queryKeys.users() }),
  })
}
