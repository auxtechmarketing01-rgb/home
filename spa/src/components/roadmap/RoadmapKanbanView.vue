<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
import RoadmapItemNode from './RoadmapItemNode.vue'
import { ROADMAP_ITEM_STATUS_STYLES } from '@/utils/colors'
import type { RoadmapItem, RoadmapItemStatus } from '@/types/roadmap'

/**
 * A second *renderer* over the same store data the timeline reads -- never a
 * second data source. Columns are derived, so a status change in either view
 * shows up in both without a refetch.
 */
withDefaults(
  defineProps<{
    columns: Array<{ status: RoadmapItemStatus; items: RoadmapItem[] }>
    canEdit?: boolean
    canAssign?: boolean
  }>(),
  { canEdit: true },
)

const emit = defineEmits<{
  status: [RoadmapItem, RoadmapItemStatus]
  edit: [RoadmapItem]
  destroy: [RoadmapItem]
  focus: [RoadmapItem]
  assign: [RoadmapItem]
}>()
</script>

<template>
  <div class="-mx-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:px-0">
    <div class="grid min-w-[52rem] grid-cols-4 gap-3">
      <section
        v-for="column in columns"
        :key="column.status"
        class="flex flex-col gap-2.5 rounded-xl border border-line bg-canvas-deep p-2.5"
        :aria-label="ROADMAP_ITEM_STATUS_STYLES[column.status].label"
      >
        <header class="flex items-center gap-2 px-1 pt-0.5">
          <span
            class="size-1.5 rounded-full"
            :class="ROADMAP_ITEM_STATUS_STYLES[column.status].dot"
            aria-hidden="true"
          />
          <h4 class="text-[12.5px] font-semibold text-ink">
            {{ ROADMAP_ITEM_STATUS_STYLES[column.status].label }}
          </h4>
          <span class="tnum ml-auto text-[11px] text-ink-faint">{{ column.items.length }}</span>
        </header>

        <RoadmapItemNode
          v-for="item in column.items"
          :key="item.id"
          :item="item"
          :can-edit="canEdit"
          :can-assign="canAssign"
          compact
          @status="emit('status', item, $event)"
          @edit="emit('edit', item)"
          @destroy="emit('destroy', item)"
          @focus="emit('focus', item)"
          @assign="emit('assign', item)"
        />

        <p
          v-if="column.items.length === 0"
          class="flex items-center justify-center gap-1.5 rounded-lg border border-dashed border-line px-3 py-6 text-[11.5px] text-ink-faint"
        >
          <AppIcon name="minus" :size="12" />
          Nothing here
        </p>
      </section>
    </div>
  </div>
</template>
