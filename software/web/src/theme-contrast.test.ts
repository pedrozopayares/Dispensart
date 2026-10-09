/// <reference types="node" />
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

// Contraste WCAG de los tokens de tema (S12, app-shell › Contraste AA de los pares de tokens).
// Lee `index.css` como texto: la prueba evalúa los valores escritos, no un estilo computado.
// Se lee del disco: con `css: false` en Vitest, `index.css?raw` llega vacío.
const themeCss = readFileSync(resolve(import.meta.dirname, 'index.css'), 'utf8')

type Theme = 'claro' | 'oscuro'
type Rgb = [number, number, number] // sRGB codificado, 0..1

const SELECTOR: Record<Theme, string> = { claro: ':root', oscuro: '.dark' }

interface Pair {
  // Token (`foreground`) o color literal (`#ffffff`, texto fijo de un componente).
  fg: string
  bg: string
  min: number
  // Fondo con transparencia compuesto sobre otro token (p. ej. `dark:bg-destructive/60`).
  overlay?: { alpha: number; over: string }
}

const AA = 4.5
const NON_TEXT = 3

const COMMON_PAIRS: Pair[] = [
  { fg: 'foreground', bg: 'background', min: AA },
  { fg: 'card-foreground', bg: 'card', min: AA },
  { fg: 'popover-foreground', bg: 'popover', min: AA },
  { fg: 'primary-foreground', bg: 'primary', min: AA },
  { fg: 'primary', bg: 'background', min: AA },
  { fg: 'secondary-foreground', bg: 'secondary', min: AA },
  { fg: 'muted-foreground', bg: 'muted', min: AA },
  { fg: 'muted-foreground', bg: 'background', min: AA },
  { fg: 'muted-foreground', bg: 'card', min: AA },
  { fg: 'accent-foreground', bg: 'accent', min: AA },
  { fg: 'sidebar-foreground', bg: 'sidebar', min: AA },
  { fg: 'sidebar-primary-foreground', bg: 'sidebar-primary', min: AA },
  { fg: 'sidebar-accent-foreground', bg: 'sidebar-accent', min: AA },
  { fg: 'destructive', bg: 'background', min: AA },
  { fg: 'destructive', bg: 'card', min: AA },
  { fg: 'ring', bg: 'background', min: NON_TEXT },
]

// Insignia y botón destructivos: `bg-destructive text-white`; en oscuro el fondo es `destructive/60`.
const THEME_PAIRS: Record<Theme, Pair[]> = {
  claro: [...COMMON_PAIRS, { fg: '#ffffff', bg: 'destructive', min: AA }],
  oscuro: [
    ...COMMON_PAIRS,
    { fg: '#ffffff', bg: 'destructive', min: AA, overlay: { alpha: 0.6, over: 'background' } },
    { fg: '#ffffff', bg: 'destructive', min: AA, overlay: { alpha: 0.6, over: 'card' } },
  ],
}

function extractTokens(css: string, selector: string): Record<string, string> {
  const escaped = selector.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const block = new RegExp(`(?:^|\\n)\\s*${escaped}\\s*\\{([^}]*)\\}`).exec(css)
  const tokens: Record<string, string> = {}
  if (!block) return tokens
  for (const match of block[1].matchAll(/--([\w-]+)\s*:\s*([^;]+);/g)) {
    tokens[match[1]] = match[2].trim()
  }
  return tokens
}

function parseHex(value: string): Rgb | null {
  const hex = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(value)?.[1]
  if (!hex) return null
  const full = hex.length === 3 ? [...hex].map((c) => c + c).join('') : hex
  return [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16) / 255) as Rgb
}

function toEncoded(linear: number): number {
  const c = Math.min(1, Math.max(0, linear))
  return c <= 0.0031308 ? 12.92 * c : 1.055 * c ** (1 / 2.4) - 0.055
}

function toLinear(encoded: number): number {
  return encoded <= 0.04045 ? encoded / 12.92 : ((encoded + 0.055) / 1.055) ** 2.4
}

// oklch → sRGB (Björn Ottosson). Solo para leer tokens escritos en oklch.
function parseOklch(value: string): Rgb | null {
  const m = /^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)\s*(?:\/[^)]*)?\)$/i.exec(value)
  if (!m) return null
  const l = Number(m[1]) / (m[2] ? 100 : 1)
  const hue = (Number(m[4]) * Math.PI) / 180
  const a = Number(m[3]) * Math.cos(hue)
  const b = Number(m[3]) * Math.sin(hue)
  const l_ = (l + 0.3963377774 * a + 0.2158037573 * b) ** 3
  const m_ = (l - 0.1055613458 * a - 0.0638541728 * b) ** 3
  const s_ = (l - 0.0894841775 * a - 1.291485548 * b) ** 3
  return [
    4.0767416621 * l_ - 3.3077115913 * m_ + 0.2309699292 * s_,
    -1.2684380046 * l_ + 2.6097574011 * m_ - 0.3413193965 * s_,
    -0.0041960863 * l_ - 0.7034186147 * m_ + 1.707614701 * s_,
  ].map(toEncoded) as Rgb
}

function toHex(rgb: Rgb): string {
  return `#${rgb.map((c) => Math.round(c * 255).toString(16).padStart(2, '0')).join('')}`
}

function luminance([r, g, b]: Rgb): number {
  return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b)
}

function contrast(a: Rgb, b: Rgb): number {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)
  return (hi + 0.05) / (lo + 0.05)
}

interface PairResult {
  label: string
  ratio: number
  min: number
}

