import { fireEvent, render, screen, within } from '@testing-library/react'
import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { ErrorMessage } from '@/components/error-message'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api'
import { strings } from '@/lib/strings'

// Tarea 1.5 — operator-workspace › "Disposición común de las pantallas".

function DialogProbe({ onConfirm }: { onConfirm: () => void }) {
  const [open, setOpen] = useState(false)
  return (
    <>
      <Button onClick={() => setOpen(true)}>Despachar</Button>
      <ConfirmDialog
        open={open}
        onOpenChange={setOpen}
        title="¿Despachar el traslado?"
        description="Las existencias saldrán de la bodega de origen."
        confirmLabel="Confirmar despacho"
        pendingLabel="Despachando…"
        pending={false}
        onConfirm={onConfirm}
      />
    </>
  )
}

describe('disposición común', () => {
  it('Confirmación en diálogo propio: foco dentro, Escape cierra sin enviar, nunca window.confirm', async () => {
    const nativeConfirm = vi.spyOn(window, 'confirm')
    const onConfirm = vi.fn()
    render(<DialogProbe onConfirm={onConfirm} />)

    fireEvent.click(screen.getByRole('button', { name: 'Despachar' }))
    const dialog = await screen.findByRole('alertdialog')
    expect(dialog).toContainElement(document.activeElement as HTMLElement)
    expect(within(dialog).getByRole('button', { name: strings.common.cancel })).toBeInTheDocument()

    fireEvent.keyDown(document.activeElement!, { key: 'Escape' })

    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(onConfirm).not.toHaveBeenCalled()
    expect(nativeConfirm).not.toHaveBeenCalled()
  })

  it('Confirmar invoca la acción dentro del diálogo', async () => {
    const onConfirm = vi.fn()
    render(<DialogProbe onConfirm={onConfirm} />)

    fireEvent.click(screen.getByRole('button', { name: 'Despachar' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Confirmar despacho' }))

    expect(onConfirm).toHaveBeenCalledTimes(1)
  })

  it('Error anunciado: el mensaje vive en una región con rol de alerta, sin el código', () => {
    render(<ErrorMessage error={new ApiError(422, 'lot_expired')} onRetry={() => {}} />)

    const alert = screen.getByRole('alert')
    expect(alert).toHaveTextContent(strings.errors.lotExpired)
    expect(alert).not.toHaveTextContent('lot_expired')
    expect(within(alert).getByRole('button', { name: strings.common.retry })).toBeInTheDocument()
  })

  it('forbidden no ofrece reintentar', () => {
    render(<ErrorMessage error={new ApiError(403, 'forbidden')} onRetry={() => {}} />)

    expect(screen.getByRole('alert')).toHaveTextContent(strings.errors.forbidden)
    expect(screen.queryByRole('button', { name: strings.common.retry })).not.toBeInTheDocument()
  })
})
