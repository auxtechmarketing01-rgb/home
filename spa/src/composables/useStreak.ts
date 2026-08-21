import { computed, type Ref } from 'vue'
import { differenceInCalendarDays } from 'date-fns'
import { toDate } from '@/utils/date'

export interface StreakInput {
  current: number
  longest: number
  last_active_date: string | null
}

/**
 * Formats streak state for the several places that show it, so the date maths
 * lives once. The server stays authoritative for *computing* a streak -- this
 * only decides how today looks against the last active day.
 */
export function useStreak(source: Ref<StreakInput | null | undefined>) {
  const current = computed(() => source.value?.current ?? 0)
  const longest = computed(() => source.value?.longest ?? 0)

  const daysSinceActive = computed<number | null>(() => {
    const last = toDate(source.value?.last_active_date ?? null)

    return last ? differenceInCalendarDays(new Date(), last) : null
  })

  const loggedToday = computed(() => daysSinceActive.value === 0)

  /**
   * A live streak with nothing logged today is the one state worth nudging: one
   * more idle day and it resets. Broken is a fact, at-risk is a prompt.
   */
  const atRiskToday = computed(() => current.value > 0 && daysSinceActive.value === 1)

  const isBroken = computed(() => (daysSinceActive.value ?? 0) > 1)

  const label = computed(() => {
    if (current.value === 0) {
      return 'No streak yet'
    }

    return `${current.value} day${current.value === 1 ? '' : 's'}`
  })

  const hint = computed(() => {
    if (loggedToday.value) {
      return 'Logged today'
    }

    if (atRiskToday.value) {
      return 'At risk - nothing logged today'
    }

    if (isBroken.value) {
      return 'Streak reset'
    }

    return 'Start one with a focus sprint'
  })

  return { current, longest, daysSinceActive, loggedToday, atRiskToday, isBroken, label, hint }
}
