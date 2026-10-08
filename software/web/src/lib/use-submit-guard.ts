import { useCallback, useRef } from 'react'

// Candado síncrono contra el doble envío (design D4). Dos clics o dos Enter en el mismo ciclo llegan
// antes del re-render que deshabilita el botón: `isPending` solo no basta. `run` recibe `release`
// y debe llamarlo cuando la escritura termine, con éxito o con rechazo.
export function useSubmitGuard() {
  const inFlight = useRef(false)
  return useCallback((run: (release: () => void) => void) => {
    if (inFlight.current) return
    inFlight.current = true
    run(() => {
      inFlight.current = false
    })
  }, [])
}
