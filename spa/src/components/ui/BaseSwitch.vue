<script setup lang="ts">
import { useId } from 'vue'

defineProps<{
  label: string
  description?: string
  disabled?: boolean
}>()

const model = defineModel<boolean>({ default: false })

const uid = useId()
const descriptionId = `switch-desc-${uid}`
</script>

<template>
  <div class="flex items-start justify-between gap-4">
    <div class="min-w-0">
      <p class="text-sm font-medium text-ink">{{ label }}</p>
      <p v-if="description" :id="descriptionId" class="mt-0.5 text-xs leading-relaxed text-ink-muted">
        {{ description }}
      </p>
    </div>

    <button
      type="button"
      role="switch"
      :aria-checked="model"
      :aria-label="label"
      :aria-describedby="description ? descriptionId : undefined"
      :disabled="disabled"
      class="relative h-6 w-11 shrink-0 rounded-full border transition-colors duration-150 disabled:opacity-45"
      :class="model ? 'border-transparent bg-brand' : 'border-line bg-surface-3'"
      @click="model = !model"
    >
      <span
        class="absolute top-0.5 size-4.5 rounded-full transition-[left] duration-200 ease-out"
        :class="model ? 'left-[22px] bg-brand-ink' : 'left-0.5 bg-ink-faint'"
        aria-hidden="true"
      />
    </button>
  </div>
</template>
