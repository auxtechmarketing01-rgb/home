import type { Group } from './group'
import type { Roadmap } from './roadmap'
import type { UserSummary } from './user'

export type GoalStatus = 'draft' | 'active' | 'paused' | 'completed' | 'abandoned'
export type GoalVisibility = 'private' | 'group'

export const GOAL_STATUSES: GoalStatus[] = ['draft', 'active', 'paused', 'completed', 'abandoned']

/** Mirrors CategoryResource. */
export interface Category {
  id: number
  name: string
  icon: string | null
}

/**
 * Mirrors GoalStatsResource -- the `goal_stats` cache row, never recomputed per
 * request. Absent until the first RecalculateGoalStatsJob run, which is why the
 * whole object is nullable on Goal.
 */
export interface GoalStats {
  total_focus_seconds: number
  sessions_count: number
  completion_percentage: number
  current_streak: number
  longest_streak: number
  projected_completion_date: string | null
  last_recalculated_at: string | null
}

/** Mirrors GoalResource. ISO date strings, parsed at the edge -- never Date here. */
export interface Goal {
  id: number
  title: string
  description: string | null
  status: GoalStatus
  visibility: GoalVisibility
  target_start_date: string | null
  target_end_date: string | null
  completed_at: string | null
  category: Category | null
  group_id: number | null
  group?: Group | null
  stats?: GoalStats | null
  roadmap?: Roadmap | null
  roadmap_item_count?: number | null
  user?: UserSummary
}

/** StoreGoalRequest / UpdateGoalRequest payload. */
export interface GoalPayload {
  title: string
  description?: string | null
  category_id?: number | null
  status?: GoalStatus
  visibility?: GoalVisibility
  group_id?: number | null
  target_start_date?: string | null
  target_end_date?: string | null
}

/** IndexGoalRequest query. */
export interface GoalFilters {
  status?: GoalStatus
  visibility?: GoalVisibility
  category_id?: number
  search?: string
  per_page?: number
  page?: number
}
