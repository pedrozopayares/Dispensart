import { ApiError } from '@/lib/api'
import { format, strings } from '@/lib/strings'

// Catálogo único de mensajes por `code` (design D5). Ninguna pantalla mapea códigos por su cuenta y
// ninguna muestra `error.message`, el código ni la traza.

// Códigos con texto propio. Incluye los de S3–S5 (dispensación, traslados) aunque su contrato aún no
// esté publicado: el `code` es estable y la pantalla los recibe como texto.
const messagesByCode: Record<string, string> = {
  invalid_credentials: strings.errors.invalidCredentials,
  too_many_attempts: strings.errors.tooManyAttempts,
  csrf_token_mismatch: strings.errors.csrfExpired,
  forbidden: strings.errors.forbidden,
  not_found: strings.errors.notFound,
  validation_failed: strings.errors.validation,
  unauthenticated: strings.session.expired,
  network_error: strings.errors.network,
  server_error: strings.errors.network,
  lot_expired: strings.errors.lotExpired,
  prescription_expired: strings.errors.prescriptionExpired,
  prescription_exhausted: strings.errors.prescriptionExhausted,
  exceeds_prescription: strings.errors.exceedsPrescription,
  invalid_authorizer: strings.errors.invalidAuthorizer,
  authorizer_must_differ: strings.errors.authorizerMustDiffer,
  authorization_required: strings.errors.authorizationRequired,
  idempotency_key_reused: strings.errors.idempotencyKeyReused,
  segregation_of_duties: strings.errors.segregationOfDuties,
  invalid_transfer_transition: strings.errors.invalidTransferTransition,
}

export type DescribeOptions = {
  // Nombre del producto para `insufficient_stock` (la API devuelve solo `product_id`).
  productName?: (productId: number) => string | undefined
}

// `true` si el error es un rechazo de la API con ese `code`.
export function hasCode(error: unknown, code: string): boolean {
  return error instanceof ApiError && error.code === code
}

// Texto en español para cualquier error; varias líneas separadas por salto de línea.
export function describeError(error: unknown, { productName }: DescribeOptions = {}): string {
  // Un TypeError de `fetch` u otra excepción fuera del cliente: nunca su mensaje técnico.
  if (error instanceof TypeError) return strings.errors.network
  if (!(error instanceof ApiError)) return strings.errors.unexpected

  if (error.code === 'insufficient_stock') {
    if (error.shortages.length === 0) return strings.errors.insufficientStockGeneric
    return error.shortages
      .map((shortage) =>
        format(strings.errors.insufficientStock, {
          product: productName?.(shortage.product_id) ?? strings.errors.unknownProduct,
          requested: String(shortage.requested),
          available: String(shortage.available),
        }),
      )
      .join('\n')
  }

  return Object.hasOwn(messagesByCode, error.code)
    ? messagesByCode[error.code]
    : strings.errors.unexpected
}

// Primer mensaje por campo de un `validation_failed`, para mostrarlo junto al campo.
export function fieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError) || error.code !== 'validation_failed') return {}
  return Object.fromEntries(
    Object.entries(error.errors)
      .filter(([, messages]) => messages.length > 0)
      .map(([field, messages]) => [field, messages[0]]),
  )
}
