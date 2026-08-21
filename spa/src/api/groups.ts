import { apiClient, getCollection, getResource, sendResource } from './client'
import type { Challenge, ChallengePayload, Group } from '@/types/group'

export const groupsApi = {
  list(): Promise<Group[]> {
    return getCollection<Group>('/groups')
  },

  show(id: number): Promise<Group> {
    return getResource<Group>(`/groups/${id}`)
  },

  create(name: string): Promise<Group> {
    return sendResource<Group>('post', '/groups', { name })
  },

  update(id: number, name: string): Promise<Group> {
    return sendResource<Group>('put', `/groups/${id}`, { name })
  },

  join(inviteCode: string): Promise<Group> {
    return sendResource<Group>('post', '/groups/join', { invite_code: inviteCode })
  },

  async invite(id: number, email?: string | null): Promise<{ message?: string }> {
    const response = await apiClient.post<{ message?: string }>(`/groups/${id}/invite`, {
      email: email ?? null,
    })

    return response.data
  },

  regenerateInviteCode(id: number): Promise<Group> {
    return sendResource<Group>('post', `/groups/${id}/invite-code`)
  },

  async removeMember(groupId: number, memberId: number): Promise<void> {
    await apiClient.delete(`/groups/${groupId}/members/${memberId}`)
  },

  async leave(id: number): Promise<void> {
    await apiClient.post(`/groups/${id}/leave`)
  },

  async destroy(id: number): Promise<void> {
    await apiClient.delete(`/groups/${id}`)
  },
}

export const challengesApi = {
  list(groupId: number): Promise<Challenge[]> {
    return getCollection<Challenge>(`/groups/${groupId}/challenges`)
  },

  show(id: number): Promise<Challenge> {
    return getResource<Challenge>(`/challenges/${id}`)
  },

  create(groupId: number, payload: ChallengePayload): Promise<Challenge> {
    return sendResource<Challenge>('post', `/groups/${groupId}/challenges`, payload)
  },

  join(id: number, goalId?: number | null): Promise<Challenge> {
    return sendResource<Challenge>('post', `/challenges/${id}/join`, { goal_id: goalId ?? null })
  },

  leave(id: number): Promise<Challenge> {
    return sendResource<Challenge>('post', `/challenges/${id}/leave`)
  },

  async destroy(id: number): Promise<void> {
    await apiClient.delete(`/challenges/${id}`)
  },
}
