<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { onClickOutside } from '@vueuse/core'
import AppIcon from '@/components/ui/AppIcon.vue'
import { humanise } from '@/composables/useRealtimeNotifications'
import { useNotificationsStore } from '@/stores/notifications'
import { formatRelative } from '@/utils/date'

const notifications = useNotificationsStore()

const open = ref(false)
const panel = ref<HTMLElement | null>(null)

onClickOutside(panel, () => {
  open.value = false
})

onMounted(() => {
  void notifications.fetchAll()
})

const rows = computed(() => notifications.items.slice(0, 12))

/**
 * The socket is a freshness signal, not a gate: the list is populated by
 * fetching, so a dead connection costs live delivery and nothing else. Hence a
 * quiet inline hint rather than an error state.
 */
const reconnecting = computed(() => notifications.socketState === 'reconnecting')

function bodyOf(payload: Record<string, unknown>): string | null {
  const value = payload.body ?? payload.message

  return typeof value === 'string' ? value : null
}
</script>

<template>
  <div ref="panel" class="relative">
    <button
      type="button"
      class="relative grid size-9 place-items-center rounded-lg text-ink-muted transition-colors duration-150 hover:bg-surface-2 hover:text-ink"
      :aria-expanded="open"
      aria-haspopup="true"
      :aria-label="
        notifications.unreadCount > 0
          ? `Notifications, ${notifications.unreadCount} unread`
          : 'Notifications'
      "
      @click="open = !open"
    >
      <AppIcon name="bell" :size="18" />
      <span
        v-if="notifications.unreadCount > 0"
        class="tnum absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-ember px-1 text-[9.5px] font-bold text-ember-ink"
        aria-hidden="true"
      >
        {{ notifications.unreadCount > 9 ? '9+' : notifications.unreadCount }}
      </span>
    </button>

    <div
      v-if="open"
      class="pf-rise absolute right-0 top-11 z-40 w-[22rem] max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-line bg-surface"
      role="region"
      aria-label="Notifications"
    >
      <header class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
        <p class="text-[13px] font-semibold text-ink">Notifications</p>
        <button
          v-if="notifications.unreadCount > 0"
          type="button"
          class="text-[11.5px] font-medium text-brand transition-opacity hover:opacity-75"
          @click="notifications.markAllRead()"
        >
          Mark all read
        </button>
      </header>

      <p
        v-if="reconnecting"
        class="flex items-center gap-1.5 border-b border-line bg-surface-2 px-4 py-2 text-[11px] text-ink-faint"
      >
        <AppIcon name="wifiOff" :size="12" />
        Reconnecting - the list is still up to date as of your last load.
      </p>

      <ul v-if="rows.length > 0" class="max-h-[26rem] divide-y divide-line overflow-y-auto">
        <li v-for="row in rows" :key="row.id">
          <button
            type="button"
            class="flex w-full items-start gap-2.5 px-4 py-3 text-left transition-colors hover:bg-surface-2"
            @click="notifications.markRead(row.id)"
          >
            <span
              class="mt-1.5 size-1.5 shrink-0 rounded-full"
              :class="row.read_at === null ? 'bg-ember' : 'bg-transparent'"
              aria-hidden="true"
            />
            <span class="min-w-0 flex-1">
              <span class="block text-[13px] font-medium leading-snug text-ink">
                {{ humanise(row.type) }}
              </span>
              <span
                v-if="bodyOf(row.payload)"
                class="mt-0.5 block truncate text-[12px] text-ink-muted"
              >
                {{ bodyOf(row.payload) }}
              </span>
              <span class="mt-1 block text-[10.5px] text-ink-faint">
                {{ formatRelative(row.created_at) }}
              </span>
            </span>
          </button>
        </li>
      </ul>

      <p v-else class="px-4 py-8 text-center text-[13px] text-ink-faint">
        Nothing yet. Sprints, rewards and mentor activity land here.
      </p>
    </div>
  </div>
</template>
