<script setup lang="ts">
import { nextTick, onUnmounted, ref, useId, watch } from 'vue'
import AppIcon from './AppIcon.vue'

const props = withDefaults(
  defineProps<{
    title: string
    description?: string
    size?: 'sm' | 'md' | 'lg'
  }>(),
  { size: 'md' },
)

const open = defineModel<boolean>('open', { default: false })

const panel = ref<HTMLElement | null>(null)
const uid = useId()
const titleId = `modal-title-${uid}`
const descriptionId = `modal-desc-${uid}`

const SIZES = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' }

let lastFocused: HTMLElement | null = null

function close(): void {
  open.value = false
}

/**
 * Focus is moved into the panel on open and returned to whatever opened it on
 * close, and Tab is cycled inside -- otherwise keyboard focus wanders behind
 * the overlay and the dialog becomes a trap of the wrong kind.
 */
function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.stopPropagation()
    close()

    return
  }

  if (event.key !== 'Tab' || !panel.value) {
    return
  }

  const focusable = [
    ...panel.value.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  ].filter((element) => element.offsetParent !== null)

  if (focusable.length === 0) {
    return
  }

  const first = focusable[0] as HTMLElement
  const last = focusable[focusable.length - 1] as HTMLElement

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

watch(open, async (isOpen) => {
  if (isOpen) {
    lastFocused = document.activeElement as HTMLElement | null
    document.body.style.overflow = 'hidden'
    await nextTick()
    panel.value?.querySelector<HTMLElement>('[data-autofocus]')?.focus()
    panel.value?.focus()
  } else {
    document.body.style.overflow = ''
    lastFocused?.focus()
  }
})

onUnmounted(() => {
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6"
      @keydown="onKeydown"
    >
      <div
        class="absolute inset-0 bg-canvas-deep/80 backdrop-blur-[2px]"
        aria-hidden="true"
        @click="close"
      />

      <div
        ref="panel"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        :aria-describedby="description ? descriptionId : undefined"
        tabindex="-1"
        :class="[
          'pf-rise relative w-full border border-line bg-surface outline-none',
          'max-h-[92dvh] overflow-y-auto rounded-t-2xl sm:rounded-2xl',
          SIZES[props.size],
        ]"
      >
        <header class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
          <div class="min-w-0">
            <h2 :id="titleId" class="text-base font-semibold text-ink">{{ title }}</h2>
            <p v-if="description" :id="descriptionId" class="mt-1 text-xs leading-relaxed text-ink-muted">
              {{ description }}
            </p>
          </div>
          <button
            type="button"
            class="-mr-1 -mt-1 grid size-8 shrink-0 place-items-center rounded-md text-ink-muted transition-colors hover:bg-surface-2 hover:text-ink"
            aria-label="Close dialog"
            @click="close"
          >
            <AppIcon name="x" :size="17" />
          </button>
        </header>

        <div class="px-5 py-5">
          <slot />
        </div>

        <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-line px-5 py-4">
          <slot name="footer" />
        </footer>
      </div>
    </div>
  </Teleport>
</template>
