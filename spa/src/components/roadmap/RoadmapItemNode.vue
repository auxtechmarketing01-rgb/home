<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import AssignmentBadge from './AssignmentBadge.vue'
import { ROADMAP_ITEM_STATUS_STYLES } from '@/utils/colors'
import { formatDuration, formatMinutes } from '@/utils/formatDuration'
import { formatShortDate } from '@/utils/date'
import type { RoadmapItem, RoadmapItemStatus } from '@/types/roadmap'

const props = withDefaults(
  defineProps<{
    item: RoadmapItem
    canEdit?: boolean
    canAssign?: boolean
    draggable?: boolean
    /** Disables the move controls at the ends of the list. */
    isFirst?: boolean
    isLast?: boolean
    compact?: boolean
  }>(),
  { canEdit: true },
)

const emit = defineEmits<{
  status: [RoadmapItemStatus]
  edit: []
  destroy: []
  focus: []
  assign: []
  moveUp: []
  moveDown: []
}>()

const style = computed(() => ROADMAP_ITEM_STATUS_STYLES[props.item.status])

const spent = computed(() => props.item.time_spent_seconds ?? 0)

/** Only meaningful once the member has both an estimate and logged time. */
const overEstimate = computed(() => {
  const estimate = props.item.estimated_minutes

  return estimate !== null && spent.value > estimate * 60
})

const nextStatus = computed<RoadmapItemStatus>(() =>
  props.item.status === 'done' ? 'todo' : props.item.status === 'todo' ? 'in_progress' : 'done',
)
</script>

<template>
  <article
    class="group relative rounded-xl border border-line bg-surface transition-colors duration-150 hover:border-line-strong"
    :class="compact ? 'p-3' : 'p-3.5'"
  >
    <div class="flex items-start gap-2.5">
      <span
        v-if="draggable && canEdit"
        class="mt-0.5 cursor-grab text-ink-faint opacity-0 transition-opacity group-hover:opacity-100 active:cursor-grabbing"
        data-drag-handle
        aria-hidden="true"
      >
        <AppIcon name="grip" :size="15" />
      </span>

      <!--
        The status control is a real button with an accessible name, and it
        announces the change - a coloured dot alone would be invisible to a
        screen reader and unclickable on touch.
      -->
      <button
        type="button"
        class="mt-px grid size-5 shrink-0 place-items-center rounded-full border transition-colors duration-150 disabled:cursor-default"
        :class="[
          item.status === 'done'
            ? 'border-transparent bg-status-done text-canvas'
            : item.status === 'in_progress'
              ? 'border-status-progress text-status-progress'
              : 'border-line-strong text-transparent hover:border-brand',
        ]"
        :disabled="!canEdit"
        :aria-label="`${item.title} - ${style.label}. Change status.`"
        @click="emit('status', nextStatus)"
      >
        <AppIcon v-if="item.status === 'done'" name="check" :size="12" :stroke-width="2.5" />
        <AppIcon v-else-if="item.status === 'in_progress'" name="play" :size="9" />
      </button>

      <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
          <h4
            class="text-[13.5px] font-medium leading-snug"
            :class="
              item.status === 'done'
                ? 'text-ink-muted line-through decoration-line-strong'
                : item.status === 'skipped'
                  ? 'text-ink-faint line-through decoration-line-strong'
                  : 'text-ink'
            "
          >
            {{ item.title }}
          </h4>

          <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
            <!-- Keyboard path for reordering. Not a fallback: the only path without a pointer. -->
            <template v-if="draggable && canEdit">
              <button
                type="button"
                class="grid size-7 place-items-center rounded-md text-ink-faint transition-colors hover:bg-surface-2 hover:text-ink disabled:opacity-30"
                :disabled="isFirst"
                :aria-label="`Move ${item.title} up`"
                @click="emit('moveUp')"
              >
                <AppIcon name="arrowUp" :size="14" />
              </button>
              <button
                type="button"
                class="grid size-7 place-items-center rounded-md text-ink-faint transition-colors hover:bg-surface-2 hover:text-ink disabled:opacity-30"
                :disabled="isLast"
                :aria-label="`Move ${item.title} down`"
                @click="emit('moveDown')"
              >
                <AppIcon name="arrowDown" :size="14" />
              </button>
            </template>

            <button
              type="button"
              class="grid size-7 place-items-center rounded-md text-ink-faint transition-colors hover:bg-surface-2 hover:text-brand"
              :aria-label="`Start a focus sprint on ${item.title}`"
              @click="emit('focus')"
            >
              <AppIcon name="timer" :size="14" />
            </button>

            <button
              v-if="canAssign"
              type="button"
              class="grid size-7 place-items-center rounded-md text-ink-faint transition-colors hover:bg-surface-2 hover:text-violet"
              :aria-label="`Set an expectation on ${item.title}`"
              @click="emit('assign')"
            >
              <AppIcon name="handshake" :size="14" />
            </button>

            <button
              v-if="canEdit"
              type="button"
              class="grid size-7 place-items-center rounded-md text-ink-faint transition-colors hover:bg-surface-2 hover:text-ink"
              :aria-label="`Edit ${item.title}`"
              @click="emit('edit')"
            >
              <AppIcon name="pencil" :size="14" />
            </button>

            <button
              v-if="canEdit"
              type="button"
              class="grid size-7 place-items-center rounded-md text-ink-faint transition-colors hover:bg-danger-soft hover:text-danger"
              :aria-label="`Delete ${item.title}`"
              @click="emit('destroy')"
            >
              <AppIcon name="trash" :size="14" />
            </button>
          </div>
        </div>

        <p
          v-if="item.description && !compact"
          class="mt-1 line-clamp-2 text-[12.5px] leading-relaxed text-ink-muted"
        >
          {{ item.description }}
        </p>

        <div class="mt-2 flex flex-wrap items-center gap-1.5">
          <BaseBadge :tone="style.chip" :dot="style.dot">{{ style.label }}</BaseBadge>

          <BaseBadge v-if="item.day_number" tone="bg-surface-2 text-ink-muted border-line">
            <span class="tnum">Day {{ item.day_number }}</span>
          </BaseBadge>

          <BaseBadge v-if="item.scheduled_date" tone="bg-surface-2 text-ink-muted border-line">
            <AppIcon name="calendar" :size="11" />
            <span class="tnum">{{ formatShortDate(item.scheduled_date) }}</span>
          </BaseBadge>

          <BaseBadge v-if="item.estimated_minutes !== null" tone="bg-surface-2 text-ink-muted border-line">
            <AppIcon name="hourglass" :size="11" />
            <span class="tnum">est {{ formatMinutes(item.estimated_minutes) }}</span>
          </BaseBadge>

          <BaseBadge
            v-if="spent > 0"
            :tone="
              overEstimate
                ? 'bg-ember-soft text-ember border-ember/25'
                : 'bg-brand-soft text-brand border-brand/25'
            "
          >
            <AppIcon name="timer" :size="11" />
            <span class="tnum">{{ formatDuration(spent) }} logged</span>
          </BaseBadge>

          <AssignmentBadge :item="item" />
        </div>

        <p
          v-if="item.reflection_note && !compact"
          class="mt-2 border-l-2 border-line pl-2.5 text-[12px] italic leading-relaxed text-ink-muted"
        >
          {{ item.reflection_note }}
        </p>
      </div>
    </div>
  </article>
</template>
