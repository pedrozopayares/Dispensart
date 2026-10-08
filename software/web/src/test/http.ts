import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'

// Red simulada en el borde HTTP (design D2): las pruebas importan solo este módulo, nunca `msw`.
// Cada ruta "MÉTODO /api/ruta" responde con su cola de manejadores en orden; el último se repite.
// Toda petición atendida queda registrada con método, ruta, consulta, cabeceras y cuerpo.

export { apiError, deferred, json, noContent, setXsrfCookie } from '@/test/fake-api'

export type RecordedRequest = {
  method: string
  path: string
  query: Record<string, string>
  // Nombres de cabecera en minúscula (Fetch los normaliza).
  headers: Record<string, string>
  body: unknown
}

type Resolver = (request: RecordedRequest) => Response | Promise<Response>
type Verb = 'get' | 'post' | 'put' | 'patch' | 'delete'

export const server = setupServer()

const recorded: RecordedRequest[] = []

// Peticiones sin manejador: `setup.ts` falla la prueba si quedan (evita un falso "error de red").
export const unhandledRequests: string[] = []

export function clearRecordedRequests(): void {
  recorded.length = 0
}

// Fallo de red: `fetch` rechaza con TypeError, como sin conexión.
export const networkError = (): Response => HttpResponse.error()

async function record(request: Request): Promise<RecordedRequest> {
  const url = new URL(request.url)
  const text = await request.clone().text()
  return {
    method: request.method,
    path: url.pathname,
    query: Object.fromEntries(url.searchParams),
    headers: Object.fromEntries(request.headers),
    body: text === '' ? undefined : JSON.parse(text),
  }
}

export function serveApi(routes: Record<string, Resolver | Resolver[]>) {
  const handlers = Object.entries(routes).map(([route, value]) => {
    const [method, path] = route.split(' ')
    const queue = Array.isArray(value) ? [...value] : [value]
    return http[method.toLowerCase() as Verb](`*${path}`, async ({ request }) => {
      const call = await record(request)
      recorded.push(call)
      const resolver = queue.length > 1 ? queue.shift()! : queue[0]
      return resolver(call)
    })
  })
  server.use(...handlers)

  const requestsTo = (method: string, path: string) =>
    recorded.filter((call) => call.method === method && call.path === path)
  return { requests: recorded, requestsTo }
}
