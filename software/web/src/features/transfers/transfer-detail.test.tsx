import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import type { Transfer } from '@/lib/api-types'
import { strings } from '@/lib/strings'
import type { RoleCode } from '@/lib/strings'
import { deferred, json } from '@/test/http'
import { renderAs } from '@/test/render'
import {
  approved,
  auxiliar,
  detail,
  discrepancy,
  inTransit,
  line,
  otherRegente,
  regente,
  requested,
  transfer,
  withXsrf,
} from '@/test/transfer-fixtures'

// Tareas 3.2, 3.4 y 3.5 — transfers-screen › "Detalle con estado y discrepancias", "Acciones según
// estado y rol", "Despacho confirmado", "Recepción por línea", "Anulación con motivo".

type Routes = NonNullable<Parameters<typeof renderAs>[2]>

const s = strings.transfers
const ACTION_LABELS = [s.actions.request, s.actions.approve, s.actions.dispatch, s.actions.receive, s.actions.void]
const AT = '2026-10-08T16:30:00+00:00'

const rejected = (status: number, code: string, extra: Record<string, unknown> = {}) =>
  json(status, { code, message: 'mensaje del servidor', ...extra })
const ok = (value: Transfer) => () => json(200, { data: value })

async function openDetail(role: RoleCode, current: Transfer | Transfer[], extra: Routes = {}) {
  const queue = Array.isArray(current) ? current : [current]
  const view = renderAs(
    role,
    '/transfers/12',
    withXsrf({ 'GET /api/transfers/12': queue.map((value) => detail(value)), ...extra }),
  )
  await screen.findByRole('heading', { name: 'Traslado #12' })
  return view
}

// `hidden`: con un diálogo abierto, Radix oculta el resto de la página a lectores de pantalla.
const statusBadge = () => screen.getByRole('heading', { name: 'Traslado #12', hidden: true }).nextElementSibling
const historyEntry = (label: string) => {
  const term = within(screen.getByLabelText(s.detail.history)).getByText(label)
  return term.nextElementSibling?.textContent
}
const visibleActions = () =>
  ACTION_LABELS.filter((label) => screen.queryByRole('button', { name: label }) !== null)

describe('Traslados › detalle', () => {
  it('Recibido parcial con discrepancias: estado y sección con faltante 1 "Pendiente"', async () => {
    await openDetail(
      'auxiliar_farmacia',
      inTransit({
        status: 'RECIBIDO_PARCIAL',
        received_by: auxiliar,
        received_at: AT,
        lines: [line(500, 3, 2)],
        discrepancies: [discrepancy(900, 500, 1)],
      }),
    )

    expect(statusBadge()).toHaveTextContent('Recibido parcial')
    const section = screen.getByRole('region', { name: s.detail.discrepancies })
    const cells = within(within(section).getAllByRole('row')[1]).getAllByRole('cell')
    expect(cells.map((cell) => cell.textContent)).toEqual(['Acetaminofén 500 mg', 'ACE-A1', '1', 'Pendiente'])
    expect(historyEntry(s.detail.events.received)).toContain('Auxiliar Demo')
    expect(historyEntry(s.detail.events.approved)).toContain('Regente Dos')
  })

  it('Recibido completo sin discrepancias: estado "Recibido" sin la sección', async () => {
    await openDetail('auxiliar_farmacia', inTransit({ status: 'RECIBIDO', lines: [line(500, 3, 3)] }))

    expect(statusBadge()).toHaveTextContent('Recibido')
    expect(screen.queryByRole('region', { name: s.detail.discrepancies })).not.toBeInTheDocument()
    expect(screen.queryByText(s.detail.discrepancies)).not.toBeInTheDocument()
  })

  it('Traslado inexistente: "No encontramos el traslado." con "Volver a traslados"', async () => {
    renderAs('auxiliar_farmacia', '/transfers/999999', {
      'GET /api/transfers/999999': () => rejected(404, 'not_found'),
    })

    expect(await screen.findByText(s.detail.notFound)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: s.detail.back })).toHaveAttribute('href', '/transfers')
    expect(document.body.textContent).not.toContain('not_found')
  })
})

