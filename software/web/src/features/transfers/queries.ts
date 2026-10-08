import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { listStock } from '@/features/inventory/api'
import {
  createTransfer,
  getTransfer,
  listTransfers,
  runTransferAction,
  type TransferActionInput,
} from '@/features/transfers/api'
import type { TransferQuery } from '@/lib/api-types'
import { invalidateAfterStockWrite, queryKeys } from '@/lib/query-keys'

export function useTransfers(filters: TransferQuery) {
  return useQuery({ queryKey: queryKeys.transfers(filters), queryFn: () => listTransfers(filters) })
}

export function useTransfer(transferId: number | null) {
  return useQuery({
    queryKey: queryKeys.transfer(transferId ?? 0),
    queryFn: () => getTransfer(transferId ?? 0),
    enabled: transferId !== null,
  })
}

// Existencias del origen para elegir lotes; sin origen no se consulta.
export function useOriginStock(warehouseId: number | undefined) {
  return useQuery({
    queryKey: queryKeys.stock({ warehouse_id: warehouseId }),
    queryFn: () => listStock({ warehouse_id: warehouseId }),
    enabled: warehouseId !== undefined,
  })
}

export function useCreateTransfer() {
  const client = useQueryClient()
  return useMutation({
    mutationFn: createTransfer,
    onSuccess: (transfer) => {
      client.setQueryData(queryKeys.transfer(transfer.id), transfer)
      void client.invalidateQueries({ queryKey: ['transfers', 'list'] })
    },
  })
}

// Acciones de estado. El detalle toma la respuesta de la API (estado real tras la acción); despacho y
// recepción mueven stock: inventario, kardex y alertas quedan obsoletos (ADR-0003).
export function useTransferAction(transferId: number) {
  const client = useQueryClient()
  return useMutation({
    mutationFn: (input: TransferActionInput) => runTransferAction(transferId, input),
    onSuccess: (transfer, input) => {
      client.setQueryData(queryKeys.transfer(transferId), transfer)
      void client.invalidateQueries({ queryKey: ['transfers', 'list'] })
      if (input.action === 'dispatch' || input.action === 'receive') void invalidateAfterStockWrite(client)
    },
  })
}
