import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { API_BASE_URL } from '@/api/client'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

window.Pusher = Pusher

/**
 * One Echo instance for the app, created after auth is known.
 *
 * Only the Pusher *key* and *cluster* are here. They are public by design; the
 * app secret is a server credential and must never appear in a VITE_-prefixed
 * variable, because everything with that prefix is compiled into the bundle.
 */
export function createEcho(): Echo<'pusher'> {
  return new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    /**
     * The SPA is a separate origin, so channel authorization goes through the
     * versioned API with the Sanctum session cookie attached -- Echo's default
     * `/broadcasting/auth` on the `web` group cannot see that cookie.
     */
    authEndpoint: `${API_BASE_URL}/broadcasting/auth`,
    withCredentials: true,
  })
}

export function isRealtimeConfigured(): boolean {
  return Boolean(import.meta.env.VITE_PUSHER_APP_KEY)
}
