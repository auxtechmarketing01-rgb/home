import { apiClient } from './client'

/**
 * Sent as the raw PushSubscription JSON shape the server-side webpush package
 * expects (endpoint + keys.p256dh + keys.auth), per 02 section 9.
 */
export const pushSubscriptionsApi = {
  async store(subscription: PushSubscriptionJSON): Promise<void> {
    await apiClient.post('/push-subscriptions', subscription)
  },

  /** The endpoint identifies the browser instance being unregistered. */
  async destroy(endpoint: string): Promise<void> {
    await apiClient.delete('/push-subscriptions', { data: { endpoint } })
  },
}
