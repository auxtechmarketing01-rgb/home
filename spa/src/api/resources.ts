import { apiClient, getCollection, sendResource } from './client'
import { API_BASE_URL } from './client'
import type { ResourceFile, ResourcePayload } from '@/types/resource'

export type ResourceParent = 'goal' | 'item'

function basePath(parent: ResourceParent, parentId: number): string {
  return parent === 'goal' ? `/goals/${parentId}/resources` : `/roadmap-items/${parentId}/resources`
}

/**
 * `file` uploads go as multipart with a progress callback; `link` and `note` are
 * plain JSON. One store shape covers all three because the backend models them
 * in one table (02 section 3) -- splitting them here would fork the domain.
 */
export const resourcesApi = {
  list(parent: ResourceParent, parentId: number): Promise<ResourceFile[]> {
    return getCollection<ResourceFile>(basePath(parent, parentId))
  },

  create(
    parent: ResourceParent,
    parentId: number,
    payload: ResourcePayload,
    onProgress?: (percent: number) => void,
  ): Promise<ResourceFile> {
    const url = basePath(parent, parentId)

    if (payload.type === 'file' && payload.file) {
      const form = new FormData()
      form.append('type', 'file')
      form.append('title', payload.title)
      form.append('file', payload.file)

      return sendResource<ResourceFile>('post', url, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (event) => {
          if (onProgress && event.total) {
            onProgress(Math.round((event.loaded / event.total) * 100))
          }
        },
      })
    }

    return sendResource<ResourceFile>('post', url, {
      type: payload.type,
      title: payload.title,
      url: payload.url ?? null,
      body: payload.body ?? null,
    })
  },

  async destroy(id: number): Promise<void> {
    await apiClient.delete(`/resources/${id}`)
  },

  /** Streams through the API so the Policy still gates the bytes. */
  downloadUrl(id: number): string {
    return `${API_BASE_URL}/resources/${id}/download`
  },
}
