<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { SPRINT_STATUS_STYLES } from '@/utils/colors'
import { formatDuration } from '@/utils/formatDuration'
import { formatDate, formatDateTime } from '@/utils/date'
import type { PaginationMeta } from '@/types/api'
import type { Sprint } from '@/types/sprint'

const props = defineProps<{
  sprints: Sprint[]
  loading?: boolean
  meta?: PaginationMeta | null
}>()

const emit = defineEmits<{ page: [number] }>()

/** Grouped by local day so a week of sprints reads as days, not as forty rows. */
const groups = computed(() => {
  const buckets = new Map<string, Sprint[]>()

  for (const sprint of props.sprints) {
    const key = (sprint.started_at ?? '').slice(0, 10)
    buckets.set(key, [...(buckets.get(key) ?? []), sprint])
  }

  return [...buckets.entries()].map(([date, items]) => ({
    date,
    items,
    totalSeconds: items.reduce((sum, item) => sum + (item.actual_duration_seconds ?? 0), 0),
  }))
})

const canPrev = computed(() => (props.meta?.current_page ?? 1) > 1)
const canNext = computed(
  () => (props.meta?.current_page ?? 1) < (props.meta?.last_page ?? 1),
)
</script>

<template>
  <div class="space-y-5">
    <SkeletonBlock v-if="loading" :rows="4" height="h-16" rounded="rounded-lg" />

    <EmptyState
      v-else-if="sprints.length === 0"
      icon="timer"
      title="No sprints in this range"
      body="Start a focus sprint and it will show up here, rolled up by day."
    />

    <template v-else>
      <section v-for="group in groups" :key="group.date" class="space-y-2">
        <header class="flex items-baseline justify-between gap-3 px-0.5">
          <h4 class="text-[12.5px] font-semibold text-ink">{{ formatDate(group.date) }}</h4>
          <span class="tnum text-[11.5px] text-ink-faint">
            {{ formatDuration(group.totalSeconds) }} across {{ group.items.length }}
          </span>
        </header>

        <!-- Same rail language as the roadmap: a day is a short vertical run. -->
        <ol class="relative space-y-1.5 pl-5">
          <span class="pf-rail absolute bottom-3 left-[5px] top-3 w-px" aria-hidden="true" />

          <li
            v-for="sprint in group.items"
            :key="sprint.id"
            class="relative rounded-lg border border-line bg-surface p-3"
          >
            <span
              class="absolute -left-5 top-4 size-2 rounded-full ring-2 ring-canvas"
              :class="SPRINT_STATUS_STYLES[sprint.status].dot"
              aria-hidden="true"
            />

            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="min-w-0">
                <p class="truncate text-[13px] font-medium text-ink">
                  {{ sprint.roadmap_item?.title ?? sprint.goal?.title ?? 'Unassigned focus' }}
                </p>
                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px] text-ink-faint">
                  <span class="capitalize">{{ sprint.mode }}</span>
                  <span class="tnum">{{ formatDateTime(sprint.started_at) }}</span>
                  <span v-if="sprint.paused_seconds_total > 0" class="tnum">
                    {{ formatDuration(sprint.paused_seconds_total) }} paused
                  </span>
                </p>
              </div>

              <div class="flex shrink-0 items-center gap-2">
                <span class="tnum text-[14px] font-semibold text-ink">
                  {{ formatDuration(sprint.actual_duration_seconds ?? 0) }}
                </span>
                <BaseBadge
                  :tone="SPRINT_STATUS_STYLES[sprint.status].chip"
                  :dot="SPRINT_STATUS_STYLES[sprint.status].dot"
                >
                  {{ SPRINT_STATUS_STYLES[sprint.status].label }}
                </BaseBadge>
              </div>
            </div>

            <p
              v-if="sprint.notes"
              class="mt-2 border-l-2 border-line pl-2.5 text-[12px] italic leading-relaxed text-ink-muted"
            >
              {{ sprint.notes }}
            </p>
          </li>
        </ol>
      </section>

      <nav
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3 border-t border-line pt-4"
        aria-label="Sprint history pages"
      >
        <p class="tnum text-[11.5px] text-ink-faint">
          {{ meta.from ?? 0 }}-{{ meta.to ?? 0 }} of {{ meta.total }}
        </p>

        <div class="flex gap-1.5">
          <button
            type="button"
            class="inline-flex h-8 items-center gap-1 rounded-md border border-line bg-surface-2 px-2.5 text-[12.5px] font-medium text-ink transition-colors hover:bg-surface-3 disabled:opacity-40"
            :disabled="!canPrev"
            @click="emit('page', meta.current_page - 1)"
          >
            <AppIcon name="chevronLeft" :size="14" />
            Previous
          </button>
          <button
            type="button"
            class="inline-flex h-8 items-center gap-1 rounded-md border border-line bg-surface-2 px-2.5 text-[12.5px] font-medium text-ink transition-colors hover:bg-surface-3 disabled:opacity-40"
            :disabled="!canNext"
            @click="emit('page', meta.current_page + 1)"
          >
            Next
            <AppIcon name="chevronRight" :size="14" />
          </button>
        </div>
      </nav>
    </template>
  </div>
</template>
