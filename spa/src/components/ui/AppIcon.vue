<script setup lang="ts">
import { computed } from 'vue'
import { ICON_PATHS, type IconName } from './icons'

const props = withDefaults(
  defineProps<{
    name: IconName
    size?: number | string
    /**
     * Decorative by default. Pass a label whenever the icon *is* the control's
     * only content, so it stops being invisible to a screen reader.
     */
    label?: string
    strokeWidth?: number
  }>(),
  { size: 18, strokeWidth: 1.75 },
)

const path = computed(() => ICON_PATHS[props.name])
const decorative = computed(() => !props.label)
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    :stroke-width="strokeWidth"
    stroke="currentColor"
    stroke-linecap="round"
    stroke-linejoin="round"
    :aria-hidden="decorative ? 'true' : undefined"
    :role="decorative ? undefined : 'img'"
    :aria-label="label"
    class="shrink-0"
  >
    <title v-if="label">{{ label }}</title>
    <path :d="path" />
  </svg>
</template>
