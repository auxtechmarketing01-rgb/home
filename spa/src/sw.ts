/// <reference lib="webworker" />

/**
 * Service worker. `injectManifest` mode: the plugin injects the precache list
 * below and leaves the push logic alone, which is the point -- `generateSW`
 * would replace this file with a pure caching worker and silently drop Web Push.
 *
 * Deliberately dependency-free. Workbox is not imported: the caching this app
 * needs is one navigation fallback plus the build's own assets, and pulling in
 * workbox-precaching for that would mean depending on a package the SPA does
 * not otherwise declare.
 */
declare const self: ServiceWorkerGlobalScope & {
  __WB_MANIFEST: Array<{ url: string; revision: string | null }>
}

const CACHE_NAME = 'pathforge-shell-v1'

/**
 * The build's asset list, injected here. It must be *used* -- a reference the
 * bundler can tree-shake leaves Workbox with no injection point and fails the
 * build.
 */
const PRECACHE_URLS: string[] = self.__WB_MANIFEST.map((entry) => entry.url)

self.addEventListener('install', (event) => {
  event.waitUntil(
    (async () => {
      const cache = await caches.open(CACHE_NAME)
      /** Individually, so one 404 does not abort the whole install. */
      await Promise.allSettled(PRECACHE_URLS.map((url) => cache.add(url)))
      await self.skipWaiting()
    })(),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const names = await caches.keys()
      await Promise.all(
        names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name)),
      )
      await self.clients.claim()
    })(),
  )
})

/**
 * Navigations fall back to the cached shell when the network is gone, which is
 * what makes the app installable and keeps a cold offline launch from showing a
 * browser error page. Everything else is cache-first on precached assets only --
 * API responses are never cached, because stale goal data is worse than none.
 */
self.addEventListener('fetch', (event) => {
  const { request } = event

  if (request.method !== 'GET') {
    return
  }

  if (request.mode === 'navigate') {
    event.respondWith(
      (async () => {
        try {
          return await fetch(request)
        } catch {
          const cached = await caches.match('/index.html')

          return cached ?? Response.error()
        }
      })(),
    )

    return
  }

  const url = new URL(request.url)

  if (url.origin !== self.location.origin) {
    return
  }

  event.respondWith(
    (async () => {
      const cached = await caches.match(request)

      return cached ?? fetch(request)
    })(),
  )
})

interface PushPayload {
  title?: string
  body?: string
  url?: string
  tag?: string
}

self.addEventListener('push', (event) => {
  let data: PushPayload = {}

  try {
    data = (event.data?.json() as PushPayload) ?? {}
  } catch {
    data = { body: event.data?.text() ?? '' }
  }

  event.waitUntil(
    self.registration.showNotification(data.title ?? 'Pathforge', {
      body: data.body ?? '',
      icon: '/icons/icon.svg',
      badge: '/icons/icon.svg',
      /** Collapses repeats of the same subject rather than stacking them. */
      tag: data.tag,
      data: { url: data.url ?? '/' },
    }),
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()

  const target = (event.notification.data as { url?: string } | undefined)?.url ?? '/'

  event.waitUntil(
    (async () => {
      const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true })

      /** Focus an existing tab rather than opening a duplicate of the app. */
      for (const client of clients) {
        if ('focus' in client) {
          await client.focus()
          await client.navigate(new URL(target, self.location.origin).href).catch(() => undefined)

          return
        }
      }

      await self.clients.openWindow(target)
    })(),
  )
})
