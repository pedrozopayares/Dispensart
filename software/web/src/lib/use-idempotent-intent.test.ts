import { act, renderHook } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { ApiError } from '@/lib/api'
import { generateIdempotencyKey, useIdempotentIntent } from '@/lib/use-idempotent-intent'

// Tarea 1.6 — dispensation-screen › "Confirmación idempotente", a nivel de hook (design D3, RN-09).
// La intención es el cuerpo sin credenciales del autorizador.
type Intent = { prescription_id: number; warehouse_id: number; items: { id: number; quantity: number }[] }

const intent = (quantity = 2): Intent => ({
  prescription_id: 7,
  warehouse_id: 1,
  items: [{ id: 70, quantity }],
})

const KEY_FORMAT = /^[A-Za-z0-9_-]{16,128}$/

function setup() {
  return renderHook(() => useIdempotentIntent<Intent>()).result
}

describe('clave de idempotencia por intención', () => {
  it('la clave cumple el formato del servidor y no se repite entre generaciones', () => {
    const keys = new Set(Array.from({ length: 50 }, generateIdempotencyKey))
    expect(keys.size).toBe(50)
    for (const key of keys) expect(key).toMatch(KEY_FORMAT)
  })

  it('Reintento tras fallo de red reutiliza la clave', () => {
    const hook = setup()
    const first = hook.current.keyFor(intent())
    act(() => hook.current.settleError(new ApiError(0, 'network_error')))

    expect(hook.current.keyFor(intent())).toBe(first)
  })

  it('Reintento tras autorizador corregido reutiliza la clave', () => {
    const hook = setup()
    const first = hook.current.keyFor(intent())
    act(() => hook.current.settleError(new ApiError(422, 'invalid_authorizer')))

    // La contraseña corregida no forma parte de la intención: la huella no cambia.
    expect(hook.current.keyFor(intent())).toBe(first)
  })

  it('Cambio de cantidad genera clave nueva', () => {
    const hook = setup()
    const first = hook.current.keyFor(intent(2))

    const changed = hook.current.keyFor(intent(3))
    expect(changed).not.toBe(first)
    expect(changed).toMatch(KEY_FORMAT)
  })

  it('Nueva dispensación genera clave nueva tras el éxito, aun con los mismos datos', () => {
    const hook = setup()
    const first = hook.current.keyFor(intent())
    act(() => hook.current.settleSuccess())

    expect(hook.current.keyFor(intent())).not.toBe(first)
  })

  it('Clave reutilizada con otros datos: idempotency_key_reused descarta la clave', () => {
    const hook = setup()
    const first = hook.current.keyFor(intent())
    act(() => hook.current.settleError(new ApiError(422, 'idempotency_key_reused')))

    expect(hook.current.keyFor(intent())).not.toBe(first)
  })

  it('la clave sobrevive a re-renderizados del componente', () => {
    const { result, rerender } = renderHook(() => useIdempotentIntent<Intent>())
    const first = result.current.keyFor(intent())
    rerender()

    expect(result.current.keyFor(intent())).toBe(first)
  })
})
