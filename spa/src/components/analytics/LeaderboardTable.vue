<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { formatDuration } from '@/utils/formatDuration'
import type { LeaderboardEntry, LeaderboardPeriod } from '@/types/analytics'

type SortKey = 'focus_minutes' | 'current_streak' | 'goals_completed'

const props = defineProps<{
  entries: LeaderboardEntry[]
  period: LeaderboardPeriod
  loading?: boolean
  currentUserId?: number | null
}>()

const emit = defineEmits<{ period: [LeaderboardPeriod] }>()

const sortKey = ref<SortKey>('focus_minutes')

const PERIODS: Array<{ value: LeaderboardPeriod; label: string }> = [
  { value: 'week', label: 'This week' },
  { value: 'month', label: 'This month' },
  { value: 'all', label: 'All time' },
]

const COLUMNS: Array<{ key: SortKey; label: string; short: string }> = [
  { key: 'focus_minutes', label: 'Focus time', short: 'Focus' },
  { key: 'current_streak', label: 'Current streak', short: 'Streak' },
  { key: 'goals_completed', label: 'Goals completed', short: 'Done' },
]

const sorted = computed(() =>
  [...props.entries].sort((a, b) => b[sortKey.value] - a[sortKey.value]),
)

const leader = computed(() => sorted.value[0]?.[sortKey.value] ?? 0)

function share(entry: LeaderboardEntry): number {
  return leader.value > 0 ? (entry[sortKey.value] / leader.value) * 100 : 0
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div
        class="inline-flex rounded-lg border border-line bg-surface-2 p-0.5"
        role="group"
        aria-label="Leaderboard period"
      >
        <button
          v-for="option in PERIODS"
          :key="option.value"
          type="button"
          :aria-pressed="period === option.value"
          :class="[
            'h-8 rounded-md px-2.5 text-[12.5px] transition-colors duration-150',
            period === option.value
              ? 'bg-surface font-semibold text-ink'
              : 'font-medium text-ink-muted hover:text-ink',
          ]"
          @click="emit('period', option.value)"
        >
          {{ option.label }}
        </button>
      </div>

      <p class="text-[11px] text-ink-faint">
        Only goals shared with this group count here.
      </p>
    </div>

    <SkeletonBlock v-if="loading" :rows="4" height="h-11" rounded="rounded-lg" />

    <EmptyState
      v-else-if="entries.length === 0"
      icon="users"
      title="Nothing on the board yet"
      body="Share a goal with this group and log a focus sprint against it - the board fills from there."
      compact
    />

    <div v-else class="overflow-x-auto">
      <table class="w-full min-w-[34rem] border-separate border-spacing-0 text-left">
        <caption class="sr-only">
          Group leaderboard, sorted by {{ sortKey.replace('_', ' ') }}
        </caption>

        <thead>
          <tr>
            <th
              scope="col"
              class="border-b border-line pb-2 pl-1 text-[11px] font-medium uppercase tracking-[0.1em] text-ink-faint"
            >
              Member
            </th>
            <th
              v-for="column in COLUMNS"
              :key="column.key"
              scope="col"
              class="border-b border-line pb-2 text-right text-[11px] font-medium uppercase tracking-[0.1em]"
              :aria-sort="sortKey === column.key ? 'descending' : 'none'"
            >
              <button
                type="button"
                class="inline-flex items-center gap-1 transition-colors"
                :class="sortKey === column.key ? 'text-ink' : 'text-ink-faint hover:text-ink-muted'"
                @click="sortKey = column.key"
              >
                <span class="hidden sm:inline">{{ column.label }}</span>
                <span class="sm:hidden">{{ column.short }}</span>
                <AppIcon v-if="sortKey === column.key" name="arrowDown" :size="11" />
              </button>
            </th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="(entry, index) in sorted"
            :key="entry.user.id"
            class="group"
            :class="entry.user.id === currentUserId ? 'bg-brand-soft/40' : ''"
          >
            <th scope="row" class="border-b border-line py-2.5 pl-1 font-normal">
              <span class="flex items-center gap-2.5">
                <!-- Rank is a number, not a medal: three trophies would rank a family. -->
                <span
                  class="tnum grid size-6 shrink-0 place-items-center rounded-md border text-[11px] font-semibold"
                  :class="
                    index === 0
                      ? 'border-brand/40 bg-brand-soft text-brand'
                      : 'border-line bg-surface-2 text-ink-faint'
                  "
                >
                  {{ index + 1 }}
                </span>
                <span class="min-w-0">
                  <span class="block truncate text-[13px] font-medium text-ink">
                    {{ entry.user.name }}
                    <span v-if="entry.user.id === currentUserId" class="text-[11px] text-brand">
                      (you)
                    </span>
                  </span>
                  <!-- A bar per row, so the gap is visible without reading three numbers. -->
                  <span class="mt-1 block h-[3px] w-full max-w-32 overflow-hidden rounded-full bg-surface-3">
                    <span
                      class="block h-full rounded-full bg-brand transition-[width] duration-300"
                      :style="{ width: `${share(entry)}%` }"
                    />
                  </span>
                </span>
              </span>
            </th>

            <td class="tnum border-b border-line py-2.5 text-right text-[13px] font-semibold text-ink">
              {{ formatDuration(entry.focus_minutes * 60) }}
            </td>
            <td class="tnum border-b border-line py-2.5 text-right text-[13px] text-ink-muted">
              {{ entry.current_streak }}
            </td>
            <td class="tnum border-b border-line py-2.5 text-right text-[13px] text-ink-muted">
              {{ entry.goals_completed }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
