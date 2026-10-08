import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { strings } from '@/lib/strings'
import {
  clearPatient,
  dispensationRoutes,
  item,
  maskedPatient,
  openPatient,
  plainPrescription,
  prescription,
  record,
  searchInput,
} from '@/test/dispensation-fixtures'
import { deferred, json, networkError } from '@/test/http'
import { renderAs } from '@/test/render'

// Tareas 2.1–2.3 — dispensation-screen › "Búsqueda de paciente", "Datos enmascarados para el auditor",
// "Prescripciones del paciente", "Modo consulta sin dispensar"; operator-workspace › "Datos del paciente
// fuera del navegador persistente: URL sin datos personales" y "Error durante una consulta de paciente".

const s = strings.dispensation
const submitSearch = () => fireEvent.submit(searchInput().closest('form')!)
const typeTerm = (term: string) => fireEvent.change(searchInput(), { target: { value: term } })

describe('Dispensación › búsqueda de paciente', () => {
  it('Búsqueda con resultados: foco inicial, "Buscando pacientes…" y lista con documento y nombre', async () => {
    const pending = deferred<Response>()
    const { api } = renderAs('auxiliar_farmacia', '/dispensations', {
      ...dispensationRoutes(record([plainPrescription])),
      'GET /api/patients': () => pending.promise,
    })
    await waitFor(() => expect(searchInput()).toHaveFocus())

    typeTerm('SINT')
    submitSearch()

    expect(await screen.findByText(s.search.loading)).toBeInTheDocument()
    pending.resolve(json(200, { data: [{ ...clearPatient }] }))
    const option = await screen.findByRole('option')
    expect(option).toHaveTextContent('CC 1000000001')
    expect(option).toHaveTextContent('Paciente Sintética Uno')
    expect(api.requestsTo('GET', '/api/patients')[0].query).toEqual({ q: 'SINT' })
  })

  it('Término demasiado corto: aviso y ninguna petición', async () => {
    const { api } = renderAs('auxiliar_farmacia', '/dispensations', dispensationRoutes(record([])))
    await screen.findByRole('heading', { name: s.title })

    typeTerm('SI')
    submitSearch()

    expect(await screen.findByText(s.search.tooShort)).toBeInTheDocument()
    expect(api.requestsTo('GET', '/api/patients')).toHaveLength(0)
  })

  it('Sin resultados: mensaje de vacío', async () => {
    renderAs('auxiliar_farmacia', '/dispensations', {
      ...dispensationRoutes(record([])),
      'GET /api/patients': () => json(200, { data: [] }),
    })
    await screen.findByRole('heading', { name: s.title })

    typeTerm('ZZZZ')
    submitSearch()

    expect(await screen.findByText(s.search.empty)).toBeInTheDocument()
    expect(screen.queryByRole('option')).not.toBeInTheDocument()
  })

  it('Fallo de la búsqueda: mensaje de red, "Reintentar" repite el término y la consola no recibe nada', async () => {
    const consoleSpies = (['log', 'info', 'warn', 'error', 'debug'] as const).map((method) =>
      vi.spyOn(console, method).mockImplementation(() => {}),
    )
    const { api } = renderAs('auxiliar_farmacia', '/dispensations', {
      ...dispensationRoutes(record([])),
      'GET /api/patients': [networkError, () => json(200, { data: [{ ...clearPatient }] })],
    })
    await screen.findByRole('heading', { name: s.title })
    typeTerm('1000000001')
    submitSearch()

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent(strings.errors.network)
    fireEvent.click(within(alert).getByRole('button', { name: strings.common.retry }))

    expect(await screen.findByRole('option')).toHaveTextContent('Paciente Sintética Uno')
    expect(api.requestsTo('GET', '/api/patients').map((request) => request.query)).toEqual([
      { q: '1000000001' },
      { q: '1000000001' },
    ])
    // Ninguna llamada a la consola (y por tanto ninguna con el término o datos del paciente).
    for (const spy of consoleSpies) expect(spy).not.toHaveBeenCalled()
  })

  it('Selección con teclado abre la ficha y la URL no lleva documento, nombre ni teléfono', async () => {
    const { router, api } = renderAs(
      'auxiliar_farmacia',
      '/dispensations',
      dispensationRoutes(record([plainPrescription])),
    )
    await screen.findByRole('heading', { name: s.title })

    await openPatient('1000000001')

    expect(api.requestsTo('GET', '/api/patients/1')).toHaveLength(1)
    expect(screen.getByRole('region', { name: 'Prescripción #7' })).toBeInTheDocument()
    const url = router.state.location.pathname + router.state.location.search + router.state.location.hash
    expect(url).toBe('/dispensations')
    for (const value of ['1000000001', 'Sintética', '3000000001']) expect(url).not.toContain(value)
  })
})