describe('Traslados › acciones según estado y rol', () => {
  it('Solicitante no ve Aprobar: el regente que lo solicitó ve el aviso de segregación', async () => {
    await openDetail('regente_farmacia', requested(regente))

    expect(screen.queryByRole('button', { name: s.actions.approve })).not.toBeInTheDocument()
    expect(screen.getByText(s.detail.requesterNotice)).toBeInTheDocument()
  })

  it('Otro regente aprueba: pasa a "Aprobado" con su nombre como aprobador', async () => {
    const after = requested(auxiliar, { status: 'APROBADO', approved_by: regente, approved_at: AT })
    const { api } = await openDetail('regente_farmacia', requested(auxiliar), {
      'POST /api/transfers/12/approve': ok(after),
    })
    expect(screen.queryByText(s.detail.requesterNotice)).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: s.actions.approve }))

    await waitFor(() => expect(statusBadge()).toHaveTextContent('Aprobado'))
    expect(historyEntry(s.detail.events.approved)).toContain('Regente Demo')
    expect(api.requestsTo('POST', '/api/transfers/12/approve')).toHaveLength(1)
  })

  it('Auxiliar no ve Aprobar en un traslado Solicitado', async () => {
    await openDetail('auxiliar_farmacia', requested(otherRegente))

    expect(screen.queryByRole('button', { name: s.actions.approve })).not.toBeInTheDocument()
  })

  it.each([
    ['Borrador', transfer()],
    ['Solicitado', requested(auxiliar)],
    ['Aprobado', approved()],
    ['En tránsito', inTransit()],
  ])('Auditor sin acciones (%s)', async (_, current) => {
    await openDetail('auditor', current)

    expect(visibleActions()).toEqual([])
  })

  it.each([
    ['Recibido', inTransit({ status: 'RECIBIDO', lines: [line(500, 3, 3)] })],
    ['Recibido parcial', inTransit({ status: 'RECIBIDO_PARCIAL', lines: [line(500, 3, 2)], discrepancies: [discrepancy(900, 500, 1)] })],
    ['Anulado', transfer({ status: 'ANULADO', voided_by: regente, voided_at: AT, void_reason: 'Duplicado' })],
  ])('Estado terminal sin acciones (%s)', async (_, current) => {
    await openDetail('regente_farmacia', current)

    expect(visibleActions()).toEqual([])
  })

  it('control positivo: el regente distinto del solicitante ve Aprobar y Anular en Solicitado', async () => {
    await openDetail('regente_farmacia', requested(auxiliar))

    expect(visibleActions()).toEqual([s.actions.approve, s.actions.void])
  })

  it('Segregación rechazada por el servidor: mensaje y el estado sigue "Solicitado"', async () => {
    await openDetail('regente_farmacia', requested(otherRegente), {
      'POST /api/transfers/12/approve': () => rejected(403, 'segregation_of_duties'),
    })

    fireEvent.click(screen.getByRole('button', { name: s.actions.approve }))

    expect(await screen.findByRole('alert')).toHaveTextContent(strings.errors.segregationOfDuties)
    expect(statusBadge()).toHaveTextContent('Solicitado')
  })

  it('Estado cambiado por otro usuario: mensaje y el detalle se recarga con el estado real', async () => {
    const { api } = await openDetail('regente_farmacia', [requested(otherRegente), approved()], {
      'POST /api/transfers/12/approve': () => rejected(409, 'invalid_transfer_transition'),
    })

    fireEvent.click(screen.getByRole('button', { name: s.actions.approve }))

    expect(await screen.findByRole('alert')).toHaveTextContent(strings.errors.invalidTransferTransition)
    await waitFor(() => expect(statusBadge()).toHaveTextContent('Aprobado'))
    expect(api.requestsTo('GET', '/api/transfers/12')).toHaveLength(2)
  })

  it('Doble clic en Solicitar: una sola petición y "Procesando…" deshabilitado hasta la respuesta', async () => {
    const pending = deferred<Response>()
    const { api } = await openDetail('auxiliar_farmacia', transfer(), {
      'POST /api/transfers/12/request': () => pending.promise,
    })
    const button = screen.getByRole('button', { name: s.actions.request })

    fireEvent.click(button)
    fireEvent.click(button)

    expect(await screen.findByRole('button', { name: s.actions.working })).toBeDisabled()
    expect(api.requestsTo('POST', '/api/transfers/12/request')).toHaveLength(1)
    pending.resolve(json(200, { data: requested(auxiliar) }))
    await waitFor(() => expect(statusBadge()).toHaveTextContent('Solicitado'))
    expect(api.requestsTo('POST', '/api/transfers/12/request')).toHaveLength(1)
  })
})

