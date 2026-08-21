import type { Router } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
    layout?: 'bare' | 'shell'
    title?: string
  }
}

export function installGuards(router: Router): void {
  router.beforeEach((to) => {
    const auth = useAuthStore()

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      /**
       * `redirect` is carried through so a deep link survives the detour to
       * /login -- landing on the dashboard after signing in would silently
       * discard the URL the member actually asked for.
       */
      return { name: 'login', query: { redirect: to.fullPath } }
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
      return { name: 'dashboard' }
    }

    return true
  })

  router.afterEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} - Pathforge` : 'Pathforge'
  })
}
