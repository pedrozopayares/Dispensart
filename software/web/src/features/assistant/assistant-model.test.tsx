import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import type { QueryClient } from '@tanstack/react-query'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { strings, type RoleCode } from '@/lib/strings'
import { spyOnConsole } from '@/test/console-spy'
import { apiError, deferred, json, networkError, setXsrfCookie } from '@/test/http'
import { renderAs } from '@/test/render'

// add-assistant-model-selector 5.2–5.5 — assistant-screen «Selector de modelo», «Elección de modelo
// conservada al recargar» y «Modelo que respondió». Red simulada en el borde HTTP; `localStorage` real
// de jsdom, vacío al empezar cada prueba.

const a = strings.assistant
const m = strings.assistant.model
const ASK = 'POST /api/assistant/ask'
const MODELS = 'GET /api/assistant/models'
const KEY = 'dispensart.assistant.model'
const OLLAMA_ID = 'ollama:gemma4:e2b-mlx'
const OLLAMA_LABEL = 'Ollama · gemma4:e2b-mlx'

const MOCK_MODEL = { id: 'mock', provider: 'mock', name: 'mock' }
const OLLAMA_MODEL = { id: OLLAMA_ID, provider: 'ollama', name: 'gemma4:e2b-mlx' }
const list = (...models: object[]) => () => json(200, { data: models })
const withOllama = list(MOCK_MODEL, OLLAMA_MODEL)
const mockOnly = list(MOCK_MODEL)

const answer = (model: string) => ({
  outcome: 'answered',
  answer: 'Hay 30 unidades disponibles.',
  tool_calls: [],
  model,
})
const reply = (model: string) => () => json(200, { data: answer(model) })

type Routes = Parameters<typeof renderAs>[2]

const select = () => screen.getByLabelText(m.label) as HTMLSelectElement
const optionLabels = () => Array.from(select().options).map((option) => option.textContent)
const askButton = () => screen.getByRole('button', { name: a.submit })

// Abre la pantalla y espera la lista (selector habilitado), salvo que la prueba controle la carga.
async function openAssistant(role: RoleCode, routes: Routes, waitList = true) {
  setXsrfCookie('token-1')
  const view = renderAs(role, '/assistant', routes)
  const box = (await screen.findByLabelText(a.question)) as HTMLTextAreaElement
  if (waitList) await waitFor(() => expect(select()).toBeEnabled())
  return { ...view, box }
}

const asked = (api: ReturnType<typeof renderAs>['api']) => api.requestsTo('POST', '/api/assistant/ask')
const listed = (api: ReturnType<typeof renderAs>['api']) => api.requestsTo('GET', '/api/assistant/models')

async function settle(client: QueryClient) {
  await waitFor(() => {
    expect(client.isMutating()).toBe(0)
    expect(client.isFetching()).toBe(0)
  })
}

async function ask(box: HTMLTextAreaElement, question: string) {
  fireEvent.change(box, { target: { value: question } })
  fireEvent.click(askButton())
  return screen.findByRole('article', { name: question })
}

const choose = (id: string) => fireEvent.change(select(), { target: { value: id } })

beforeEach(() => localStorage.clear())
afterEach(() => vi.restoreAllMocks())

