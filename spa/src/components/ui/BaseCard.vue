<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    padded?: boolean
    interactive?: boolean
    tone?: 'default' | 'raised' | 'inset'
    as?: string
  }>(),
  { padded: true, tone: 'default', as: 'div' },
)

const TONES = {
  default: 'bg-surface border-line',
  raised: 'bg-surface-2 border-line',
  inset: 'bg-canvas-deep border-line',
}

const classes = computed(() => [
  'border rounded-xl transition-colors duration-150',
  TONES[props.tone],
  props.padded ? 'p-5' : '',
  props.interactive ? 'hover:border-line-strong hover:bg-surface-2' : '',
])
</script>

<template>
  <component :is="as" :class="classes">
    <slot />
  </component>
</template>
