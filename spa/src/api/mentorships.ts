import { apiClient, getCollection, sendResource, toQuery } from './client'
import type {
  MentorDashboardRow,
  Mentorship,
  MentorshipFilters,
  MentorshipRequestPayload,
} from '@/types/mentorship'

export const mentorshipsApi = {
  list(filters?: MentorshipFilters): Promise<Mentorship[]> {
    return getCollection<Mentorship>('/mentorships', { params: toQuery(filters) })
  },

  /** FR-MENT-01: the target must share a Group -- the backend 403s otherwise. */
  request(payload: MentorshipRequestPayload): Promise<Mentorship> {
    return sendResource<Mentorship>('post', '/mentorships', payload)
  },

  accept(id: number): Promise<Mentorship> {
    return sendResource<Mentorship>('post', `/mentorships/${id}/accept`)
  },

  decline(id: number): Promise<Mentorship> {
    return sendResource<Mentorship>('post', `/mentorships/${id}/decline`)
  },

  end(id: number): Promise<Mentorship> {
    return sendResource<Mentorship>('post', `/mentorships/${id}/end`)
  },

  async dashboard(): Promise<MentorDashboardRow[]> {
    const response = await apiClient.get<{ data: MentorDashboardRow[] }>('/mentorships/dashboard')

    return response.data.data
  },
}
