import { vi } from 'vitest'

/**
 * jsdom implements neither `matchMedia` nor the canvas 2D context, both of which
 * the theme helper and chart.js touch on import. Stubbing them here keeps every
 * spec from having to know that.
 */
if (!window.matchMedia) {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: (query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }),
  })
}

Object.defineProperty(window, 'scrollTo', { writable: true, value: vi.fn() })

/** Router-free component specs still render RouterLink; a stub keeps them mountable. */
export const GLOBAL_STUBS = {
  RouterLink: { template: '<a><slot /></a>' },
  Teleport: true,
}
