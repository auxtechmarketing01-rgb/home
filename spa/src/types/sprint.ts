import type { Goal } from './goal'
import type { RoadmapItem } from './roadmap'

export type SprintMode = 'pomodoro' | 'countdown' | 'stopwatch'
export type SprintStatus = 'running' | 'paused' | 'completed' | 'cancelled'

export const SPRINT_MODES: SprintMode[] = ['pomodoro', 'countdown', 'stopwatch']

/**
 * Mirrors SprintResource. `deadline_at` / `is_overtime` / `overtime_seconds` are
 * server-derived conveniences -- there is no `overtime` status, because reaching
 * the plan never changes the row (FR-SPR-09).
 */
export interface Sprint {
  id: number
  goal_id: number | null
  roadmap_item_id: number | null
  mode: SprintMode
  planned_duration_seconds: number | null
  break_seconds: number | null
  started_at: string | null
  ended_at: string | null
  paused_at: string | null
  paused_seconds_total: number
  actual_duration_seconds: number | null
  status: SprintStatus
  notes: string | null
  notified_expired_at: string | null
  deadline_at: string | null
  is_overtime: boolean
  overtime_seconds: number
  focus_seconds_so_far: number
  goal?: Goal | null
  roadmap_item?: RoadmapItem | null
}

/** StartSprintRequest payload. A stopwatch is open-ended, so it sends no duration. */
export interface StartSprintPayload {
  mode: SprintMode
  goal_id?: number | null
  roadmap_item_id?: number | null
  planned_duration_seconds?: number | null
  break_seconds?: number
  notes?: string | null
}

/** IndexSprintRequest query. */
export interface SprintFilters {
  from?: string
  to?: string
  goal_id?: number
  roadmap_item_id?: number
  status?: SprintStatus
  per_page?: number
  page?: number
}
