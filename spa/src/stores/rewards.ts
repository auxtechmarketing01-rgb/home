import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { toApiFailure } from '@/api/client'
import { rewardsApi } from '@/api/rewards'
import type { ApiFailure } from '@/types/api'
import type { AppNotification } from '@/types/notification'
import type { Reward, RewardFilters, RewardLedgerRow, RewardPayload } from '@/types/reward'

export const useRewardsStore = defineStore('rewards', () => {
  const items = ref<Reward[]>([])
  const ledger = ref<RewardLedgerRow[]>([])
  const filters = ref<RewardFilters>({ per_page: 50 })

  const loading = ref(false)
  const saving = ref(false)
  const ledgerLoading = ref(false)
  const failure = ref<ApiFailure | null>(null)

  const asMentor = computed(() => items.value.filter((reward) => reward.viewer_role === 'mentor'))
  const asMentee = computed(() => items.value.filter((reward) => reward.viewer_role === 'mentee'))

  /** The bucket that drives RewardClaimButton being enabled at all. */
  const earned = computed(() => items.value.filter((reward) => reward.status === 'earned'))

  /** Anything the viewer can act on right now, from the server's own action list. */
  const actionable = computed(() =>
    items.value.filter((reward) => reward.available_actions.length > 0),
  )

  function forGoal(goalId: number): Reward[] {
    return items.value.filter((reward) => reward.goal_id === goalId)
  }

  async function fetchAll(next?: RewardFilters): Promise<void> {
    loading.value = true
    failure.value = null

    if (next) {
      filters.value = { ...filters.value, ...next }
    }

    try {
      const page = await rewardsApi.list(filters.value)
      items.value = page.items
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load rewards.')
    } finally {
      loading.value = false
    }
  }

  async function fetchLedger(): Promise<void> {
    ledgerLoading.value = true

    try {
      ledger.value = await rewardsApi.ledger()
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load the reward ledger.')
    } finally {
      ledgerLoading.value = false
    }
  }

  async function offer(payload: RewardPayload): Promise<Reward | null> {
    return mutate(async () => {
      const reward = await rewardsApi.offer(payload)
      items.value = [reward, ...items.value]

      return reward
    }, 'Could not offer that reward.')
  }

  async function request(payload: RewardPayload): Promise<Reward | null> {
    return mutate(async () => {
      const reward = await rewardsApi.request(payload)
      items.value = [reward, ...items.value]

      return reward
    }, 'Could not send that request.')
  }

  async function respond(id: number, accepted: boolean, note?: string | null): Promise<Reward | null> {
    return mutate(() => rewardsApi.respond(id, accepted, note).then(apply), 'Could not respond.')
  }

  async function claim(id: number): Promise<Reward | null> {
    return mutate(() => rewardsApi.claim(id).then(apply), 'Could not claim that reward.')
  }

  async function fulfill(id: number, note?: string | null): Promise<Reward | null> {
    return mutate(() => rewardsApi.fulfill(id, note).then(apply), 'Could not mark that fulfilled.')
  }

  async function revoke(id: number): Promise<Reward | null> {
    return mutate(() => rewardsApi.revoke(id).then(apply), 'Could not revoke that reward.')
  }

  function apply(reward: Reward): Reward {
    items.value = items.value.some((entry) => entry.id === reward.id)
      ? items.value.map((entry) => (entry.id === reward.id ? reward : entry))
      : [reward, ...items.value]

    return reward
  }

  /**
   * A RewardEarnedNotification arriving over Pusher must move the card into the
   * earned bucket without a refetch -- that is what makes RewardClaimButton
   * enable itself while the member is looking at it (06 section 3, gate 5).
   *
   * Only the status is trusted from the frame: `available_actions` is derived
   * server-side per viewer, so it is re-read from the API rather than guessed
   * at here. The optimistic hop keeps the UI honest in the meantime.
   */
  function applyLiveFrame(frame: AppNotification): boolean {
    const rewardId = extractRewardId(frame.payload)

    if (rewardId === null) {
      return false
    }

    const existing = items.value.find((entry) => entry.id === rewardId)

    if (!existing) {
      void refreshOne(rewardId)

      return false
    }

    if (frame.type === 'RewardEarnedNotification' && existing.status !== 'earned') {
      items.value = items.value.map((entry) =>
        entry.id === rewardId
          ? {
              ...entry,
              status: 'earned',
              available_actions: entry.viewer_role === 'mentee' ? ['claim'] : [],
            }
          : entry,
      )
    }

    void refreshOne(rewardId)

    return true
  }

  /** Re-reads the one row so `available_actions` comes from the Policy, not a guess. */
  async function refreshOne(rewardId: number): Promise<void> {
    try {
      const page = await rewardsApi.list({ ...filters.value })
      const fresh = page.items.find((entry) => entry.id === rewardId)

      if (fresh) {
        apply(fresh)
      }
    } catch {
      /** Freshness only; the optimistic hop above already moved the card. */
    }
  }

  async function mutate<T>(action: () => Promise<T>, message: string): Promise<T | null> {
    saving.value = true
    failure.value = null

    try {
      return await action()
    } catch (error) {
      failure.value = toApiFailure(error, message)

      return null
    } finally {
      saving.value = false
    }
  }

  function clearFailure(): void {
    failure.value = null
  }

  return {
    items,
    ledger,
    filters,
    loading,
    saving,
    ledgerLoading,
    failure,
    asMentor,
    asMentee,
    earned,
    actionable,
    forGoal,
    fetchAll,
    fetchLedger,
    offer,
    request,
    respond,
    claim,
    fulfill,
    revoke,
    applyLiveFrame,
    clearFailure,
  }
})

function extractRewardId(payload: Record<string, unknown>): number | null {
  const candidate = payload.reward_id ?? payload.rewardId ?? payload.id

  return typeof candidate === 'number' ? candidate : null
}
