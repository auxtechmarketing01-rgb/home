<script setup lang="ts">
import AppIcon from './AppIcon.vue'
import type { ApiFailure } from '@/types/api'

defineProps<{
  failure?: ApiFailure | null
  message?: string | null
  dismissible?: boolean
}>()

const emit = defineEmits<{ dismiss: [] }>()
</script>

<template>
  <!--
    `aria-live="polite"` rather than assertive: a failed save is important, but
    it should not interrupt whatever the screen reader is mid-sentence on.
  -->
  <div
    v-if="failure || message"
    role="alert"
    aria-live="polite"
    class="flex items-start gap-2.5 rounded-lg border border-danger/30 bg-danger-soft px-3.5 py-3 text-[13px] text-danger"
  >
    <AppIcon name="alert" :size="16" class="mt-0.5" />
    <div class="min-w-0 flex-1 space-y-1">
      <p class="font-medium">{{ message ?? failure?.message }}</p>
      <ul
        v-if="failure && Object.keys(failure.errors).length > 0"
        class="list-inside list-disc space-y-0.5 opacity-90"
      >
        <li v-for="(messages, field) in failure.errors" :key="field">{{ messages[0] }}</li>
      </ul>
    </div>
    <button
      v-if="dismissible"
      type="button"
      class="-mr-1 -mt-0.5 grid size-6 shrink-0 place-items-center rounded transition-colors hover:bg-danger/15"
      aria-label="Dismiss error"
      @click="emit('dismiss')"
    >
      <AppIcon name="x" :size="14" />
    </button>
  </div>
</template>
