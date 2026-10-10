import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import type { QueryClient } from '@tanstack/react-query'
import { describe, expect, it, vi } from 'vitest'
import { strings, type RoleCode } from '@/lib/strings'
import { spyOnConsole } from '@/test/console-spy'
import { installFakeApi } from '@/test/fake-api'
import { catalogRoutes, lots, noAlertsRoute, stockRow, warehouses } from '@/test/fixtures'
import { apiError, deferred, json, networkError, setXsrfCookie } from '@/test/http'
import { renderAs, sessionUser } from '@/test/render'
import { renderApp } from '@/test/render-app'

// add-assistant-screen 2.1–2.6 — assistant-screen (pantalla, envío, resultado por `outcome`,
// consultas hechas, historial en memoria, errores, ejemplos y aviso). Red simulada en el borde HTTP.

const a = strings.assistant
const ASK = 'POST /api/assistant/ask'

type Answer = {
  outcome: string
  answer: string
  tool_calls: { tool: string; arguments: Record<string, string | number>; status: string }[]
}

const answer = (overrides: Partial<Answer> = {}): Answer => ({
  outcome: 'answered',
  answer: 'Hay 30 unidades disponibles.',
  tool_calls: [],
  ...overrides,
})
const reply = (body: Answer) => () => json(200, { data: body })

type Routes = Parameters<typeof renderAs>[2]

async function openAssistant(role: RoleCode = 'auxiliar_farmacia', routes: Routes = {}) {
  setXsrfCookie('token-1')
  const view = renderAs(role, '/assistant', routes)
  const box = (await screen.findByLabelText(a.question)) as HTMLTextAreaElement
  return { ...view, box }
}

const askButton = () => screen.getByRole('button', { name: a.submit })
const asked = (api: ReturnType<typeof renderAs>['api']) => api.requestsTo('POST', '/api/assistant/ask')

// Espera a que no quede ninguna pregunta en curso: si la pantalla hubiera enviado algo, la petición
// ya está registrada al terminar.
async function settle(client: QueryClient) {
  await waitFor(() => expect(client.isMutating()).toBe(0))
}

async function ask(box: HTMLTextAreaElement, question: string) {
  fireEvent.change(box, { target: { value: question } })
  fireEvent.click(askButton())
  return screen.findByRole('article', { name: question })
}

