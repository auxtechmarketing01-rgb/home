import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  /**
   * Where the dev proxy forwards API traffic. Overridable because a machine can
   * have the conventional port shadowed by something that accepts the TCP
   * connection and never answers -- set VITE_DEV_API_PROXY and move on rather
   * than editing this file.
   */
  const apiProxyTarget = env.VITE_DEV_API_PROXY || 'http://127.0.0.1:8000'

  const proxyEntry = { target: apiProxyTarget, changeOrigin: false }

  return {
    plugins: [
      vue(),
      tailwindcss(),
      /**
       * Installability is what makes Web Push work at all on iOS (03 section 9),
       * so the PWA plugin ships with the push feature rather than after it.
       * `injectManifest` keeps our hand-written push/notificationclick handlers
       * in `src/sw.ts` -- `generateSW` would overwrite them with a pure caching
       * worker and silently drop push.
       */
      VitePWA({
        strategies: 'injectManifest',
        srcDir: 'src',
        filename: 'sw.ts',
        injectRegister: false,
        registerType: 'autoUpdate',
        manifest: {
          name: 'Pathforge',
          short_name: 'Pathforge',
          description: 'Set goals, forge roadmaps, run focus sprints.',
          theme_color: '#0e1315',
          background_color: '#0e1315',
          display: 'standalone',
          start_url: '/',
          /**
           * SVG icons -- Chrome, Edge and Android accept them directly. iOS home
           * screen install (and therefore iOS Web Push) wants a raster
           * `apple-touch-icon`; drop a 180x180 PNG at
           * /icons/apple-touch-icon.png and the link tag in index.html uses it.
           */
          icons: [
            { src: '/icons/icon.svg', sizes: 'any', type: 'image/svg+xml' },
            {
              src: '/icons/icon-maskable.svg',
              sizes: 'any',
              type: 'image/svg+xml',
              purpose: 'maskable',
            },
          ],
        },
        devOptions: { enabled: false, type: 'module' },
      }),
    ],
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: {
      port: 5173,
      strictPort: true,
      /** Binds every interface, so localhost, 127.0.0.1 and the LAN IP all work. */
      host: true,
      /**
       * In development the API is proxied rather than called cross-origin.
       *
       * Two concrete reasons, both of which bite on a Windows dev box:
       * `localhost` can resolve to `::1` while `php artisan serve` binds IPv4
       * only, and a cross-origin setup makes every request depend on CORS plus
       * third-party cookie rules. Proxying to an IPv4 literal removes both --
       * the browser only ever talks to one origin.
       *
       * `changeOrigin` stays false on purpose: the forwarded Host must remain
       * the SPA's own host so Sanctum still sees a domain listed in
       * SANCTUM_STATEFUL_DOMAINS and keeps the session stateful.
       *
       * Production is unaffected: set VITE_API_BASE_URL to the absolute API URL
       * and the client goes straight there, cross-origin, per 03 section 1.
       */
      proxy: {
        '/api': proxyEntry,
        '/sanctum': proxyEntry,
        '/storage': proxyEntry,
      },
    },
    test: {
      environment: 'jsdom',
      globals: true,
      setupFiles: ['./tests/setup.ts'],
      include: ['src/**/*.spec.ts', 'tests/**/*.spec.ts'],
    },
  }
})
