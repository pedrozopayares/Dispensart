import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import App from '@/App'
import { AppProviders } from '@/app/providers'
import { strings } from '@/lib/strings'

// Aplana el módulo de textos a la lista de valores permitidos en pantalla.
function catalogValues(node: unknown): string[] {
  if (typeof node === 'string') return [node]
  if (node && typeof node === 'object') return Object.values(node).flatMap(catalogValues)
  return []
}

// Textos visibles del árbol renderizado que no salen del módulo central.
function textsOutsideCatalog(root: HTMLElement): { visible: string[]; outside: string[] } {
  const catalog = new Set(catalogValues(strings))
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
  const visible: string[] = []
  while (walker.nextNode()) {
    const text = walker.currentNode.textContent?.trim()
    if (text) visible.push(text)
  }
  return { visible, outside: visible.filter((text) => !catalog.has(text)) }
}

function renderShell() {
  return render(
    <AppProviders>
      <App />
    </AppProviders>,
  )
}

describe('página shell (RE › Shell en la raíz)', () => {
  it('muestra el nombre del producto y el mensaje de bienvenida en español', () => {
    renderShell()
    expect(screen.getByRole('heading', { level: 1, name: strings.app.name })).toBeInTheDocument()
    expect(screen.getByText(strings.shell.welcomeTitle)).toBeInTheDocument()
    expect(screen.getByText(strings.shell.welcomeMessage)).toBeInTheDocument()
  })

  it('monta un componente de shadcn/ui (tarjeta)', () => {
    const { container } = renderShell()
    expect(container.querySelector('[data-slot="card"]')).toBeInTheDocument()
    expect(container.querySelector('[data-slot="card-title"]')).toHaveTextContent(
      strings.shell.welcomeTitle,
    )
  })
})

describe('textos del shell (RE › Textos del shell desde el módulo central)', () => {
  it('cada texto visible coincide con un valor del módulo central', () => {
    const { container } = renderShell()
    const { visible, outside } = textsOutsideCatalog(container)
    expect(visible.length).toBeGreaterThanOrEqual(3)
    expect(outside).toEqual([])
  })

  it('control positivo: un texto fuera del módulo es detectado', () => {
    const { container } = render(
      <p>
        {strings.app.name} <span>Texto suelto</span>
      </p>,
    )
    expect(textsOutsideCatalog(container).outside).toEqual(['Texto suelto'])
  })
})
