import { createBrowserRouter, Navigate, type RouteObject } from 'react-router'
import { ProtectedLayout } from '@/app/protected-layout'
import { HomePage } from '@/features/home/home-page'
import { LoginPage } from '@/features/session/login-page'

// Rutas de la SPA (design D7): `/login` pública; todo lo demás cuelga de la ruta de diseño protegida.
export const routes: RouteObject[] = [
  { path: '/login', element: <LoginPage /> },
  {
    element: <ProtectedLayout />,
    children: [
      { index: true, element: <HomePage /> },
      { path: '*', element: <Navigate to="/" replace /> },
    ],
  },
]

export function createAppRouter() {
  return createBrowserRouter(routes)
}
