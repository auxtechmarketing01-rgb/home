import { defineStore } from 'pinia'
import { ref } from 'vue'
import { toApiFailure } from '@/api/client'
import { analyticsApi } from '@/api/analytics'
import { challengesApi, groupsApi } from '@/api/groups'
import type { ApiFailure } from '@/types/api'
import type { GroupTrendSeries, LeaderboardEntry, LeaderboardPeriod } from '@/types/analytics'
import type { Challenge, ChallengePayload, Group, GroupMember } from '@/types/group'

interface LeaderboardCacheEntry {
  period: LeaderboardPeriod
  entries: LeaderboardEntry[]
}

export const useGroupsStore = defineStore('groups', () => {
  const groups = ref<Group[]>([])
  const detail = ref<Group | null>(null)
  const challenges = ref<Record<number, Challenge[]>>({})
  const leaderboards = ref<Record<number, LeaderboardCacheEntry>>({})
  const trends = ref<Record<number, GroupTrendSeries[]>>({})

  const loading = ref(false)
  const saving = ref(false)
  const leaderboardLoading = ref(false)
  const trendLoading = ref(false)
  const failure = ref<ApiFailure | null>(null)

  /**
   * Every member of every group the acting member belongs to. This is the whole
   * universe a mentor request may target (FR-MENT-01) -- there is no user
   * directory, so a free-text search would only promise a 403.
   */
  function shareableMembers(excludeUserId?: number): GroupMember[] {
    const seen = new Map<number, GroupMember>()

    for (const group of groups.value) {
      for (const member of group.members ?? []) {
        if (member.id !== excludeUserId && !seen.has(member.id)) {
          seen.set(member.id, member)
        }
      }
    }

    return [...seen.values()].sort((a, b) => a.name.localeCompare(b.name))
  }

  async function fetchAll(): Promise<void> {
    loading.value = true
    failure.value = null

    try {
      groups.value = await groupsApi.list()
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load your groups.')
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: number): Promise<Group | null> {
    loading.value = true
    failure.value = null

    try {
      detail.value = await groupsApi.show(id)
      groups.value = groups.value.map((group) =>
        group.id === id ? { ...group, ...detail.value } : group,
      )

      return detail.value
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load that group.')

      return null
    } finally {
      loading.value = false
    }
  }

  async function create(name: string): Promise<Group | null> {
    return mutate(async () => {
      const group = await groupsApi.create(name)
      groups.value = [...groups.value, group]

      return group
    }, 'Could not create the group.')
  }

  async function rename(id: number, name: string): Promise<Group | null> {
    return mutate(async () => {
      const group = await groupsApi.update(id, name)
      applyGroup(group)

      return group
    }, 'Could not rename the group.')
  }

  async function join(inviteCode: string): Promise<Group | null> {
    return mutate(async () => {
      const group = await groupsApi.join(inviteCode)
      groups.value = [...groups.value.filter((entry) => entry.id !== group.id), group]

      return group
    }, 'Could not join with that code.')
  }

  async function invite(id: number, email?: string | null): Promise<string | null> {
    saving.value = true
    failure.value = null

    try {
      const result = await groupsApi.invite(id, email)

      return result.message ?? 'Invite sent.'
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not send that invite.')

      return null
    } finally {
      saving.value = false
    }
  }

  async function regenerateInviteCode(id: number): Promise<Group | null> {
    return mutate(async () => {
      const group = await groupsApi.regenerateInviteCode(id)
      applyGroup(group)

      return group
    }, 'Could not regenerate the invite code.')
  }

  async function removeMember(groupId: number, memberId: number): Promise<boolean> {
    saving.value = true
    failure.value = null

    try {
      await groupsApi.removeMember(groupId, memberId)

      if (detail.value?.id === groupId) {
        detail.value = {
          ...detail.value,
          members: (detail.value.members ?? []).filter((member) => member.id !== memberId),
          members_count: Math.max(0, (detail.value.members_count ?? 1) - 1),
        }
      }

      return true
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not remove that member.')

      return false
    } finally {
      saving.value = false
    }
  }

  async function leave(id: number): Promise<boolean> {
    saving.value = true
    failure.value = null

    try {
      await groupsApi.leave(id)
      groups.value = groups.value.filter((group) => group.id !== id)

      return true
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not leave that group.')

      return false
    } finally {
      saving.value = false
    }
  }

  async function destroy(id: number): Promise<boolean> {
    saving.value = true
    failure.value = null

    try {
      await groupsApi.destroy(id)
      groups.value = groups.value.filter((group) => group.id !== id)

      return true
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not delete that group.')

      return false
    } finally {
      saving.value = false
    }
  }

  /** Cached per group+period; the backend cache is invalidated explicitly, so this mirrors it. */
  async function fetchLeaderboard(
    groupId: number,
    period: LeaderboardPeriod = 'week',
    force = false,
  ): Promise<void> {
    const cached = leaderboards.value[groupId]

    if (!force && cached && cached.period === period) {
      return
    }

    leaderboardLoading.value = true
    failure.value = null

    try {
      leaderboards.value = {
        ...leaderboards.value,
        [groupId]: { period, entries: await analyticsApi.leaderboard(groupId, period) },
      }
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load the leaderboard.')
    } finally {
      leaderboardLoading.value = false
    }
  }

  async function fetchTrend(groupId: number, days = 28): Promise<void> {
    trendLoading.value = true

    try {
      trends.value = { ...trends.value, [groupId]: await analyticsApi.groupTrend(groupId, days) }
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load the comparison.')
    } finally {
      trendLoading.value = false
    }
  }

  async function fetchChallenges(groupId: number): Promise<void> {
    try {
      challenges.value = { ...challenges.value, [groupId]: await challengesApi.list(groupId) }
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load challenges.')
    }
  }

  async function createChallenge(
    groupId: number,
    payload: ChallengePayload,
  ): Promise<Challenge | null> {
    saving.value = true
    failure.value = null

    try {
      const challenge = await challengesApi.create(groupId, payload)
      challenges.value = {
        ...challenges.value,
        [groupId]: [...(challenges.value[groupId] ?? []), challenge],
      }

      return challenge
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not create the challenge.')

      return null
    } finally {
      saving.value = false
    }
  }

  async function toggleChallenge(
    challenge: Challenge,
    goalId?: number | null,
  ): Promise<Challenge | null> {
    saving.value = true
    failure.value = null

    try {
      const updated = challenge.has_joined
        ? await challengesApi.leave(challenge.id)
        : await challengesApi.join(challenge.id, goalId)

      challenges.value = {
        ...challenges.value,
        [challenge.group_id]: (challenges.value[challenge.group_id] ?? []).map((entry) =>
          entry.id === updated.id ? updated : entry,
        ),
      }

      return updated
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not update your participation.')

      return null
    } finally {
      saving.value = false
    }
  }

  function applyGroup(group: Group): void {
    groups.value = groups.value.map((entry) => (entry.id === group.id ? group : entry))

    if (detail.value?.id === group.id) {
      detail.value = { ...detail.value, ...group }
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
    groups,
    detail,
    challenges,
    leaderboards,
    trends,
    loading,
    saving,
    leaderboardLoading,
    trendLoading,
    failure,
    shareableMembers,
    fetchAll,
    fetchOne,
    create,
    rename,
    join,
    invite,
    regenerateInviteCode,
    removeMember,
    leave,
    destroy,
    fetchLeaderboard,
    fetchTrend,
    fetchChallenges,
    createChallenge,
    toggleChallenge,
    clearFailure,
  }
})
