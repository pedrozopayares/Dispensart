import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, expect, vi } from 'vitest'
import { setXsrfCookie, unmatchedRoutes } from '@/test/fake-api'

// Sin globals de Vitest, Testing Library no limpia sola: se desmonta tras cada prueba.
afterEach(() => {
  cleanup()
  vi.unstubAllGlobals()
  setXsrfCookie(null)
  const unmatched = unmatchedRoutes.splice(0)
  expect(unmatched, 'peticiones sin manejador en la prueba').toEqual([])
})
