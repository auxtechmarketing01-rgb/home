import { apiClient, toQuery } from './client'
import type {
  AnalyticsOverview,
  GoalStatsResponse,
  GroupTrendSeries,
  LeaderboardEntry,
  LeaderboardPeriod,
} from '@/types/analytics'

export const analyticsApi = {
  async overview(trendDays = 28): Promise<AnalyticsOverview> {
    const response = await apiClient.get<{ data: AnalyticsOverview }>('/analytics/overview', {
      params: { trend_days: trendDays },
    })

    return response.data.data
  },

  /**
   * `data` is null until the first RecalculateGoalStatsJob has run for the goal.
   * That is a normal state for a brand-new goal, not an error -- callers must
   * render "not enough data yet" rather than treating it as a failure.
   */
  async goalStats(goalId: number, trendDays = 84): Promise<GoalStatsResponse> {
    const response = await apiClient.get<GoalStatsResponse>(`/goals/${goalId}/stats`, {
      params: { trend_days: trendDays },
    })

    return response.data
  },

  async leaderboard(
    groupId: number,
    period: LeaderboardPeriod = 'week',
  ): Promise<LeaderboardEntry[]> {
    const response = await apiClient.get<{ data: LeaderboardEntry[] }>(
      `/groups/${groupId}/leaderboard`,
      { params: toQuery({ period }) },
    )

    return response.data.data
  },

  async groupTrend(groupId: number, trendDays = 28): Promise<GroupTrendSeries[]> {
    const response = await apiClient.get<{ data: GroupTrendSeries[] }>(
      `/groups/${groupId}/trend`,
      { params: { trend_days: trendDays } },
    )

    return response.data.data
  },
}
