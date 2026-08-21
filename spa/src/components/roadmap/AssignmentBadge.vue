<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { formatMinutes } from '@/utils/formatDuration'
import { formatShortDate, isPast } from '@/utils/date'
import type { RoadmapItem } from '@/types/roadmap'

const props = defineProps<{ item: RoadmapItem }>()

/**
 * Read-only, always. A mentor sets expectations; only the mentee edits the item
 * or marks it done (FR-MENT-04/06). This badge exists so the mentee can see what
 * was set without it looking like a field they own.
 */
const hasAssignment = computed(
  () => props.item.assigned_minutes !== null || props.item.assigned_due_at !== null,
)

const overdue = computed(
  () => props.item.assigned_due_at !== null && props.item.status !== 'done' && isPast(props.item.assigned_due_at),
)

const mentorName = computed(() => props.item.assigned_by_mentor?.name ?? null)
</script>

<template>
  <div
    v-if="hasAssignment"
    class="inline-flex flex-wrap items-center gap-x-2.5 gap-y-1 rounded-md border px-2 py-1 text-[11.5px]"
    :class="overdue ? 'border-danger/30 bg-danger-soft' : 'border-violet/25 bg-violet/10'"
  >
    <span
      class="inline-flex items-center gap-1 font-medium"
      :class="overdue ? 'text-danger' : 'text-violet'"
    >
      <AppIcon name="handshake" :size="12" />
      {{ mentorName ? `${mentorName} set` : 'Mentor set' }}
    </span>

    <!--
      `assigned_minutes` is the mentor's expectation and is deliberately distinct
      from the member's own `estimated_minutes` -- never shown as the same figure.
    -->
    <span v-if="item.assigned_minutes !== null" class="tnum text-ink-muted">
      {{ formatMinutes(item.assigned_minutes) }}
    </span>

    <span
      v-if="item.assigned_due_at"
      class="tnum"
      :class="overdue ? 'font-medium text-danger' : 'text-ink-muted'"
    >
      {{ overdue ? 'overdue' : 'due' }} {{ formatShortDate(item.assigned_due_at) }}
    </span>
  </div>
</template>
