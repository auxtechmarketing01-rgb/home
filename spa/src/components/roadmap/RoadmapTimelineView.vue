<script setup lang="ts">
import { computed } from 'vue'
// @ts-expect-error -- vuedraggable ships no types for its Vue 3 build.
import draggable from 'vuedraggable'
import EmptyState from '@/components/ui/EmptyState.vue'
import RoadmapItemNode from './RoadmapItemNode.vue'
import type { RoadmapItem, RoadmapItemStatus } from '@/types/roadmap'

const props = withDefaults(
  defineProps<{
    items: RoadmapItem[]
    canEdit?: boolean
    canAssign?: boolean
    reordering?: boolean
  }>(),
  { canEdit: true },
)

const emit = defineEmits<{
  'update:items': [RoadmapItem[]]
  drop: []
  status: [RoadmapItem, RoadmapItemStatus]
  edit: [RoadmapItem]
  destroy: [RoadmapItem]
  focus: [RoadmapItem]
  assign: [RoadmapItem]
  moveUp: [RoadmapItem]
  moveDown: [RoadmapItem]
  create: []
}>()

const list = computed({
  get: () => props.items,
  set: (value: RoadmapItem[]) => emit('update:items', value),
})

const lastIndex = computed(() => props.items.length - 1)
</script>

<template>
  <div>
    <EmptyState
      v-if="items.length === 0"
      icon="route"
      title="No steps yet"
      body="Break the goal into ordered steps. Each one can hold its own focus time, notes and files."
    >
      <button
        v-if="canEdit"
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3.5 text-[13px] font-semibold text-brand-ink transition-colors hover:bg-brand-hover"
        @click="emit('create')"
      >
        Add the first step
      </button>
    </EmptyState>

    <!--
      The rail: one hairline running the full height with a node per step. This is
      the product's signature structure, and the same language the brand mark and
      the sidebar active marker use.
    -->
    <div v-else class="relative pl-7 sm:pl-9">
      <span
        class="pf-rail absolute bottom-4 left-[9px] top-4 w-px sm:left-[13px]"
        aria-hidden="true"
      />

      <draggable
        v-model="list"
        item-key="id"
        handle="[data-drag-handle]"
        :disabled="!canEdit"
        ghost-class="opacity-40"
        drag-class="cursor-grabbing"
        :animation="180"
        class="space-y-2.5"
        tag="ol"
        @end="emit('drop')"
      >
        <template #item="{ element, index }">
          <li class="relative">
            <span
              class="absolute -left-7 top-5 grid size-[18px] place-items-center rounded-full border-2 bg-canvas sm:-left-9"
              :class="{
                'border-status-done': element.status === 'done',
                'border-status-progress': element.status === 'in_progress',
                'border-line-strong': element.status === 'todo',
                'border-line': element.status === 'skipped',
              }"
              aria-hidden="true"
            >
              <span
                v-if="element.status === 'done'"
                class="size-1.5 rounded-full bg-status-done"
              />
            </span>

            <RoadmapItemNode
              :item="element"
              :can-edit="canEdit"
              :can-assign="canAssign"
              draggable
              :is-first="index === 0"
              :is-last="index === lastIndex"
              @status="emit('status', element, $event)"
              @edit="emit('edit', element)"
              @destroy="emit('destroy', element)"
              @focus="emit('focus', element)"
              @assign="emit('assign', element)"
              @move-up="emit('moveUp', element)"
              @move-down="emit('moveDown', element)"
            />
          </li>
        </template>
      </draggable>

      <p
        v-if="reordering"
        class="mt-3 text-[11.5px] text-ink-faint"
        role="status"
        aria-live="polite"
      >
        Saving order...
      </p>
    </div>
  </div>
</template>