describe('Pantalla Asistente para los roles de operación', () => {
  it('Médico abre el asistente desde el menú: título, caja con foco, "Preguntar" y enlace actual', async () => {
    const { router } = renderAs('medico', '/')
    const menu = await screen.findByRole('navigation', { name: strings.nav.label })

    fireEvent.click(within(menu).getByRole('link', { name: strings.nav.assistant }))

    expect(await screen.findByRole('heading', { name: 'Asistente de inventario' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/assistant')
    expect(screen.getByLabelText('Tu pregunta')).toHaveFocus()
    expect(screen.getByRole('button', { name: 'Preguntar' })).toBeEnabled()
    expect(within(menu).getByRole('link', { name: strings.nav.assistant })).toHaveAttribute('aria-current', 'page')
  })

  it('Apertura sin preguntas enviadas: estado vacío y ninguna petición al asistente', async () => {
    const { api, client } = await openAssistant('auxiliar_farmacia', { [ASK]: reply(answer()) })

    expect(screen.getByText('Aún no has hecho preguntas. Prueba con uno de los ejemplos.')).toBeInTheDocument()
    await settle(client)
    expect(asked(api)).toHaveLength(0)
  })

  it('Acceso directo sin sesión: lleva a /login sin la pantalla ni preguntas', async () => {
    const api = installFakeApi({ 'GET /api/auth/me': () => apiError(401, 'unauthenticated') })
    const { router } = renderApp('/assistant')

    expect(await screen.findByLabelText(strings.login.email)).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(screen.queryByText(a.title)).not.toBeInTheDocument()
    expect(api.callsTo('POST', '/api/assistant/ask')).toHaveLength(0)
  })

  // add-admin-screens 2.1 — el asistente no es función del admin (§ 3): la guarda muestra el aviso.
  it('Admin escribe la dirección del asistente: aviso de permiso, sin la caja ni preguntas', async () => {
    const { api, client } = renderAs('admin', '/assistant', { [ASK]: reply(answer()) })

    expect(await screen.findByText(strings.guard.forbidden)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: strings.guard.backHome })).toHaveAttribute('href', '/')
    expect(screen.queryByLabelText(a.question)).not.toBeInTheDocument()
    await settle(client)
    expect(asked(api)).toHaveLength(0)
  })

  it('control positivo: el médico que escribe la misma dirección ve la caja "Tu pregunta"', async () => {
    renderAs('medico', '/assistant')

    expect(await screen.findByLabelText(a.question)).toBeInTheDocument()
    expect(screen.queryByText(strings.guard.forbidden)).not.toBeInTheDocument()
  })
})

describe('Envío de una pregunta', () => {
  const QUESTION = '¿Cuánto stock hay de acetaminofén en la farmacia central?'

  it('Pregunta enviada: una sola petición con {"question": …} y progreso anunciado', async () => {
    const pending = deferred<Response>()
    const { api, box } = await openAssistant('auxiliar_farmacia', { [ASK]: () => pending.promise })

    fireEvent.change(box, { target: { value: QUESTION } })
    fireEvent.click(askButton())

    expect(await screen.findByRole('button', { name: 'Consultando…' })).toBeDisabled()
    expect(screen.getByRole('status')).toHaveTextContent('Consultando al asistente…')
    await waitFor(() => expect(asked(api)).toHaveLength(1))
    expect(asked(api)[0].body).toEqual({ question: QUESTION })
    expect(asked(api)[0].headers['x-xsrf-token']).toBe('token-1')

    pending.resolve(json(200, { data: answer() }))
    expect(await screen.findByRole('article', { name: QUESTION })).toBeInTheDocument()
    expect(asked(api)).toHaveLength(1)
  })

  it.each(['', '   '])('Pregunta vacía (%j): "Este campo es obligatorio." y ninguna petición', async (value) => {
    const { api, client, box } = await openAssistant('auxiliar_farmacia', { [ASK]: reply(answer()) })

    fireEvent.change(box, { target: { value } })
    fireEvent.click(askButton())

    expect(await screen.findByText('Este campo es obligatorio.')).toBeInTheDocument()
    expect(box).toHaveAttribute('aria-invalid', 'true')
    await settle(client)
    expect(asked(api)).toHaveLength(0)
  })

  it('Pregunta demasiado corta: "ab" con Enter muestra el mínimo y no envía', async () => {
    const { api, client, box } = await openAssistant('auxiliar_farmacia', { [ASK]: reply(answer()) })

    fireEvent.change(box, { target: { value: 'ab' } })
    fireEvent.keyDown(box, { key: 'Enter' })

    expect(await screen.findByText('Escribe al menos 3 caracteres.')).toBeInTheDocument()
    await settle(client)
    expect(asked(api)).toHaveLength(0)
  })

  it('Tope de 500 caracteres: 600 pegados quedan en 500, contador "500/500" y se envían 500', async () => {
    const { api, box } = await openAssistant('auxiliar_farmacia', { [ASK]: reply(answer()) })

    fireEvent.change(box, { target: { value: 'a'.repeat(600) } })

    expect(box.value).toHaveLength(500)
    expect(screen.getByText('500/500')).toBeInTheDocument()
    fireEvent.click(askButton())
    await screen.findByRole('article')
    expect((asked(api)[0].body as { question: string }).question).toHaveLength(500)
  })

  it('Doble clic produce una sola pregunta', async () => {
    const pending = deferred<Response>()
    const { api, client, box } = await openAssistant('auxiliar_farmacia', { [ASK]: () => pending.promise })
    fireEvent.change(box, { target: { value: QUESTION } })

    const button = askButton()
    fireEvent.click(button)
    fireEvent.click(button)

    await waitFor(() => expect(asked(api).length).toBeGreaterThan(0))
    pending.resolve(json(200, { data: answer() }))
    await screen.findByRole('article', { name: QUESTION })
    await settle(client)
    expect(asked(api)).toHaveLength(1)
  })

  it('Enter repetido: una sola pregunta', async () => {
    const pending = deferred<Response>()
    const { api, client, box } = await openAssistant('auxiliar_farmacia', { [ASK]: () => pending.promise })
    fireEvent.change(box, { target: { value: QUESTION } })

    fireEvent.keyDown(box, { key: 'Enter' })
    fireEvent.keyDown(box, { key: 'Enter' })

    await waitFor(() => expect(asked(api).length).toBeGreaterThan(0))
    pending.resolve(json(200, { data: answer() }))
    await screen.findByRole('article', { name: QUESTION })
    await settle(client)
    expect(asked(api)).toHaveLength(1)
  })

  it('Salto de línea sin envío: Shift+Enter deja el salto al navegador y no envía', async () => {
    const { api, client, box } = await openAssistant('auxiliar_farmacia', { [ASK]: reply(answer()) })
    fireEvent.change(box, { target: { value: 'primera línea' } })

    // `fireEvent` devuelve `false` si el manejador anuló la acción por defecto (el salto de línea).
    expect(fireEvent.keyDown(box, { key: 'Enter', shiftKey: true })).toBe(true)
    fireEvent.change(box, { target: { value: 'primera línea\nsegunda línea' } })

    expect(box.value).toBe('primera línea\nsegunda línea')
    await settle(client)
    expect(asked(api)).toHaveLength(0)
    // Control positivo: Enter solo sí anula el salto y envía.
    expect(fireEvent.keyDown(box, { key: 'Enter' })).toBe(false)
    await screen.findByRole('article')
    expect(asked(api)).toHaveLength(1)
  })
})

describe('Resultado según el outcome del servidor', () => {
  it('Pregunta respondida: "Respondida", dos líneas separadas, caja vacía con foco', async () => {
    const lines = ['Hay 30 unidades disponibles en Farmacia Central.', 'El lote más próximo vence en marzo.']
    const { box } = await openAssistant('auxiliar_farmacia', {
      [ASK]: reply(answer({ answer: lines.join('\n') })),
    })

    const entry = await ask(box, '¿Cuánto acetaminofén hay?')

    const badge = within(entry).getByText('Respondida')
    expect(badge).toHaveAttribute('data-variant', 'default')
    expect(within(entry).getByText(lines[0])).not.toBe(within(entry).getByText(lines[1]))
    expect(within(entry).getByText(lines[0]).textContent).toBe(lines[0])
    expect(screen.getAllByRole('article')[0]).toBe(entry)
    expect(box.value).toBe('')
    expect(box).toHaveFocus()
  })

  it('Sin resultados: etiqueta propia, con otro estilo, y sin "Respondida"', async () => {
    const text = 'No encontré resultados para esa consulta.'
    const { box } = await openAssistant('auxiliar_farmacia', {
      [ASK]: reply(answer({ outcome: 'no_results', answer: text })),
    })

    const entry = await ask(box, '¿Hay ibuprofeno en urgencias?')

    expect(within(entry).getByText('Sin resultados')).toHaveAttribute('data-variant', 'outline')
    expect(within(entry).getByText(text)).toBeInTheDocument()
    expect(within(entry).queryByText('Respondida')).not.toBeInTheDocument()
  })

  it('Pregunta fuera de alcance: "Fuera de alcance" con el mensaje del servidor', async () => {
    const text = 'Solo puedo responder preguntas sobre inventario y traslados.'
    const { box } = await openAssistant('auxiliar_farmacia', {
      [ASK]: reply(answer({ outcome: 'out_of_scope', answer: text })),
    })

    const entry = await ask(box, '¿Va a llover mañana en Santa Marta?')

    expect(within(entry).getByText('Fuera de alcance')).toBeInTheDocument()
    expect(within(entry).getByText(text)).toBeInTheDocument()
    expect(within(entry).queryByText('Respondida')).not.toBeInTheDocument()
  })

  it('Pregunta sobre un paciente: "Fuera de alcance" y ningún dato adicional del paciente', async () => {
    const question = '¿Qué medicamentos le dispensaron a la paciente Ana Sintética Pérez?'
    const text = 'Solo puedo responder preguntas sobre inventario y traslados.'
    const { box } = await openAssistant('regente_farmacia', {
      [ASK]: reply(answer({ outcome: 'out_of_scope', answer: text })),
    })

    const entry = await ask(box, question)

    expect(within(entry).getByText('Fuera de alcance')).toBeInTheDocument()
    // Todo el texto de la entrada: la pregunta escrita, la etiqueta, el mensaje y "sin consultas".
    expect(entry.textContent).toBe(
      [question, 'Fuera de alcance', text, a.toolCalls, 'Sin consultas a herramientas.'].join(''),
    )
  })

  it('Rol sin permiso para la consulta: "Sin permiso", ningún código de lote ni cantidad', async () => {
    const text = 'Tu rol no tiene permiso para consultar esa información.'
    const { box } = await openAssistant('medico', {
      [ASK]: reply(
        answer({
          outcome: 'not_permitted',
          answer: text,
          tool_calls: [{ tool: 'find_expiring_lots', arguments: { days: 30 }, status: 'denied' }],
        }),
      ),
    })

    const entry = await ask(box, '¿Qué lotes vencen en los próximos 30 días?')

    expect(within(entry).getAllByText('Sin permiso')[0]).toHaveAttribute('data-variant', 'outline')
    expect(within(entry).getByText(text)).toBeInTheDocument()
    expect(entry.textContent).not.toMatch(/L-[A-Z]{3}|unidades/)
  })

  it('Sin respuesta: "Sin respuesta" con el texto del servidor', async () => {
    const text = 'No sé responder esa pregunta con la información disponible.'
    const { box } = await openAssistant('auxiliar_farmacia', {
      [ASK]: reply(answer({ outcome: 'unknown', answer: text })),
    })

    const entry = await ask(box, '¿Cuál es el mejor proveedor?')

    expect(within(entry).getByText('Sin respuesta')).toBeInTheDocument()
    expect(within(entry).getByText(text)).toBeInTheDocument()
  })

  it('Respuesta con marcado no se interpreta: texto literal y ningún `img` en el documento', async () => {
    const markup = '<img src=x onerror=alert(1)>'
    const { box } = await openAssistant('auxiliar_farmacia', { [ASK]: reply(answer({ answer: markup })) })

    const entry = await ask(box, '¿Cuánto acetaminofén hay?')

    expect(within(entry).getByText(markup)).toBeInTheDocument()
    expect(document.querySelectorAll('img')).toHaveLength(0)
  })
})

describe('Consultas hechas por pregunta', () => {
  const toolsSection = (entry: HTMLElement) => within(entry).getByRole('region', { name: a.toolCalls })

  it('Consulta de existencias: herramienta, estado y argumentos en español', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', {
      [ASK]: reply(
        answer({
          tool_calls: [
            { tool: 'get_stock', arguments: { product: 'acetaminofén', warehouse: 'Farmacia Central' }, status: 'ok' },
          ],
        }),
      ),
    })

    const tools = toolsSection(await ask(box, '¿Cuánto acetaminofén hay?'))

    expect(within(tools).getByText('Existencias')).toBeInTheDocument()
    expect(within(tools).getByText('Consultada')).toBeInTheDocument()
    expect(within(tools).getByText('Producto: acetaminofén')).toBeInTheDocument()
    expect(within(tools).getByText('Bodega: Farmacia Central')).toBeInTheDocument()
  })

  it('Estado de traslado en español: "Estado: En tránsito", nunca EN_TRANSITO', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', {
      [ASK]: reply(
        answer({ tool_calls: [{ tool: 'get_transfer_status', arguments: { status: 'EN_TRANSITO' }, status: 'ok' }] }),
      ),
    })

    const tools = toolsSection(await ask(box, '¿Cuántos traslados hay en tránsito?'))

    expect(within(tools).getByText('Estado de traslados')).toBeInTheDocument()
    expect(within(tools).getByText('Estado: En tránsito')).toBeInTheDocument()
    expect(document.body.textContent).not.toContain('EN_TRANSITO')
  })

  it('Llamada negada: "Sin permiso" y ninguna línea de argumentos', async () => {
    const { box } = await openAssistant('medico', {
      [ASK]: reply(
        answer({
          outcome: 'not_permitted',
          answer: 'Tu rol no tiene permiso para consultar esa información.',
          tool_calls: [{ tool: 'get_transfer_status', arguments: {}, status: 'denied' }],
        }),
      ),
    })

    const tools = toolsSection(await ask(box, '¿Cuántos traslados hay en tránsito?'))

    const [call] = within(tools).getAllByRole('listitem')
    expect(within(call).getByText('Estado de traslados')).toBeInTheDocument()
    expect(within(call).getByText('Sin permiso')).toBeInTheDocument()
    expect(within(call).queryByRole('list')).not.toBeInTheDocument()
  })

  it('Herramienta fuera del catálogo: texto genérico, "Rechazada" y nunca el nombre pedido', async () => {
    const { box } = await openAssistant('regente_farmacia', {
      [ASK]: reply(
        answer({
          outcome: 'unknown',
          answer: 'No sé responder esa pregunta con la información disponible.',
          tool_calls: [{ tool: 'approve_transfer', arguments: {}, status: 'rejected' }],
        }),
      ),
    })

    const tools = toolsSection(await ask(box, 'Aprueba el traslado 12'))

    expect(within(tools).getByText('Herramienta fuera del catálogo')).toBeInTheDocument()
    expect(within(tools).getByText('Rechazada')).toBeInTheDocument()
    expect(document.body.textContent).not.toContain('approve_transfer')
  })

  it('Sin consultas: "Sin consultas a herramientas."', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', { [ASK]: reply(answer({ tool_calls: [] })) })

    const tools = toolsSection(await ask(box, '¿Cuánto acetaminofén hay?'))

    expect(within(tools).getByText('Sin consultas a herramientas.')).toBeInTheDocument()
  })
})

