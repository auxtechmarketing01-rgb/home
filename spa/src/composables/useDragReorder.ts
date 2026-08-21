import { computed, ref, watch, type Ref } from 'vue'
import type { RoadmapItem } from '@/types/roadmap'

/**
 * Thin wrapper over the draggable list. It holds the in-flight local order so
 * the list does not snap back mid-drag, and emits the committed order once --
 * batched, never one request per row.
 *
 * Drag is not the only way to reorder: `moveUp`/`moveDown` are the keyboard
 * path, and they are first-class rather than a fallback bolted on later.
 */
export function useDragReorder(
  source: Ref<RoadmapItem[]>,
  commit: (ordered: RoadmapItem[]) => Promise<boolean>,
) {
  const draft = ref<RoadmapItem[]>([...source.value])
  const committing = ref(false)

  watch(
    source,
    (next) => {
      if (!committing.value) {
        draft.value = [...next]
      }
    },
    { deep: false },
  )

  const isDirty = computed(
    () => draft.value.map((item) => item.id).join(',') !== source.value.map((item) => item.id).join(','),
  )

  async function commitOrder(ordered?: RoadmapItem[]): Promise<boolean> {
    const next = ordered ?? draft.value
    draft.value = [...next]
    committing.value = true

    try {
      const ok = await commit(next)

      if (!ok) {
        draft.value = [...source.value]
      }

      return ok
    } finally {
      committing.value = false
    }
  }

  /** Called by the draggable component after a drop -- draft is already mutated. */
  function onDrop(): Promise<boolean> {
    return commitOrder()
  }

  function moveBy(itemId: number, delta: -1 | 1): Promise<boolean> {
    const index = draft.value.findIndex((item) => item.id === itemId)
    const target = index + delta

    if (index === -1 || target < 0 || target >= draft.value.length) {
      return Promise.resolve(false)
    }

    const next = [...draft.value]
    const [moved] = next.splice(index, 1)
    next.splice(target, 0, moved as RoadmapItem)

    return commitOrder(next)
  }

  return {
    draft,
    committing,
    isDirty,
    onDrop,
    commitOrder,
    moveUp: (itemId: number) => moveBy(itemId, -1),
    moveDown: (itemId: number) => moveBy(itemId, 1),
  }
}
