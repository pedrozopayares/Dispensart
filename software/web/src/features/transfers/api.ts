import { apiRequest } from '@/lib/api'
import type {
  NewTransfer,
  ResponseOf,
  Transfer,
  TransferPage,
  TransferQuery,
  TransferReceipt,
  TransferVoid,
} from '@/lib/api-types'

// Funciones por recurso de S4: listado, detalle, creación y acciones de estado (RN-07, RN-08).

// Traslados del más reciente al más antiguo, paginados (transfers.view).
export async function listTransfers(query: TransferQuery): Promise<TransferPage> {
  return apiRequest<TransferPage>('GET', '/transfers', undefined, { query })
}

export async function getTransfer(transferId: number): Promise<Transfer> {
  const { data } = await apiRequest<ResponseOf<'/transfers/{transfer}', 'get'>>(
    'GET',
    `/transfers/${transferId}`,
  )
  return data
}

// Borrador nuevo (transfers.create); la API rechaza lotes vencidos con `lot_expired`.
export async function createTransfer(body: NewTransfer): Promise<Transfer> {
  const { data } = await apiRequest<ResponseOf<'/transfers', 'post'>>('POST', '/transfers', body)
  return data
}

export type TransferAction = 'request' | 'approve' | 'dispatch' | 'receive' | 'void'

// Cuerpo de cada acción: recepción y anulación llevan datos; las demás van vacías.
export type TransferActionInput =
  | { action: 'request' | 'approve' | 'dispatch' }
  | { action: 'receive'; body: TransferReceipt }
  | { action: 'void'; body: TransferVoid }

// Acción de estado; la respuesta es el traslado con su estado nuevo.
export async function runTransferAction(transferId: number, input: TransferActionInput): Promise<Transfer> {
  const body = 'body' in input ? input.body : undefined
  const { data } = await apiRequest<ResponseOf<'/transfers/{transfer}/request', 'post'>>(
    'POST',
    `/transfers/${transferId}/${input.action}`,
    body,
  )
  return data
}
