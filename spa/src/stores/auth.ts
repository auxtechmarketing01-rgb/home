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

  const isAuthenticated = computed(() => user.value !== null)
  const needsVerification = computed(
    () => user.value !== null && user.value.email_verified_at === null,
  )
  const gamificationEnabled = computed(() => user.value?.gamification_enabled ?? false)

  /**
   * Resolved once, before the router mounts. A 401 here is the expected answer
   * for a visitor, not a failure -- so it clears the user and stays silent.
   */
  async function bootstrap(): Promise<void> {
    if (bootstrapped.value) {
      return
    }

    try {
      user.value = await authApi.me()
    } catch {
      user.value = null
    } finally {
      bootstrapped.value = true
    }
  }

  async function login(payload: LoginPayload): Promise<boolean> {
    loading.value = true
    failure.value = null

    try {
      user.value = await authApi.login(payload)

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
    isAuthenticated,
    needsVerification,
    gamificationEnabled,
    bootstrap,
    login,
    register,
    logout,
    updateProfile,
    refresh,
    clearSession,
  }
})
