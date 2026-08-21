<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    /** 0..1 */
    value: number
    size?: number
    thickness?: number
    /** A CSS colour or var() -- the ring is SVG, so it cannot take a Tailwind class. */
    color?: string
    trackColor?: string
    /** Overtime look: the arc completes and switches to the ember token. */
    overtime?: boolean
    label?: string
  }>(),
  {
    size: 220,
    thickness: 6,
    color: 'var(--pf-brand)',
    trackColor: 'var(--pf-line)',
  },
)

const radius = computed(() => (props.size - props.thickness) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)

const clamped = computed(() => Math.min(1, Math.max(0, Number.isFinite(props.value) ? props.value : 0)))

const dashOffset = computed(() =>
  props.overtime ? 0 : circumference.value * (1 - clamped.value),
)

const strokeColor = computed(() => (props.overtime ? 'var(--pf-ember)' : props.color))
</script>

<template>
  <!--
    A single arc, rotated so it fills clockwise from twelve. Only stroke-dashoffset
    animates, which keeps the whole thing off the layout path.
  -->
  <svg
    :width="size"
    :height="size"
    :viewBox="`0 0 ${size} ${size}`"
    :role="label ? 'img' : undefined"
    :aria-hidden="label ? undefined : 'true'"
    :aria-label="label"
    class="-rotate-90"
  >
    <circle
      :cx="size / 2"
      :cy="size / 2"
      :r="radius"
      fill="none"
      :stroke="trackColor"
      :stroke-width="thickness"
    />
    <circle
      :cx="size / 2"
      :cy="size / 2"
      :r="radius"
      fill="none"
      :stroke="strokeColor"
      :stroke-width="thickness"
      stroke-linecap="round"
      :stroke-dasharray="circumference"
      :stroke-dashoffset="dashOffset"
      :class="overtime ? 'pf-pulse-ember' : ''"
      style="transition: stroke-dashoffset 240ms cubic-bezier(0.22, 1, 0.36, 1), stroke 200ms linear"
    />
  </svg>
</template>
