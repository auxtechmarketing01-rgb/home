<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import GamificationPanel from '@/components/analytics/GamificationPanel.vue'
import HeatmapCalendar from '@/components/analytics/HeatmapCalendar.vue'
import StatCard from '@/components/analytics/StatCard.vue'
import FocusTimerWidget from '@/components/focus/FocusTimerWidget.vue'
import GoalCard from '@/components/goals/GoalCard.vue'
import { authApi } from '@/api/auth'
import { useStreak } from '@/composables/useStreak'
import { useAnalyticsStore } from '@/stores/analytics'
import { useAuthStore } from '@/stores/auth'
import { useGoalsStore } from '@/stores/goals'
import { useMentorshipsStore } from '@/stores/mentorships'
import { useRewardsStore } from '@/stores/rewards'
import { useSprintsStore } from '@/stores/sprints'
import { useToastsStore } from '@/stores/toasts'
import { formatDuration } from '@/utils/formatDuration'

const auth = useAuthStore()
const analytics = useAnalyticsStore()
const goals = useGoalsStore()
const sprints = useSprintsStore()
const mentorships = useMentorshipsStore()
const rewards = useRewardsStore()
const toasts = useToastsStore()

const resending = ref(false)

onMounted(() => {
  void analytics.fetchOverview(84)
  void goals.fetchAll({ status: 'active', per_page: 6 })
})

const overview = computed(() => analytics.overview)
const streak = useStreak(computed(() => overview.value?.streak ?? null))

const greeting = computed(() => {
  const hour = new Date().getHours()
  const name = auth.user?.name?.split(' ')[0] ?? 'there'

  if (hour < 5) {
    return `Still up, ${name}?`
  }

  if (hour < 12) {
    return `Morning, ${name}`
  }

  if (hour < 18) {
    return `Afternoon, ${name}`
  }

  return `Evening, ${name}`
})

/** Everything waiting on the member, from both Phase 4 stores. */
const attention = computed(() => [
  ...mentorships.pendingForMe.map((entry) => ({
    key: `mentorship-${entry.id}`,
    icon: 'handshake' as const,
    label:
      entry.viewer_role === 'mentee'
        ? `${entry.mentor?.name ?? 'Someone'} offered to mentor you`
        : `${entry.mentee?.name ?? 'Someone'} asked you to mentor them`,
    to: '/mentorships',
    cta: 'Respond',
  })),
  ...rewards.earned
    .filter((reward) => reward.available_actions.includes('claim'))
    .map((reward) => ({
      key: `reward-${reward.id}`,
      icon: 'gift' as const,
      label: `"${reward.title}" is earned and waiting to be claimed`,
      to: '/rewards',
      cta: 'Claim',
    })),
  ...rewards.items
    .filter((reward) => reward.available_actions.includes('respond'))
    .map((reward) => ({
      key: `respond-${reward.id}`,
      icon: 'gift' as const,
      label: `A reward request needs your answer: "${reward.title}"`,
      to: '/rewards',
      cta: 'Review',
    })),
  ...rewards.items
    .filter((reward) => reward.available_actions.includes('fulfill'))
    .map((reward) => ({
      key: `fulfill-${reward.id}`,
      icon: 'wallet' as const,
      label: `"${reward.title}" was claimed - mark it fulfilled when done`,
      to: '/rewards',
      cta: 'Fulfil',
    })),
])

async function resendVerification(): Promise<void> {
  resending.value = true

  try {
    const message = await authApi.resendVerification()
    toasts.success('Verification sent', message)
  } catch {
    toasts.error('Could not resend', 'Try again in a moment.')
  } finally {
    resending.value = false
  }
}
</script>

