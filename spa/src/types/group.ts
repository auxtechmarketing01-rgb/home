export type GroupRole = 'owner' | 'member'

/** A row of the GroupResource `members` projection. */
export interface GroupMember {
  id: number
  name: string
  role: GroupRole
}

/** Mirrors GroupResource. `invite_code` is present for the owner only. */
export interface Group {
  id: number
  name: string
  owner_id: number
  is_owner: boolean
  invite_code?: string | null
  members_count?: number | null
  members?: GroupMember[]
  created_at: string | null
}

export type ChallengeStatus = 'upcoming' | 'active' | 'completed' | 'cancelled'

export interface ChallengeParticipant {
  user: { id: number; name: string | null }
  goal: {
    id: number
    title: string
    status: string
    completion_percentage: number
    total_focus_seconds: number
  } | null
  joined_at: string | null
}

/** Mirrors ChallengeResource (FR-GRP-04). */
export interface Challenge {
  id: number
  group_id: number
  title: string
  description: string | null
  status: ChallengeStatus
  starts_on: string | null
  ends_on: string | null
  created_by: number
  participants_count?: number | null
  has_joined: boolean
  participants?: ChallengeParticipant[]
}

export interface ChallengePayload {
  title: string
  description?: string | null
  starts_on?: string | null
  ends_on?: string | null
  goal_id?: number | null
}
