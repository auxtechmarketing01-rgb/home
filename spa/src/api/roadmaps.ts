import { apiClient, getCollection, sendResource } from './client'
import type {
  AssignmentPayload,
  ReorderEntry,
  RoadmapItem,
  RoadmapItemPayload,
  RoadmapItemUpdatePayload,
} from '@/types/roadmap'

export const roadmapsApi = {
  items(roadmapId: number): Promise<RoadmapItem[]> {
    return getCollection<RoadmapItem>(`/roadmaps/${roadmapId}/items`)
  },

  createItem(roadmapId: number, payload: RoadmapItemPayload): Promise<RoadmapItem> {
    return sendResource<RoadmapItem>('post', `/roadmaps/${roadmapId}/items`, payload)
  },

  updateItem(itemId: number, payload: RoadmapItemUpdatePayload): Promise<RoadmapItem> {
    return sendResource<RoadmapItem>('put', `/roadmap-items/${itemId}`, payload)
  },

  async destroyItem(itemId: number): Promise<void> {
    await apiClient.delete(`/roadmap-items/${itemId}`)
  },

  /** Batch reorder (FR-RM-05). One request for the whole diff, never one per row. */
  async reorder(roadmapId: number, items: ReorderEntry[]): Promise<void> {
    await apiClient.post(`/roadmaps/${roadmapId}/items/reorder`, { items })
  },

  /**
   * FR-MENT-05. A distinct endpoint because it is a distinct ability: a mentor
   * may set expectations on an item they can never otherwise edit.
   */
  assign(itemId: number, payload: AssignmentPayload): Promise<RoadmapItem> {
    return sendResource<RoadmapItem>('patch', `/roadmap-items/${itemId}/assign`, payload)
  },
}
