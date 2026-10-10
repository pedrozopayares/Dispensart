import { apiRequest } from '@/lib/api'
import type { NewUser, ResponseOf, User } from '@/lib/api-types'

// Usuarios del sistema (users.manage). La API nunca devuelve la contraseña.

export async function listUsers(): Promise<User[]> {
  const { data } = await apiRequest<ResponseOf<'/users', 'get'>>('GET', '/users')
  return data
}

export async function createUser(body: NewUser): Promise<User> {
  const { data } = await apiRequest<ResponseOf<'/users', 'post'>>('POST', '/users', body)
  return data
}
