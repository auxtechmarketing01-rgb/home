<script setup lang="ts">
import { computed, useId } from 'vue'
import AppIcon from './AppIcon.vue'
import type { SelectOption } from './types'

const props = defineProps<{
  label: string
  options: SelectOption[]
  hint?: string
  error?: string | null
  placeholder?: string
  required?: boolean
  disabled?: boolean
}>()

const model = defineModel<string | number | null>({ default: null })

const uid = useId()
const fieldId = `select-${uid}`
const hintId = `select-hint-${uid}`
const errorId = `select-error-${uid}`

const describedBy = computed(() => {
  const ids = [props.hint ? hintId : null, props.error ? errorId : null].filter(Boolean)

  return ids.length > 0 ? ids.join(' ') : undefined
})
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label :for="fieldId" class="text-[13px] font-medium text-ink-muted">
      {{ label }}
      <span v-if="required" class="text-ember" aria-hidden="true">*</span>
    </label>

    <div class="relative">
      <select
        :id="fieldId"
        v-model="model"
        :required="required"
        :disabled="disabled"
        :aria-invalid="error ? 'true' : undefined"
        :aria-describedby="describedBy"
        :class="[
          'h-10 w-full appearance-none rounded-lg border bg-surface-2 pl-3 pr-9 text-sm text-ink',
          'outline-none transition-colors duration-150 focus:border-brand focus:bg-surface',
          'disabled:opacity-50',
          error ? 'border-danger' : 'border-line',
        ]"
      >
        <option v-if="placeholder" :value="null">{{ placeholder }}</option>
        <option
          v-for="option in options"
          :key="String(option.value)"
          :value="option.value"
          :disabled="option.disabled"
        >
          {{ option.label }}
        </option>
      </select>
      <AppIcon
        name="chevronDown"
        :size="16"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-ink-faint"
      />
    </div>

    <p v-if="error" :id="errorId" class="flex items-start gap-1.5 text-xs text-danger">
      <AppIcon name="alert" :size="13" class="mt-0.5" />
      <span>{{ error }}</span>
    </p>
    <p v-else-if="hint" :id="hintId" class="text-xs text-ink-faint">{{ hint }}</p>
  </div>
</template>
