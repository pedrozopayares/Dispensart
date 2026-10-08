import type { components } from '@/lib/api-schema'
import { strings } from '@/lib/strings'

// Cliente HTTP de la SPA (design D8). Mismo origen, cookie de sesión HttpOnly de Sanctum: el
// navegador la envía solo; la SPA nunca ve ni guarda un token. Toda escritura lleva X-XSRF-TOKEN.

type ApiErrorBody = components['schemas']['ApiError']
export type AuthenticatedUser = components['schemas']['AuthenticatedUserResource']
export type LoginCredentials = components['schemas']['LoginRequest']

// `network_error` no viene de la API: la petición no llegó o la respuesta no tenía la forma de rechazo.
export type ApiErrorCode = ApiErrorBody['code'] | 'network_error'

export class ApiError extends Error {
  readonly status: number
  readonly code: ApiErrorCode
  readonly errors: Record<string, string[]>

  constructor(status: number, code: ApiErrorCode, errors: Record<string, string[]> = {}) {
    super(code)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.errors = errors
  }
}

const API_PREFIX = '/api'
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie'
const CSRF_COOKIE = 'XSRF-TOKEN'
const WRITE_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

// Valor de la cookie XSRF-TOKEN; Laravel la emite codificada para URL.
function readXsrfToken(): string | null {
  for (const part of document.cookie.split(';')) {
    const [name, ...rest] = part.trim().split('=')
    if (name === CSRF_COOKIE) return decodeURIComponent(rest.join('='))
  }
  return null
}

function isApiErrorBody(value: unknown): value is ApiErrorBody {
  return (
    typeof value === 'object' &&
    value !== null &&
    typeof (value as { code?: unknown }).code === 'string'
  )
}

async function send(input: string, init: RequestInit): Promise<Response> {
  try {
    return await fetch(input, { credentials: 'same-origin', ...init })
  } catch {
    // Sin red o servidor caído: fetch rechaza con TypeError; se expone como código estable.
    throw new ApiError(0, 'network_error')
  }
}

async function toApiError(response: Response): Promise<ApiError> {
  const body: unknown = await response.json().catch(() => null)
  if (isApiErrorBody(body)) return new ApiError(response.status, body.code, body.errors ?? {})
  // Sin la forma de rechazo (p. ej. 502 del proxy): se trata como servidor inalcanzable.
  return new ApiError(response.status, response.status >= 500 ? 'server_error' : 'network_error')
}

// Pide a Sanctum la cookie XSRF-TOKEN (204). Obligatorio antes del login.
export async function fetchCsrfCookie(): Promise<void> {
  const response = await send(CSRF_COOKIE_URL, { headers: { Accept: 'application/json' } })
  if (!response.ok) throw await toApiError(response)
}

async function attempt(method: Method, path: string, body: unknown): Promise<Response> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (body !== undefined) headers['Content-Type'] = 'application/json'
  if (WRITE_METHODS.has(method)) {
    if (readXsrfToken() === null) await fetchCsrfCookie()
    headers['X-XSRF-TOKEN'] = readXsrfToken() ?? ''
  }
  return send(`${API_PREFIX}${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  })
}

// Petición a la API. Ante `csrf_token_mismatch` renueva la cookie y reintenta una sola vez.
export async function apiRequest<T>(method: Method, path: string, body?: unknown): Promise<T> {
  let response = await attempt(method, path, body)
  if (!response.ok) {
    let error = await toApiError(response)
    if (error.code === 'csrf_token_mismatch' && WRITE_METHODS.has(method)) {
      await fetchCsrfCookie()
      response = await attempt(method, path, body)
      if (!response.ok) error = await toApiError(response)
    }
    if (!response.ok) throw error
  }
  if (response.status === 204) return undefined as T
  return (await response.json()) as T
}

// Usuario de la sesión; sin sesión (401) es `null`, no un error.
export async function getCurrentUser(): Promise<AuthenticatedUser | null> {
  try {
    const { data } = await apiRequest<{ data: AuthenticatedUser }>('GET', '/auth/me')
    return data
  } catch (error) {
    if (error instanceof ApiError && error.code === 'unauthenticated') return null
    throw error
  }
}

export async function login(credentials: LoginCredentials): Promise<AuthenticatedUser> {
  await fetchCsrfCookie()
  const { data } = await apiRequest<{ data: AuthenticatedUser }>('POST', '/auth/login', credentials)
  return data
}

export async function logout(): Promise<void> {
  await apiRequest<void>('POST', '/auth/logout')
}

// Texto en español para un error de la API; nunca el JSON crudo ni la traza.
export function errorMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return strings.errors.unexpected
  switch (error.code) {
    case 'invalid_credentials':
      return strings.errors.invalidCredentials
    case 'too_many_attempts':
      return strings.errors.tooManyAttempts
    case 'csrf_token_mismatch':
      return strings.errors.csrfExpired
    case 'forbidden':
      return strings.errors.forbidden
    case 'not_found':
      return strings.errors.notFound
    case 'validation_failed':
      return strings.errors.validation
    case 'unauthenticated':
      return strings.session.expired
    case 'network_error':
    case 'server_error':
      return strings.errors.network
    default:
      return strings.errors.unexpected
  }
}
