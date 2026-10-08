import { useCallback, useRef } from 'react'
import { hasCode } from '@/lib/api-errors'

// Clave de idempotencia por intención (design D3, RN-09). Una intención es el cuerpo de la escritura
// sin credenciales del autorizador; su huella es el JSON de ese mismo cuerpo, así huella y cuerpo
// enviado nunca divergen. La clave vive solo en memoria: nunca en almacenamiento ni en la URL.

const KEY_BYTES = 16

// 16 bytes aleatorios en base64url: 22 caracteres de `[A-Za-z0-9_-]`. `getRandomValues` funciona
// fuera de contexto seguro, a diferencia de `crypto.randomUUID`.
export function generateIdempotencyKey(): string {
  const bytes = crypto.getRandomValues(new Uint8Array(KEY_BYTES))
  return btoa(String.fromCharCode(...bytes))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/, '')
}

type Current = { fingerprint: string; key: string }

export function useIdempotentIntent<Intent>() {
  const current = useRef<Current | null>(null)

  // Clave para enviar esta intención: la misma en todo reintento; nueva si la intención cambió.
  const keyFor = useCallback((intent: Intent): string => {
    const fingerprint = JSON.stringify(intent)
    if (current.current?.fingerprint !== fingerprint) {
      current.current = { fingerprint, key: generateIdempotencyKey() }
    }
    return current.current.key
  }, [])

  // Tras éxito (incluida la repetición `Idempotent-Replayed`) la intención quedó consumida.
  const settleSuccess = useCallback(() => {
    current.current = null
  }, [])

  // Tras un rechazo la clave se conserva (el servidor no la consumió), salvo
  // `idempotency_key_reused`: esa clave ya pertenece a otro cuerpo.
  const settleError = useCallback((error: unknown) => {
    if (hasCode(error, 'idempotency_key_reused')) current.current = null
  }, [])

  return { keyFor, settleSuccess, settleError }
}
