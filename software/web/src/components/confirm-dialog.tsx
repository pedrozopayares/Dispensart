import type { ReactNode } from 'react'
import { SubmitButton } from '@/components/submit-button'
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { strings } from '@/lib/strings'

type ConfirmDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: string
  description: string
  confirmLabel: string
  pendingLabel: string
  pending: boolean
  onConfirm: () => void
  // Mensaje de error u otro contenido bajo la descripción.
  children?: ReactNode
}

// Confirmación en un diálogo propio de la SPA, nunca `window.confirm`: foco atrapado dentro y Escape
// cierra sin enviar (Radix AlertDialog). Confirmar no cierra solo: la pantalla decide al terminar.
export function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel,
  pendingLabel,
  pending,
  onConfirm,
  children,
}: ConfirmDialogProps) {
  return (
    <AlertDialog open={open} onOpenChange={(next) => !pending && onOpenChange(next)}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
        {children}
        <AlertDialogFooter>
          <AlertDialogCancel disabled={pending}>{strings.common.cancel}</AlertDialogCancel>
          <SubmitButton type="button" pending={pending} pendingLabel={pendingLabel} onClick={onConfirm}>
            {confirmLabel}
          </SubmitButton>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
