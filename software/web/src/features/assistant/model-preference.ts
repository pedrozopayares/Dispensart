// Preferencia de modelo del asistente (S15, design D8). En `localStorage` va solo el `id` elegido, nunca
// la pregunta ni el historial (RN-10). El valor guardado es dato no confiable: solo se usa si figura en
// la lista que devolvió el servidor.

export const MODEL_STORAGE_KEY = 'dispensart.assistant.model'
export const DEFAULT_MODEL = 'mock'

// Sin almacenamiento (modo privado, política del navegador) la lectura vale `null`: la pantalla sigue
// con `mock` y no muestra error (spec «Almacenamiento no disponible»).
export function readStoredModel(): string | null {
  try {
    return window.localStorage.getItem(MODEL_STORAGE_KEY)
  } catch {
    return null
  }
}

// Se llama solo al elegir en el selector. Si escribir falla, la elección vale solo para la visita.
export function storeModel(id: string): void {
  try {
    window.localStorage.setItem(MODEL_STORAGE_KEY, id)
  } catch {
    // Sin almacenamiento: nada que hacer; la elección sigue en el estado de la pantalla.
  }
}

export type ResolvedModel = { id: string; storedMissing: boolean }

// Modelo efectivo: la elección de la visita si está en la lista; si no, el guardado si está en la
// lista; si no, `mock`. `storedMissing` avisa que la preferencia ya no está disponible.
export function resolveModel(ids: readonly string[], stored: string | null, chosen: string | null): ResolvedModel {
  if (chosen !== null && ids.includes(chosen)) return { id: chosen, storedMissing: false }
  if (stored !== null && ids.includes(stored)) return { id: stored, storedMissing: false }
  const preferred = chosen ?? stored
  return { id: DEFAULT_MODEL, storedMissing: preferred !== null && preferred !== '' && preferred !== DEFAULT_MODEL }
}
