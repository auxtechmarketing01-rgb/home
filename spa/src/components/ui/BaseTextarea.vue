<script setup lang="ts">
import { computed, useId } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps<{
  label: string
  placeholder?: string
  hint?: string
  error?: string | null
  rows?: number
  required?: boolean
  disabled?: boolean
  maxlength?: number
}>()

const model = defineModel<string | null>({ default: '' })

const uid = useId()
const fieldId = `area-${uid}`
const hintId = `area-hint-${uid}`
const errorId = `area-error-${uid}`

const describedBy = computed(() => {
  const ids = [props.hint ? hintId : null, props.error ? errorId : null].filter(Boolean)

  return ids.length > 0 ? ids.join(' ') : undefined
})

const used = computed(() => (model.value ?? '').length)
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <div class="flex items-baseline justify-between gap-3">
      <label :for="fieldId" class="text-[13px] font-medium text-ink-muted">
        {{ label }}
        <span v-if="required" class="text-ember" aria-hidden="true">*</span>
      </label>
      <span v-if="maxlength" class="tnum text-[11px] text-ink-faint">
        {{ used }}/{{ maxlength }}
      </span>
    </div>

    <textarea
      :id="fieldId"
      v-model="model"
      :rows="rows ?? 4"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :maxlength="maxlength"
      :aria-invalid="error ? 'true' : undefined"
      :aria-describedby="describedBy"
      :class="[
        'w-full resize-y rounded-lg border bg-surface-2 px-3 py-2.5 text-sm leading-relaxed',
        'text-ink placeholder:text-ink-faint outline-none transition-colors duration-150',
        'focus:border-brand focus:bg-surface disabled:opacity-50',
        error ? 'border-danger' : 'border-line',
      ]"
    />

    <p v-if="error" :id="errorId" class="flex items-start gap-1.5 text-xs text-danger">
      <AppIcon name="alert" :size="13" class="mt-0.5" />
      <span>{{ error }}</span>
    </p>
    <p v-else-if="hint" :id="hintId" class="text-xs text-ink-faint">{{ hint }}</p>
  </div>
</template>
