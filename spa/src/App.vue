<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import ToastHost from '@/components/ui/ToastHost.vue'

const route = useRoute()

/**
 * Vue Router's first navigation is asynchronous, and the guard inside it awaits
 * the session probe. Until that settles, `route` is still START_LOCATION: no
 * name, no meta, and an empty `matched`. `matched` is what is checked here
 * because it is the only one of the three that cannot be produced by a real
 * route.
 *
 * This matters beyond tidiness. Reading `meta.layout` off START_LOCATION yields
 * `undefined`, which is not `'bare'`, so the app used to mount `AppShell`
 * during that window -- flashing the sidebar in front of the login screen and,
 * worse, mounting `NotificationBell`, whose `onMounted` fetch then fired before
 * anyone was authenticated and came back 401 on every cold load.
 */
const routeResolved = computed(() => route.matched.length > 0)

/** Auth screens get the full viewport; everything else lives inside the shell. */
const isBare = computed(() => route.meta.layout === 'bare')
</script>

<template>
  <ToastHost />

  <template v-if="routeResolved">
    <RouterView v-if="isBare" />
    <AppShell v-else />
  </template>

  <!--
    Deliberately the same composition as the pre-mount shell in index.html, so
    handing over from static markup to Vue is not a visible event.
  -->
  <div
    v-else
    class="fixed inset-0 flex flex-col items-center justify-center gap-2 bg-canvas"
    role="status"
    aria-live="polite"
  >
    <div
      class="mb-1 size-[34px] animate-spin rounded-[10px] border-2 border-brand/35 border-t-brand"
      aria-hidden="true"
    ></div>
    <p class="font-display text-[15px] font-semibold tracking-tight text-ink">Pathforge</p>
    <p class="text-[12.5px] text-ink-muted">Restoring your session…</p>
  </div>
</template>
