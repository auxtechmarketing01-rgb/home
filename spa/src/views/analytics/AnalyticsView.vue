<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import GamificationPanel from '@/components/analytics/GamificationPanel.vue'
import HeatmapCalendar from '@/components/analytics/HeatmapCalendar.vue'
import StatCard from '@/components/analytics/StatCard.vue'
import VelocityChart from '@/components/analytics/VelocityChart.vue'
import { useStreak } from '@/composables/useStreak'
import { useAnalyticsStore } from '@/stores/analytics'
import { formatDuration } from '@/utils/formatDuration'
import { formatDate } from '@/utils/date'

const analytics = useAnalyticsStore()

const windowDays = ref(84)

const RANGES = [
  { value: 28, label: '4 weeks' },
  { value: 84, label: '12 weeks' },
  { value: 182, label: '6 months' },
  { value: 365, label: '1 year' },
]

onMounted(() => {
  void analytics.fetchOverview(windowDays.value)
})

watch(windowDays, (days) => {
  void analytics.fetchOverview(days)
})

const overview = computed(() => analytics.overview)
const streak = useStreak(computed(() => overview.value?.streak ?? null))

const weeks = computed(() => Math.min(53, Math.ceil(windowDays.value / 7)))

const busiestDay = computed(() => {
  const trend = overview.value?.daily_trend ?? []

  return trend.reduce<(typeof trend)[number] | null>(
    (best, point) => (best === null || point.focus_minutes > best.focus_minutes ? point : best),
    null,
  )
})

const averagePerActiveDay = computed(() => {
  const trend = overview.value?.daily_trend ?? []
  const active = trend.filter((point) => point.focus_minutes > 0)

  if (active.length === 0) {
    return 0
  }

  return Math.round(
    active.reduce((sum, point) => sum + point.focus_minutes, 0) / active.length,
  )
})
</script>

<template>
  <div class="space-y-7">
    <SectionHeader
      eyebrow="Insight"
      title="Analytics"
      subtitle="Everything here is read from the stats cache the server rebuilds nightly - never recomputed live."
    >
      <template #actions>
        <div
          class="inline-flex rounded-lg border border-line bg-surface-2 p-0.5"
          role="group"
          aria-label="Time window"
        >
          <button
            v-for="range in RANGES"
            :key="range.value"
            type="button"
            :aria-pressed="windowDays === range.value"
            :class="[
              'h-8 rounded-md px-2.5 text-[12.5px] transition-colors duration-150',
              windowDays === range.value
                ? 'bg-surface font-semibold text-ink'
                : 'font-medium text-ink-muted hover:text-ink',
            ]"
            @click="windowDays = range.value"
          >
            {{ range.label }}
          </button>
        </div>
      </template>
    </SectionHeader>

    <ErrorBanner :failure="analytics.failure" />

    <SkeletonBlock
      v-if="analytics.loading && !overview"
      :rows="4"
      height="h-24"
      rounded="rounded-xl"
    />

    <EmptyState
      v-else-if="!overview"
      icon="chart"
      title="No analytics yet"
      body="Log a focus sprint and this page fills in."
    >
      <BaseButton variant="primary" size="sm" to="/focus" icon="play">Start a sprint</BaseButton>
    </EmptyState>

    <template v-else>
      <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Total focus"
          :value="formatDuration(overview.totals.total_focus_seconds)"
          icon="timer"
          tone="brand"
          :hint="`${overview.totals.sessions_count} completed sprints`"
        />
        <StatCard
          label="Per active day"
          :value="`${averagePerActiveDay}m`"
          icon="trend"
          hint="Average across days you logged something"
        />
        <StatCard
          label="Streak"
          :value="`${overview.streak.current}d`"
          icon="flame"
          :tone="streak.atRiskToday.value ? 'ember' : 'brand'"
          :hint="streak.hint.value"
        />
        <StatCard
          label="Goals"
          :value="`${overview.totals.active_goals} / ${overview.totals.active_goals + overview.totals.completed_goals}`"
          icon="target"
          :hint="`${overview.totals.completed_goals} completed`"
        />
      </section>

      <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
        <section class="space-y-3 rounded-xl border border-line bg-surface p-4 sm:p-5">
          <SectionHeader
            eyebrow="Velocity"
            title="Focus minutes per day"
            :subtitle="
              busiestDay && busiestDay.focus_minutes > 0
                ? `Your best day in this window was ${formatDate(busiestDay.date)} at ${busiestDay.focus_minutes} minutes.`
                : undefined
            "
          />
          <VelocityChart :trend="overview.daily_trend" :height="250" />
        </section>

        <div class="space-y-5">
          <GamificationPanel :gamification="overview.gamification" />

          <section class="space-y-3 rounded-xl border border-line bg-surface p-4 sm:p-5">
            <SectionHeader eyebrow="Streak" title="Consistency" />

            <dl class="space-y-2.5">
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-[12.5px] text-ink-muted">Current</dt>
                <dd class="tnum text-[15px] font-semibold text-ink">
                  {{ overview.streak.current }} days
                </dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-[12.5px] text-ink-muted">Longest</dt>
                <dd class="tnum text-[15px] font-semibold text-ink">
                  {{ overview.streak.longest }} days
                </dd>
              </div>
              <div class="flex items-baseline justify-between gap-2">
                <dt class="text-[12.5px] text-ink-muted">Last active</dt>
                <dd class="tnum text-[13px] text-ink-muted">
                  {{
                    overview.streak.last_active_date
                      ? formatDate(overview.streak.last_active_date)
                      : 'Never'
                  }}
                </dd>
              </div>
            </dl>

            <p
              v-if="streak.atRiskToday.value"
              class="rounded-lg border border-ember/25 bg-ember-soft px-3 py-2 text-[11.5px] leading-relaxed text-ink"
            >
              Nothing logged today. One sprint keeps the streak alive.
            </p>
          </section>
        </div>
      </div>

      <section class="space-y-3">
        <SectionHeader
          eyebrow="Consistency"
          :title="`Your last ${weeks} weeks`"
          subtitle="Day boundaries follow your own timezone, so this is your midnight, not the server's."
        />

        <div class="rounded-xl border border-line bg-surface p-4 sm:p-5">
          <HeatmapCalendar :trend="overview.daily_trend" :weeks="weeks" />
        </div>
      </section>

      <section v-if="overview.by_category.length > 0" class="space-y-3">
        <SectionHeader eyebrow="Where it went" title="Focus by category" />

        <ul class="space-y-3 rounded-xl border border-line bg-surface p-4 sm:p-5">
          <li
            v-for="row in overview.by_category"
            :key="row.category?.id ?? 'uncategorised'"
            class="space-y-1.5"
          >
            <span class="flex items-baseline justify-between gap-2">
              <span class="truncate text-[13px] font-medium text-ink">
                {{ row.category?.name ?? 'Uncategorised' }}
              </span>
              <span class="tnum shrink-0 text-[12px] text-ink-muted">
                {{ formatDuration(row.focus_seconds) }}
              </span>
            </span>
            <span class="block h-1.5 overflow-hidden rounded-full bg-surface-3">
              <span
                class="block h-full rounded-full bg-brand transition-[width] duration-300"
                :style="{
                  width: `${
                    (row.focus_seconds /
                      Math.max(1, ...overview.by_category.map((entry) => entry.focus_seconds))) *
                    100
                  }%`,
                }"
              />
            </span>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
