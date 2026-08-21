<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import type { SprintMode } from '@/types/sprint'
import type { IconName } from '@/components/ui/icons'

const mode = defineModel<SprintMode>('mode', { required: true })
const minutes = defineModel<number>('minutes', { required: true })

defineProps<{ disabled?: boolean }>()

const MODES: Array<{ value: SprintMode; label: string; icon: IconName; blurb: string }> = [
  {
    value: 'pomodoro',
    label: 'Pomodoro',
    icon: 'hourglass',
    blurb: '25 on, 5 off. The default.',
  },
  {
    value: 'countdown',
    label: 'Countdown',
    icon: 'timer',
    blurb: 'Your own block of time.',
  },
  {
    value: 'stopwatch',
    label: 'Stopwatch',
    icon: 'clock',
    blurb: 'Open-ended. Counts up.',
  },
]

const PRESETS = [15, 25, 45, 60, 90]

/** A stopwatch is open-ended by definition, so there is nothing to set. */
const showsDuration = computed(() => mode.value !== 'stopwatch')
</script>

<template>
  <div class="space-y-4">
    <fieldset :disabled="disabled">
      <legend class="mb-2 text-[13px] font-medium text-ink-muted">Mode</legend>

      <div class="grid gap-2 sm:grid-cols-3">
        <label
          v-for="option in MODES"
          :key="option.value"
          :class="[
            'flex cursor-pointer flex-col gap-1 rounded-xl border p-3 transition-colors duration-150',
            mode === option.value
              ? 'border-brand bg-brand-soft'
              : 'border-line bg-surface-2 hover:border-line-strong',
            disabled ? 'pointer-events-none opacity-50' : '',
          ]"
        >
          <span class="flex items-center gap-2">
            <input
              v-model="mode"
              type="radio"
              name="focus-mode"
              :value="option.value"
              class="sr-only"
            />
            <AppIcon
              :name="option.icon"
              :size="16"
              :class="mode === option.value ? 'text-brand' : 'text-ink-faint'"
            />
            <span class="text-[13px] font-semibold text-ink">{{ option.label }}</span>
          </span>
          <span class="text-[11.5px] leading-relaxed text-ink-muted">{{ option.blurb }}</span>
        </label>
      </div>
    </fieldset>

    <fieldset v-if="showsDuration" :disabled="disabled">
      <legend class="mb-2 text-[13px] font-medium text-ink-muted">Length</legend>

      <div class="flex flex-wrap items-center gap-2">
        <button
          v-for="preset in PRESETS"
          :key="preset"
          type="button"
          :aria-pressed="minutes === preset"
          :class="[
            'tnum h-9 rounded-lg border px-3 text-[13px] font-medium transition-colors duration-150',
            minutes === preset
              ? 'border-brand bg-brand-soft text-brand'
              : 'border-line bg-surface-2 text-ink-muted hover:bg-surface-3 hover:text-ink',
          ]"
          @click="minutes = preset"
        >
          {{ preset }}m
        </button>

        <label class="ml-1 flex items-center gap-2">
          <span class="text-[12px] text-ink-faint">Custom</span>
          <input
            v-model.number="minutes"
            type="number"
            :min="1"
            :max="480"
            aria-label="Custom length in minutes"
            class="tnum h-9 w-20 rounded-lg border border-line bg-surface-2 px-2.5 text-[13px] text-ink outline-none transition-colors focus:border-brand focus:bg-surface"
          />
        </label>
      </div>
    </fieldset>

    <p v-else class="text-[12px] leading-relaxed text-ink-faint">
      A stopwatch has no planned length, so it never reaches overtime. Stop it when you are done.
    </p>
  </div>
</template>
