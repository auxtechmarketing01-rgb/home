import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from '@/App.vue'
import { router } from '@/router'
import { useAuthStore } from '@/stores/auth'
import { applyStoredTheme } from '@/utils/theme'
import '@/assets/theme.css'

/** Before Vue mounts, so a dark-mode load never flashes white. */
applyStoredTheme()

const app = createApp(App)
app.use(createPinia())

/**
 * A render error must not leave a blank page behind. Without this a single thrown
 * component wipes the tree and the member sees white with nothing in the console
 * they would think to open.
 */
app.config.errorHandler = (error, _instance, info) => {
  console.error('[pathforge] render error', info, error)
}

/**
 * The session resolves *alongside* the mount, never before it.
 *
 * Awaiting the session first was a real bug: `GET /user` is a network round trip,
 * and until it settled nothing was mounted -- so a slow or unreachable API showed
 * a blank white page with no error anywhere. First paint must never depend on the
 * network. Guards await `authReady` instead, so routing still sees a settled auth
 * state without the whole app waiting on it to draw.
 */
const auth = useAuthStore()
void auth.bootstrap()

app.use(router)
app.mount('#app')