describe('Dispensación › ficha del paciente', () => {
  it('Auditor ve datos enmascarados con el aviso, sin fecha de nacimiento', async () => {
    renderAs('auditor', '/dispensations', dispensationRoutes(record([plainPrescription], maskedPatient)))
    await openPatient()

    const patient = screen.getByRole('region', { name: s.patient.title })
    expect(patient).toHaveTextContent('*******001')
    expect(patient).toHaveTextContent('P*** S*** U***')
    expect(within(patient).getByText(s.patient.masked)).toBeInTheDocument()
    expect(patient).not.toHaveTextContent(s.patient.birthDate)
  })

  it('Auditor sin forma de desenmascarar: ningún botón ni enlace en la ficha', async () => {
    renderAs('auditor', '/dispensations', dispensationRoutes(record([plainPrescription], maskedPatient)))
    await openPatient()

    const patient = screen.getByRole('region', { name: s.patient.title })
    expect(within(patient).queryAllByRole('button')).toEqual([])
    expect(within(patient).queryAllByRole('link')).toEqual([])
    expect(document.body.textContent).not.toContain('1000000001')
  })

  it('Auxiliar ve datos en claro, sin el aviso de enmascarado', async () => {
    renderAs('auxiliar_farmacia', '/dispensations', dispensationRoutes(record([plainPrescription])))
    await openPatient()

    const patient = screen.getByRole('region', { name: s.patient.title })
    expect(patient).toHaveTextContent('CC 1000000001')
    expect(patient).toHaveTextContent('Paciente Sintética Uno')
    expect(patient).toHaveTextContent('1980-05-01')
    expect(within(patient).queryByText(s.patient.masked)).not.toBeInTheDocument()
  })

  it('Prescripción vigente elegible: "Vigente", prescriptor, saldos por ítem y "Dispensar esta prescripción"', async () => {
    const mixed = prescription(9, 'vigente', [item(90, false, 10, 4), item(91, true, 2)])
    renderAs('auxiliar_farmacia', '/dispensations', dispensationRoutes(record([mixed])))
    await openPatient()

    const card = screen.getByRole('region', { name: 'Prescripción #9' })
    expect(within(card).getByText(s.prescription.status.vigente)).toBeInTheDocument()
    expect(card).toHaveTextContent('Médico Demo')
    const [, first, second] = within(card).getAllByRole('row')
    expect(within(first).getAllByRole('cell').map((cell) => cell.textContent)).toEqual([
      'Acetaminofén 500 mg',
      '10',
      '4',
      '6',
    ])
    expect(within(second).getByText(s.prescription.controlled)).toBeInTheDocument()
    expect(within(first).queryByText(s.prescription.controlled)).not.toBeInTheDocument()
    expect(within(card).getByRole('button', { name: s.prescription.dispense })).toBeInTheDocument()
  })

  it('Prescripciones vencida y agotada no elegibles', async () => {
    renderAs(
      'auxiliar_farmacia',
      '/dispensations',
      dispensationRoutes(
        record([prescription(10, 'vencida', [item(100, false, 5)]), prescription(11, 'agotada', [item(110, false, 5, 5)])]),
      ),
    )
    await openPatient()

    expect(
      within(screen.getByRole('region', { name: 'Prescripción #10' })).getByText(s.prescription.status.vencida),
    ).toBeInTheDocument()
    expect(
      within(screen.getByRole('region', { name: 'Prescripción #11' })).getByText(s.prescription.status.agotada),
    ).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: s.prescription.dispense })).not.toBeInTheDocument()
  })

  it('Paciente sin prescripciones', async () => {
    renderAs('auxiliar_farmacia', '/dispensations', dispensationRoutes(record([])))
    await openPatient()

    expect(screen.getByText(s.patient.noPrescriptions)).toBeInTheDocument()
  })

  it('Fallo al cargar la ficha: mensaje de red con "Reintentar"', async () => {
    const patient = record([plainPrescription])
    const { api } = renderAs('auxiliar_farmacia', '/dispensations', {
      ...dispensationRoutes(patient),
      'GET /api/patients/1': [networkError, () => json(200, { data: patient })],
    })
    await screen.findByRole('heading', { name: s.title })
    typeTerm('SINT')
    submitSearch()
    fireEvent.click(await screen.findByRole('option'))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent(strings.errors.network)
    fireEvent.click(within(alert).getByRole('button', { name: strings.common.retry }))

    expect(await screen.findByRole('region', { name: 'Prescripción #7' })).toBeInTheDocument()
    expect(api.requestsTo('GET', '/api/patients/1')).toHaveLength(2)
  })
})

describe('Dispensación › modo consulta', () => {
  const formControls = [s.form.warehouse, s.form.preview, s.confirm]

  it.each(['auditor', 'medico'] as const)(
    '%s ve la prescripción con sus pendientes y ningún control de dispensación',
    async (role) => {
      renderAs(role, '/dispensations', dispensationRoutes(record([plainPrescription])))
      await openPatient()

      const card = screen.getByRole('region', { name: 'Prescripción #7' })
      expect(within(card).getByText(s.prescription.status.vigente)).toBeInTheDocument()
      expect(within(card).getAllByRole('row')[1]).toHaveTextContent('5')
      expect(screen.queryByRole('button', { name: s.prescription.dispense })).not.toBeInTheDocument()
      for (const label of formControls) expect(screen.queryByText(label)).not.toBeInTheDocument()
    },
  )

  it('control positivo: el auxiliar sí ve "Dispensar esta prescripción" y el formulario', async () => {
    renderAs('auxiliar_farmacia', '/dispensations', dispensationRoutes(record([plainPrescription])))
    await openPatient()

    fireEvent.click(screen.getByRole('button', { name: s.prescription.dispense }))

    expect(await screen.findByLabelText(s.form.warehouse)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: s.form.preview })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: s.confirm })).toBeDisabled()
  })
})
