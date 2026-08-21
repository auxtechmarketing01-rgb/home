<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import { formatMinutes } from '@/utils/formatDuration'
import type { ApiFailure } from '@/types/api'
import type { AssignmentPayload, RoadmapItem } from '@/types/roadmap'

const props = defineProps<{
  item: RoadmapItem
  saving?: boolean
  failure?: ApiFailure | null
}>()

const emit = defineEmits<{ submit: [AssignmentPayload]; cancel: [] }>()

const form = reactive({
  assigned_minutes: '' as string | number,
  assigned_due_at: '',
})

/** `datetime-local` wants `YYYY-MM-DDTHH:mm`; the API sends a full ISO string. */
function toLocalInput(iso: string | null): string {
  if (!iso) {
    return ''
  }

  const date = new Date(iso)

  if (Number.isNaN(date.getTime())) {
    return ''
  }

  const pad = (value: number): string => String(value).padStart(2, '0')

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

watch(
  () => props.item,
  (item) => {
    form.assigned_minutes = item.assigned_minutes ?? ''
    form.assigned_due_at = toLocalInput(item.assigned_due_at)
  },
  { immediate: true },
)

function serverError(field: string): string | null {
  return props.failure?.errors[field]?.[0] ?? null
}

const minutesError = computed(() => {
  if (form.assigned_minutes === '') {
    return null
  }

  const parsed = Number(form.assigned_minutes)

  return Number.isFinite(parsed) && parsed >= 1 ? null : 'Use a whole number of minutes, 1 or more.'
})

function submit(): void {
  if (minutesError.value) {
    return
  }

  emit('submit', {
    assigned_minutes: form.assigned_minutes === '' ? null : Number(form.assigned_minutes),
    /** Sent as a real ISO instant so the server stores UTC, not the mentor's clock. */
    assigned_due_at: form.assigned_due_at ? new Date(form.assigned_due_at).toISOString() : null,
  })
}

function clear(): void {
  form.assigned_minutes = ''
  form.assigned_due_at = ''
  emit('submit', { assigned_minutes: null, assigned_due_at: null })
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <!--
      The boundary, stated in the UI and not just enforced in a Policy: a mentor
      sets expectations, never content. There is no title field here on purpose.
    -->
    <div class="flex items-start gap-2.5 rounded-lg border border-violet/25 bg-violet/10 px-3 py-2.5">
      <AppIcon name="handshake" :size="15" class="mt-0.5 text-violet" />
      <p class="text-[12px] leading-relaxed text-ink-muted">
        You are setting an expectation on
        <span class="font-medium text-ink">{{ item.title }}</span>. Only
        {{ 'the owner' }} can edit the step itself or mark it done.
      </p>
    </div>

    <ErrorBanner v-if="failure && Object.keys(failure.errors).length === 0" :failure="failure" />

    <BaseInput
      v-model="form.assigned_minutes"
      label="Time budget"
      type="number"
      :min="1"
      numeric
      placeholder="60"
      :hint="
        item.estimated_minutes !== null
          ? `Their own estimate is ${formatMinutes(item.estimated_minutes)} - yours is tracked separately.`
          : 'In minutes. Kept separate from their own estimate.'
      "
      :error="serverError('assigned_minutes') ?? minutesError"
    />

    <BaseInput
      v-model="form.assigned_due_at"
      label="Due"
      type="datetime-local"
      hint="Stored in UTC and shown in each member's own timezone."
      :error="serverError('assigned_due_at')"
    />

    <div class="flex items-center justify-between gap-2 pt-1">
      <BaseButton
        v-if="item.assigned_minutes !== null || item.assigned_due_at !== null"
        variant="ghost"
        size="sm"
        @click="clear"
      >
        Clear assignment
      </BaseButton>
      <span v-else />

      <span class="flex gap-2">
        <BaseButton variant="ghost" size="sm" @click="emit('cancel')">Cancel</BaseButton>
        <BaseButton type="submit" variant="primary" size="sm" :loading="saving">Save</BaseButton>
      </span>
    </div>
  </form>
</template>