describe('Historial de la pantalla solo en memoria', () => {
  it('Dos preguntas seguidas: ambas con su resultado, la segunda arriba', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', {
      [ASK]: [reply(answer({ answer: 'Primera respuesta.' })), reply(answer({ answer: 'Segunda respuesta.' }))],
    })

    await ask(box, 'Primera pregunta')
    await ask(box, 'Segunda pregunta')

    const entries = screen.getAllByRole('article')
    expect(entries.map((entry) => within(entry).getByRole('heading', { level: 3 }).textContent)).toEqual([
      'Segunda pregunta',
      'Primera pregunta',
    ])
    expect(within(entries[0]).getByText('Segunda respuesta.')).toBeInTheDocument()
    expect(within(entries[1]).getByText('Primera respuesta.')).toBeInTheDocument()
  })

  it('Undécima pregunta: 10 entradas y ya no la primera', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', { [ASK]: reply(answer()) })

    for (let n = 1; n <= 11; n += 1) await ask(box, `Pregunta número ${n}`)

    const entries = screen.getAllByRole('article')
    expect(entries).toHaveLength(10)
    expect(within(entries[0]).getByRole('heading', { level: 3 })).toHaveTextContent('Pregunta número 11')
    expect(screen.queryByRole('article', { name: 'Pregunta número 1' })).not.toBeInTheDocument()
    expect(screen.getByRole('article', { name: 'Pregunta número 2' })).toBeInTheDocument()
  })

  it('Salir y volver vacía el historial', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', {
      [ASK]: reply(answer()),
      ...catalogRoutes(),
      ...noAlertsRoute(),
      'GET /api/stock': () => json(200, { data: [stockRow(1, warehouses[0], lots[0], 8)] }),
    })
    await ask(box, '¿Cuánto acetaminofén hay?')
    const menu = screen.getByRole('navigation', { name: strings.nav.label })

    fireEvent.click(within(menu).getByRole('link', { name: strings.nav.inventory }))
    expect(await screen.findByRole('heading', { name: strings.inventory.title })).toBeInTheDocument()
    fireEvent.click(within(menu).getByRole('link', { name: strings.nav.assistant }))

    expect(await screen.findByText('Aún no has hecho preguntas. Prueba con uno de los ejemplos.')).toBeInTheDocument()
    expect(screen.queryByRole('article')).not.toBeInTheDocument()
    expect(document.body.textContent).not.toContain('¿Cuánto acetaminofén hay?')
  })

  it('Pregunta con un documento fuera del navegador persistente', async () => {
    const DOC = '9999010001'
    const spies = spyOnConsole()
    const { box, router } = await openAssistant('auxiliar_farmacia', { [ASK]: reply(answer()) })

    // Barrido: almacenamientos, URL (router y ventana) y cada argumento de cada llamada a la consola.
    const dump = (storage: Storage) =>
      Array.from({ length: storage.length }, (_, i) => `${storage.key(i)}=${storage.getItem(storage.key(i) ?? '')}`)
    const sweep = (extra: string[] = []) =>
      [
        ...dump(localStorage),
        ...dump(sessionStorage),
        JSON.stringify(router.state.location),
        window.location.href,
        ...spies.flatMap((spy) => vi.mocked(spy).mock.calls.map((call) => JSON.stringify(call))),
        ...extra,
      ].filter((value) => value.includes(DOC))

    const question = `¿Cuánto acetaminofén retiró ${DOC}?`
    fireEvent.change(box, { target: { value: question } })
    // Control positivo: el mismo barrido encuentra el documento en la caja antes de enviar.
    expect(sweep([box.value])).toHaveLength(1)

    fireEvent.click(askButton())
    await screen.findByRole('article', { name: question })

    expect(sweep()).toEqual([])
  })
})

