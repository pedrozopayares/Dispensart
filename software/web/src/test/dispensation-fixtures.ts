import { fireEvent, screen, within } from '@testing-library/react'
import { expect } from 'vitest'
import type {
  Dispensation,
  DispensationPreview,
  PatientRecord,
  Prescription,
  PrescriptionItem,
  PreviewItem,
} from '@/lib/api-types'
import { strings } from '@/lib/strings'
import { products, warehouses } from '@/test/fixtures'
import { json, setXsrfCookie, type RecordedRequest } from '@/test/http'

// Datos sintéticos de S3 con la forma del contrato (tipos derivados del OpenAPI): nunca datos reales.

const [acetaminofen, morfina] = products

export const clearPatient: Omit<PatientRecord, 'prescriptions'> = {
  id: 1,
  document_type: 'CC',
  document_number: '1000000001',
  full_name: 'Paciente Sintética Uno',
  birth_date: '1980-05-01',
  phone: '3000000001',
  masked: false,
}

// Como lo entrega la API al auditor (RN-10): el enmascarado lo hace el servidor.
export const maskedPatient: Omit<PatientRecord, 'prescriptions'> = {
  id: 1,
  document_type: 'CC',
  document_number: '*******001',
  full_name: 'P*** S*** U***',
  birth_date: null,
  phone: '********01',
  masked: true,
}

export function item(
  id: number,
  controlled: boolean,
  prescribed: number,
  dispensed = 0,
): PrescriptionItem {
  const product = controlled ? morfina : acetaminofen
  return {
    id,
    product: { id: product.id, code: product.code, name: product.name, is_controlled: product.is_controlled },
    prescribed_quantity: prescribed,
    dispensed_quantity: dispensed,
    pending_quantity: prescribed - dispensed,
  }
}

export function prescription(
  id: number,
  status: 'vigente' | 'vencida' | 'agotada',
  items: PrescriptionItem[],
): Prescription {
  return {
    id,
    patient_id: 1,
    status,
    valid_until: '2026-12-31',
    created_at: '2026-10-01T14:00:00+00:00',
    prescriber: { id: 3, name: 'Médico Demo' },
    items,
  }
}

// Prescripción vigente de un ítem de acetaminofén con 5 pendientes.
export const plainPrescription = prescription(7, 'vigente', [item(70, false, 5)])
// Prescripción vigente con morfina (control especial, RN-05).
export const controlledPrescription = prescription(8, 'vigente', [item(80, true, 2)])

export const record = (
  prescriptions: Prescription[],
  patient: Omit<PatientRecord, 'prescriptions'> = clearPatient,
): PatientRecord => ({ ...patient, prescriptions })

export function previewItem(
  line: PrescriptionItem,
  requested: number,
  allocations: { lot_id: number; lot_code: string; expires_on: string; quantity: number }[],
  extra: Partial<Pick<PreviewItem, 'expired_excluded_quantity' | 'available'>> = {},
): PreviewItem {
  const allocated = allocations.reduce((sum, allocation) => sum + allocation.quantity, 0)
  return {
    prescription_item_id: line.id,
    product_id: line.product.id,
    requested,
    available: extra.available ?? allocated,
    shortage: Math.max(requested - allocated, 0),
    expired_excluded_quantity: extra.expired_excluded_quantity ?? 0,
    requires_authorization: line.product.is_controlled,
    allocations,
  }
}

export function preview(source: Prescription, items: PreviewItem[], warehouseId = 1): DispensationPreview {
  return {
    prescription_id: source.id,
    warehouse_id: warehouseId,
    requires_authorization: items.some((entry) => entry.requires_authorization),
    fulfillable: items.every((entry) => entry.shortage === 0),
    items,
  }
}

// Vista previa feliz: L1 (vence antes) y L2, en el orden de la API.
export const fefoPreview = preview(plainPrescription, [
  previewItem(plainPrescription.items[0], 5, [
    { lot_id: 100, lot_code: 'L1', expires_on: '2027-01-31', quantity: 3 },
    { lot_id: 102, lot_code: 'L2', expires_on: '2027-06-30', quantity: 2 },
  ]),
])

export const controlledPreview = preview(controlledPrescription, [
  previewItem(controlledPrescription.items[0], 2, [
    { lot_id: 110, lot_code: 'MOR-C3', expires_on: '2027-03-31', quantity: 2 },
  ]),
])

export function dispensationFrom(source: DispensationPreview, id = 500): Dispensation {
  let lineId = 0
  return {
    id,
    prescription_id: source.prescription_id,
    patient_id: 1,
    warehouse_id: source.warehouse_id,
    dispensed_by: 1,
    authorized_by: null,
    created_at: '2026-10-08T15:00:00+00:00',
    lines: source.items.flatMap((entry) =>
      entry.allocations.map((allocation) => ({
        id: ++lineId,
        prescription_item_id: entry.prescription_item_id,
        product_id: entry.product_id,
        lot_id: allocation.lot_id,
        lot_code: allocation.lot_code,
        expires_on: allocation.expires_on,
        quantity: allocation.quantity,
        kardex_movement_id: 900 + lineId,
      })),
    ),
  }
}

type Resolver = (request: RecordedRequest) => Response | Promise<Response>

// Red simulada de la pantalla: búsqueda y ficha del paciente, bodegas y lo que la prueba agregue.
export function dispensationRoutes(
  patient: PatientRecord,
  extra: Record<string, Resolver | Resolver[]> = {},
) {
  // Escrituras con cookie XSRF ya emitida: la prueba no depende de /sanctum/csrf-cookie.
  setXsrfCookie('token-1')
  // La búsqueda no trae prescripciones (`undefined` no se serializa).
  const summary = { ...patient, prescriptions: undefined }
  return {
    'GET /api/warehouses': () => json(200, { data: warehouses }),
    'GET /api/patients': () => json(200, { data: [summary] }),
    [`GET /api/patients/${patient.id}`]: () => json(200, { data: patient }),
    ...extra,
  }
}

export const searchInput = () => screen.getByLabelText(strings.dispensation.search.label)

// Busca con Enter y abre la ficha del primer resultado con flecha abajo + Enter (teclado).
export async function openPatient(term = 'SINT') {
  const input = await screen.findByLabelText(strings.dispensation.search.label)
  fireEvent.change(input, { target: { value: term } })
  fireEvent.submit(input.closest('form')!)
  await screen.findByRole('option')
  fireEvent.keyDown(input, { key: 'ArrowDown' })
  fireEvent.submit(input.closest('form')!)
  await screen.findByRole('heading', { name: strings.dispensation.patient.prescriptions })
}

// Abre el formulario de la prescripción, elige bodega y pide la vista previa.
export async function previewDispensation(prescriptionId: number, warehouseId = 1) {
  const card = screen.getByRole('region', {
    name: strings.dispensation.prescription.title.replace('{id}', String(prescriptionId)),
  })
  fireEvent.click(within(card).getByRole('button', { name: strings.dispensation.prescription.dispense }))
  const warehouse = await screen.findByLabelText(strings.dispensation.form.warehouse)
  await within(warehouse).findByRole('option', { name: warehouses[0].name })
  fireEvent.change(warehouse, { target: { value: String(warehouseId) } })
  fireEvent.click(screen.getByRole('button', { name: strings.dispensation.form.preview }))
  await screen.findByRole('heading', { name: strings.dispensation.preview.title })
}

export const confirmButton = () => screen.getByRole('button', { name: strings.dispensation.confirm })

export function expectNoRequest(requests: RecordedRequest[], method: string, path: string) {
  expect(requests.filter((request) => request.method === method && request.path === path)).toEqual([])
}
