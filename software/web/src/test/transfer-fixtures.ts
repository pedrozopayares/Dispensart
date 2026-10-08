import type { Transfer, TransferDiscrepancy, TransferLine, TransferSummary } from '@/lib/api-types'
import { lots, products, warehouses } from '@/test/fixtures'
import { json, setXsrfCookie } from '@/test/http'
import { sessionUser } from '@/test/render'

// Traslados sintéticos con la forma del contrato de S4 (tipos derivados del OpenAPI).

export const auxiliar = { id: sessionUser('auxiliar_farmacia').id, name: 'Auxiliar Demo' }
export const regente = { id: sessionUser('regente_farmacia').id, name: 'Regente Demo' }
// Regente distinto del usuario de la sesión (segregación de funciones, RN-08).
export const otherRegente = { id: 99, name: 'Regente Dos' }

const AT = '2026-10-08T15:00:00+00:00'

export function line(id: number, quantity: number, received: number | null = null, lotIndex = 0): TransferLine {
  const lot = lots[lotIndex]
  const product = products.find((candidate) => candidate.id === lot.product_id)!
  return {
    id,
    product: { id: product.id, code: product.code, name: product.name },
    lot: { id: lot.id, lot_code: lot.lot_code, expires_on: lot.expires_on, is_expired: lot.is_expired },
    quantity,
    received_quantity: received,
  }
}

export function discrepancy(id: number, lineId: number, shortage: number): TransferDiscrepancy {
  return {
    id,
    line_id: lineId,
    lot_id: lots[0].id,
    shortage,
    status: 'pending',
    resolution: null,
    resolution_reason: null,
    resolved_by: null,
    resolved_at: null,
  }
}

// Traslado 12 de Farmacia Central a Farmacia Urgencias; por defecto un borrador del auxiliar.
export function transfer(overrides: Partial<Transfer> = {}): Transfer {
  return {
    id: 12,
    status: 'BORRADOR',
    notes: null,
    origin_warehouse: warehouses[0],
    destination_warehouse: warehouses[1],
    created_by: auxiliar,
    created_at: AT,
    requested_by: null,
    requested_at: null,
    approved_by: null,
    approved_at: null,
    dispatched_by: null,
    dispatched_at: null,
    received_by: null,
    received_at: null,
    voided_by: null,
    voided_at: null,
    void_reason: null,
    lines: [line(500, 3)],
    discrepancies: [],
    ...overrides,
  }
}

// Atajos por estado con actores coherentes con la base (solicitante = creador, aprobador distinto).
export const requested = (by = auxiliar, overrides: Partial<Transfer> = {}) =>
  transfer({ status: 'SOLICITADO', created_by: by, requested_by: by, requested_at: AT, ...overrides })
export const approved = (overrides: Partial<Transfer> = {}) =>
  requested(auxiliar, { status: 'APROBADO', approved_by: otherRegente, approved_at: AT, ...overrides })
export const inTransit = (overrides: Partial<Transfer> = {}) =>
  approved({ status: 'EN_TRANSITO', dispatched_by: auxiliar, dispatched_at: AT, ...overrides })

export function summary(id: number, status: TransferSummary['status']): TransferSummary {
  return {
    id,
    status,
    origin_warehouse: warehouses[0],
    destination_warehouse: warehouses[1],
    created_by: auxiliar,
    created_at: AT,
  }
}

// Página del listado con la forma de Laravel.
export function transferPage(data: TransferSummary[], page = 1, lastPage = 1) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: {
      current_page: page,
      from: data.length > 0 ? 1 : null,
      last_page: lastPage,
      links: [],
      path: '/api/transfers',
      per_page: 50,
      to: data.length > 0 ? data.length : null,
      total: data.length,
    },
  }
}

export const detail = (value: Transfer) => () => json(200, { data: value })

// Escrituras con cookie XSRF ya emitida: la prueba no depende de /sanctum/csrf-cookie.
export function withXsrf<T>(routes: T): T {
  setXsrfCookie('token-1')
  return routes
}
