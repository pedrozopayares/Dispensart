import { QueryClient } from '@tanstack/react-query'
import { describe, expect, it } from 'vitest'
import { invalidateAfterStockWrite, queryKeys } from '@/lib/query-keys'

// Tarea 1.3 — invalidación tras toda escritura de stock (ADR-0003): inventario, kardex, alertas y
// ficha del paciente quedan obsoletos; el catálogo no.
describe('claves de consulta', () => {
  it('una escritura de stock invalida existencias, kardex, alertas y ficha, y no el catálogo', async () => {
    const client = new QueryClient()
    const affected = [
      queryKeys.stock({ warehouse_id: 1 }),
      queryKeys.kardex({ page: 2 }),
      queryKeys.alerts({}),
      queryKeys.patient(5),
    ]
    for (const key of [...affected, queryKeys.warehouses()]) client.setQueryData(key, [])

    await invalidateAfterStockWrite(client)

    for (const key of affected) expect(client.getQueryState(key)?.isInvalidated).toBe(true)
    expect(client.getQueryState(queryKeys.warehouses())?.isInvalidated).toBe(false)
  })

  it('los filtros forman parte de la clave: bodegas distintas no comparten caché', () => {
    expect(queryKeys.stock({ warehouse_id: 1 })).not.toEqual(queryKeys.stock({ warehouse_id: 2 }))
  })
})