describe('Errores de la pregunta', () => {
  const QUESTION = '¿Cuánto acetaminofén hay?'

  it('Validación del servidor: errors.question junto a la caja, texto conservado, botón habilitado', async () => {
    const message = 'La pregunta debe tener al menos 3 caracteres.'
    const { box } = await openAssistant('auxiliar_farmacia', {
      [ASK]: () => json(422, { code: 'validation_failed', message: 'x', errors: { question: [message] } }),
    })

    fireEvent.change(box, { target: { value: QUESTION } })
    fireEvent.click(askButton())

    expect(await screen.findByText(message)).toBeInTheDocument()
    expect(box).toHaveAttribute('aria-invalid', 'true')
    expect(box.value).toBe(QUESTION)
    // Dos alertas: el mensaje del catálogo y el error del campo.
    expect(screen.getAllByRole('alert').map((alert) => alert.textContent)).toEqual([
      message,
      strings.errors.validation,
    ])
    expect(await screen.findByRole('button', { name: a.submit })).toBeEnabled()
  })

  it('Demasiadas preguntas: texto propio y pregunta conservada', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', { [ASK]: () => apiError(429, 'too_many_requests') })

    fireEvent.change(box, { target: { value: QUESTION } })
    fireEvent.click(askButton())

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Hiciste muchas preguntas seguidas. Espera un minuto e intenta de nuevo.',
    )
    expect(box.value).toBe(QUESTION)
  })

  it('Asistente no disponible: texto propio, pregunta conservada y sin entrada nueva', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', { [ASK]: () => apiError(503, 'assistant_unavailable') })

    fireEvent.change(box, { target: { value: QUESTION } })
    fireEvent.click(askButton())

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'El asistente no está disponible en este momento. Intenta más tarde.',
    )
    expect(box.value).toBe(QUESTION)
    expect(screen.queryByRole('article')).not.toBeInTheDocument()
    expect(screen.getByText(a.empty)).toBeInTheDocument()
    expect(await screen.findByRole('button', { name: a.submit })).toBeEnabled()
  })

  it.each([
    ['red', networkError],
    ['server_error', () => apiError(500, 'server_error')],
  ])('Fallo de red (%s): texto de red sin el mensaje técnico', async (_caso, resolver) => {
    const { box } = await openAssistant('auxiliar_farmacia', { [ASK]: resolver })

    fireEvent.change(box, { target: { value: QUESTION } })
    fireEvent.click(askButton())

    expect(await screen.findByRole('alert')).toHaveTextContent('No pudimos conectar con el servidor. Intenta de nuevo.')
    expect(document.body.textContent).not.toMatch(/Failed to fetch|server_error|network_error/)
  })

  it('Código desconocido: texto genérico, sin el código', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', { [ASK]: () => apiError(429, 'quota_exceeded') })

    fireEvent.change(box, { target: { value: QUESTION } })
    fireEvent.click(askButton())

    expect(await screen.findByRole('alert')).toHaveTextContent('Ocurrió un error inesperado. Intenta de nuevo.')
    expect(document.body.textContent).not.toContain('quota_exceeded')
  })

  it('Sesión expirada al preguntar: navega a /login con el aviso de app-shell', async () => {
    const { box, router } = await openAssistant('auxiliar_farmacia', {
      'GET /api/auth/me': [
        () => json(200, { data: sessionUser('auxiliar_farmacia') }),
        () => apiError(401, 'unauthenticated'),
      ],
      [ASK]: () => apiError(401, 'unauthenticated'),
    })

    fireEvent.change(box, { target: { value: QUESTION } })
    fireEvent.click(askButton())

    // Primero la pantalla de login: el aviso es el de app-shell, no la alerta de esta pantalla.
    expect(await screen.findByLabelText(strings.login.email)).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/login')
    expect(screen.getByText('Tu sesión expiró. Inicia sesión de nuevo.')).toBeInTheDocument()
    expect(screen.queryByLabelText(a.question)).not.toBeInTheDocument()
  })

  it('Error anterior se limpia: la respuesta siguiente retira el mensaje y queda arriba', async () => {
    const { box } = await openAssistant('auxiliar_farmacia', {
      [ASK]: [() => apiError(503, 'assistant_unavailable'), reply(answer())],
    })

    fireEvent.change(box, { target: { value: QUESTION } })
    fireEvent.click(askButton())
    await screen.findByRole('alert')
    fireEvent.click(await screen.findByRole('button', { name: a.submit }))

    const entry = await screen.findByRole('article', { name: QUESTION })
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
    expect(screen.getAllByRole('article')[0]).toBe(entry)
  })
})

