<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    /** 0..100 */
    value: number
    tone?: 'brand' | 'ember' | 'ok'
    height?: 'hair' | 'sm' | 'md'
    label?: string
  }>(),
  { tone: 'brand', height: 'sm' },
)

const TONES = { brand: 'bg-brand', ember: 'bg-ember', ok: 'bg-ok' }
const HEIGHTS = { hair: 'h-[2px]', sm: 'h-1.5', md: 'h-2.5' }

const pct = computed(() => Math.min(100, Math.max(0, Number.isFinite(props.value) ? props.value : 0)))
</script>

<template>
  <div
    :class="['w-full overflow-hidden rounded-full bg-surface-3', HEIGHTS[height]]"
    role="progressbar"
    :aria-valuenow="Math.round(pct)"
    aria-valuemin="0"
    aria-valuemax="100"
    :aria-label="label"
  >
    <div
      :class="['h-full rounded-full transition-[width] duration-300 ease-out', TONES[tone]]"
      :style="{ width: `${pct}%` }"
    />
  </div>
</template>
