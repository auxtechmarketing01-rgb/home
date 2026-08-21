<script setup lang="ts">
import { computed, useId } from 'vue'
import AppIcon from './AppIcon.vue'
import type { IconName } from './icons'

const props = withDefaults(
  defineProps<{
    label: string
    type?: string
    placeholder?: string
    hint?: string
    error?: string | null
    icon?: IconName
    required?: boolean
    disabled?: boolean
    autocomplete?: string
    min?: number | string
    max?: number | string
    step?: number | string
    /** Numeric fields get the mono face so digits line up column-to-column. */
    numeric?: boolean
  }>(),
  { type: 'text' },
)

const model = defineModel<string | number | null>({ default: '' })

const uid = useId()
const inputId = `field-${uid}`
const hintId = `hint-${uid}`
const errorId = `error-${uid}`

/**
 * A visible label, always. A placeholder is a hint, never a label -- it
 * disappears the moment someone types, which is exactly when they need it.
 */
const describedBy = computed(() => {
  const ids = [props.hint ? hintId : null, props.error ? errorId : null].filter(Boolean)

  return ids.length > 0 ? ids.join(' ') : undefined
})
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="inputId" class="text-[13px] font-medium text-ink-muted">
      {{ label }}
      <span v-if="required" class="text-ember" aria-hidden="true">*</span>
    </label>

    <div class="relative">
      <AppIcon
        v-if="icon"
        :name="icon"
        :size="16"
        class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-faint"
      />
      <input
        :id="inputId"
        v-model="model"
        :type="type"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        :autocomplete="autocomplete"
        :min="min"
        :max="max"
        :step="step"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="describedBy"
        :class="[
          'h-10 w-full rounded-lg border bg-surface-2 text-sm text-ink placeholder:text-ink-faint',
          'transition-colors duration-150 outline-none',
          'focus:border-brand focus:bg-surface',
          'disabled:opacity-50',
          icon ? 'pl-9 pr-3' : 'px-3',
          numeric ? 'tnum' : '',
          error ? 'border-danger' : 'border-line',
        ]"
      />
    </div>

    <p v-if="error" :id="errorId" class="flex items-start gap-1.5 text-xs text-danger">
      <AppIcon name="alert" :size="13" class="mt-0.5" />
      <span>{{ error }}</span>
    </p>
    <p v-else-if="hint" :id="hintId" class="text-xs text-ink-faint">{{ hint }}</p>
  </div>
</template>
