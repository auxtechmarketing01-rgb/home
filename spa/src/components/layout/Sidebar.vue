<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BrandMark from './BrandMark.vue'
import { useAuthStore } from '@/stores/auth'
import { useMentorshipsStore } from '@/stores/mentorships'
import { useRewardsStore } from '@/stores/rewards'
import type { IconName } from '@/components/ui/icons'

const emit = defineEmits<{ navigate: [] }>()

const auth = useAuthStore()
const mentorships = useMentorshipsStore()
const rewards = useRewardsStore()

interface NavItem {
  to: string
  label: string
  icon: IconName
  badge?: number
}

/**
 * Grouped by what the member is doing, not by data model: their own work, the
 * circle they compare inside, and the numbers. Seven destinations in a flat list
 * is a wall; three groups of two or three is a map.
 */
const groups = computed<Array<{ heading: string; items: NavItem[] }>>(() => [
  {
    heading: 'Work',
    items: [
      { to: '/', label: 'Today', icon: 'home' },
      { to: '/goals', label: 'Goals', icon: 'target' },
      { to: '/focus', label: 'Focus', icon: 'timer' },
    ],
  },
  {
    heading: 'Circle',
    items: [
      { to: '/groups', label: 'Groups', icon: 'users' },
      {
        to: '/mentorships',
        label: 'Mentorship',
        icon: 'handshake',
        badge: mentorships.pendingForMe.length,
      },
      { to: '/rewards', label: 'Rewards', icon: 'gift', badge: rewards.actionable.length },
    ],
  },
  {
    heading: 'Insight',
    items: [{ to: '/analytics', label: 'Analytics', icon: 'chart' }],
  },
])

const initials = computed(() => {
  const name = auth.user?.name ?? ''

  return (
    name
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join('') || 'P'
  )
})
</script>

<template>
  <div class="flex h-full flex-col bg-canvas-deep">
    <div class="flex h-14 items-center px-5">
      <RouterLink to="/" class="rounded-lg" aria-label="Pathforge home" @click="emit('navigate')">
        <BrandMark :size="26" with-wordmark />
      </RouterLink>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Main">
      <div v-for="group in groups" :key="group.heading" class="mb-5">
        <p class="mb-1.5 px-2.5 text-[10.5px] font-semibold uppercase tracking-[0.14em] text-ink-faint">
          {{ group.heading }}
        </p>

        <ul class="space-y-0.5">
          <li v-for="item in group.items" :key="item.to">
            <RouterLink
              v-slot="{ isActive, isExactActive }"
              :to="item.to"
              custom
            >
              <a
                :href="item.to"
                :aria-current="(item.to === '/' ? isExactActive : isActive) ? 'page' : undefined"
                :class="[
                  'group relative flex h-9 items-center gap-2.5 rounded-lg px-2.5 text-[13.5px] transition-colors duration-150',
                  (item.to === '/' ? isExactActive : isActive)
                    ? 'bg-surface font-semibold text-ink'
                    : 'font-medium text-ink-muted hover:bg-surface/60 hover:text-ink',
                ]"
                @click.prevent="
                  () => {
                    $router.push(item.to)
                    emit('navigate')
                  }
                "
              >
                <!-- The active marker is a rail segment, matching the roadmap language. -->
                <span
                  v-if="item.to === '/' ? isExactActive : isActive"
                  class="absolute -left-3 top-1/2 h-5 w-[2px] -translate-y-1/2 rounded-r-full bg-brand"
                  aria-hidden="true"
                />
                <AppIcon
                  :name="item.icon"
                  :size="17"
                  :class="
                    (item.to === '/' ? isExactActive : isActive) ? 'text-brand' : 'text-ink-faint group-hover:text-ink-muted'
                  "
                />
                <span class="flex-1 truncate">{{ item.label }}</span>
                <span
                  v-if="item.badge"
                  class="tnum grid h-4.5 min-w-4.5 place-items-center rounded-full bg-ember px-1 text-[10px] font-bold text-ember-ink"
                  :aria-label="`${item.badge} needing attention`"
                >
                  {{ item.badge > 9 ? '9+' : item.badge }}
                </span>
              </a>
            </RouterLink>
          </li>
        </ul>
      </div>
    </nav>

    <div class="border-t border-line p-3">
      <RouterLink
        to="/settings"
        class="flex items-center gap-2.5 rounded-lg p-2 transition-colors hover:bg-surface"
        @click="emit('navigate')"
      >
        <span
          class="grid size-8 shrink-0 place-items-center rounded-full border border-brand/30 bg-brand-soft text-[11.5px] font-bold text-brand"
          aria-hidden="true"
        >
          {{ initials }}
        </span>
        <span class="min-w-0 flex-1">
          <span class="block truncate text-[13px] font-medium text-ink">
            {{ auth.user?.name ?? 'Member' }}
          </span>
          <span class="block truncate text-[11px] text-ink-faint">
            {{ auth.gamificationEnabled ? `Level ${auth.user?.level ?? 1}` : 'Settings' }}
          </span>
        </span>
        <AppIcon name="sliders" :size="15" class="text-ink-faint" />
      </RouterLink>
    </div>
  </div>
</template>