describe('Selector de modelo', () => {
  it('Selector con Ollama disponible: "Simulado (sin red)" y "Ollama · …" en orden, Simulado elegido', async () => {
    await openAssistant('auxiliar_farmacia', { [MODELS]: withOllama })

    // Etiqueta asociada: el selector se encuentra por su nombre accesible "Modelo".
    expect(select().tagName).toBe('SELECT')
    expect(optionLabels()).toEqual(['Simulado (sin red)', OLLAMA_LABEL])
    expect(select().value).toBe('mock')
    expect(screen.getByRole('combobox', { name: 'Modelo' })).toBe(select())
  })

  it('Ollama no disponible: solo "Simulado (sin red)" y ningún aviso de error', async () => {
    await openAssistant('auxiliar_farmacia', { [MODELS]: mockOnly })

    expect(optionLabels()).toEqual(['Simulado (sin red)'])
    expect(document.querySelectorAll(`option[value="${OLLAMA_ID}"]`)).toHaveLength(0)
    expect(screen.queryByText(m.listFailed)).not.toBeInTheDocument()
    expect(screen.queryByText(m.storedMissing)).not.toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('Apertura consulta solo la lista: un GET de modelos y ningún POST de preguntas', async () => {
    const { api, client } = await openAssistant('regente_farmacia', {
      [MODELS]: withOllama,
      [ASK]: reply('mock'),
    })

    await settle(client)
    expect(listed(api)).toHaveLength(1)
    expect(asked(api)).toHaveLength(0)
  })

  it('Carga de la lista: selector deshabilitado en Simulado, aviso de carga, caja escribible y "Preguntar" en espera', async () => {
    const pending = deferred<Response>()
    const { box } = await openAssistant('auxiliar_farmacia', { [MODELS]: () => pending.promise }, false)

    expect(select()).toBeDisabled()
    expect(select().value).toBe('mock')
    expect(optionLabels()).toEqual(['Simulado (sin red)'])
    expect(screen.getByRole('status')).toHaveTextContent(m.loading)
    fireEvent.change(box, { target: { value: '¿Cuánto acetaminofén hay?' } })
    expect(box.value).toBe('¿Cuánto acetaminofén hay?')
    expect(askButton()).toBeDisabled()

    pending.resolve(withOllama())
    // Control positivo: al llegar la lista, selector y botón quedan habilitados y el aviso se retira.
    await waitFor(() => expect(askButton()).toBeEnabled())
    expect(select()).toBeEnabled()
    expect(screen.queryByText(m.loading)).not.toBeInTheDocument()
  })

  it.each([
    ['red', networkError],
    ['server_error', () => apiError(500, 'server_error')],
  ])('Fallo de la lista (%s): solo Simulado, aviso propio y la pregunta lleva model mock', async (_caso, resolver) => {
    const { api, box } = await openAssistant('auxiliar_farmacia', { [MODELS]: resolver, [ASK]: reply('mock') })

    expect(optionLabels()).toEqual(['Simulado (sin red)'])
    expect(screen.getByText(m.listFailed)).toBeInTheDocument()
    expect(document.body.textContent).not.toMatch(/server_error|Failed to fetch/)

    await ask(box, '¿Cuánto acetaminofén hay?')
    expect(asked(api)[0].body).toEqual({ question: '¿Cuánto acetaminofén hay?', model: 'mock' })
  })

  it('Pregunta con un modelo de Ollama: una sola petición con el model elegido', async () => {
    const QUESTION = '¿Cuántos traslados hay en tránsito?'
    const { api, box } = await openAssistant('auxiliar_farmacia', { [MODELS]: withOllama, [ASK]: reply(OLLAMA_ID) })

    choose(OLLAMA_ID)
    await ask(box, QUESTION)

    expect(asked(api)).toHaveLength(1)
    expect(asked(api)[0].body).toEqual({ question: QUESTION, model: OLLAMA_ID })
  })

  it('Selector durante una consulta: deshabilitado y sin cambiar de valor hasta la respuesta', async () => {
    const pending = deferred<Response>()
    const { box } = await openAssistant('auxiliar_farmacia', { [MODELS]: withOllama, [ASK]: () => pending.promise })

    fireEvent.change(box, { target: { value: 'Pregunta en curso' } })
    fireEvent.click(askButton())
    await screen.findByRole('button', { name: a.submitting })

    expect(select()).toBeDisabled()
    choose(OLLAMA_ID)
    expect(select().value).toBe('mock')

    pending.resolve(json(200, { data: answer('mock') }))
    await screen.findByRole('article', { name: 'Pregunta en curso' })
    // Control positivo: con la respuesta recibida el selector vuelve a aceptar el cambio.
    await waitFor(() => expect(select()).toBeEnabled())
    choose(OLLAMA_ID)
    expect(select().value).toBe(OLLAMA_ID)
  })

  it('Modelo rechazado por el servidor: mensaje junto al selector, pregunta conservada y lista pedida otra vez', async () => {
    const QUESTION = '¿Cuánto acetaminofén hay?'
    const message = 'El modelo elegido no está disponible.'
    const { api, box } = await openAssistant('auxiliar_farmacia', {
      [MODELS]: [withOllama, mockOnly],
      [ASK]: () => json(422, { code: 'validation_failed', message: 'x', errors: { model: [message] } }),
    })
    choose(OLLAMA_ID)

    fireEvent.change(box, { target: { value: QUESTION } })
    fireEvent.click(askButton())

    // Junto al selector: dentro de su mismo campo y anunciado por su `aria-describedby`.
    const field = select().closest('[data-slot="field"]') as HTMLElement
    expect(await within(field).findByText(message)).toBeInTheDocument()
    expect(select()).toHaveAttribute('aria-invalid', 'true')
    expect(box.value).toBe(QUESTION)
    expect(screen.queryByRole('article')).not.toBeInTheDocument()
    await waitFor(() => expect(listed(api)).toHaveLength(2))
    await waitFor(() => expect(select().value).toBe('mock'))
    expect(optionLabels()).toEqual(['Simulado (sin red)'])
  })

  it('Admin sin consulta de modelos: aviso de permiso y ningún GET de modelos', async () => {
    const { api, client } = renderAs('admin', '/assistant', { [MODELS]: withOllama })

    expect(await screen.findByText(strings.guard.forbidden)).toBeInTheDocument()
    await settle(client)
    expect(listed(api)).toHaveLength(0)
    expect(screen.queryByLabelText(m.label)).not.toBeInTheDocument()
  })
})

describe('Elección de modelo conservada al recargar', () => {
  it('La elección sobrevive a una recarga: el selector la restaura y la pregunta la lleva', async () => {
    const routes = { [MODELS]: withOllama, [ASK]: reply(OLLAMA_ID) }
    const first = await openAssistant('auxiliar_farmacia', routes)
    choose(OLLAMA_ID)
    first.unmount()

    // Recarga: SPA montada de nuevo, caché de consultas nueva; solo `localStorage` persiste.
    const { api, box } = await openAssistant('auxiliar_farmacia', routes)

    expect(select().value).toBe(OLLAMA_ID)
    expect(select().selectedOptions[0].textContent).toBe(OLLAMA_LABEL)
    await ask(box, '¿Cuánto acetaminofén hay?')
    expect(asked(api)[0].body).toEqual({ question: '¿Cuánto acetaminofén hay?', model: OLLAMA_ID })
  })

  it('Primera carga: Simulado elegido y ninguna clave en localStorage hasta elegir', async () => {
    await openAssistant('auxiliar_farmacia', { [MODELS]: withOllama })

    expect(select().value).toBe('mock')
    expect(localStorage.length).toBe(0)
    // Control positivo: elegir sí escribe la clave, con solo el `id`.
    choose(OLLAMA_ID)
    expect(localStorage.getItem(KEY)).toBe(OLLAMA_ID)
  })

  it('Modelo guardado que ya no está disponible: Simulado, aviso y la pregunta lleva model mock', async () => {
    localStorage.setItem(KEY, OLLAMA_ID)
    const { api, box } = await openAssistant('auxiliar_farmacia', { [MODELS]: mockOnly, [ASK]: reply('mock') })

    expect(select().value).toBe('mock')
    expect(screen.getByText(m.storedMissing)).toBeInTheDocument()
    await ask(box, '¿Cuánto acetaminofén hay?')
    expect(asked(api)[0].body).toEqual({ question: '¿Cuánto acetaminofén hay?', model: 'mock' })
    // El regreso a Simulado no sobrescribe la elección guardada: vuelve cuando Ollama vuelva.
    expect(localStorage.getItem(KEY)).toBe(OLLAMA_ID)
  })

  it.each(['http://atacante.example/api', '<img src=x onerror=alert(1)>', ''])(
    'Valor guardado manipulado (%j): Simulado, ninguna petición lo lleva y ningún img',
    async (stored) => {
      localStorage.setItem(KEY, stored)
      const { api, box } = await openAssistant('auxiliar_farmacia', { [MODELS]: withOllama, [ASK]: reply('mock') })

      expect(select().value).toBe('mock')
      await ask(box, '¿Cuánto acetaminofén hay?')
      expect(asked(api)[0].body).toEqual({ question: '¿Cuánto acetaminofén hay?', model: 'mock' })
      const sent = api.requests.map((request) => JSON.stringify(request))
      if (stored !== '') expect(sent.filter((request) => request.includes(stored))).toEqual([])
      expect(document.querySelectorAll('img')).toHaveLength(0)
      // Control positivo: el valor sigue guardado (la pantalla lo leyó y lo descartó).
      expect(localStorage.getItem(KEY)).toBe(stored)
    },
  )

  it('Almacenamiento no disponible: funciona con Simulado, la elección vale para la visita y sin error', async () => {
    const spies = spyOnConsole()
    const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('bloqueado', 'SecurityError')
    })
    const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('bloqueado', 'SecurityError')
    })
    const { api, box } = await openAssistant('auxiliar_farmacia', { [MODELS]: withOllama, [ASK]: reply(OLLAMA_ID) })

    expect(select().value).toBe('mock')
    choose(OLLAMA_ID)
    expect(select().value).toBe(OLLAMA_ID)
    await ask(box, '¿Cuánto acetaminofén hay?')

    expect(asked(api)[0].body).toEqual({ question: '¿Cuánto acetaminofén hay?', model: OLLAMA_ID })
    // Control positivo: la pantalla sí intentó leer y escribir el almacenamiento.
    expect(getItem).toHaveBeenCalledWith(KEY)
    expect(setItem).toHaveBeenCalledWith(KEY, OLLAMA_ID)
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    for (const spy of spies) expect(spy).not.toHaveBeenCalled()
  })
})

