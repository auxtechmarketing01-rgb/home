import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useRewardsStore } from '@/stores/rewards'
import { makeReward } from './factories'
import type { AppNotification } from '@/types/notification'

vi.mock('@/api/rewards', () => ({
  rewardsApi: {
    list: vi.fn(),
    offer: vi.fn(),
    request: vi.fn(),
    respond: vi.fn(),
    claim: vi.fn(),
    fulfill: vi.fn(),
    revoke: vi.fn(),
    ledger: vi.fn(),
  },
}))

const { rewardsApi } = await import('@/api/rewards')

function earnedFrame(rewardId: number): AppNotification {
  return {
    id: 'frame-1',
    type: 'RewardEarnedNotification',
    payload: { reward_id: rewardId },
    read_at: null,
    created_at: '2026-06-01T09:00:00.000Z',
  }
}

describe('rewards store live frames (06 section 3, gate 5)', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.mocked(rewardsApi.list).mockResolvedValue({
      items: [],
      meta: {
        current_page: 1,
        from: null,
        last_page: 1,
        path: '/rewards',
        per_page: 50,
        to: null,
        total: 0,
      },
    })
  })

  it('moves an offered reward into the earned bucket without a refetch', () => {
    const store = useRewardsStore()
    store.items = [makeReward('offered', 'mentee', [])]

    const applied = store.applyLiveFrame(earnedFrame(1))

    expect(applied).toBe(true)
    expect(store.items[0]?.status).toBe('earned')
    expect(store.earned).toHaveLength(1)
  })

  it('makes the claim action available to the mentee, which is what enables the button', () => {
    const store = useRewardsStore()
    store.items = [makeReward('offered', 'mentee', [])]

    store.applyLiveFrame(earnedFrame(1))

    expect(store.items[0]?.available_actions).toContain('claim')
    expect(store.actionable).toHaveLength(1)
  })

  it('does not hand a mentor a claim action on the same frame', () => {
    const store = useRewardsStore()
    store.items = [makeReward('offered', 'mentor', [])]

    store.applyLiveFrame(earnedFrame(1))

    expect(store.items[0]?.status).toBe('earned')
    expect(store.items[0]?.available_actions).not.toContain('claim')
  })

  it('ignores a frame whose payload carries no reward id', () => {
    const store = useRewardsStore()
    store.items = [makeReward('offered', 'mentee', [])]

    const applied = store.applyLiveFrame({
      id: 'x',
      type: 'RewardEarnedNotification',
      payload: {},
      read_at: null,
      created_at: '2026-06-01T09:00:00.000Z',
    })

    expect(applied).toBe(false)
    expect(store.items[0]?.status).toBe('offered')
  })

  it('leaves an already-earned reward alone rather than re-applying', () => {
    const store = useRewardsStore()
    store.items = [makeReward('earned', 'mentee', ['claim'])]

    store.applyLiveFrame(earnedFrame(1))

    expect(store.items).toHaveLength(1)
    expect(store.items[0]?.status).toBe('earned')
  })

  it('splits rewards by the viewer side the server reported', () => {
    const store = useRewardsStore()
    store.items = [
      makeReward('offered', 'mentee', [], { id: 1 }),
      makeReward('claimed', 'mentor', ['fulfill'], { id: 2 }),
    ]

    expect(store.asMentee.map((reward) => reward.id)).toEqual([1])
    expect(store.asMentor.map((reward) => reward.id)).toEqual([2])
  })

  it('scopes rewards to a goal for the per-goal tab', () => {
    const store = useRewardsStore()
    store.items = [
      makeReward('offered', 'mentee', [], { id: 1, goal_id: 10 }),
      makeReward('offered', 'mentee', [], { id: 2, goal_id: 11 }),
    ]

    expect(store.forGoal(10).map((reward) => reward.id)).toEqual([1])
  })
})
