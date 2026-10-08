import { describe, expect, it } from 'vitest'
import indexHtml from '../index.html?raw'

// El documento que sirve la SPA declara el idioma español (RE › Textos del shell desde el módulo central).
describe('documento de la SPA', () => {
  it('declara lang="es" en <html>', () => {
    const doc = new DOMParser().parseFromString(indexHtml, 'text/html')
    expect(doc.documentElement.getAttribute('lang')).toBe('es')
  })
})
