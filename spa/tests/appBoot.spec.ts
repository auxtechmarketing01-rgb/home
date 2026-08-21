import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import App from '@/App.vue'

/**
 * Guards the boot sequence, which is where two separate cold-load bugs lived.
 *
 * Vue Router's first navigation is asynchronous and the app's guard awaits the
 * session probe inside it. Anything App.vue renders during that window renders
 * before anyone is known to be authenticated, so what it renders is a
 * correctness question, not a cosmetic one.
 */

/** Inlined rather than hoisted into a const: `vi.mock` factories run first. */
vi.mock('@/components/layout/AppShell.vue', () => ({
  default: { name: 'AppShell', template: '<div data-test="shell">shell</div>' },
}))
vi.mock('@/components/ui/ToastHost.vue', () => ({
  default: { name: 'ToastHost', template: '<div />' },
}))

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: { template: '<p>login</p>' }, meta: { layout: 'bare' } },
      { path: '/', name: 'dashboard', component: { template: '<p>dashboard</p>' } },
    ],
  })
}

describe('App boot', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('shows the session splash instead of the shell before the first route resolves', () => {
    const router = makeRouter()
    const wrapper = mount(App, { global: { plugins: [router] } })

    /**
     * No `await router.isReady()` here on purpose -- this is the START_LOCATION
     * window. `meta.layout` is `undefined` there, so a naive `=== 'bare'` check
     * fell through to the shell: the sidebar flashed ahead of the login screen
     * and NotificationBell's mounted fetch fired unauthenticated, returning 401
     * on every cold load.
     */
    expect(wrapper.find('[data-test="shell"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Restoring your session')
  })

  it('renders the bare layout without the shell on an auth screen', async () => {
    const router = makeRouter()
    router.push('/login')
    await router.isReady()

    const wrapper = mount(App, { global: { plugins: [router] } })
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-test="shell"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('login')
  })

  it('renders the shell once a shell route has resolved', async () => {
    const router = makeRouter()
    router.push('/')
    await router.isReady()

    const wrapper = mount(App, { global: { plugins: [router] } })
    await wrapper.vm.$nextTick()

    expect(wrapper.find('[data-test="shell"]').exists()).toBe(true)
    expect(wrapper.text()).not.toContain('Restoring your session')
  })
})
