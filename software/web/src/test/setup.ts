import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterAll, afterEach, beforeAll, expect, vi } from 'vitest'
import { setXsrfCookie, unmatchedRoutes } from '@/test/fake-api'
import { clearRecordedRequests, server, unhandledRequests } from '@/test/http'

// Red simulada de S6 (design D2). Una petición sin manejador se registra y hace fallar la prueba,
// además de rechazar el `fetch` (nunca sale a la red real).
beforeAll(() => {
  server.listen({
    onUnhandledRequest: (request, print) => {
      const url = new URL(request.url)
      unhandledRequests.push(`${request.method} ${url.pathname}`)
      print.error()
    },
  })
})

afterAll(() => server.close())

// Sin globals de Vitest, Testing Library no limpia sola: se desmonta tras cada prueba.
afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  vi.unstubAllEnvs()
  setXsrfCookie(null)
  server.resetHandlers()
  clearRecordedRequests()
  const unmatched = [...unmatchedRoutes.splice(0), ...unhandledRequests.splice(0)]
  expect(unmatched, 'peticiones sin manejador en la prueba').toEqual([])
})