describe('Modelo que respondió', () => {
  it('Respuesta de Ollama: "Respondió: Ollama · gemma4:e2b-mlx"', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', { [MODELS]: withOllama, [ASK]: reply(OLLAMA_ID) })
    choose(OLLAMA_ID)

    const entry = await ask(box, '¿Cuántos traslados hay en tránsito?')

    expect(within(entry).getByText(`Respondió: ${OLLAMA_LABEL}`)).toBeInTheDocument()
  })

  it('Cambiar el selector no reescribe el historial: la entrada sigue con su modelo', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', { [MODELS]: withOllama, [ASK]: reply('mock') })

    const entry = await ask(box, '¿Cuánto acetaminofén hay?')
    expect(within(entry).getByText('Respondió: Simulado (sin red)')).toBeInTheDocument()
    choose(OLLAMA_ID)

    // Control positivo: el selector sí cambió.
    expect(select().value).toBe(OLLAMA_ID)
    expect(within(entry).getByText('Respondió: Simulado (sin red)')).toBeInTheDocument()
    expect(within(entry).queryByText(`Respondió: ${OLLAMA_LABEL}`)).not.toBeInTheDocument()
  })

  it('Modelo desconocido en la respuesta: "Respondió: Modelo desconocido" sin el texto recibido', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', { [MODELS]: withOllama, [ASK]: reply('openai:gpt-4o') })

    const entry = await ask(box, '¿Cuánto acetaminofén hay?')

    expect(within(entry).getByText('Respondió: Modelo desconocido')).toBeInTheDocument()
    expect(document.body.innerHTML).not.toContain('openai:gpt-4o')
  })
})
