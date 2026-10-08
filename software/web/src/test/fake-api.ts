import { vi } from 'vitest'

// Frontera HTTP falsa para Vitest (design D8: `fetch` sustituido, sin MSW en S1). Cada ruta
// "MÉTODO /ruta" responde con la cola de manejadores en orden; el último se repite.

export type RecordedCall = {
  method: string
  path: string
  headers: Record<string, string>
  credentials: RequestCredentials | undefined
  body: unknown
}

// Rutas pedidas sin manejador: `setup.ts` falla la prueba si quedan (evita un falso "error de red").
export const unmatchedRoutes: string[] = []

type Handler = (call: RecordedCall) => Response | Promise<Response>

export function json(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', ...headers },
  })
}

export const noContent = (): Response => new Response(null, { status: 204 })

export const apiError = (status: number, code: string): Response =>
  json(status, { code, message: 'mensaje del servidor' })

// Promesa que la prueba resuelve a mano (petición "en curso").
export function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

export function setXsrfCookie(value: string | null): void {
  document.cookie =
    value === null
      ? 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/'
      : `XSRF-TOKEN=${encodeURIComponent(value)}; path=/`
}

// Emisión de la cookie CSRF como lo hace Sanctum: 204 + cookie legible por la SPA.
export function csrfCookieHandler(token = 'token-1'): Handler {
  return () => {
    setXsrfCookie(token)
    return noContent()
  }
}

export const user = (overrides: Partial<Record<string, unknown>> = {}) => ({
  id: 1,
  name: 'Ana Admin',
  email: 'admin@dispensart.test',
  role: 'admin',
  abilities: ['catalog.view', 'catalog.manage', 'users.manage'],
  ...overrides,
})

export function installFakeApi(routes: Record<string, Handler | Handler[]>) {
  const calls: RecordedCall[] = []
  const queues = new Map(
    Object.entries(routes).map(([key, value]) => [key, Array.isArray(value) ? [...value] : [value]]),
  )

  const fetchMock = vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
    const path = typeof input === 'string' ? input : input.toString()
    const method = (init.method ?? 'GET').toUpperCase()
    const call: RecordedCall = {
      method,
      path,
      headers: { ...(init.headers as Record<string, string> | undefined) },
      credentials: init.credentials,
      body: typeof init.body === 'string' ? JSON.parse(init.body) : undefined,
    }
    calls.push(call)
    const queue = queues.get(`${method} ${path}`)
    if (!queue || queue.length === 0) {
      unmatchedRoutes.push(`${method} ${path}`)
      throw new Error(`Ruta sin manejador en la prueba: ${method} ${path}`)
    }
    const handler = queue.length > 1 ? queue.shift()! : queue[0]
    return handler(call)
  })

  vi.stubGlobal('fetch', fetchMock)
  return {
    calls,
    callsTo: (method: string, path: string) =>
      calls.filter((call) => call.method === method && call.path === path),
  }
}
