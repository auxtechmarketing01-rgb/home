<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import type { Goal } from '@/types/goal'
import type { SprintFilters, SprintStatus } from '@/types/sprint'

const props = defineProps<{
  filters: SprintFilters
  goals: Goal[]
  exportUrl: string
}>()

const emit = defineEmits<{ apply: [SprintFilters]; reset: [] }>()

const draft = reactive<{
  from: string
  to: string
  goal_id: number | null
  status: SprintStatus | null
}>({
  from: props.filters.from ?? '',
  to: props.filters.to ?? '',
  goal_id: props.filters.goal_id ?? null,
  status: props.filters.status ?? null,
})

watch(
  () => props.filters,
  (next) => {
    draft.from = next.from ?? ''
    draft.to = next.to ?? ''
    draft.goal_id = next.goal_id ?? null
    draft.status = next.status ?? null
  },
)

const statusOptions = [
  { value: 'completed', label: 'Completed' },
  { value: 'running', label: 'Running' },
  { value: 'paused', label: 'Paused' },
  { value: 'cancelled', label: 'Cancelled' },
]

const goalOptions = computed(() =>
  props.goals.map((goal) => ({ value: goal.id, label: goal.title })),
)

const rangeError = computed(() =>
  draft.from && draft.to && draft.to < draft.from ? 'The end date is before the start.' : null,
)

function apply(): void {
  if (rangeError.value) {
    return
  }

  emit('apply', {
    from: draft.from || undefined,
    to: draft.to || undefined,
    goal_id: draft.goal_id ?? undefined,
    status: draft.status ?? undefined,
    page: 1,
  })
}
</script>

<template>
  <form
    class="grid gap-3 rounded-xl border border-line bg-surface p-4 sm:grid-cols-2 lg:grid-cols-5"
    @submit.prevent="apply"
  >
    <BaseInput v-model="draft.from" label="From" type="date" />
    <BaseInput v-model="draft.to" label="To" type="date" :error="rangeError" />
    <BaseSelect v-model="draft.goal_id" label="Goal" placeholder="Any goal" :options="goalOptions" />
    <BaseSelect
      v-model="draft.status"
      label="Status"
      placeholder="Any status"
      :options="statusOptions"
    />

    <div class="flex items-end gap-2">
      <BaseButton type="submit" variant="primary" size="sm" icon="filter">Apply</BaseButton>
      <BaseButton variant="ghost" size="sm" @click="emit('reset')">Reset</BaseButton>
      <!--
        The CSV is built server-side (FR-SPR-08). This is a plain link carrying
        the session cookie, so the browser handles the download and the export
        respects exactly the filters shown above.
      -->
      <BaseButton
        variant="subtle"
        size="sm"
        icon="download"
        :href="exportUrl"
        label="Export filtered sprints as CSV"
      >
        CSV
      </BaseButton>
    </div>
  </form>
</template>
