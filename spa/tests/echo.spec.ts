import { beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Channel authorization is the one request Echo would otherwise make on its own
 * terms, and that is exactly why it broke: pusher-js's built-in `authEndpoint`
 * XHR sends the session cookie but not the `X-XSRF-TOKEN` header, so Laravel's
 * CSRF middleware answered every private-channel subscription with 419 and
 * real-time degraded to silence with nothing in the UI to show it.
 *
 * These specs pin the fix: the auth call goes through the app's axios client,
 * which is the thing that already primes the CSRF cookie and mirrors the header.
 */

type AuthorizeCallback = (error: Error | null, data: unknown) => void

interface CapturedOptions {
  authEndpoint?: string
  authorizer?: (channel: { name: string }) => {
    authorize: (socketId: string, callback: AuthorizeCallback) => void
  }
}

const captured: { options: CapturedOptions | null } = { options: null }

vi.mock('laravel-echo', () => ({
  default: class {
    constructor(options: CapturedOptions) {
      captured.options = options
    }
  },
}))

vi.mock('pusher-js', () => ({ default: class {} }))

vi.mock('@/api/client', () => ({
  apiClient: { post: vi.fn() },
  API_BASE_URL: '/api/v1',
}))

const { apiClient } = await import('@/api/client')
const { createEcho } = await import('@/echo')

const post = apiClient.post as unknown as ReturnType<typeof vi.fn>

describe('createEcho', () => {
  beforeEach(() => {
    captured.options = null
    post.mockReset()
  })

  it('authorizes channels through the api client rather than Echo authEndpoint', async () => {
    post.mockResolvedValue({ data: { auth: 'key:signature' } })
    createEcho()

    const authorizer = captured.options?.authorizer
    expect(authorizer).toBeTypeOf('function')
    /** Leaving this set would let pusher-js's own CSRF-less XHR back in. */
    expect(captured.options?.authEndpoint).toBeUndefined()

    const callback = vi.fn()
    authorizer!({ name: 'private-App.Models.User.7' }).authorize('812.4471', callback)
    await vi.waitFor(() => expect(callback).toHaveBeenCalled())

    expect(post).toHaveBeenCalledWith('/broadcasting/auth', {
      socket_id: '812.4471',
      channel_name: 'private-App.Models.User.7',
    })
    expect(callback).toHaveBeenCalledWith(null, { auth: 'key:signature' })
  })

  it('reports a rejected authorization to pusher instead of hanging', async () => {
    const failure = new Error('Forbidden')
    post.mockRejectedValue(failure)
    createEcho()

    const callback = vi.fn()
    captured.options!.authorizer!({ name: 'private-App.Models.User.7' }).authorize('1.1', callback)
    await vi.waitFor(() => expect(callback).toHaveBeenCalled())

    /**
     * pusher-js retries a subscription whose callback never fires, so the error
     * branch has to call back too -- silence here is a reconnect loop.
     */
    expect(callback).toHaveBeenCalledWith(failure, null)
  })
})
