<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import MobileTabBar from './MobileTabBar.vue'
import NotificationPermissionPrompt from './NotificationPermissionPrompt.vue'
import PersistentFocusBar from './PersistentFocusBar.vue'
import Sidebar from './Sidebar.vue'
import Topbar from './Topbar.vue'
import { useRealtimeNotifications } from '@/composables/useRealtimeNotifications'
import { useAuthStore } from '@/stores/auth'
import { useGroupsStore } from '@/stores/groups'
import { useMentorshipsStore } from '@/stores/mentorships'
import { useRewardsStore } from '@/stores/rewards'
import { useSprintsStore } from '@/stores/sprints'

const route = useRoute()
const auth = useAuthStore()
const sprints = useSprintsStore()
const mentorships = useMentorshipsStore()
const rewards = useRewardsStore()
const groups = useGroupsStore()

const drawerOpen = ref(false)

/**
 * Mounted here, once, for the whole session -- per-route would tear the socket
 * down and re-establish it on every navigation.
 */
useRealtimeNotifications()

onMounted(() => {
  if (!auth.isAuthenticated) {
    return
  }

  /**
   * The running sprint is fetched at shell level, not in FocusView: the timer
   * has to be correct the moment the app opens on any route, because the sprint
   * has been running server-side the whole time the app was closed.
   */
  void sprints.fetchActive()

  /** Sidebar badge counts, and the member roster the mentor picker draws from. */
  void mentorships.fetchAll()
  void rewards.fetchAll()
  void groups.fetchAll()
})

watch(
  () => route.fullPath,
  () => {
    drawerOpen.value = false
  },
)
</script>

<template>
  <div class="pf-grain relative min-h-dvh bg-canvas">
    <!-- Desktop: a persistent rail. The 15rem column is fixed so long pages scroll under it. -->
    <aside
      class="fixed inset-y-0 left-0 z-30 hidden w-60 border-r border-line lg:block"
      aria-label="Sidebar"
    >
      <Sidebar />
    </aside>

    <!-- Mobile: the same nav as a drawer, so there is one navigation model, not two. -->
    <Transition
      enter-active-class="transition-opacity duration-150"
      leave-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="drawerOpen"
        class="fixed inset-0 z-40 bg-canvas-deep/75 backdrop-blur-[2px] lg:hidden"
        aria-hidden="true"
        @click="drawerOpen = false"
      />
    </Transition>

    <Transition
      enter-active-class="transition-transform duration-200 ease-out"
      leave-active-class="transition-transform duration-150 ease-out"
      enter-from-class="-translate-x-full"
      leave-to-class="-translate-x-full"
    >
      <aside
        v-if="drawerOpen"
        class="fixed inset-y-0 left-0 z-50 w-64 border-r border-line lg:hidden"
        aria-label="Navigation"
      >
        <Sidebar @navigate="drawerOpen = false" />
      </aside>
    </Transition>

    <div class="relative z-10 lg:pl-60">
      <Topbar @open-drawer="drawerOpen = true" />

      <main
        id="main"
        class="mx-auto w-full max-w-[84rem] px-4 pb-40 pt-5 sm:px-6 lg:px-8 lg:pb-32"
      >
        <NotificationPermissionPrompt class="mb-5" />

        <RouterView v-slot="{ Component, route: current }">
          <Transition
            mode="out-in"
            enter-active-class="transition-opacity duration-150"
            leave-active-class="transition-opacity duration-100"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
          >
            <component :is="Component" :key="current.path" />
          </Transition>
        </RouterView>
      </main>
    </div>

    <!--
      Rendered outside <router-view> on purpose: an active sprint stays visible
      and controllable across every route change.
    -->
    <PersistentFocusBar />
    <MobileTabBar />
  </div>
</template>
