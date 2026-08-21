import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

/**
 * Wraps the auth store with the two things every auth view needs and no view
 * should re-derive: where to land after signing in, and the field-error lookup
 * a form binds to.
 */
export function useAuth() {
  const store = useAuthStore()
  const router = useRouter()
  const route = useRoute()

  const user = computed(() => store.user)
  const isAuthenticated = computed(() => store.isAuthenticated)

  function fieldError(field: string): string | null {
    return store.failure?.errors[field]?.[0] ?? null
  }

  /** The banner message, minus anything already shown next to a field. */
  const formError = computed(() => {
    const failure = store.failure

    if (!failure) {
      return null
    }

    return Object.keys(failure.errors).length > 0 ? null : failure.message
  })

  async function redirectAfterAuth(): Promise<void> {
    const target = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    await router.replace(target)
  }

  async function signOut(): Promise<void> {
    await store.logout()
    await router.replace({ name: 'login' })
  }

  return { store, user, isAuthenticated, fieldError, formError, redirectAfterAuth, signOut }
}
