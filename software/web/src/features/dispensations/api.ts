import { apiRequest, apiRequestWithHeaders } from '@/lib/api'
import type {
  CreatedPrescription,
  Dispensation,
  DispensationPreview,
  DispensationRequest,
  NewPrescription,
  PatientRecord,
  PatientSummary,
  PreviewRequest,
  ResponseOf,
} from '@/lib/api-types'

// Funciones por recurso de S3 (pacientes, prescripciones, vista previa y dispensación).

const REPLAYED_HEADER = 'Idempotent-Replayed'

// Búsqueda por documento (prefijo) o nombre; la API enmascara según el rol (RN-10).
export async function searchPatients(q: string): Promise<PatientSummary[]> {
  const { data } = await apiRequest<ResponseOf<'/patients', 'get'>>('GET', '/patients', undefined, {
    query: { q },
  })
  return data
}

// Ficha con prescripciones, estado calculado y saldos por ítem.
export async function getPatient(patientId: number): Promise<PatientRecord> {
  const { data } = await apiRequest<ResponseOf<'/patients/{patient}', 'get'>>(
    'GET',
    `/patients/${patientId}`,
  )
  return data
}

// Alta de prescripción (solo `prescriptions.create`, el médico).
export async function createPrescription(body: NewPrescription): Promise<CreatedPrescription> {
  const { data } = await apiRequest<ResponseOf<'/prescriptions', 'post'>>('POST', '/prescriptions', body)
  return data
}

// Asignación FEFO sin escribir nada: el faltante llega como dato (`fulfillable`), no como rechazo.
export async function previewDispensation(body: PreviewRequest): Promise<DispensationPreview> {
  const { data } = await apiRequest<ResponseOf<'/dispensations/preview', 'post'>>(
    'POST',
    '/dispensations/preview',
    body,
  )
  return data
}

export type DispenseResult = {
  dispensation: Dispensation
  // `true` si la API devolvió la respuesta original de una clave ya usada (RN-09).
  replayed: boolean
}

// Dispensación con la clave de la intención (design D3). La repetición es un éxito como cualquier otro.
export async function dispense(body: DispensationRequest, idempotencyKey: string): Promise<DispenseResult> {
  const { body: response, headers } = await apiRequestWithHeaders<ResponseOf<'/dispensations', 'post'>>(
    'POST',
    '/dispensations',
    body,
    { headers: { 'Idempotency-Key': idempotencyKey } },
  )
  return { dispensation: response.data, replayed: headers.get(REPLAYED_HEADER) === 'true' }
}
