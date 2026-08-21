import { apiClient, getPaginated, sendResource, toQuery } from './client'
import { API_BASE_URL } from './client'
import type { Paginated } from '@/types/api'
import type { Sprint, SprintFilters, StartSprintPayload } from '@/types/sprint'

export const sprintsApi = {
  history(filters?: SprintFilters): Promise<Paginated<Sprint>> {
    return getPaginated<Sprint>('/sprints', { params: toQuery(filters) })
  },

  /**
   * The running sprint, or null. This single call is what makes a reopened app
   * -- new tab, new device, six hours later -- resume the correct countdown:
   * the row's `started_at` is the timer, not anything in browser memory.
   */
  async active(): Promise<Sprint | null> {
    const response = await apiClient.get<{ data: Sprint | null }>('/sprints/active')

    return response.data.data ?? null
  },

  start(payload: StartSprintPayload): Promise<Sprint> {
    return sendResource<Sprint>('post', '/sprints/start', payload)
  },

  pause(id: number): Promise<Sprint> {
    return sendResource<Sprint>('post', `/sprints/${id}/pause`)
  },

  resume(id: number): Promise<Sprint> {
    return sendResource<Sprint>('post', `/sprints/${id}/resume`)
  },

  complete(id: number, notes?: string | null): Promise<Sprint> {
    return sendResource<Sprint>('post', `/sprints/${id}/complete`, { notes })
  },

  cancel(id: number): Promise<Sprint> {
    return sendResource<Sprint>('post', `/sprints/${id}/cancel`)
  },

  /**
   * The backend owns the CSV (FR-SPR-08), so this hands the browser a URL with
   * the session cookie attached rather than assembling rows client-side.
   */
  exportUrl(filters?: SprintFilters): string {
    const query = new URLSearchParams(
      Object.entries(toQuery(filters)).map(([key, value]) => [key, String(value)]),
    ).toString()

    return `${API_BASE_URL}/sprints/export${query ? `?${query}` : ''}`
  },
}
