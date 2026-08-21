import { defineStore } from 'pinia'
import { ref } from 'vue'
import { toApiFailure } from '@/api/client'
import { roadmapsApi } from '@/api/roadmaps'
import type { ApiFailure } from '@/types/api'
import type {
  AssignmentPayload,
  ReorderEntry,
  RoadmapItem,
  RoadmapItemPayload,
  RoadmapItemStatus,
  RoadmapItemUpdatePayload,
} from '@/types/roadmap'

export const useRoadmapsStore = defineStore('roadmaps', () => {
  /**
   * Keyed by roadmap id. Both the Timeline and the Kanban renderer read this one
   * array -- the Kanban is a second *view*, never a second data source.
   */
  const itemsByRoadmap = ref<Record<number, RoadmapItem[]>>({})
  const loading = ref(false)
  const saving = ref(false)
  const failure = ref<ApiFailure | null>(null)

  function items(roadmapId: number | null | undefined): RoadmapItem[] {
    return roadmapId ? (itemsByRoadmap.value[roadmapId] ?? []) : []
  }

  function setItems(roadmapId: number, next: RoadmapItem[]): void {
    itemsByRoadmap.value = {
      ...itemsByRoadmap.value,
      [roadmapId]: [...next].sort((a, b) => a.position - b.position || a.id - b.id),
    }
  }

  function replaceItem(item: RoadmapItem): void {
    const current = itemsByRoadmap.value[item.roadmap_id] ?? []
    setItems(
      item.roadmap_id,
      current.some((entry) => entry.id === item.id)
        ? current.map((entry) => (entry.id === item.id ? { ...entry, ...item } : entry))
        : [...current, item],
    )
  }

  async function fetchItems(roadmapId: number): Promise<void> {
    loading.value = true
    failure.value = null

    try {
      setItems(roadmapId, await roadmapsApi.items(roadmapId))
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load the roadmap.')
    } finally {
      loading.value = false
    }
  }

  async function createItem(
    roadmapId: number,
    payload: RoadmapItemPayload,
  ): Promise<RoadmapItem | null> {
    saving.value = true
    failure.value = null

    try {
      const item = await roadmapsApi.createItem(roadmapId, payload)
      replaceItem(item)

      return item
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not add that step.')

      return null
    } finally {
      saving.value = false
    }
  }

  async function updateItem(
    itemId: number,
    payload: RoadmapItemUpdatePayload,
  ): Promise<RoadmapItem | null> {
    saving.value = true
    failure.value = null

    try {
      const item = await roadmapsApi.updateItem(itemId, payload)
      replaceItem(item)

      return item
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not save that step.')

      return null
    } finally {
      saving.value = false
    }
  }

  /**
   * Optimistic: the column moves under the pointer immediately, then the server
   * row is reconciled. A rejection restores the previous status rather than
   * leaving the card in a state the server never accepted.
   */
  async function setStatus(
    item: RoadmapItem,
    status: RoadmapItemStatus,
  ): Promise<RoadmapItem | null> {
    const previous = item.status

    if (previous === status) {
      return item
    }

    replaceItem({ ...item, status })

    const updated = await updateItem(item.id, { status })

    if (!updated) {
      replaceItem({ ...item, status: previous })
    }

    return updated
  }

  async function destroyItem(item: RoadmapItem): Promise<boolean> {
    const snapshot = items(item.roadmap_id)
    setItems(
      item.roadmap_id,
      snapshot.filter((entry) => entry.id !== item.id),
    )

    try {
      await roadmapsApi.destroyItem(item.id)

      return true
    } catch (error) {
      setItems(item.roadmap_id, snapshot)
      failure.value = toApiFailure(error, 'Could not remove that step.')

      return false
    }
  }

  /**
   * FR-RM-05. The reordered array is applied locally first so the drag does not
   * snap back while the request is in flight, and the pre-drag snapshot is kept
   * so a failure rolls the whole batch back rather than leaving a half-order.
   */
  async function reorder(roadmapId: number, ordered: RoadmapItem[]): Promise<boolean> {
    const snapshot = items(roadmapId)
    const renumbered = ordered.map((item, index) => ({ ...item, position: index }))
    const diff: ReorderEntry[] = renumbered.map((item) => ({ id: item.id, position: item.position }))

    setItems(roadmapId, renumbered)
    saving.value = true
    failure.value = null

    try {
      await roadmapsApi.reorder(roadmapId, diff)

      return true
    } catch (error) {
      setItems(roadmapId, snapshot)
      failure.value = toApiFailure(error, 'Could not save the new order.')

      return false
    } finally {
      saving.value = false
    }
  }

  /** Keyboard fallback for drag-reorder: move one item by one slot. */
  async function move(roadmapId: number, itemId: number, delta: -1 | 1): Promise<boolean> {
    const current = items(roadmapId)
    const index = current.findIndex((entry) => entry.id === itemId)
    const target = index + delta

    if (index === -1 || target < 0 || target >= current.length) {
      return false
    }

    const next = [...current]
    const [moved] = next.splice(index, 1)
    next.splice(target, 0, moved as RoadmapItem)

    return reorder(roadmapId, next)
  }

  /** FR-MENT-05, mentor side. */
  async function assign(itemId: number, payload: AssignmentPayload): Promise<RoadmapItem | null> {
    saving.value = true
    failure.value = null

    try {
      const item = await roadmapsApi.assign(itemId, payload)
      replaceItem(item)

      return item
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not save the assignment.')

      return null
    } finally {
      saving.value = false
    }
  }

  function clearFailure(): void {
    failure.value = null
  }

  return {
    itemsByRoadmap,
    loading,
    saving,
    failure,
    items,
    setItems,
    replaceItem,
    fetchItems,
    createItem,
    updateItem,
    setStatus,
    destroyItem,
    reorder,
    move,
    assign,
    clearFailure,
  }
})
