import { onMounted, onUnmounted } from 'vue'
import { createEcho, isRealtimeConfigured } from '@/echo'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { useRewardsStore } from '@/stores/rewards'
import { useSprintsStore } from '@/stores/sprints'
import { useToastsStore } from '@/stores/toasts'
import type { AppNotification } from '@/types/notification'

/**
 * The open-tab half of notification delivery (FR-NOT-03). Mounted **once** in
 * AppShell -- subscribing per-route would tear down and re-establish the socket
 * on every navigation.
 *
 * Three rules keep this from becoming a second source of truth:
 *   1. A live frame is the same shape as a fetched row (AppNotification).
 *   2. The socket is a latency optimisation. The store is still populated by
 *      fetchAll(), so a dropped connection costs freshness, not data.
 *   3. receiveLive is idempotent by id, because a refetch and a frame race.
 */
export function useRealtimeNotifications() {
  const auth = useAuthStore()
  const notifications = useNotificationsStore()
  const rewards = useRewardsStore()
  const sprints = useSprintsStore()
  const toasts = useToastsStore()

  let echo: ReturnType<typeof createEcho> | null = null

  function handleFrame(frame: AppNotification): void {
    notifications.receiveLive(frame)

    /**
     * A RewardEarnedNotification must move the card into the earned bucket
     * without a refetch -- that is what makes RewardClaimButton enable itself
     * while the member is looking at it.
     */
    if (frame.type.startsWith('Reward')) {
      rewards.applyLiveFrame(frame)
    }

    /**
     * The sprint passed its planned duration. Overtime is a UI state, not a
     * status change, so this only re-reads the row -- it never stops anything.
     */
    if (frame.type === 'SprintExpiredNotification') {
      void sprints.fetchActive()
    }

    toasts.info(titleFor(frame), bodyFor(frame))
  }

  onMounted(() => {
    if (!auth.user || !isRealtimeConfigured()) {
      return
    }

    notifications.setSocketState('connecting')
    echo = createEcho()

    const connection = (echo.connector as { pusher?: Pusher_ }).pusher

    connection?.connection.bind('connected', () => notifications.setSocketState('connected'))
    connection?.connection.bind('connecting', () => notifications.setSocketState('reconnecting'))
    connection?.connection.bind('unavailable', () => notifications.setSocketState('reconnecting'))
    connection?.connection.bind('failed', () => notifications.setSocketState('reconnecting'))
    connection?.connection.bind('disconnected', () => notifications.setSocketState('reconnecting'))

    /**
     * Laravel's broadcast notification channel is `App.Models.User.{id}`; Echo's
     * `.notification()` binds to the framework's BroadcastNotificationCreated
     * event for us.
     */
    echo.private(`App.Models.User.${auth.user.id}`).notification(handleFrame)
  })

  onUnmounted(() => {
    echo?.disconnect()
    echo = null
    notifications.setSocketState('idle')
  })
}

/** Minimal shape of the pusher-js connection Echo exposes on its connector. */
interface Pusher_ {
  connection: { bind: (event: string, handler: () => void) => void }
}

function titleFor(frame: AppNotification): string {
  const payload = frame.payload as { title?: unknown }

  if (typeof payload.title === 'string') {
    return payload.title
  }

  return humanise(frame.type)
}

function bodyFor(frame: AppNotification): string | undefined {
  const payload = frame.payload as { body?: unknown; message?: unknown }

  if (typeof payload.body === 'string') {
    return payload.body
  }

  return typeof payload.message === 'string' ? payload.message : undefined
}

/** `RewardEarnedNotification` -> `Reward earned`. */
export function humanise(type: string): string {
  const words = type
    .replace(/Notification$/, '')
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .toLowerCase()

  return words.charAt(0).toUpperCase() + words.slice(1)
}
