import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { toApiFailure } from '@/api/client'
import { notificationsApi } from '@/api/notifications'
import type { ApiFailure } from '@/types/api'
import type { AppNotification } from '@/types/notification'

export type SocketState = 'idle' | 'connecting' | 'connected' | 'reconnecting'

export const useNotificationsStore = defineStore('notifications', () => {
  const items = ref<AppNotification[]>([])
  const loading = ref(false)
  const failure = ref<ApiFailure | null>(null)
  const socketState = ref<SocketState>('idle')

  const unread = computed(() => items.value.filter((item) => item.read_at === null))
  const unreadCount = computed(() => unread.value.length)

  /**
   * No polling. FR-NOT-03 makes live delivery the mechanism, so the bell is
   * populated by this fetch on mount and kept current by the Pusher frame --
   * a poll layered on a working socket is just duplicate requests.
   */
  async function fetchAll(): Promise<void> {
    loading.value = true
    failure.value = null

    try {
      const page = await notificationsApi.list({ per_page: 30 })
      items.value = page.items
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load notifications.')
    } finally {
      loading.value = false
    }
  }

  /**
   * Idempotent by id, because a refetch and a live frame for the same
   * notification will race. Same shape either way -- there is no separate
   * "live event" type to drift from the fetched row.
   */
  function receiveLive(frame: AppNotification): void {
    const existing = items.value.findIndex((item) => item.id === frame.id)

    if (existing !== -1) {
      items.value = items.value.map((item, index) =>
        index === existing ? { ...item, ...frame } : item,
      )

      return
    }

    items.value = [frame, ...items.value]
  }

  async function markRead(id: string): Promise<void> {
    const snapshot = items.value
    const stampedAt = new Date().toISOString()

    items.value = items.value.map((item) =>
      item.id === id ? { ...item, read_at: item.read_at ?? stampedAt } : item,
    )

    try {
      const updated = await notificationsApi.markRead(id)
      receiveLive(updated)
    } catch (error) {
      items.value = snapshot
      failure.value = toApiFailure(error, 'Could not mark that as read.')
    }
  }

  async function markAllRead(): Promise<void> {
    await Promise.all(unread.value.map((item) => markRead(item.id)))
  }

  function setSocketState(state: SocketState): void {
    socketState.value = state
  }

  return {
    items,
    loading,
    failure,
    socketState,
    unread,
    unreadCount,
    fetchAll,
    receiveLive,
    markRead,
    markAllRead,
    setSocketState,
  }
})
