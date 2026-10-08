import { vi } from 'vitest'

// Espía de consola para las pruebas de privacidad (RN-10, operator-workspace «Datos del paciente fuera
// del navegador persistente»): silencia los cinco métodos y devuelve sus espías para afirmar que la
// SPA no escribió nada, ni el término buscado ni datos del paciente.
export function spyOnConsole() {
  for (const method of ['log', 'info', 'warn', 'error', 'debug'] as const) {
    vi.spyOn(console, method).mockImplementation(() => {})
  }
  // Referencias directas a los métodos ya sustituidos por sus espías.
  // eslint-disable-next-line no-console -- solo lectura de los espías instalados arriba
  return [console.log, console.info, console.warn, console.error, console.debug]
}
