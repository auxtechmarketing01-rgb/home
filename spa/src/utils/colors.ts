import type { GoalStatus } from '@/types/goal'
import type { MentorshipStatus } from '@/types/mentorship'
import type { RoadmapItemStatus } from '@/types/roadmap'
import type { RewardStatus } from '@/types/reward'
import type { SprintStatus } from '@/types/sprint'

/**
 * Every status-to-colour decision in the product resolves here, as Tailwind
 * class strings built on the theme tokens. RoadmapItemNode, the Kanban columns
 * and HeatmapCalendar all read the same map, which is what stops the four views
 * of "in progress" from drifting apart.
 */
export interface StatusStyle {
  /** Chip: tinted fill plus matching text. */
  chip: string
  /** Bare colour for a dot, rail segment or chart series. */
  dot: string
  text: string
  label: string
}

export const ROADMAP_ITEM_STATUS_STYLES: Record<RoadmapItemStatus, StatusStyle> = {
  todo: {
    chip: 'bg-surface-2 text-ink-muted border-line',
    dot: 'bg-status-todo',
    text: 'text-status-todo',
    label: 'To do',
  },
  in_progress: {
    chip: 'bg-ember-soft text-ember border-ember/25',
    dot: 'bg-status-progress',
    text: 'text-status-progress',
    label: 'In progress',
  },
  done: {
    chip: 'bg-brand-soft text-brand border-brand/25',
    dot: 'bg-status-done',
    text: 'text-status-done',
    label: 'Done',
  },
  skipped: {
    chip: 'bg-surface-2 text-ink-faint border-line',
    dot: 'bg-status-skipped',
    text: 'text-status-skipped',
    label: 'Skipped',
  },
}

export const GOAL_STATUS_STYLES: Record<GoalStatus, StatusStyle> = {
  draft: {
    chip: 'bg-surface-2 text-ink-muted border-line',
    dot: 'bg-status-todo',
    text: 'text-ink-muted',
    label: 'Draft',
  },
  active: {
    chip: 'bg-brand-soft text-brand border-brand/25',
    dot: 'bg-brand',
    text: 'text-brand',
    label: 'Active',
  },
  paused: {
    chip: 'bg-ember-soft text-ember border-ember/25',
    dot: 'bg-ember',
    text: 'text-ember',
    label: 'Paused',
  },
  completed: {
    chip: 'bg-ok/12 text-ok border-ok/25',
    dot: 'bg-ok',
    text: 'text-ok',
    label: 'Completed',
  },
  abandoned: {
    chip: 'bg-surface-2 text-ink-faint border-line',
    dot: 'bg-status-skipped',
    text: 'text-ink-faint',
    label: 'Abandoned',
  },
}

export const SPRINT_STATUS_STYLES: Record<SprintStatus, StatusStyle> = {
  running: {
    chip: 'bg-ember-soft text-ember border-ember/25',
    dot: 'bg-ember',
    text: 'text-ember',
    label: 'Running',
  },
  paused: {
    chip: 'bg-warn/12 text-warn border-warn/25',
    dot: 'bg-warn',
    text: 'text-warn',
    label: 'Paused',
  },
  completed: {
    chip: 'bg-brand-soft text-brand border-brand/25',
    dot: 'bg-brand',
    text: 'text-brand',
    label: 'Completed',
  },
  cancelled: {
    chip: 'bg-surface-2 text-ink-faint border-line',
    dot: 'bg-status-skipped',
    text: 'text-ink-faint',
    label: 'Cancelled',
  },
}

export const MENTORSHIP_STATUS_STYLES: Record<MentorshipStatus, StatusStyle> = {
  pending: {
    chip: 'bg-warn/12 text-warn border-warn/25',
    dot: 'bg-warn',
    text: 'text-warn',
    label: 'Pending',
  },
  accepted: {
    chip: 'bg-brand-soft text-brand border-brand/25',
    dot: 'bg-brand',
    text: 'text-brand',
    label: 'Active',
  },
  declined: {
    chip: 'bg-danger-soft text-danger border-danger/25',
    dot: 'bg-danger',
    text: 'text-danger',
    label: 'Declined',
  },
  ended: {
    chip: 'bg-surface-2 text-ink-faint border-line',
    dot: 'bg-status-skipped',
    text: 'text-ink-faint',
    label: 'Ended',
  },
}

/**
 * All seven reward states get a visually distinct chip. The point of the state
 * machine is that these mean different things, so a generic "reward" chip would
 * throw away the only signal the member has.
 */
export const REWARD_STATUS_STYLES: Record<RewardStatus, StatusStyle> = {
  requested: {
    chip: 'bg-violet/12 text-violet border-violet/25',
    dot: 'bg-violet',
    text: 'text-violet',
    label: 'Requested',
  },
  offered: {
    chip: 'bg-info/12 text-info border-info/25',
    dot: 'bg-info',
    text: 'text-info',
    label: 'Offered',
  },
  earned: {
    chip: 'bg-brand-soft text-brand border-brand/30',
    dot: 'bg-brand',
    text: 'text-brand',
    label: 'Earned',
  },
  claimed: {
    chip: 'bg-ember-soft text-ember border-ember/30',
    dot: 'bg-ember',
    text: 'text-ember',
    label: 'Claimed',
  },
  fulfilled: {
    chip: 'bg-ok/14 text-ok border-ok/30',
    dot: 'bg-ok',
    text: 'text-ok',
    label: 'Fulfilled',
  },
  denied: {
    chip: 'bg-danger-soft text-danger border-danger/25',
    dot: 'bg-danger',
    text: 'text-danger',
    label: 'Denied',
  },
  revoked: {
    chip: 'bg-surface-2 text-ink-faint border-line',
    dot: 'bg-status-skipped',
    text: 'text-ink-faint',
    label: 'Revoked',
  },
}

/** Deterministic series colours for the group comparison chart (FR-ANL-04). */
export const SERIES_COLORS = [
  '#2dd4bf',
  '#fb923c',
  '#a78bfa',
  '#60a5fa',
  '#f472b6',
  '#facc15',
  '#4ade80',
  '#f87171',
]

export function seriesColor(index: number): string {
  return SERIES_COLORS[index % SERIES_COLORS.length] as string
}

/**
 * Heatmap ramp. Five steps of one hue rather than a rainbow, so intensity reads
 * as one axis; the empty step keeps its own token so an inactive day is legible
 * as "nothing" and not as "very little".
 */
export function heatmapStep(minutes: number, max: number): string {
  if (minutes <= 0) {
    return 'bg-surface-2'
  }

  const ratio = max > 0 ? minutes / max : 0

  if (ratio > 0.75) {
    return 'bg-brand'
  }
  if (ratio > 0.5) {
    return 'bg-brand/75'
  }
  if (ratio > 0.25) {
    return 'bg-brand/50'
  }

  return 'bg-brand/25'
}
