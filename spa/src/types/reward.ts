import type { Goal } from './goal'
import type { MentorshipRole } from './mentorship'
import type { RoadmapItem } from './roadmap'

export type RewardType = 'monetary' | 'privilege' | 'custom'

export type RewardStatus =
  | 'requested'
  | 'offered'
  | 'earned'
  | 'claimed'
  | 'fulfilled'
  | 'denied'
  | 'revoked'

export const REWARD_STATUSES: RewardStatus[] = [
  'requested',
  'offered',
  'earned',
  'claimed',
  'fulfilled',
  'denied',
  'revoked',
]

/** The transitions RewardResource says this viewer may trigger right now. */
export type RewardAction = 'respond' | 'revoke' | 'fulfill' | 'claim'

/** Mirrors RewardResource. */
export interface Reward {
  id: number
  mentorship_id: number
  goal_id: number | null
  roadmap_item_id: number | null
  title: string
  description: string | null
  type: RewardType
  /** Decimal column, so it arrives as a string on some drivers. */
  monetary_amount: string | number | null
  currency_label: string | null
  status: RewardStatus
  requested_by: MentorshipRole
  claimed_at: string | null
  fulfilled_at: string | null
  fulfilled_note: string | null
  viewer_role: MentorshipRole | null
  /**
   * Authoritative: computed server-side from the same side-and-state pair the
   * Policy enforces, so the UI can neither offer a button the API refuses nor
   * hide one it would have allowed.
   */
  available_actions: RewardAction[]
  goal?: Goal | null
  roadmap_item?: RoadmapItem | null
}

/** StoreRewardRequest (mentor offer) and RequestRewardRequest (mentee demand). */
export interface RewardPayload {
  mentorship_id: number
  goal_id?: number | null
  roadmap_item_id?: number | null
  title: string
  description?: string | null
  type: RewardType
  monetary_amount?: number | null
  currency_label?: string | null
}

export interface RewardFilters {
  status?: RewardStatus
  role?: MentorshipRole
  mentorship_id?: number
  per_page?: number
  page?: number
}

/**
 * GET /rewards/ledger `data` (FR-RWD-06). Grouped by currency label rather than
 * summed, because the label is free text -- adding "500 BDT" to "20 USD" would
 * produce a meaningless number. A record, never a balance.
 */
export interface RewardLedgerRow {
  mentorship_id: number
  mentor: { id: number; name: string | null }
  mentee: { id: number; name: string | null }
  fulfilled_count: number
  totals_by_label: Record<string, string>
}