describe('Traslados › despacho confirmado', () => {
  const openDispatch = async (extra: Routes = {}) => {
    const view = await openDetail('auxiliar_farmacia', approved(), extra)
    fireEvent.click(screen.getByRole('button', { name: s.actions.dispatch }))
    const dialog = await screen.findByRole('alertdialog', { name: s.dispatch.title })
    return { ...view, dialog }
  }

  it('Despacho exitoso: aviso de origen en el diálogo y luego "En tránsito" con su nombre', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm')
    const { dialog, api } = await openDispatch({ 'POST /api/transfers/12/dispatch': ok(inTransit()) })
    expect(dialog).toHaveTextContent('El stock saldrá de Farmacia Central y quedará en tránsito.')

    fireEvent.click(within(dialog).getByRole('button', { name: s.dispatch.confirm }))

    await waitFor(() => expect(statusBadge()).toHaveTextContent('En tránsito'))
    expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument()
    expect(historyEntry(s.detail.events.dispatched)).toContain('Auxiliar Demo')
    expect(api.requestsTo('POST', '/api/transfers/12/dispatch')).toHaveLength(1)
    expect(confirmSpy).not.toHaveBeenCalled()
  })

  it.each(['Cancelar', 'Escape'])('Despacho cancelado (%s): sin petición y sigue "Aprobado"', async (how) => {
    const { dialog, api } = await openDispatch()

    if (how === 'Escape') fireEvent.keyDown(dialog, { key: 'Escape' })
    else fireEvent.click(within(dialog).getByRole('button', { name: strings.common.cancel }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(statusBadge()).toHaveTextContent('Aprobado')
    expect(api.requestsTo('POST', '/api/transfers/12/dispatch')).toHaveLength(0)
  })

  it.each([
    ['insufficient_stock', 409, s.dispatch.insufficientStock],
    ['lot_expired', 422, strings.errors.lotExpired],
  ])('Rechazo %s al despachar: el diálogo muestra su mensaje y sigue "Aprobado"', async (code, status, message) => {
    const { dialog } = await openDispatch({ 'POST /api/transfers/12/dispatch': () => rejected(status, code) })

    fireEvent.click(within(dialog).getByRole('button', { name: s.dispatch.confirm }))

    expect(await within(dialog).findByRole('alert')).toHaveTextContent(message)
    expect(statusBadge()).toHaveTextContent('Aprobado')
  })
})

describe('Traslados › recepción por línea', () => {
  const received = (quantity: number) =>
    inTransit({
      status: quantity === 3 ? 'RECIBIDO' : 'RECIBIDO_PARCIAL',
      received_by: auxiliar,
      received_at: AT,
      lines: [line(500, 3, quantity)],
      discrepancies: quantity === 3 ? [] : [discrepancy(900, 500, 3 - quantity)],
    })
  const quantityField = () => screen.getByLabelText('Cantidad recibida de Acetaminofén 500 mg · Lote ACE-A1')
  const confirm = () => screen.getByRole('button', { name: s.receive.confirm })

  const openReceive = async (extra: Routes = {}) => {
    const view = await openDetail('auxiliar_farmacia', inTransit(), extra)
    fireEvent.click(screen.getByRole('button', { name: s.actions.receive }))
    return view
  }

  it('Recepción completa: por defecto lo enviado y luego "Recibido" sin discrepancias', async () => {
    const { api } = await openReceive({ 'POST /api/transfers/12/receive': ok(received(3)) })
    expect(quantityField()).toHaveValue(3)
    expect(screen.queryByText(s.receive.discrepancyNotice)).not.toBeInTheDocument()

    fireEvent.click(confirm())

    await waitFor(() => expect(statusBadge()).toHaveTextContent('Recibido'))
    expect(screen.queryByRole('region', { name: s.detail.discrepancies })).not.toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/transfers/12/receive')[0].body).toEqual({
      lines: [{ line_id: 500, received_quantity: 3 }],
    })
  })

  it('Recepción parcial: aviso antes de confirmar y luego "Recibido parcial" con faltante 1', async () => {
    const { api } = await openReceive({ 'POST /api/transfers/12/receive': ok(received(2)) })

    fireEvent.change(quantityField(), { target: { value: '2' } })
    expect(screen.getByText(s.receive.discrepancyNotice)).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/transfers/12/receive')).toHaveLength(0)
    fireEvent.click(confirm())

    await waitFor(() => expect(statusBadge()).toHaveTextContent('Recibido parcial'))
    const section = screen.getByRole('region', { name: s.detail.discrepancies })
    expect(within(section).getAllByRole('row')[1]).toHaveTextContent('1')
    expect(api.requestsTo('POST', '/api/transfers/12/receive')[0].body).toEqual({
      lines: [{ line_id: 500, received_quantity: 2 }],
    })
  })

  it('Cantidad recibida mayor que la enviada: "No puede superar lo enviado (3)." sin petición', async () => {
    const { api } = await openReceive()

    fireEvent.change(quantityField(), { target: { value: '4' } })
    fireEvent.click(confirm())

    expect(await screen.findByText('No puede superar lo enviado (3).')).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/transfers/12/receive')).toHaveLength(0)
  })

  it('Doble clic en Confirmar recepción: una sola petición', async () => {
    const pending = deferred<Response>()
    const { api } = await openReceive({ 'POST /api/transfers/12/receive': () => pending.promise })

    const button = confirm()
    fireEvent.click(button)
    fireEvent.click(button)

    expect(await screen.findByRole('button', { name: s.actions.working })).toBeDisabled()
    expect(api.requestsTo('POST', '/api/transfers/12/receive')).toHaveLength(1)
    pending.resolve(json(200, { data: received(3) }))
    await waitFor(() => expect(statusBadge()).toHaveTextContent('Recibido'))
    expect(api.requestsTo('POST', '/api/transfers/12/receive')).toHaveLength(1)
  })
})

