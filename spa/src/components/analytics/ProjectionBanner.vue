<script setup lang="ts">
import { computed } from 'vue'
import { differenceInCalendarDays } from 'date-fns'
import AppIcon from '@/components/ui/AppIcon.vue'
import { formatDate, toDate } from '@/utils/date'

const props = defineProps<{
  projectedCompletionDate: string | null
  targetEndDate?: string | null
  loading?: boolean
}>()

/**
 * `null` is an explicit, expected state -- a goal with too little history to
 * project from. It gets its own copy rather than being hidden or filled with a
 * made-up date, because a wrong forecast is worse than an honest blank.
 */
const hasProjection = computed(() => props.projectedCompletionDate !== null)

const daysOut = computed(() => {
  const projected = toDate(props.projectedCompletionDate)

  return projected ? differenceInCalendarDays(projected, new Date()) : null
})

/** Compared against the member's own target, when they set one. */
const slip = computed(() => {
  const projected = toDate(props.projectedCompletionDate)
  const target = toDate(props.targetEndDate ?? null)

  if (!projected || !target) {
    return null
  }

  return differenceInCalendarDays(projected, target)
})

const tone = computed(() => {
  if (!hasProjection.value) {
    return 'neutral'
  }

  if (slip.value === null) {
    return 'brand'
  }

  return slip.value > 0 ? 'warn' : 'ok'
})

const TONE_CLASSES = {
  neutral: 'border-line bg-surface-2',
  brand: 'border-brand/25 bg-brand-soft',
  ok: 'border-ok/25 bg-ok/10',
  warn: 'border-ember/30 bg-ember-soft',
}

const TONE_ICON_CLASSES = {
  neutral: 'text-ink-faint',
  brand: 'text-brand',
  ok: 'text-ok',
  warn: 'text-ember',
}
</script>

<template>
  <div
    class="flex items-start gap-3 rounded-xl border px-4 py-3.5"
    :class="TONE_CLASSES[tone]"
    role="status"
  >
    <span class="mt-0.5 shrink-0" :class="TONE_ICON_CLASSES[tone]" aria-hidden="true">
      <AppIcon :name="hasProjection ? 'trend' : 'hourglass'" :size="17" />
    </span>

    <div class="min-w-0 flex-1">
      <template v-if="loading">
        <p class="text-[13.5px] font-semibold text-ink">Working out a projection...</p>
      </template>

      <template v-else-if="!hasProjection">
        <p class="text-[13.5px] font-semibold text-ink">Not enough data yet</p>
        <p class="mt-0.5 text-[12px] leading-relaxed text-ink-muted">
          A few more logged sprints and completed steps and this will start forecasting a finish
          date. Nothing is wrong.
        </p>
      </template>

      <template v-else>
        <p class="text-[13.5px] font-semibold text-ink">
          On this pace, done around
          <span class="tnum">{{ formatDate(projectedCompletionDate) }}</span>
        </p>
        <p class="mt-0.5 text-[12px] leading-relaxed text-ink-muted">
          <span v-if="daysOut !== null" class="tnum">
            {{ daysOut <= 0 ? 'Any day now' : `About ${daysOut} days out` }}.
          </span>
          <template v-if="slip !== null">
            <span v-if="slip > 0" class="text-ember">
              {{ slip }} day{{ slip === 1 ? '' : 's' }} past your target of
              {{ formatDate(targetEndDate) }}.
            </span>
            <span v-else class="text-ok">
              {{ Math.abs(slip) }} day{{ Math.abs(slip) === 1 ? '' : 's' }} ahead of your target.
            </span>
          </template>
        </p>
      </template>
    </div>
  </div>
</template>
