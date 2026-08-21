import { getPaginated, sendResource, toQuery } from './client'
import type { Paginated } from '@/types/api'
import type { AppNotification } from '@/types/notification'

export const notificationsApi = {
  list(params?: { unread?: boolean; per_page?: number; page?: number }): Promise<
    Paginated<AppNotification>
  > {
    return getPaginated<AppNotification>('/notifications', { params: toQuery(params) })
  },

  markRead(id: string): Promise<AppNotification> {
    return sendResource<AppNotification>('patch', `/notifications/${id}/read`)
  },
}
