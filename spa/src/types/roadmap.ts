import type { UserSummary } from './user'

export type RoadmapItemStatus = 'todo' | 'in_progress' | 'done' | 'skipped'

export const ROADMAP_ITEM_STATUSES: RoadmapItemStatus[] = [
  'todo',
  'in_progress',
  'done',
  'skipped',
]

/** Mirrors RoadmapResource. */
export interface Roadmap {
  id: number
  goal_id: number
  title: string | null
  items?: RoadmapItem[]
  items_count?: number | null
}

/** Mirrors RoadmapItemResource. */
export interface RoadmapItem {
  id: number
  roadmap_id: number
  parent_id: number | null
  title: string
  description: string | null
  day_number: number | null
  scheduled_date: string | null
  /** The member's own estimate. */
  estimated_minutes: number | null
  time_spent_seconds: number
  status: RoadmapItemStatus
  position: number
  reflection_note: string | null
  /** FR-MENT-05. A mentor expectation -- never conflate with estimated_minutes. */
  assigned_by_mentor?: UserSummary | null
  assigned_minutes: number | null
  assigned_due_at: string | null
  children?: RoadmapItem[]
}

export interface RoadmapItemPayload {
  title: string
  description?: string | null
  parent_id?: number | null
  day_number?: number | null
  scheduled_date?: string | null
  estimated_minutes?: number | null
  status?: RoadmapItemStatus
  position?: number
}

export interface RoadmapItemUpdatePayload extends Partial<RoadmapItemPayload> {
  reflection_note?: string | null
}

/** ReorderRoadmapItemsRequest payload -- the normalised diff useDragReorder emits. */
export interface ReorderEntry {
  id: number
  position: number
}

/** AssignRoadmapItemRequest payload (FR-MENT-05). */
export interface AssignmentPayload {
  assigned_minutes?: number | null
  assigned_due_at?: string | null
}
