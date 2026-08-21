<script setup lang="ts">
import AppIcon from './AppIcon.vue'
import type { IconName } from './icons'

withDefaults(
  defineProps<{
    icon?: IconName
    title: string
    body?: string
    compact?: boolean
  }>(),
  { icon: 'sparkle' },
)
</script>

<template>
  <!--
    Every list that can be empty gets one of these. A blank panel reads as a
    failed load; naming the state and offering the next action reads as a
    starting point.
  -->
  <div
    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-line text-center"
    :class="compact ? 'gap-2 px-5 py-8' : 'gap-3 px-6 py-14'"
  >
    <span
      class="grid place-items-center rounded-xl border border-line bg-surface-2 text-ink-faint"
      :class="compact ? 'size-9' : 'size-11'"
      aria-hidden="true"
    >
      <AppIcon :name="icon" :size="compact ? 17 : 20" />
    </span>

    <div class="max-w-sm space-y-1">
      <p class="font-display font-semibold text-ink" :class="compact ? 'text-sm' : 'text-[15px]'">
        {{ title }}
      </p>
      <p v-if="body" class="text-[13px] leading-relaxed text-ink-muted">{{ body }}</p>
    </div>

    <div v-if="$slots.default" class="mt-1">
      <slot />
    </div>
  </div>
</template>
