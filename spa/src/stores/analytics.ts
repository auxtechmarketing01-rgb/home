import { defineStore } from 'pinia'
import { ref } from 'vue'
import { analyticsApi } from '@/api/analytics'
import { toApiFailure } from '@/api/client'
import type { AnalyticsOverview, GoalStatsResponse } from '@/types/analytics'
import type { ApiFailure } from '@/types/api'

export const useAnalyticsStore = defineStore('analytics', () => {
  const overview = ref<AnalyticsOverview | null>(null)
  const goalStats = ref<Record<number, GoalStatsResponse>>({})

  const loading = ref(false)
  const goalLoading = ref(false)
  const failure = ref<ApiFailure | null>(null)

  async function fetchOverview(trendDays = 28): Promise<void> {
    loading.value = true
    failure.value = null

    try {
      overview.value = await analyticsApi.overview(trendDays)
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load your analytics.')
    } finally {
      loading.value = false
    }
  }

  /**
   * A null `data` means the stats cache has not been built for this goal yet --
   * normal for a goal with no logged time. Stored as-is so the view can say
   * "not enough data yet" rather than guessing at a zero.
   */
  async function fetchGoalStats(goalId: number, trendDays = 84): Promise<void> {
    goalLoading.value = true
    failure.value = null

    try {
      goalStats.value = {
        ...goalStats.value,
        [goalId]: await analyticsApi.goalStats(goalId, trendDays),
      }
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load stats for that goal.')
    } finally {
      goalLoading.value = false
    }
  }

  function statsFor(goalId: number): GoalStatsResponse | null {
    return goalStats.value[goalId] ?? null
  }

  return { overview, goalStats, loading, goalLoading, failure, fetchOverview, fetchGoalStats, statsFor }
})
