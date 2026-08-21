import { computed, ref, watch } from 'vue'
import { useRoadmapsStore } from '@/stores/roadmaps'
import { useDragReorder } from '@/composables/useDragReorder'
import type { RoadmapItem, RoadmapItemStatus } from '@/types/roadmap'

export type RoadmapRenderMode = 'timeline' | 'kanban'

const RENDER_MODE_KEY = 'pathforge:roadmap-view'

/**
 * Local reactive state for the builder: the drag draft, the render mode, and a
 * debounced commit so a member dragging three items in a row sends one request
 * rather than three.
 *
 * The Kanban columns are derived from the *same* draft the timeline renders --
 * two renderers, one data source.
 */
export function useRoadmapBuilder(roadmapId: () => number | null, debounceMs = 400) {
  const store = useRoadmapsStore()

  const items = computed(() => store.items(roadmapId()))

  const renderMode = ref<RoadmapRenderMode>(
    localStorage.getItem(RENDER_MODE_KEY) === 'kanban' ? 'kanban' : 'timeline',
  )

  watch(renderMode, (mode) => localStorage.setItem(RENDER_MODE_KEY, mode))

  let debounceHandle: number | undefined

  const reorder = useDragReorder(items, (ordered) => {
    const id = roadmapId()

    if (!id) {
      return Promise.resolve(false)
    }

    return new Promise<boolean>((resolve) => {
      if (debounceHandle !== undefined) {
        window.clearTimeout(debounceHandle)
      }

      debounceHandle = window.setTimeout(() => {
        void store.reorder(id, ordered).then(resolve)
      }, debounceMs)
    })
  })

  const columns = computed<Array<{ status: RoadmapItemStatus; items: RoadmapItem[] }>>(() => {
    const statuses: RoadmapItemStatus[] = ['todo', 'in_progress', 'done', 'skipped']

    return statuses.map((status) => ({
      status,
      items: reorder.draft.value.filter((item) => item.status === status),
    }))
  })

  const totals = computed(() => {
    const all = reorder.draft.value

    return {
      count: all.length,
      done: all.filter((item) => item.status === 'done').length,
      estimatedMinutes: all.reduce((sum, item) => sum + (item.estimated_minutes ?? 0), 0),
      spentSeconds: all.reduce((sum, item) => sum + (item.time_spent_seconds ?? 0), 0),
      assigned: all.filter((item) => item.assigned_minutes !== null || item.assigned_due_at !== null)
        .length,
    }
  })

  return { items, renderMode, columns, totals, ...reorder }
}
