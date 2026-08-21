<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useSprintsStore } from '@/stores/sprints'
import type { IconName } from '@/components/ui/icons'

const sprints = useSprintsStore()

/**
 * Five destinations, hard cap. A sixth turns a thumb-reachable bar into a row of
 * unreadable icons -- the rest of the nav lives in the drawer, which is the same
 * component the desktop rail uses.
 */
const items: Array<{ to: string; label: string; icon: IconName; exact?: boolean }> = [
  { to: '/', label: 'Today', icon: 'home', exact: true },
  { to: '/goals', label: 'Goals', icon: 'target' },
  { to: '/focus', label: 'Focus', icon: 'timer' },
  { to: '/groups', label: 'Groups', icon: 'users' },
  { to: '/analytics', label: 'Stats', icon: 'chart' },
]

/** Nudged up when the focus bar is docked, so the two never overlap. */
const raised = computed(() => sprints.hasActiveSprint)
</script>

<template>
  <nav
    class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-canvas/95 backdrop-blur-md lg:hidden"
    :class="raised ? '' : ''"
    aria-label="Primary"
  >
    <ul class="flex items-stretch pb-[env(safe-area-inset-bottom)]">
      <li v-for="item in items" :key="item.to" class="flex-1">
        <RouterLink v-slot="{ isActive, isExactActive }" :to="item.to" custom>
          <a
            :href="item.to"
            :aria-current="(item.exact ? isExactActive : isActive) ? 'page' : undefined"
            class="flex h-14 min-w-11 flex-col items-center justify-center gap-1 transition-colors duration-150"
            :class="(item.exact ? isExactActive : isActive) ? 'text-brand' : 'text-ink-faint'"
            @click.prevent="$router.push(item.to)"
          >
            <AppIcon :name="item.icon" :size="19" />
            <span class="text-[10px] font-medium tracking-wide">{{ item.label }}</span>
          </a>
        </RouterLink>
      </li>
    </ul>
  </nav>
</template>
