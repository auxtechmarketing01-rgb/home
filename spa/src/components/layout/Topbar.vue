<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import NotificationBell from './NotificationBell.vue'
import ThemeToggle from './ThemeToggle.vue'
import { useStreak } from '@/composables/useStreak'
import { useAnalyticsStore } from '@/stores/analytics'
import { useNotificationsStore } from '@/stores/notifications'

const emit = defineEmits<{ openDrawer: [] }>()

const route = useRoute()
const analytics = useAnalyticsStore()
const notifications = useNotificationsStore()

const title = computed(() => route.meta.title ?? 'Pathforge')

const streakSource = computed(() => analytics.overview?.streak ?? null)
const streak = useStreak(streakSource)

/**
 * A dropped socket costs freshness, not data -- so this is a hairline dot with a
 * tooltip, never a banner. A member on a flaky train connection should not be
 * told the app is broken.
 */
const socketTitle = computed(() =>
  notifications.socketState === 'connected'
    ? 'Live updates connected'
    : notifications.socketState === 'reconnecting'
      ? 'Reconnecting to live updates - your data is still current'
      : 'Live updates idle',
)
</script>

<template>
  <header
    class="sticky top-0 z-20 border-b border-line bg-canvas/85 backdrop-blur-md"
  >
    <div class="mx-auto flex h-14 w-full max-w-[84rem] items-center gap-2 px-4 sm:px-6 lg:px-8">
      <button
        type="button"
        class="-ml-1.5 grid size-9 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-surface-2 hover:text-ink lg:hidden"
        aria-label="Open navigation"
        @click="emit('openDrawer')"
      >
        <AppIcon name="list" :size="19" />
      </button>

      <h1 class="min-w-0 flex-1 truncate font-display text-[15px] font-semibold text-ink">
        {{ title }}
      </h1>

      <!-- Streak lives in the chrome because it is the one number that is true on every screen. -->
      <span
        v-if="streak.current.value > 0"
        class="hidden items-center gap-1.5 rounded-lg border border-line bg-surface-2 px-2.5 py-1.5 sm:inline-flex"
        :title="streak.hint.value"
      >
        <AppIcon
          name="flame"
          :size="14"
          :class="streak.atRiskToday.value ? 'text-ink-faint' : 'text-ember'"
        />
        <span class="tnum text-[12.5px] font-semibold text-ink">{{ streak.current.value }}</span>
        <span class="text-[11px] text-ink-faint">day</span>
      </span>

      <span
        class="hidden size-1.5 rounded-full sm:block"
        :class="{
          'bg-brand': notifications.socketState === 'connected',
          'bg-warn pf-pulse-ember': notifications.socketState === 'reconnecting',
          'bg-line-strong': notifications.socketState === 'idle' || notifications.socketState === 'connecting',
        }"
        :title="socketTitle"
        role="status"
        :aria-label="socketTitle"
      />

      <NotificationBell />
      <ThemeToggle class="hidden sm:flex" />
    </div>
  </header>
</template>