describe('Traslados › anulación con motivo', () => {
  const openVoid = async (extra: Routes = {}) => {
    const view = await openDetail('auxiliar_farmacia', transfer(), extra)
    fireEvent.click(screen.getByRole('button', { name: s.actions.void }))
    const dialog = await screen.findByRole('alertdialog', { name: s.void.title })
    return { ...view, dialog }
  }

  it('Anulación exitosa: "Anulado" con su nombre, la fecha y el motivo', async () => {
    const voided = transfer({ status: 'ANULADO', voided_by: auxiliar, voided_at: AT, void_reason: 'Pedido duplicado' })
    const { dialog, api } = await openVoid({ 'POST /api/transfers/12/void': ok(voided) })

    fireEvent.change(within(dialog).getByLabelText(s.void.reason), { target: { value: '  Pedido duplicado ' } })
    fireEvent.click(within(dialog).getByRole('button', { name: s.void.confirm }))

    await waitFor(() => expect(statusBadge()).toHaveTextContent('Anulado'))
    expect(historyEntry(s.detail.events.voided)).toBe('Auxiliar Demo · 2026-10-08 11:30')
    expect(historyEntry(s.detail.events.voidReason)).toBe('Pedido duplicado')
    expect(api.requestsTo('POST', '/api/transfers/12/void')[0].body).toEqual({ reason: 'Pedido duplicado' })
  })

  it('Anulación sin motivo: "Escribe el motivo de la anulación." y ninguna petición', async () => {
    const { dialog, api } = await openVoid()

    fireEvent.click(within(dialog).getByRole('button', { name: s.void.confirm }))

    expect(await within(dialog).findByText(s.void.reasonRequired)).toBeInTheDocument()
    expect(api.requestsTo('POST', '/api/transfers/12/void')).toHaveLength(0)
  })

  it('Error de campo del servidor: el mensaje de `errors.reason` junto a "Motivo"', async () => {
    const { dialog } = await openVoid({
      'POST /api/transfers/12/void': () =>
        rejected(422, 'validation_failed', { errors: { reason: ['El motivo no puede superar 500 caracteres.'] } }),
    })

    fireEvent.change(within(dialog).getByLabelText(s.void.reason), { target: { value: 'x' } })
    fireEvent.click(within(dialog).getByRole('button', { name: s.void.confirm }))

    expect(await within(dialog).findByText('El motivo no puede superar 500 caracteres.')).toBeInTheDocument()
    expect(within(dialog).getByLabelText(s.void.reason)).toHaveValue('x')
  })
})
