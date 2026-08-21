<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ProgressBar from '@/components/ui/ProgressBar.vue'
import type { GamificationSummary } from '@/types/analytics'

const props = defineProps<{ gamification: GamificationSummary | null | undefined }>()

/**
 * Rendered only when the member opted in. The backend answers
 * `{ enabled: false }` rather than zeros for exactly this reason -- a zeroed XP
 * bar would push a game on someone who switched it off.
 */
const enabled = computed(() => props.gamification?.enabled === true)

const detail = computed(() =>
  props.gamification?.enabled === true ? props.gamification : null,
)

/**
 * Level thresholds are the server's business; this only needs a sensible bar, so
 * it shows progress through the current 100-XP band rather than inventing a
 * curve the backend never agreed to.
 */
const bandProgress = computed(() => {
  const xp = detail.value?.xp ?? 0

  return xp % 100
})
</script>

<template>
  <section v-if="enabled && detail" class="rounded-xl border border-line bg-surface p-5">
    <div class="flex items-start justify-between gap-3">
      <div>
        <p class="text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">Progress</p>
        <p class="mt-1 font-display text-[18px] font-semibold text-ink">
          Level <span class="tnum">{{ detail.level }}</span>
        </p>
      </div>

      <span
        class="inline-flex items-center gap-1.5 rounded-lg border border-brand/25 bg-brand-soft px-2.5 py-1.5 text-brand"
      >
        <AppIcon name="zap" :size="14" />
        <span class="tnum text-[13px] font-semibold">{{ detail.xp }}</span>
        <span class="text-[11px]">XP</span>
      </span>
    </div>

    <div class="mt-3.5 space-y-1.5">
      <ProgressBar :value="bandProgress" tone="brand" label="Progress toward the next level" />
      <p class="tnum text-[11px] text-ink-faint">{{ 100 - bandProgress }} XP to the next level</p>
    </div>

    <div v-if="detail.badges.length > 0" class="mt-4 border-t border-line pt-3.5">
      <p class="mb-2 text-[11px] font-medium uppercase tracking-[0.12em] text-ink-faint">
        Badges
      </p>
      <ul class="flex flex-wrap gap-1.5">
        <li
          v-for="badge in detail.badges"
          :key="badge.key"
          class="inline-flex items-center gap-1.5 rounded-md border border-line bg-surface-2 px-2 py-1 text-[11.5px] text-ink-muted"
        >
          <AppIcon name="award" :size="12" class="text-brand" />
          {{ badge.name }}
        </li>
      </ul>
    </div>

    <p v-else class="mt-4 border-t border-line pt-3.5 text-[11.5px] leading-relaxed text-ink-faint">
      No badges yet. They arrive on streaks, completed goals and logged focus.
    </p>
  </section>
</template>
