import type { Prescription, PreviewRequest } from '@/lib/api-types'
import { format, strings } from '@/lib/strings'

// Intención de dispensar (design D3): prescripción + bodega + ítems, sin credenciales del autorizador.
// La misma función produce el cuerpo de la vista previa, el de la confirmación y la huella de la
// clave de idempotencia: cliente y servidor nunca divergen.

// Texto escrito en cada campo de cantidad, por id de ítem.
export type Quantities = Record<number, string>

// Ítems que aún admiten dispensación, en el orden de la prescripción.
export const dispensableItems = (prescription: Prescription) =>
  prescription.items.filter((item) => item.pending_quantity > 0)

// Por defecto, lo pendiente de cada ítem.
export const defaultQuantities = (prescription: Prescription): Quantities =>
  Object.fromEntries(dispensableItems(prescription).map((item) => [item.id, String(item.pending_quantity)]))

const INTEGER = /^\d+$/

export function buildDispensationIntent(
  prescription: Prescription,
  warehouseId: number,
  quantities: Quantities,
): PreviewRequest {
  return {
    prescription_id: prescription.id,
    warehouse_id: warehouseId,
    items: dispensableItems(prescription)
      .map((item) => ({ prescription_item_id: item.id, quantity: Number(quantities[item.id] ?? '0') }))
      .filter((item) => item.quantity > 0),
  }
}

export type IntentErrors = {
  // Mensaje por id de ítem, junto a su campo.
  items: Record<number, string>
  warehouse?: string
  quantities?: string
}

// Validación local antes de pedir la vista previa: entre 0 y lo pendiente; 0 excluye el ítem.
export function validateIntent(
  prescription: Prescription,
  warehouseId: number | undefined,
  quantities: Quantities,
): IntentErrors | null {
  const errors: IntentErrors = { items: {} }
  let total = 0
  for (const item of dispensableItems(prescription)) {
    const raw = (quantities[item.id] ?? '').trim()
    const pending = String(item.pending_quantity)
    if (!INTEGER.test(raw)) {
      errors.items[item.id] = format(strings.dispensation.form.quantityInvalid, { pending })
    } else if (Number(raw) > item.pending_quantity) {
      errors.items[item.id] = format(strings.dispensation.form.quantityTooHigh, { pending })
    } else {
      total += Number(raw)
    }
  }
  if (warehouseId === undefined) errors.warehouse = strings.dispensation.form.warehouseRequired
  if (Object.keys(errors.items).length === 0 && total === 0) {
    errors.quantities = strings.dispensation.form.quantitiesRequired
  }
  const valid =
    Object.keys(errors.items).length === 0 && errors.warehouse === undefined && errors.quantities === undefined
  return valid ? null : errors
}