describe('Preguntas de ejemplo y aviso de privacidad', () => {
  const examples = () =>
    within(screen.getByRole('region', { name: a.examplesTitle })).getAllByRole('button')

  it('Ejemplo rellena la caja: texto con el foco y ninguna petición', async () => {
    const { api, client, box } = await openAssistant('auxiliar_farmacia', { [ASK]: reply(answer()) })
    box.blur()

    fireEvent.click(screen.getByRole('button', { name: '¿Qué productos están por debajo del stock mínimo?' }))

    expect(box.value).toBe('¿Qué productos están por debajo del stock mínimo?')
    expect(box).toHaveFocus()
    await settle(client)
    expect(asked(api)).toHaveLength(0)
  })

  it('Ejemplos disponibles: los cuatro ejemplos y el aviso de privacidad', async () => {
    await openAssistant()

    expect(examples().map((button) => button.textContent)).toEqual([
      '¿Cuánto stock hay de acetaminofén en la farmacia central?',
      '¿Qué lotes de acetaminofén vencen en los próximos 60 días en la farmacia central?',
      '¿Qué productos están por debajo del stock mínimo?',
      '¿Cuántos traslados hay en tránsito?',
    ])
    expect(
      screen.getByText(
        'No escribas nombres ni documentos de pacientes: el asistente solo responde sobre inventario y traslados.',
      ),
    ).toBeInTheDocument()
  })

  it('Ejemplo durante una consulta: deshabilitados, caja intacta, sin otra petición', async () => {
    const pending = deferred<Response>()
    const { api, client, box } = await openAssistant('auxiliar_farmacia', { [ASK]: () => pending.promise })
    fireEvent.change(box, { target: { value: 'Pregunta en curso' } })
    fireEvent.click(askButton())
    await screen.findByRole('button', { name: a.submitting })

    for (const button of examples()) expect(button).toBeDisabled()
    fireEvent.click(examples()[0])

    expect(box.value).toBe('Pregunta en curso')
    pending.resolve(json(200, { data: answer() }))
    expect(await screen.findByRole('article', { name: 'Pregunta en curso' })).toBeInTheDocument()
    await settle(client)
    expect(asked(api)).toHaveLength(1)
    // Control positivo: sin consulta en curso los ejemplos vuelven a estar habilitados.
    for (const button of examples()) expect(button).toBeEnabled()
  })
})
