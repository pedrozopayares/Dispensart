import { useQueryClient } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { AppProviders } from '@/app/providers'
import { createQueryClient } from '@/lib/query-client'

function ClientProbe() {
  const client = useQueryClient()
  return <output>{client ? 'ok' : ''}</output>
}

describe('proveedor de TanStack Query en la raíz (ADR-0003)', () => {
  it('entrega un QueryClient a los descendientes', () => {
    render(
      <AppProviders>
        <ClientProbe />
      </AppProviders>,
    )
    expect(screen.getByRole('status')).toHaveTextContent('ok')
  })

  it('sin el proveedor, useQueryClient falla', () => {
    // React registra el error lanzado en render; se silencia solo en esta prueba.
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {})
    expect(() => render(<ClientProbe />)).toThrow(/No QueryClient set/)
    spy.mockRestore()
  })

  it('las mutaciones no se reintentan solas', () => {
    expect(createQueryClient().getDefaultOptions().mutations?.retry).toBe(false)
  })
})
