<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ProgressBar from '@/components/ui/ProgressBar.vue'
import { formatDuration } from '@/utils/formatDuration'
import { formatShortDate } from '@/utils/date'
import type { Challenge, ChallengeStatus } from '@/types/group'
import type { Goal } from '@/types/goal'

const props = defineProps<{
  challenge: Challenge
  goals: Goal[]
  canDelete?: boolean
  saving?: boolean
  currentUserId?: number | null
}>()

const emit = defineEmits<{ toggle: [number | null]; destroy: [] }>()

const picking = ref(false)
const selectedGoalId = ref<number | null>(null)

const STATUS_TONES: Record<ChallengeStatus, string> = {
  upcoming: 'bg-info/12 text-info border-info/25',
  active: 'bg-brand-soft text-brand border-brand/25',
  completed: 'bg-ok/12 text-ok border-ok/25',
  cancelled: 'bg-surface-2 text-ink-faint border-line',
}

const window = computed(() => {
  const { starts_on: start, ends_on: end } = props.challenge

  if (!start && !end) {
    return 'No dates set'
  }

  if (start && end) {
    return `${formatShortDate(start)} - ${formatShortDate(end)}`
  }

  return start ? `From ${formatShortDate(start)}` : `Until ${formatShortDate(end)}`
})

/** Only goals shared with a group can be entered; a private goal would leak. */
const goalOptions = computed(() =>
  props.goals
    .filter((goal) => goal.visibility === 'group' && goal.group_id === props.challenge.group_id)
    .map((goal) => ({ value: goal.id, label: goal.title })),
)

const standings = computed(() =>
  [...(props.challenge.participants ?? [])].sort(
    (a, b) => (b.goal?.completion_percentage ?? 0) - (a.goal?.completion_percentage ?? 0),
  ),
)

function toggle(): void {
  if (props.challenge.has_joined) {
    emit('toggle', null)

    return
  }

  if (goalOptions.value.length === 0) {
    emit('toggle', null)

    return
  }

  selectedGoalId.value = goalOptions.value[0]?.value ?? null
  picking.value = true
}

function confirmJoin(): void {
  emit('toggle', selectedGoalId.value)
  picking.value = false
}
</script>

<template>
  <article class="rounded-xl border border-line bg-surface p-4">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <div class="mb-1 flex flex-wrap items-center gap-1.5">
          <BaseBadge :tone="STATUS_TONES[challenge.status]">
            {{ challenge.status }}
          </BaseBadge>
          <span class="tnum text-[11px] text-ink-faint">{{ window }}</span>
        </div>

        <h3 class="truncate font-display text-[15px] font-semibold text-ink">
          {{ challenge.title }}
        </h3>
        <p
          v-if="challenge.description"
          class="mt-1 line-clamp-2 text-[12.5px] leading-relaxed text-ink-muted"
        >
          {{ challenge.description }}
        </p>
      </div>

      <div class="flex shrink-0 items-center gap-1.5">
        <BaseButton
          :variant="challenge.has_joined ? 'subtle' : 'primary'"
          size="sm"
          :icon="challenge.has_joined ? 'check' : 'plus'"
          :loading="saving"
          @click="toggle"
        >
          {{ challenge.has_joined ? 'Joined' : 'Join' }}
        </BaseButton>

        <BaseButton
          v-if="canDelete"
          variant="ghost"
          size="icon"
          icon="trash"
          label="Delete challenge"
          @click="emit('destroy')"
        />
      </div>
    </div>

    <p class="mt-3 flex items-center gap-1.5 text-[11.5px] text-ink-faint">
      <AppIcon name="users" :size="12" />
      <span class="tnum">{{ challenge.participants_count ?? standings.length }}</span>
      taking part
    </p>

    <ol v-if="standings.length > 0" class="mt-3 space-y-2 border-t border-line pt-3">
      <li v-for="(entry, index) in standings" :key="entry.user.id" class="flex items-center gap-2.5">
        <span class="tnum w-4 shrink-0 text-[11px] font-semibold text-ink-faint">
          {{ index + 1 }}
        </span>

        <span class="min-w-0 flex-1">
          <span class="flex items-baseline justify-between gap-2">
            <span class="truncate text-[12.5px] font-medium text-ink">
              {{ entry.user.name }}
              <span v-if="entry.user.id === currentUserId" class="text-[10.5px] text-brand">(you)</span>
            </span>
            <span class="tnum shrink-0 text-[11.5px] text-ink-muted">
              {{ Math.round(entry.goal?.completion_percentage ?? 0) }}%
              <span v-if="entry.goal" class="text-ink-faint">
                - {{ formatDuration(entry.goal.total_focus_seconds) }}
              </span>
            </span>
          </span>
          <ProgressBar
            :value="entry.goal?.completion_percentage ?? 0"
            height="hair"
            class="mt-1"
            :label="`${entry.user.name} progress`"
          />
          <span v-if="entry.goal" class="mt-1 block truncate text-[10.5px] text-ink-faint">
            {{ entry.goal.title }}
          </span>
          <span v-else class="mt-1 block text-[10.5px] text-ink-faint">No goal linked</span>
        </span>
      </li>
    </ol>

    <BaseModal
      v-model:open="picking"
      title="Which goal are you entering?"
      description="Only goals shared with this group can be linked - a private goal would expose progress the group cannot otherwise see."
      size="sm"
    >
      <BaseSelect
        v-model="selectedGoalId"
        label="Goal"
        placeholder="No goal - just take part"
        :options="goalOptions"
      />

      <template #footer>
        <BaseButton variant="ghost" size="sm" @click="picking = false">Cancel</BaseButton>
        <BaseButton variant="primary" size="sm" :loading="saving" @click="confirmJoin">
          Join challenge
        </BaseButton>
      </template>
    </BaseModal>
  </article>
</template>
