import {
  CategoryScale,
  Chart,
  Filler,
  Legend,
  LinearScale,
  LineController,
  LineElement,
  PointElement,
  Tooltip,
} from 'chart.js'

/**
 * Registered once, from the tree-shakeable build, so the bundle carries the line
 * controller and nothing else.
 */
let registered = false

export function ensureChartsRegistered(): void {
  if (registered) {
    return
  }

  Chart.register(
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Legend,
    Tooltip,
  )

  registered = true
}

/**
 * Chart.js paints to a canvas, so it cannot inherit a Tailwind class -- the
 * tokens have to be read off the document at draw time. Doing it here means a
 * theme switch produces correctly coloured axes on the next render rather than
 * baking light-mode greys into a dark chart.
 */
export function readToken(name: string, fallback: string): string {
  const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim()

  return value || fallback
}

export interface ChartTheme {
  ink: string
  inkFaint: string
  line: string
  surface: string
  brand: string
}

export function chartTheme(): ChartTheme {
  return {
    ink: readToken('--pf-ink', '#e6eef0'),
    inkFaint: readToken('--pf-ink-faint', '#667a80'),
    line: readToken('--pf-line', 'rgba(255,255,255,0.08)'),
    surface: readToken('--pf-surface', '#151b1e'),
    brand: readToken('--pf-brand', '#2dd4bf'),
  }
}