<template>
  <div class="space-y-8">
    <!-- Unverified accounts get one persistent, dismissible-by-action reminder. -->
    <section
      v-if="auth.needsVerification"
      class="flex flex-wrap items-center gap-3 rounded-xl border border-warn/30 bg-warn/10 px-4 py-3.5"
      role="status"
    >
      <AppIcon name="mail" :size="17" class="shrink-0 text-warn" />
      <p class="min-w-0 flex-1 text-[13px] leading-relaxed text-ink">
        <span class="font-semibold">Confirm your email address.</span>
        Some notifications are held back until you do.
      </p>
      <BaseButton variant="subtle" size="sm" :loading="resending" @click="resendVerification">
        Resend link
      </BaseButton>
    </section>

    <header class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-ink-faint">Today</p>
        <h2 class="mt-1 font-display text-[24px] font-semibold tracking-[-0.02em] text-ink">
          {{ greeting }}
        </h2>
        <p class="mt-1 text-[13px] text-ink-muted">{{ streak.hint.value }}</p>
      </div>

      <BaseButton v-if="!sprints.hasActiveSprint" variant="primary" size="md" icon="play" to="/focus">
        Start a focus sprint
      </BaseButton>
    </header>

    <!--
      When a sprint is running it *is* the dashboard. The persistent bar still
      handles every other route; here the full widget gets the room.
    -->
    <section v-if="sprints.activeSprint">
      <FocusTimerWidget :sprint="sprints.activeSprint" />
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <template v-if="analytics.loading && !overview">
        <SkeletonBlock v-for="n in 4" :key="n" height="h-[104px]" rounded="rounded-xl" />
      </template>

      <template v-else-if="overview">
        <StatCard
          label="Focus logged"
          :value="formatDuration(overview.totals.total_focus_seconds)"
          icon="timer"
          tone="brand"
          :hint="`${overview.totals.sessions_count} sprints all time`"
        />
        <StatCard
          label="Current streak"
          :value="`${overview.streak.current}d`"
          icon="flame"
          :tone="streak.atRiskToday.value ? 'ember' : 'brand'"
          :hint="`Longest ${overview.streak.longest} days`"
        />
        <StatCard
          label="Active goals"
          :value="overview.totals.active_goals"
          icon="target"
          :hint="`${overview.totals.completed_goals} completed`"
        />
        <StatCard
          label="Needs you"
          :value="attention.length"
          icon="bell"
          :tone="attention.length > 0 ? 'ember' : 'default'"
          hint="Mentor requests and rewards"
        />
      </template>
    </section>

    <section v-if="attention.length > 0" class="space-y-3">
      <SectionHeader
        eyebrow="Waiting on you"
        title="A few things need an answer"
        subtitle="Requests and rewards sit here until you act on them."
      />

      <ul class="divide-y divide-line overflow-hidden rounded-xl border border-line">
        <li
          v-for="entry in attention"
          :key="entry.key"
          class="flex flex-wrap items-center gap-3 bg-surface p-3.5"
        >
          <span
            class="grid size-8 shrink-0 place-items-center rounded-lg border border-ember/25 bg-ember-soft text-ember"
            aria-hidden="true"
          >
            <AppIcon :name="entry.icon" :size="15" />
          </span>
          <p class="min-w-0 flex-1 text-[13px] text-ink">{{ entry.label }}</p>
          <BaseButton variant="subtle" size="sm" :to="entry.to" trailing-icon="arrowRight">
            {{ entry.cta }}
          </BaseButton>
        </li>
      </ul>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1.6fr_1fr]">
      <section class="space-y-3">
        <SectionHeader
          eyebrow="Consistency"
          title="Your last twelve weeks"
          subtitle="One square per day, shaded by focus minutes. Today is outlined."
        />

        <div class="rounded-xl border border-line bg-surface p-4">
          <SkeletonBlock v-if="analytics.loading && !overview" :rows="3" height="h-14" />
          <HeatmapCalendar v-else-if="overview" :trend="overview.daily_trend" :weeks="12" />
        </div>
      </section>

      <div class="space-y-6">
        <GamificationPanel :gamification="overview?.gamification" />

        <section v-if="overview && overview.by_category.length > 0" class="space-y-3">
          <SectionHeader eyebrow="Where it went" title="Focus by category" />

          <ul class="space-y-2.5 rounded-xl border border-line bg-surface p-4">
            <li
              v-for="row in overview.by_category"
              :key="row.category?.id ?? 'uncategorised'"
              class="space-y-1.5"
            >
              <span class="flex items-baseline justify-between gap-2">
                <span class="truncate text-[12.5px] font-medium text-ink">
                  {{ row.category?.name ?? 'Uncategorised' }}
                </span>
                <span class="tnum shrink-0 text-[11.5px] text-ink-muted">
                  {{ formatDuration(row.focus_seconds) }}
                </span>
              </span>
              <span class="block h-[3px] overflow-hidden rounded-full bg-surface-3">
                <span
                  class="block h-full rounded-full bg-brand"
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
      </div>
    </div>

    <section class="space-y-3">
      <SectionHeader eyebrow="In flight" title="Active goals">
        <template #actions>
          <BaseButton variant="ghost" size="sm" to="/goals" trailing-icon="arrowRight">
            All goals
          </BaseButton>
        </template>
      </SectionHeader>

      <SkeletonBlock v-if="goals.loading && goals.list.length === 0" :rows="2" height="h-40" rounded="rounded-xl" />

      <EmptyState
        v-else-if="goals.activeGoals.length === 0"
        icon="target"
        title="Nothing active right now"
        body="Create a goal, break it into a roadmap, then log focus time against the steps."
      >
        <BaseButton variant="primary" size="sm" to="/goals" icon="plus">Create a goal</BaseButton>
      </EmptyState>

      <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <GoalCard v-for="goal in goals.activeGoals" :key="goal.id" :goal="goal" />
      </div>
    </section>
  </div>
</template>
