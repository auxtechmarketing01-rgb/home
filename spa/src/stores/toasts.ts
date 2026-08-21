import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastTone = 'info' | 'success' | 'warn' | 'error'

export interface Toast {
  id: number
  tone: ToastTone
  title: string
  body?: string
}

let nextId = 1

/**
 * Non-blocking feedback for actions whose result would otherwise be invisible
 * (a reorder that saved, a reward that was claimed). Failures that belong to a
 * specific field never come here -- they go back to the form.
 */
export const useToastsStore = defineStore('toasts', () => {
  const items = ref<Toast[]>([])

  function dismiss(id: number): void {
    items.value = items.value.filter((toast) => toast.id !== id)
  }

  function push(tone: ToastTone, title: string, body?: string): number {
    const id = nextId++
    items.value = [...items.value, { id, tone, title, body }]

    window.setTimeout(() => dismiss(id), tone === 'error' ? 7000 : 4200)

    return id
  }

  return {
    items,
    dismiss,
    push,
    info: (title: string, body?: string) => push('info', title, body),
    success: (title: string, body?: string) => push('success', title, body),
    warn: (title: string, body?: string) => push('warn', title, body),
    error: (title: string, body?: string) => push('error', title, body),
  }
})
