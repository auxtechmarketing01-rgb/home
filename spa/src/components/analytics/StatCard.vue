<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
import type { IconName } from '@/components/ui/icons'

withDefaults(
  defineProps<{
    label: string
    value: string | number
    icon?: IconName
    hint?: string
    tone?: 'default' | 'brand' | 'ember' | 'ok'
  }>(),
  { tone: 'default' },
)

const TONES = {
  default: 'text-ink-faint',
  brand: 'text-brand',
  ember: 'text-ember',
  ok: 'text-ok',
}
</script>

<template>
  <div class="rounded-xl border border-line bg-surface p-4">
    <div class="flex items-center justify-between gap-2">
      <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">{{ label }}</p>
      <AppIcon v-if="icon" :name="icon" :size="15" :class="TONES[tone]" />
    </div>

    <p class="tnum mt-2 text-[26px] font-semibold leading-none tracking-tight text-ink">
      {{ value }}
    </p>

    <p v-if="hint" class="mt-1.5 text-[11.5px] leading-relaxed text-ink-muted">{{ hint }}</p>
  </div>
</template>
