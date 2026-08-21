import { ref } from 'vue'
import { pushSubscriptionsApi } from '@/api/pushSubscriptions'

function urlBase64ToUint8Array(base64: string): Uint8Array {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'))

  return Uint8Array.from(raw, (char) => char.charCodeAt(0))
}

const STORAGE_KEY = 'pathforge:push-prompt-dismissed'

/**
 * The closed-tab half of notification delivery. Web Push cannot update a page,
 * only raise an OS notification -- it is the complement of the Pusher socket,
 * not an alternative to it.
 */
export function usePushNotifications() {
  const busy = ref(false)
  const error = ref<string | null>(null)
  const permission = ref<NotificationPermission>(
    'Notification' in window ? Notification.permission : 'default',
  )

  function isSupported(): boolean {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
  }

  async function currentSubscription(): Promise<PushSubscription | null> {
    if (!isSupported()) {
      return null
    }

    const registration = await navigator.serviceWorker.getRegistration()

    return (await registration?.pushManager.getSubscription()) ?? null
  }

  async function subscribe(): Promise<boolean> {
    error.value = null

    if (!isSupported()) {
      error.value = 'This browser cannot receive push notifications.'

      return false
    }

    busy.value = true

    try {
      const granted = await Notification.requestPermission()
      permission.value = granted

      if (granted !== 'granted') {
        error.value =
          granted === 'denied'
            ? 'Notifications are blocked for this site in your browser settings.'
            : 'Notification permission was dismissed.'

        return false
      }

      const registration =
        (await navigator.serviceWorker.getRegistration()) ??
        (await navigator.serviceWorker.register('/sw.js', { type: 'module' }))

      await navigator.serviceWorker.ready

      const existing = await registration.pushManager.getSubscription()
      const subscription =
        existing ??
        (await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(import.meta.env.VITE_VAPID_PUBLIC_KEY),
        }))

      await pushSubscriptionsApi.store(subscription.toJSON())

      return true
    } catch (cause) {
      error.value = cause instanceof Error ? cause.message : 'Could not enable notifications.'

      return false
    } finally {
      busy.value = false
    }
  }

  async function unsubscribe(): Promise<void> {
    busy.value = true

    try {
      const subscription = await currentSubscription()

      if (subscription) {
        await pushSubscriptionsApi.destroy(subscription.endpoint)
        await subscription.unsubscribe()
      }
    } finally {
      busy.value = false
    }
  }

  function dismissPrompt(): void {
    localStorage.setItem(STORAGE_KEY, '1')
  }

  function promptDismissed(): boolean {
    return localStorage.getItem(STORAGE_KEY) === '1'
  }

  return {
    busy,
    error,
    permission,
    isSupported,
    currentSubscription,
    subscribe,
    unsubscribe,
    dismissPrompt,
    promptDismissed,
  }
}
