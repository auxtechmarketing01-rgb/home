import { apiClient, getPaginated, sendResource, toQuery } from './client'
import type { Paginated } from '@/types/api'
import type { Reward, RewardFilters, RewardLedgerRow, RewardPayload } from '@/types/reward'

/**
 * One method per transition, mirroring the backend one-route-per-transition
 * shape. There is deliberately no generic `update`: the state machine is the
 * feature, so a caller must name the move it is making.
 */
export const rewardsApi = {
  list(filters?: RewardFilters): Promise<Paginated<Reward>> {
    return getPaginated<Reward>('/rewards', { params: toQuery(filters) })
  },

  /** Mentor side: offering a reward up front (FR-RWD-01). */
  offer(payload: RewardPayload): Promise<Reward> {
    return sendResource<Reward>('post', '/rewards', payload)
  },

  /** Mentee side: asking for one (FR-RWD-03). */
  request(payload: RewardPayload): Promise<Reward> {
    return sendResource<Reward>('post', '/rewards/request', payload)
  },

  respond(id: number, accepted: boolean, note?: string | null): Promise<Reward> {
    return sendResource<Reward>('post', `/rewards/${id}/respond`, { accepted, note: note ?? null })
  },

  claim(id: number): Promise<Reward> {
    return sendResource<Reward>('post', `/rewards/${id}/claim`)
  },

  fulfill(id: number, note?: string | null): Promise<Reward> {
    return sendResource<Reward>('post', `/rewards/${id}/fulfill`, { note: note ?? null })
  },

  revoke(id: number): Promise<Reward> {
    return sendResource<Reward>('post', `/rewards/${id}/revoke`)
  },

  async ledger(): Promise<RewardLedgerRow[]> {
    const response = await apiClient.get<{ data: RewardLedgerRow[] }>('/rewards/ledger')

    return response.data.data
  },
}
