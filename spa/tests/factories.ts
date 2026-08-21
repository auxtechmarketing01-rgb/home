import type { Goal } from '@/types/goal'
import type { LeaderboardEntry } from '@/types/analytics'
import type { Reward, RewardAction, RewardStatus } from '@/types/reward'
import type { RoadmapItem } from '@/types/roadmap'
import type { Sprint } from '@/types/sprint'

export function makeSprint(overrides: Partial<Sprint> = {}): Sprint {
  return {
    id: 1,
    goal_id: 10,
    roadmap_item_id: null,
    mode: 'pomodoro',
    planned_duration_seconds: 1500,
    break_seconds: 300,
    started_at: new Date().toISOString(),
    ended_at: null,
    paused_at: null,
    paused_seconds_total: 0,
    actual_duration_seconds: null,
    status: 'running',
    notes: null,
    notified_expired_at: null,
    deadline_at: null,
    is_overtime: false,
    overtime_seconds: 0,
    focus_seconds_so_far: 0,
    ...overrides,
  }
}

export function makeRoadmapItem(overrides: Partial<RoadmapItem> = {}): RoadmapItem {
  return {
    id: 1,
    roadmap_id: 5,
    parent_id: null,
    title: 'Draft the hero section',
    description: null,
    day_number: null,
    scheduled_date: null,
    estimated_minutes: null,
    time_spent_seconds: 0,
    status: 'todo',
    position: 0,
    reflection_note: null,
    assigned_minutes: null,
    assigned_due_at: null,
    ...overrides,
  }
}

export function makeGoal(overrides: Partial<Goal> = {}): Goal {
  return {
    id: 10,
    title: 'Ship the portfolio',
    description: null,
    status: 'active',
    visibility: 'private',
    target_start_date: null,
    target_end_date: null,
    completed_at: null,
    category: null,
    group_id: null,
    ...overrides,
  }
}

export function makeReward(
  status: RewardStatus,
  viewerRole: 'mentor' | 'mentee',
  availableActions: RewardAction[],
  overrides: Partial<Reward> = {},
): Reward {
  return {
    id: 1,
    mentorship_id: 3,
    goal_id: null,
    roadmap_item_id: null,
    title: 'Dinner out',
    description: null,
    type: 'custom',
    monetary_amount: null,
    currency_label: null,
    status,
    requested_by: 'mentor',
    claimed_at: null,
    fulfilled_at: null,
    fulfilled_note: null,
    viewer_role: viewerRole,
    available_actions: availableActions,
    ...overrides,
  }
}

export function makeLeaderboardEntry(
  name: string,
  focusMinutes: number,
  streak: number,
  goalsCompleted: number,
  id = 1,
): LeaderboardEntry {
  return {
    user: { id, name },
    focus_minutes: focusMinutes,
    current_streak: streak,
    goals_completed: goalsCompleted,
  }
}
