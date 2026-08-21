<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ProgressBar from '@/components/ui/ProgressBar.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { formatDuration } from '@/utils/formatDuration'
import { formatDate } from '@/utils/date'
import type { MentorDashboardRow } from '@/types/mentorship'

defineProps<{ rows: MentorDashboardRow[]; loading?: boolean }>()
</script>

<template>
  <div class="space-y-4">
    <SkeletonBlock v-if="loading" :rows="2" height="h-32" rounded="rounded-xl" />

    <EmptyState
      v-else-if="rows.length === 0"
      icon="handshake"
      title="No mentees yet"
      body="Once someone accepts you as their mentor, a rollup of their streaks and goal progress appears here."
    />

    <!--
      A rollup of what the mentee lists already fetch, one card per mentee. Read
      only: a mentor sees progress and sets expectations elsewhere, never edits
      anything here.
    -->
    <article
      v-for="row in rows"
      :key="row.mentorship_id"
      class="rounded-xl border border-line bg-surface p-4"
    >
      <header class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
          <h3 class="truncate font-display text-[15px] font-semibold text-ink">
            {{ row.mentee.name }}
          </h3>
          <p class="mt-0.5 tnum text-[11.5px] text-ink-faint">
            {{ row.goals.length }} visible goal{{ row.goals.length === 1 ? '' : 's' }}
          </p>
        </div>

        <div class="flex items-center gap-2">
          <span
            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5"
            :class="
              row.current_streak > 0
                ? 'border-ember/25 bg-ember-soft text-ember'
                : 'border-line bg-surface-2 text-ink-faint'
            "
          >
            <AppIcon name="flame" :size="13" />
            <span class="tnum text-[12.5px] font-semibold">{{ row.current_streak }}</span>
            <span class="text-[10.5px]">day</span>
          </span>

          <span
            class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface-2 px-2.5 py-1.5 text-ink-muted"
            :title="`Longest streak: ${row.longest_streak} days`"
          >
            <AppIcon name="award" :size="13" />
            <span class="tnum text-[12.5px]">{{ row.longest_streak }}</span>
          </span>
        </div>
      </header>

      <ul v-if="row.goals.length > 0" class="mt-3.5 space-y-2.5 border-t border-line pt-3.5">
        <li v-for="goal in row.goals" :key="goal.id">
          <RouterLink
            :to="{ name: 'goal-detail', params: { id: goal.id } }"
            class="block rounded-lg p-2 -m-2 transition-colors hover:bg-surface-2"
          >
            <span class="flex items-baseline justify-between gap-2">
              <span class="truncate text-[12.5px] font-medium text-ink">{{ goal.title }}</span>
              <span class="tnum shrink-0 text-[11.5px] font-semibold text-ink">
                {{ Math.round(goal.completion_percentage) }}%
              </span>
            </span>

            <ProgressBar
              :value="goal.completion_percentage"
              height="hair"
              class="mt-1.5"
              :label="`${goal.title} completion`"
            />

            <span class="mt-1.5 flex flex-wrap items-center gap-x-3 text-[10.5px] text-ink-faint">
              <span class="tnum">{{ formatDuration(goal.total_focus_seconds) }} focused</span>
              <span v-if="goal.roadmap_item_count !== null" class="tnum">
                {{ goal.roadmap_item_count }} steps
              </span>
              <span class="capitalize">{{ goal.status }}</span>
              <span v-if="goal.projected_completion_date" class="tnum">
                projected {{ formatDate(goal.projected_completion_date) }}
              </span>
              <span v-else>no projection yet</span>
            </span>
          </RouterLink>
        </li>
      </ul>

      <p v-else class="mt-3.5 border-t border-line pt-3.5 text-[12px] text-ink-faint">
        They have not shared a goal you can see yet.
      </p>
    </article>
  </div>
</template>
