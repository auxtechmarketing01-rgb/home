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
import type { RoadmapItem } from '@/types/roadmap'
import type { RewardPayload, RewardType } from '@/types/reward'

/**
 * Mentor side. Deliberately a different form from RewardRequestForm rather than
 * one form with a role toggle: an offer needs a linked goal or step up front,
 * because that is what makes the reward earnable. A request does not.
 */
const props = defineProps<{
  mentorships: Mentorship[]
  goals: Goal[]
  items?: RoadmapItem[]
  presetMentorshipId?: number | null
  presetGoalId?: number | null
  saving?: boolean
  failure?: ApiFailure | null
}>()

const emit = defineEmits<{ submit: [RewardPayload]; cancel: [] }>()

const form = reactive({
  mentorship_id: null as number | null,
  goal_id: null as number | null,
  roadmap_item_id: null as number | null,
  title: '',
  description: '',
  type: 'custom' as RewardType,
  monetary_amount: '' as string | number,
  currency_label: '',
})

watch(
  () => [props.presetMentorshipId, props.presetGoalId, props.mentorships.length] as const,
  () => {
    form.mentorship_id =
      props.presetMentorshipId ?? form.mentorship_id ?? props.mentorships[0]?.id ?? null
    form.goal_id = props.presetGoalId ?? form.goal_id
  },
  { immediate: true },
)

const mentorshipOptions = computed(() =>
  props.mentorships.map((mentorship) => ({
    value: mentorship.id,
    label: mentorship.mentee?.name ?? `Mentorship #${mentorship.id}`,
  })),
)

const goalOptions = computed(() =>
  props.goals.map((goal) => ({ value: goal.id, label: goal.title })),
)

const itemOptions = computed(() =>
  (props.items ?? [])
    .filter((item) => form.goal_id === null || true)
    .map((item) => ({ value: item.id, label: item.title })),
)

const isMonetary = computed(() => form.type === 'monetary')

/** Mirrors the backend `prohibited` rule so the fields disappear rather than 422. */
watch(isMonetary, (monetary) => {
  if (!monetary) {
    form.monetary_amount = ''
    form.currency_label = ''
  }
})

const linkError = computed(() =>
  form.goal_id === null && form.roadmap_item_id === null
    ? 'Link a goal or a step, otherwise there is nothing to earn it against.'
    : null,
)

const canSubmit = computed(
  () => form.mentorship_id !== null && form.title.trim().length > 0 && linkError.value === null,
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
    roadmap_item_id: form.roadmap_item_id,
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
      title="No mentees to offer to"
      body="A reward hangs off an accepted mentorship. Once someone accepts you as their mentor you can offer them one."
      compact
    />

    <form v-else class="space-y-4" novalidate @submit.prevent="submit">
      <ErrorBanner v-if="failure && Object.keys(failure.errors).length === 0" :failure="failure" />

      <BaseSelect
        v-model="form.mentorship_id"
        label="Mentee"
        :options="mentorshipOptions"
        required
        :error="serverError('mentorship_id')"
      />

      <div class="grid gap-4 sm:grid-cols-2">
        <BaseSelect
          v-model="form.goal_id"
          label="Against which goal"
          placeholder="No goal"
          :options="goalOptions"
          :error="serverError('goal_id') ?? linkError"
        />
        <BaseSelect
          v-model="form.roadmap_item_id"
          label="Or a specific step"
          placeholder="No step"
          :options="itemOptions"
          :hint="
            itemOptions.length === 0 ? 'Open a goal roadmap to pick a specific step.' : undefined
          "
          :error="serverError('roadmap_item_id')"
        />
      </div>

      <BaseInput
        v-model="form.title"
        label="Reward"
        placeholder="Dinner out when the roadmap is done"
        required
        :error="serverError('title')"
      />

      <BaseTextarea
        v-model="form.description"
        label="Detail"
        :rows="2"
        :maxlength="2000"
        :error="serverError('description')"
      />

      <BaseSelect
        v-model="form.type"
        label="Kind"
        :options="[
          { value: 'custom', label: 'Custom - anything else' },
          { value: 'privilege', label: 'Privilege - a permission or perk' },
          { value: 'monetary', label: 'Monetary - a recorded amount' },
        ]"
        :error="serverError('type')"
      />

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
          hint="Free text - amounts are grouped by label, never summed across labels."
          :error="serverError('currency_label')"
        />
      </div>

      <p
        v-if="isMonetary"
        class="flex items-start gap-2 rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-[11.5px] leading-relaxed text-ink-muted"
      >
        <AppIcon name="info" :size="14" class="mt-0.5 shrink-0 text-ink-faint" />
        Bookkeeping only. Pathforge records that a reward was agreed and later fulfilled - there are
        no payment rails and nothing here is a spendable balance.
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
          Offer reward
        </BaseButton>
      </div>
    </form>
  </div>
</template>
