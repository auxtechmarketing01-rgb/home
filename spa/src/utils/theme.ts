export type ThemePreference = 'light' | 'dark' | 'system'

const STORAGE_KEY = 'pathforge:theme'

function systemPrefersDark(): boolean {
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

export function storedPreference(): ThemePreference {
  const raw = localStorage.getItem(STORAGE_KEY)

  return raw === 'light' || raw === 'dark' || raw === 'system' ? raw : 'system'
}

/**
 * Dark is the product's default look, so `system` resolves through the media
 * query rather than assuming light -- and the class lands on `documentElement`
 * before Vue mounts, which is what avoids a white flash on a dark-mode load.
 */
export function applyTheme(preference: ThemePreference): void {
  const isDark = preference === 'dark' || (preference === 'system' && systemPrefersDark())

  document.documentElement.classList.toggle('dark', isDark)
  document.documentElement.style.colorScheme = isDark ? 'dark' : 'light'
}

export function setTheme(preference: ThemePreference): void {
  localStorage.setItem(STORAGE_KEY, preference)
  applyTheme(preference)
}

export function applyStoredTheme(): void {
  const preference = storedPreference()
  applyTheme(preference)

  window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', () => {
      if (storedPreference() === 'system') {
        applyTheme('system')
      }
    })
}
