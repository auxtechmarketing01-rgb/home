import { defineStore } from 'pinia'
import { ref } from 'vue'
import { toApiFailure } from '@/api/client'
import { resourcesApi, type ResourceParent } from '@/api/resources'
import type { ApiFailure } from '@/types/api'
import type { ResourceFile, ResourcePayload } from '@/types/resource'

function key(parent: ResourceParent, parentId: number): string {
  return `${parent}:${parentId}`
}

/**
 * Attachments hang off either a Goal or a Roadmap Item, so the cache is keyed by
 * both -- one flat list would make "the notes on step 3" indistinguishable from
 * "the notes on the goal".
 */
export const useResourcesStore = defineStore('resources', () => {
  const byParent = ref<Record<string, ResourceFile[]>>({})
  const loading = ref(false)
  const uploading = ref(false)
  const progress = ref(0)
  const failure = ref<ApiFailure | null>(null)

  function items(parent: ResourceParent, parentId: number | null | undefined): ResourceFile[] {
    return parentId ? (byParent.value[key(parent, parentId)] ?? []) : []
  }

  async function fetchFor(parent: ResourceParent, parentId: number): Promise<void> {
    loading.value = true
    failure.value = null

    try {
      byParent.value = {
        ...byParent.value,
        [key(parent, parentId)]: await resourcesApi.list(parent, parentId),
      }
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load attachments.')
    } finally {
      loading.value = false
    }
  }

  async function create(
    parent: ResourceParent,
    parentId: number,
    payload: ResourcePayload,
  ): Promise<ResourceFile | null> {
    uploading.value = true
    progress.value = 0
    failure.value = null

    try {
      const resource = await resourcesApi.create(parent, parentId, payload, (percent) => {
        progress.value = percent
      })

      const cacheKey = key(parent, parentId)
      byParent.value = {
        ...byParent.value,
        [cacheKey]: [resource, ...(byParent.value[cacheKey] ?? [])],
      }

      return resource
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not attach that.')

      return null
    } finally {
      uploading.value = false
      progress.value = 0
    }
  }

  async function destroy(
    parent: ResourceParent,
    parentId: number,
    resourceId: number,
  ): Promise<boolean> {
    const cacheKey = key(parent, parentId)
    const snapshot = byParent.value[cacheKey] ?? []

    byParent.value = {
      ...byParent.value,
      [cacheKey]: snapshot.filter((entry) => entry.id !== resourceId),
    }

    try {
      await resourcesApi.destroy(resourceId)

      return true
    } catch (error) {
      byParent.value = { ...byParent.value, [cacheKey]: snapshot }
      failure.value = toApiFailure(error, 'Could not remove that attachment.')

      return false
    }
  }

  function clearFailure(): void {
    failure.value = null
  }

  return { byParent, loading, uploading, progress, failure, items, fetchFor, create, destroy, clearFailure }
})
