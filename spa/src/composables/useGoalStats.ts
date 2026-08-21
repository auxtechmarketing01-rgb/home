import { computed, onMounted, watch, type Ref } from 'vue'
import { useAnalyticsStore } from '@/stores/analytics'

/**
 * Thin resolver over the analytics store for one goal, with the loading and
 * empty states a view actually needs. `hasStats` is deliberately separate from
 * `loading`: a goal with no logged time has finished loading *and* has nothing.
 */
export function useGoalStats(goalId: Ref<number | null>, trendDays = 84) {
  const analytics = useAnalyticsStore()

  const response = computed(() => (goalId.value ? analytics.statsFor(goalId.value) : null))
  const stats = computed(() => response.value?.data ?? null)
  const trend = computed(() => response.value?.daily_trend ?? [])
  const hasStats = computed(() => stats.value !== null)
  const loading = computed(() => analytics.goalLoading)

  async function load(force = false): Promise<void> {
    const id = goalId.value

    if (!id || (!force && response.value)) {
      return
    }

    await analytics.fetchGoalStats(id, trendDays)
  }

  onMounted(load)
  watch(goalId, () => void load())

  return { response, stats, trend, hasStats, loading, load }
}