interface ThemeAnalysis {
  colors: Record<string, Rgb>
  results: PairResult[]
}

// Analiza un tema: falla (no pasa en vacío) si el bloque no tiene tokens o si falta un token de un par.
function analyzeTheme(css: string, theme: Theme): ThemeAnalysis {
  const tokens = extractTokens(css, SELECTOR[theme])
  if (Object.keys(tokens).length === 0) {
    throw new Error(`tema ${theme}: el bloque ${SELECTOR[theme]} no tiene tokens`)
  }
  const colors: Record<string, Rgb> = {}
  const color = (ref: string): Rgb => {
    const literal = parseHex(ref)
    if (literal) return literal
    const raw = tokens[ref]
    if (raw === undefined) throw new Error(`tema ${theme}: falta el token --${ref}`)
    const parsed = parseHex(raw) ?? parseOklch(raw)
    if (!parsed) throw new Error(`tema ${theme}: el token --${ref} no es un color legible (${raw})`)
    colors[ref] = parsed
    return parsed
  }
  const results = THEME_PAIRS[theme].map((pair) => {
    const fg = color(pair.fg)
    let bg = color(pair.bg)
    let label = `${pair.fg}/${pair.bg}`
    if (pair.overlay) {
      const under = color(pair.overlay.over)
      const { alpha } = pair.overlay
      bg = bg.map((c, i) => alpha * c + (1 - alpha) * under[i]) as Rgb
      label = `${pair.fg}/${pair.bg}@${alpha}·${pair.overlay.over}`
    }
    return { label, ratio: contrast(fg, bg), min: pair.min }
  })
  return { colors, results }
}

function failures(analysis: ThemeAnalysis, theme: Theme): string[] {
  return analysis.results
    .filter((r) => r.ratio < r.min)
    .map((r) => `par ${r.label} en tema ${theme}: razón ${r.ratio.toFixed(2)}:1 < ${r.min}:1`)
}

const LIME = '#a9cd43'
const NAVY = '#232955'

describe('contraste de los tokens de tema', () => {
  for (const theme of ['claro', 'oscuro'] as const) {
    it(`tema ${theme}: todos los pares cumplen AA (foco ≥ 3:1)`, () => {
      const analysis = analyzeTheme(themeCss, theme)
      expect(analysis.results).toHaveLength(THEME_PAIRS[theme].length)
      expect(failures(analysis, theme)).toEqual([])
    })

    it(`tema ${theme}: el destructivo conserva el rojo`, () => {
      const [r, g, b] = analyzeTheme(themeCss, theme).colors.destructive
      expect(r).toBeGreaterThan(1.5 * g)
      expect(r).toBeGreaterThan(1.5 * b)
    })
  }

  it('tema claro: usa la paleta de la IPS', () => {
    const { colors } = analyzeTheme(themeCss, 'claro')
    expect(toHex(colors.background)).toBe('#f8f9fa')
    expect(toHex(colors.foreground)).toBe('#212b51')
    expect(toHex(colors.primary)).toBe(NAVY)
    expect(luminance(colors['primary-foreground'])).toBeGreaterThan(0.8)
    expect(toHex(colors.secondary)).toBe(LIME)
    expect(toHex(colors['secondary-foreground'])).toBe(NAVY)
  })

  it('tema claro: el verde lima no es token de texto ni de foco', () => {
    const tokens = extractTokens(themeCss, SELECTOR.claro)
    const textual = Object.keys(tokens).filter(
      (name) => name.endsWith('-foreground') || name === 'destructive' || name.endsWith('ring'),
    )
    expect(textual.length).toBeGreaterThan(0)
    const lime = textual.filter((name) => {
      const parsed = parseHex(tokens[name]) ?? parseOklch(tokens[name])
      return parsed !== null && toHex(parsed) === LIME
    })
    expect(lime).toEqual([])
  })

  it('tema oscuro: derivado de la paleta (fondo azul marino oscuro, primario verde lima)', () => {
    const { colors } = analyzeTheme(themeCss, 'oscuro')
    const [r, g, b] = colors.background
    expect(luminance(colors.background)).toBeLessThan(0.03)
    expect(b).toBeGreaterThan(r)
    expect(b).toBeGreaterThan(g)
    expect(luminance(colors.foreground)).toBeGreaterThan(0.8)
    expect(toHex(colors.primary)).toBe(LIME)
    expect(toHex(colors['primary-foreground'])).toBe(NAVY)
  })
})

describe('guardas: tema sin tokens o con token ausente', () => {
  it('sin tokens: un bloque vacío hace fallar el análisis nombrando tema y bloque', () => {
    expect(() => analyzeTheme(':root {\n}\n.dark {\n}\n', 'claro')).toThrow(
      'tema claro: el bloque :root no tiene tokens',
    )
    expect(() => analyzeTheme('', 'oscuro')).toThrow('tema oscuro: el bloque .dark no tiene tokens')
  })

  it('sin tokens: falta --secondary-foreground y el análisis falla nombrando tema y token', () => {
    const complete = extractTokens(themeCss, SELECTOR.claro)
    expect(complete['secondary-foreground']).toBeDefined()
    const declarations = Object.entries(complete)
      .filter(([name]) => name !== 'secondary-foreground')
      .map(([name, value]) => `  --${name}: ${value};`)
      .join('\n')
    expect(() => analyzeTheme(`:root {\n${declarations}\n}\n`, 'claro')).toThrow(
      'tema claro: falta el token --secondary-foreground',
    )
  })
})
