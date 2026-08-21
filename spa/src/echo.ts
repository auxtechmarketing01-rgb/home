import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { apiClient } from '@/api/client'

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
     * Channel authorization is pushed through the app's own axios client rather
     * than left to Echo's built-in `authEndpoint`.
     *
     * `authEndpoint` uses pusher-js's own XHR, which sends the session cookie but
     * *not* the `X-XSRF-TOKEN` header -- so Laravel's CSRF middleware answered
     * every private-channel subscription with a 419 and real-time silently
     * degraded to nothing. Going through `apiClient` reuses the interceptor that
     * already primes the CSRF cookie and mirrors the header onto every mutation,
     * so the two can never drift apart again.
     *
     * `apiClient` is based at `/api/v1`, which is also where the route lives:
     * Echo's default `/broadcasting/auth` sits on the `web` group and cannot see
     * a Sanctum SPA session.
     */
    authorizer: (channel) => ({
      authorize: (
        socketId: string,
        callback: (error: Error | null, data: unknown) => void,
      ): void => {
        apiClient
          .post('/broadcasting/auth', {
            socket_id: socketId,
            channel_name: channel.name,
          })
          .then((response) => callback(null, response.data))
          .catch((error: Error) => callback(error, null))
      },
    }),
    withCredentials: true,
  })
}

export function isRealtimeConfigured(): boolean {
  return Boolean(import.meta.env.VITE_PUSHER_APP_KEY)
}
