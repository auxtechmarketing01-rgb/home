import type { UserSummary } from './user'

export type MentorshipStatus = 'pending' | 'accepted' | 'declined' | 'ended'
export type MentorshipRole = 'mentor' | 'mentee'

/** Mirrors MentorshipResource, including the viewer convenience flags. */
export interface Mentorship {
  id: number
  mentor?: UserSummary
  mentee?: UserSummary
  status: MentorshipStatus
  requested_by_user_id: number
  responded_at: string | null
  created_at: string | null
  /** Which side the acting member is on -- null if neither. */
  viewer_role: MentorshipRole | null
  /** Pending, and the acting member is not the requester. */
  viewer_can_respond: boolean
}

/**
 * RequestMentorshipRequest payload. `role` is the role the *target* takes:
 * asking someone to mentor you sends role `mentor`.
 */
export interface MentorshipRequestPayload {
  user_id: number
  role: MentorshipRole
}

export interface MentorshipFilters {
  status?: MentorshipStatus
  role?: MentorshipRole
}

export interface MentorDashboardGoal {
  id: number
  title: string
  status: string
  roadmap_item_count: number | null
  completion_percentage: number
  total_focus_seconds: number
  projected_completion_date: string | null
}

/** GET /mentorships/dashboard `data` -- one row per accepted mentee (SRS 6.1). */
export interface MentorDashboardRow {
  mentorship_id: number
  mentee: UserSummary
  current_streak: number
  longest_streak: number
  goals: MentorDashboardGoal[]
}
