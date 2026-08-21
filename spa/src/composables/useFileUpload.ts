import { computed, ref } from 'vue'

/**
 * Client-side validation here is a **UX nicety only**. The real gate is the
 * backend allow-list in StoreResourceRequest, which also sniffs content -- so
 * this exists to save a member a pointless round trip, never to create the
 * impression that the browser is enforcing anything.
 */
const DEFAULT_ALLOWED_EXTENSIONS = [
  'pdf',
  'png',
  'jpg',
  'jpeg',
  'webp',
  'gif',
  'txt',
  'md',
  'csv',
  'zip',
  'doc',
  'docx',
  'xls',
  'xlsx',
  'ppt',
  'pptx',
]

const DEFAULT_MAX_BYTES = 10 * 1024 * 1024

export function useFileUpload(options?: { allowedExtensions?: string[]; maxBytes?: number }) {
  const allowed = options?.allowedExtensions ?? DEFAULT_ALLOWED_EXTENSIONS
  const maxBytes = options?.maxBytes ?? DEFAULT_MAX_BYTES

  const file = ref<File | null>(null)
  const localError = ref<string | null>(null)
  const progress = ref(0)

  const accept = computed(() => allowed.map((extension) => `.${extension}`).join(','))

  function extensionOf(name: string): string {
    return name.split('.').pop()?.toLowerCase() ?? ''
  }

  function select(next: File | null): boolean {
    localError.value = null

    if (!next) {
      file.value = null

      return true
    }

    if (!allowed.includes(extensionOf(next.name))) {
      localError.value = `That file type is not accepted. Allowed: ${allowed.join(', ')}.`
      file.value = null

      return false
    }

    if (next.size > maxBytes) {
      localError.value = `That file is larger than ${Math.round(maxBytes / 1024 / 1024)} MB.`
      file.value = null

      return false
    }

    file.value = next

    return true
  }

  function onInputChange(event: Event): boolean {
    const input = event.target as HTMLInputElement

    return select(input.files?.[0] ?? null)
  }

  function reset(): void {
    file.value = null
    localError.value = null
    progress.value = 0
  }

  return { file, localError, progress, accept, allowed, maxBytes, select, onInputChange, reset }
}
