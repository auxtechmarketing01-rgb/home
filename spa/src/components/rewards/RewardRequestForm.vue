<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import type { ApiFailure } from '@/types/api'
import type { Goal } from '@/types/goal'
import type { Mentorship } from '@/types/mentorship'
import type { RewardPayload, RewardType } from '@/types/reward'

/**
 * Mentee side -- the literal "ask for a reward" UI (FR-RWD-03). Separate from
 * the offer form because the fields and validation genuinely differ: a request
 * does not need a linked goal up front, and it lands as `requested` for the
 * mentor to answer rather than as an earnable `offered`.
 */
const props = defineProps<{
  mentorships: Mentorship[]
  goals: Goal[]
  presetGoalId?: number | null
  saving?: boolean
  failure?: ApiFailure | null
}>()

const emit = defineEmits<{ submit: [RewardPayload]; cancel: [] }>()

const form = reactive({
  mentorship_id: null as number | null,
  goal_id: null as number | null,
  title: '',
  description: '',
  type: 'custom' as RewardType,
  monetary_amount: '' as string | number,
  currency_label: '',
})

watch(
  () => [props.presetGoalId, props.mentorships.length] as const,
  () => {
    form.mentorship_id = form.mentorship_id ?? props.mentorships[0]?.id ?? null
    form.goal_id = props.presetGoalId ?? form.goal_id
  },
  { immediate: true },
)

const mentorshipOptions = computed(() =>
  props.mentorships.map((mentorship) => ({
    value: mentorship.id,
    label: mentorship.mentor?.name ?? `Mentorship #${mentorship.id}`,
  })),
)

const goalOptions = computed(() =>
  props.goals.map((goal) => ({ value: goal.id, label: goal.title })),
)

const isMonetary = computed(() => form.type === 'monetary')

watch(isMonetary, (monetary) => {
  if (!monetary) {
    form.monetary_amount = ''
    form.currency_label = ''
  }
})

const canSubmit = computed(
  () => form.mentorship_id !== null && form.title.trim().length > 0,
)

function serverError(field: string): string | null {
  return props.failure?.errors[field]?.[0] ?? null
}

function submit(): void {
  if (!canSubmit.value || form.mentorship_id === null) {
    return
  }

  emit('submit', {
    mentorship_id: form.mentorship_id,
    goal_id: form.goal_id,
    roadmap_item_id: null,
    title: form.title.trim(),
    description: form.description.trim() || null,
    type: form.type,
    monetary_amount: isMonetary.value && form.monetary_amount !== '' ? Number(form.monetary_amount) : null,
    currency_label: isMonetary.value ? form.currency_label.trim() || null : null,
  })
}
</script>

<template>
  <div>
    <EmptyState
      v-if="mentorships.length === 0"
      icon="handshake"
      title="No mentor to ask"
      body="Rewards are agreed between a mentor and a mentee. Ask someone in one of your groups to mentor you first."
      compact
    >
      <BaseButton variant="primary" size="sm" to="/mentorships" icon="handshake">
        Set up a mentorship
      </BaseButton>
    </EmptyState>

    <form v-else class="space-y-4" novalidate @submit.prevent="submit">
      <ErrorBanner v-if="failure && Object.keys(failure.errors).length === 0" :failure="failure" />

      <BaseSelect
        v-model="form.mentorship_id"
        label="Ask which mentor"
        :options="mentorshipOptions"
        required
        :error="serverError('mentorship_id')"
      />

      <BaseInput
        v-model="form.title"
        label="What are you asking for?"
        placeholder="A new keyboard when I finish the course"
        required
        :error="serverError('title')"
      />

      <BaseTextarea
        v-model="form.description"
        label="Why (optional)"
        placeholder="Make the case."
        :rows="3"
        :maxlength="2000"
        :error="serverError('description')"
      />

      <div class="grid gap-4 sm:grid-cols-2">
        <BaseSelect
          v-model="form.goal_id"
          label="Tie it to a goal (optional)"
          placeholder="No goal"
          :options="goalOptions"
          hint="Optional here - your mentor can attach it when they accept."
          :error="serverError('goal_id')"
        />
        <BaseSelect
          v-model="form.type"
          label="Kind"
          :options="[
            { value: 'custom', label: 'Custom' },
            { value: 'privilege', label: 'Privilege' },
            { value: 'monetary', label: 'Monetary' },
          ]"
          :error="serverError('type')"
        />
      </div>

      <div v-if="isMonetary" class="grid gap-4 sm:grid-cols-2">
        <BaseInput
          v-model="form.monetary_amount"
          label="Amount"
          type="number"
          :min="0"
          step="0.01"
          numeric
          placeholder="500"
          :error="serverError('monetary_amount')"
        />
        <BaseInput
          v-model="form.currency_label"
          label="Currency label"
          placeholder="BDT"
          :error="serverError('currency_label')"
        />
      </div>

      <p class="flex items-start gap-2 rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-[11.5px] leading-relaxed text-ink-muted">
        <AppIcon name="info" :size="14" class="mt-0.5 shrink-0 text-ink-faint" />
        This goes to your mentor as a request. If they accept it becomes something you can earn, then
        claim. Nothing is paid through Pathforge.
      </p>

      <div class="flex justify-end gap-2">
        <BaseButton variant="ghost" size="sm" @click="emit('cancel')">Cancel</BaseButton>
        <BaseButton
          type="submit"
          variant="primary"
          size="sm"
          :loading="saving"
          :disabled="!canSubmit"
        >
          Send request
        </BaseButton>
      </div>
    </form>
  </div>
</template>
