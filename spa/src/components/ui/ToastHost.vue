<script setup lang="ts">
import AppIcon from './AppIcon.vue'
import { useToastsStore, type ToastTone } from '@/stores/toasts'
import type { IconName } from './icons'

const toasts = useToastsStore()

const TONE_CLASSES: Record<ToastTone, string> = {
  info: 'border-line bg-surface text-ink',
  success: 'border-brand/30 bg-surface text-ink',
  warn: 'border-warn/30 bg-surface text-ink',
  error: 'border-danger/30 bg-surface text-ink',
}

const TONE_ICONS: Record<ToastTone, IconName> = {
  info: 'info',
  success: 'checkCircle',
  warn: 'alert',
  error: 'xCircle',
}

const TONE_ACCENTS: Record<ToastTone, string> = {
  info: 'text-info',
  success: 'text-brand',
  warn: 'text-warn',
  error: 'text-danger',
}
</script>

<template>
  <div
    class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 px-4 pb-[calc(env(safe-area-inset-bottom)+5.5rem)] sm:items-end sm:px-6 sm:pb-[calc(env(safe-area-inset-bottom)+5.5rem)]"
    role="region"
    aria-label="Notifications"
  >
    <div
      v-for="toast in toasts.items"
      :key="toast.id"
      class="pf-rise pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-xl border px-3.5 py-3"
      :class="TONE_CLASSES[toast.tone]"
      role="status"
      aria-live="polite"
    >
      <AppIcon :name="TONE_ICONS[toast.tone]" :size="17" :class="['mt-0.5', TONE_ACCENTS[toast.tone]]" />
      <div class="min-w-0 flex-1">
        <p class="text-[13px] font-semibold leading-snug">{{ toast.title }}</p>
        <p v-if="toast.body" class="mt-0.5 text-xs leading-relaxed text-ink-muted">{{ toast.body }}</p>
      </div>
      <button
        type="button"
        class="-mr-1 -mt-0.5 grid size-6 shrink-0 place-items-center rounded text-ink-faint transition-colors hover:bg-surface-2 hover:text-ink"
        aria-label="Dismiss"
        @click="toasts.dismiss(toast.id)"
      >
        <AppIcon name="x" :size="13" />
      </button>
    </div>
  </div>
</template>
