<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import type { ApiFailure } from '@/types/api'
import type { RoadmapItem, RoadmapItemStatus, RoadmapItemUpdatePayload } from '@/types/roadmap'

const props = defineProps<{
  item?: RoadmapItem | null
  saving?: boolean
  failure?: ApiFailure | null
}>()

const emit = defineEmits<{ submit: [RoadmapItemUpdatePayload]; cancel: [] }>()

const form = reactive({
  title: '',
  description: '',
  day_number: '' as string | number,
  scheduled_date: '',
  estimated_minutes: '' as string | number,
  status: 'todo' as RoadmapItemStatus,
  reflection_note: '',
})

watch(
  () => props.item,
  (item) => {
    form.title = item?.title ?? ''
    form.description = item?.description ?? ''
    form.day_number = item?.day_number ?? ''
    form.scheduled_date = item?.scheduled_date ?? ''
    form.estimated_minutes = item?.estimated_minutes ?? ''
    form.status = item?.status ?? 'todo'
    form.reflection_note = item?.reflection_note ?? ''
  },
  { immediate: true },
)

const statusOptions = [
  { value: 'todo', label: 'To do' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'done', label: 'Done' },
  { value: 'skipped', label: 'Skipped' },
]

const titleError = computed(() =>
  form.title.trim().length === 0 && form.title.length > 0 ? 'Give the step a title.' : null,
)

const canSubmit = computed(() => form.title.trim().length > 0)

function serverError(field: string): string | null {
  return props.failure?.errors[field]?.[0] ?? null
}

function toNumber(value: string | number): number | null {
  if (value === '' || value === null) {
    return null
  }

  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : null
}

function submit(): void {
  if (!canSubmit.value) {
    return
  }

  emit('submit', {
    title: form.title.trim(),
    description: form.description.trim() || null,
    day_number: toNumber(form.day_number),
    scheduled_date: form.scheduled_date || null,
    estimated_minutes: toNumber(form.estimated_minutes),
    status: form.status,
    reflection_note: form.reflection_note.trim() || null,
  })
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <ErrorBanner v-if="failure && Object.keys(failure.errors).length === 0" :failure="failure" />

    <BaseInput
      v-model="form.title"
      label="Step"
      placeholder="Draft the hero section"
      required
      :error="serverError('title') ?? titleError"
    />

    <BaseTextarea
      v-model="form.description"
      label="Detail"
      placeholder="Anything you want to remember when you get here."
      :rows="3"
      :error="serverError('description')"
    />

    <div class="grid gap-4 sm:grid-cols-3">
      <BaseInput
        v-model="form.day_number"
        label="Day"
        type="number"
        :min="1"
        numeric
        placeholder="1"
        hint="Position in a day-numbered plan."
        :error="serverError('day_number')"
      />
      <BaseInput
        v-model="form.scheduled_date"
        label="Scheduled"
        type="date"
        :error="serverError('scheduled_date')"
      />
      <!--
        The member's own estimate. A mentor's expectation is a separate field on
        a separate endpoint -- these two are never merged into one input.
      -->
      <BaseInput
        v-model="form.estimated_minutes"
        label="Your estimate"
        type="number"
        :min="1"
        numeric
        placeholder="45"
        hint="In minutes."
        :error="serverError('estimated_minutes')"
      />
    </div>

    <BaseSelect
      v-model="form.status"
      label="Status"
      :options="statusOptions"
      :error="serverError('status')"
    />

    <BaseTextarea
      v-if="item"
      v-model="form.reflection_note"
      label="Reflection"
      placeholder="What actually happened here?"
      :rows="2"
      :error="serverError('reflection_note')"
    />

    <div class="flex justify-end gap-2 pt-1">
      <BaseButton variant="ghost" size="sm" @click="emit('cancel')">Cancel</BaseButton>
      <BaseButton type="submit" variant="primary" size="sm" :loading="saving" :disabled="!canSubmit">
        {{ item ? 'Save step' : 'Add step' }}
      </BaseButton>
    </div>
  </form>
</template>
