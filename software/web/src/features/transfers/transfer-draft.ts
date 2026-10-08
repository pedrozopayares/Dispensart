import type { NewTransfer, StockRow } from '@/lib/api-types'
import { strings } from '@/lib/strings'

// Borrador del formulario "Nuevo traslado" y su validación local (transfers-screen "Creación de
// traslado"). Ninguna petición sale con datos que la interfaz ya sabe inválidos.

export type DraftLine = { key: number; lotId?: number; quantity: string }

export type TransferDraft = {
  originId?: number
  destinationId?: number
  notes: string
  lines: DraftLine[]
}

export type DraftErrors = {
  origin?: string
  destination?: string
  lines?: string
  byLine: Record<number, { lot?: string; quantity?: string }>
}

const POSITIVE_INTEGER = /^[1-9]\d*$/

// Lotes elegibles del origen: no vencidos y con existencia (RN-01).
export function eligibleStock(rows: readonly StockRow[] = []): StockRow[] {
  return rows.filter((row) => !row.lot.is_expired && row.quantity > 0)
}

// `null` si el borrador es válido.
export function validateDraft(draft: TransferDraft): DraftErrors | null {
  const { form } = strings.transfers
  const errors: DraftErrors = { byLine: {} }
  if (draft.originId === undefined) errors.origin = strings.common.required
  if (draft.destinationId === undefined) errors.destination = strings.common.required
  else if (draft.destinationId === draft.originId) errors.destination = form.sameWarehouse
  if (draft.lines.length === 0) errors.lines = form.noLines
  for (const line of draft.lines) {
    const lineErrors = {
      lot: line.lotId === undefined ? form.lotRequired : undefined,
      quantity: POSITIVE_INTEGER.test(line.quantity.trim()) ? undefined : form.quantityInvalid,
    }
    if (lineErrors.lot !== undefined || lineErrors.quantity !== undefined) errors.byLine[line.key] = lineErrors
  }
  const valid =
    errors.origin === undefined &&
    errors.destination === undefined &&
    errors.lines === undefined &&
    Object.keys(errors.byLine).length === 0
  return valid ? null : errors
}

// Cuerpo de `POST /transfers` desde un borrador ya validado.
export function buildNewTransfer(draft: TransferDraft): NewTransfer {
  const notes = draft.notes.trim()
  return {
    origin_warehouse_id: draft.originId ?? 0,
    destination_warehouse_id: draft.destinationId ?? 0,
    ...(notes === '' ? {} : { notes }),
    lines: draft.lines.map((line) => ({ lot_id: line.lotId ?? 0, quantity: Number(line.quantity) })),
  }
}
