import type { TransferAction } from '@/features/transfers/api'
import { can } from '@/lib/abilities'
import type { AuthenticatedUser } from '@/lib/api'
import type { Transfer, TransferStatus } from '@/lib/api-types'
import { strings } from '@/lib/strings'

// Reglas de la interfaz para traslados. La SPA solo oculta lo que el rol no puede hacer; la API
// (TransferTransitions + TransferPolicy) sigue siendo la autoridad y responde 403/409 si cambia algo.

export const TRANSFER_STATUSES = [
  'BORRADOR',
  'SOLICITADO',
  'APROBADO',
  'EN_TRANSITO',
  'RECIBIDO',
  'RECIBIDO_PARCIAL',
  'ANULADO',
] as const satisfies readonly TransferStatus[]

export function isTransferStatus(value: string | null): value is TransferStatus {
  return value !== null && (TRANSFER_STATUSES as readonly string[]).includes(value)
}

// Estado en español; nunca el literal de la API.
export function transferStatusLabel(status: string): string {
  return isTransferStatus(status) ? strings.transfers.status[status] : strings.transfers.unknownStatus
}

const VOIDABLE: readonly TransferStatus[] = ['BORRADOR', 'SOLICITADO', 'APROBADO']

export type TransferActions = {
  actions: TransferAction[]
  // Regente que solicitó: no ve "Aprobar" y recibe el aviso de segregación (RN-08).
  requesterNotice: boolean
}

// Acciones válidas para el estado y el rol (transfers-screen "Acciones según estado y rol").
export function transferActions(transfer: Transfer, user: AuthenticatedUser): TransferActions {
  const isCreator = transfer.created_by?.id === user.id
  // Solicitante = creador en la base (`transfers_requester_is_creator`); se compara con quien solicitó.
  const isRequester = (transfer.requested_by ?? transfer.created_by)?.id === user.id
  const canCreate = can(user, 'transfers.create')
  const canApprove = can(user, 'transfers.approve')
  const actions: TransferAction[] = []

  if (transfer.status === 'BORRADOR' && canCreate && isCreator) actions.push('request')
  if (transfer.status === 'SOLICITADO' && canApprove && !isRequester) actions.push('approve')
  if (transfer.status === 'APROBADO' && canCreate) actions.push('dispatch')
  if (transfer.status === 'EN_TRANSITO' && can(user, 'transfers.receive')) actions.push('receive')
  if (VOIDABLE.includes(transfer.status) && (canApprove || (canCreate && isCreator))) actions.push('void')

  return {
    actions,
    requesterNotice: transfer.status === 'SOLICITADO' && canApprove && isRequester,
  }
}
