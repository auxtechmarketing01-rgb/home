import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, type LoginPayload, type ProfilePayload, type RegisterPayload } from '@/api/auth'
import { resetCsrfCookie, setUnauthorizedHandler, toApiFailure } from '@/api/client'
import type { ApiFailure } from '@/types/api'
import type { User } from '@/types/user'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const failure = ref<ApiFailure | null>(null)
  const bootstrapped = ref(false)
  /** False once the session probe fails for a transport reason rather than a 401. */
  const backendReachable = ref(true)

  /**
   * Resolves when the session probe has settled either way. Held here so router
   * guards can await it without the app deferring its first paint on it.
   */
  let readyPromise: Promise<void> | null = null

  const isAuthenticated = computed(() => user.value !== null)
  const needsVerification = computed(
    () => user.value !== null && user.value.email_verified_at === null,
  )
  const gamificationEnabled = computed(() => user.value?.gamification_enabled ?? false)

  /**
   * Probes the session once. A 401 is the expected answer for a visitor, not a
   * failure, so it clears the user and stays silent.
   *
   * Deliberately bounded by its own short timeout: this runs on every cold load,
   * and an unreachable API must land on the login screen quickly rather than
   * leave the app waiting. `backendReachable` records which of the two happened,
   * so the UI can say "cannot reach the server" instead of "signed out".
   */
  function bootstrap(): Promise<void> {
    if (!readyPromise) {
      readyPromise = authApi
        .me({ timeout: 8000 })
        .then((resolved) => {
          user.value = resolved
          backendReachable.value = true
        })
        .catch((error: unknown) => {
          user.value = null
          /** A null status means the request never got an answer at all. */
          backendReachable.value = toApiFailure(error).status !== null
        })
        .finally(() => {
          bootstrapped.value = true
        })
    }

    return readyPromise
  }

  /** Awaited by the router guards; safe to call before or after bootstrap starts. */
  function ready(): Promise<void> {
    return bootstrap()
  }

  async function login(payload: LoginPayload): Promise<boolean> {
    loading.value = true
    failure.value = null

    try {
      user.value = await authApi.login(payload)
      backendReachable.value = true
      readyPromise = Promise.resolve()

      return true
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not sign you in.')

      return false
    } finally {
      loading.value = false
    }
  }

  async function register(payload: RegisterPayload): Promise<boolean> {
    loading.value = true
    failure.value = null

    try {
      user.value = await authApi.register(payload)
      backendReachable.value = true
      readyPromise = Promise.resolve()

      return true
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not create your account.')

      return false
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } catch {
      /** A failed logout still ends the local session; the cookie is already suspect. */
    } finally {
      clearSession()
    }
  }

  async function updateProfile(payload: ProfilePayload): Promise<boolean> {
    loading.value = true
    failure.value = null

    try {
      user.value = await authApi.updateProfile(payload)

      return true
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not save your profile.')

      return false
    } finally {
      loading.value = false
    }
  }

  async function refresh(): Promise<void> {
    try {
      user.value = await authApi.me()
    } catch {
      /** Leave the cached user in place; a transient failure is not a logout. */
    }
  }

  function clearSession(): void {
    user.value = null
    failure.value = null
    resetCsrfCookie()
    /** Settled and signed out -- a later sign-in replaces this outright. */
    readyPromise = Promise.resolve()
    bootstrapped.value = true
  }

  /**
   * A 401 on any request means the session died out from under us -- most often
   * because the account was disabled mid-session (FR-ADM-01). Clearing here is
   * what makes the router guard bounce the very next navigation to /login.
   */
  setUnauthorizedHandler(() => {
    if (user.value !== null) {
      clearSession()
    }
  })

  return {
    user,
    loading,
    failure,
    bootstrapped,
    backendReachable,
    isAuthenticated,
    needsVerification,
    gamificationEnabled,
    bootstrap,
    ready,
    login,
    register,
    logout,
    updateProfile,
    refresh,
    clearSession,
  }
})
