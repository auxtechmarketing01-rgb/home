import type { GoalStats } from './goal'

/** One cell of a heatmap / one point of a velocity line. */
export interface TrendPoint {
  date: string
  focus_minutes: number
}

export interface OverviewTotals {
  total_focus_seconds: number
  sessions_count: number
  active_goals: number
  completed_goals: number
}

export interface OverviewStreak {
  current: number
  longest: number
  last_active_date: string | null
}

export interface CategoryFocus {
  category: { id: number; name: string } | null
  focus_seconds: number
}

export interface Badge {
  key: string
  name: string
}

/**
 * `enabled: false` is the whole payload when the member opted out -- the UI must
 * branch on it rather than render a zeroed XP bar (FR-GAM opt-in).
 */
export type GamificationSummary =
  | { enabled: false }
  | { enabled: true; xp: number; level: number; badges: Badge[] }

/** GET /analytics/overview `data`. */
export interface AnalyticsOverview {
  totals: OverviewTotals
  streak: OverviewStreak
  by_category: CategoryFocus[]
  daily_trend: TrendPoint[]
  gamification: GamificationSummary
}

/** GET /goals/{goal}/stats -- `data` is null before the first recalculation. */
export interface GoalStatsResponse {
  data: GoalStats | null
  daily_trend: TrendPoint[]
}

export type LeaderboardPeriod = 'week' | 'month' | 'all'

/** Mirrors LeaderboardEntryResource (FR-GRP-03). */
export interface LeaderboardEntry {
  user: { id: number; name: string }
  focus_minutes: number
  current_streak: number
  goals_completed: number
}

/** GET /groups/{group}/trend `data` (FR-ANL-04). */
export interface GroupTrendSeries {
  user: { id: number; name: string }
  series: TrendPoint[]
}
