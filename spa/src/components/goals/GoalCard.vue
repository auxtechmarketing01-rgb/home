<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import ProgressBar from '@/components/ui/ProgressBar.vue'
import GoalStatusBadge from './GoalStatusBadge.vue'
import { formatDuration } from '@/utils/formatDuration'
import { formatShortDate } from '@/utils/date'
import type { Goal } from '@/types/goal'

const props = defineProps<{ goal: Goal; showOwner?: boolean }>()

const completion = computed(() => Number(props.goal.stats?.completion_percentage ?? 0))
const focusSeconds = computed(() => props.goal.stats?.total_focus_seconds ?? 0)
const itemCount = computed(() => props.goal.roadmap_item_count ?? props.goal.roadmap?.items_count ?? null)

const window = computed(() => {
  const { target_start_date: start, target_end_date: end } = props.goal

  if (!start && !end) {
    return null
  }

  if (start && end) {
    return `${formatShortDate(start)} - ${formatShortDate(end)}`
  }

  return start ? `from ${formatShortDate(start)}` : `by ${formatShortDate(end)}`
})
</script>

<template>
  <RouterLink
    :to="{ name: 'goal-detail', params: { id: goal.id } }"
    class="group flex flex-col gap-3.5 rounded-xl border border-line bg-surface p-4 transition-colors duration-150 hover:border-line-strong hover:bg-surface-2"
  >
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p
          v-if="goal.category"
          class="mb-1 flex items-center gap-1 text-[11px] font-medium uppercase tracking-[0.1em] text-ink-faint"
        >
          <AppIcon name="layers" :size="11" />
          {{ goal.category.name }}
        </p>
        <h3 class="truncate font-display text-[15px] font-semibold text-ink">{{ goal.title }}</h3>
        <p v-if="showOwner && goal.user" class="mt-0.5 text-[11.5px] text-ink-faint">
          {{ goal.user.name }}
        </p>
      </div>
      <GoalStatusBadge :status="goal.status" />
    </div>

    <p v-if="goal.description" class="line-clamp-2 text-[13px] leading-relaxed text-ink-muted">
      {{ goal.description }}
    </p>

    <div class="mt-auto space-y-2">
      <div class="flex items-baseline justify-between gap-2">
        <span class="text-[11.5px] font-medium text-ink-faint">Completion</span>
        <span class="tnum text-[13px] font-semibold text-ink">{{ Math.round(completion) }}%</span>
      </div>
      <ProgressBar
        :value="completion"
        :tone="goal.status === 'completed' ? 'ok' : 'brand'"
        :label="`${goal.title} completion`"
      />
    </div>

    <dl class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-line pt-3 text-[11.5px]">
      <div class="flex items-center gap-1.5">
        <AppIcon name="timer" :size="13" class="text-ink-faint" />
        <dt class="sr-only">Focus logged</dt>
        <dd class="tnum text-ink-muted">{{ formatDuration(focusSeconds) }}</dd>
      </div>
      <div v-if="itemCount !== null" class="flex items-center gap-1.5">
        <AppIcon name="route" :size="13" class="text-ink-faint" />
        <dt class="sr-only">Roadmap steps</dt>
        <dd class="tnum text-ink-muted">{{ itemCount }}</dd>
      </div>
      <div v-if="window" class="flex items-center gap-1.5">
        <AppIcon name="calendar" :size="13" class="text-ink-faint" />
        <dt class="sr-only">Target window</dt>
        <dd class="text-ink-muted">{{ window }}</dd>
      </div>
      <div v-if="goal.visibility === 'group'" class="ml-auto flex items-center gap-1.5">
        <AppIcon name="users" :size="13" class="text-brand" />
        <dd class="text-brand">Shared</dd>
      </div>
      <div v-else class="ml-auto flex items-center gap-1.5">
        <AppIcon name="lock" :size="13" class="text-ink-faint" />
        <dd class="text-ink-faint">Private</dd>
      </div>
    </dl>
  </RouterLink>
</template>
