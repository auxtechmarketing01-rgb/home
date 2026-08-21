<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    /** Tailwind classes from utils/colors -- never an inline hex. */
    tone?: string
    dot?: string
    size?: 'sm' | 'md'
  }>(),
  { tone: 'bg-surface-2 text-ink-muted border-line', size: 'sm' },
)

const classes = computed(() => [
  'inline-flex items-center gap-1.5 border rounded-md font-medium whitespace-nowrap',
  props.size === 'sm' ? 'h-6 px-2 text-[11.5px] tracking-wide' : 'h-7 px-2.5 text-xs',
  props.tone,
])
</script>

<template>
  <span :class="classes">
    <span v-if="dot" :class="['size-1.5 rounded-full', dot]" aria-hidden="true" />
    <slot />
  </span>
</template>
