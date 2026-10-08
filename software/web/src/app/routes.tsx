import { createBrowserRouter, Navigate, type RouteObject } from 'react-router'
import { ProtectedLayout } from '@/app/protected-layout'
import { RequireAbility } from '@/app/require-ability'
import { screens } from '@/app/screens'
import { HomePage } from '@/features/home/home-page'
import { LoginPage } from '@/features/session/login-page'

// Rutas de la SPA (design D7): `/login` pública; todo lo demás cuelga de la ruta de diseño protegida.
export const routes: RouteObject[] = [
  { path: '/login', element: <LoginPage /> },
  {
    element: <ProtectedLayout />,
    children: [
      { index: true, element: <HomePage /> },
      // Cada pantalla de operación con su guarda, desde la tabla única (design D6).
      ...screens.map(({ path, Component, abilities }) => ({
        path,
        element: (
          <RequireAbility anyOf={abilities}>
            <Component />
          </RequireAbility>
        ),
      })),
      { path: '*', element: <Navigate to="/" replace /> },
    ],
  },
]

export function createAppRouter() {
  return createBrowserRouter(routes)
}
