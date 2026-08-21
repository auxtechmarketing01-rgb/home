import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { toApiFailure } from '@/api/client'
import { categoriesApi, goalsApi } from '@/api/goals'
import type { ApiFailure, PaginationMeta } from '@/types/api'
import type { Category, Goal, GoalFilters, GoalPayload } from '@/types/goal'

export const useGoalsStore = defineStore('goals', () => {
  /** Keyed by id so a detail fetch and a list fetch converge on one record. */
  const byId = ref<Map<number, Goal>>(new Map())
  const listOrder = ref<number[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const filters = ref<GoalFilters>({ per_page: 24 })
  const categories = ref<Category[]>([])

  const loading = ref(false)
  const saving = ref(false)
  const failure = ref<ApiFailure | null>(null)

  const list = computed(() =>
    listOrder.value.map((id) => byId.value.get(id)).filter((goal): goal is Goal => Boolean(goal)),
  )

  const activeGoals = computed(() => list.value.filter((goal) => goal.status === 'active'))

  /** Goals a sprint can reasonably be logged against. */
  const selectableGoals = computed(() =>
    list.value.filter((goal) => goal.status === 'active' || goal.status === 'draft'),
  )

  function upsert(goal: Goal): Goal {
    const next = new Map(byId.value)
    const existing = next.get(goal.id)

    /**
     * Merged rather than replaced: the list endpoint omits relations the detail
     * endpoint loads, so a plain overwrite would blank out a roadmap the view is
     * already rendering.
     */
    const merged = existing ? { ...existing, ...goal } : goal
    next.set(goal.id, merged)
    byId.value = next

    return merged
  }

  function get(id: number): Goal | undefined {
    return byId.value.get(id)
  }

  async function fetchAll(next?: GoalFilters): Promise<void> {
    loading.value = true
    failure.value = null

    if (next) {
      filters.value = { ...filters.value, ...next }
    }

    try {
      const page = await goalsApi.list(filters.value)
      page.items.forEach(upsert)
      listOrder.value = page.items.map((goal) => goal.id)
      meta.value = page.meta
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load your goals.')
    } finally {
      loading.value = false
    }
  }

  async function fetchOne(id: number): Promise<Goal | null> {
    loading.value = true
    failure.value = null

    try {
      return upsert(await goalsApi.show(id))
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load that goal.')

      return null
    } finally {
      loading.value = false
    }
  }

  async function create(payload: GoalPayload): Promise<Goal | null> {
    saving.value = true
    failure.value = null

    try {
      const goal = upsert(await goalsApi.create(payload))
      listOrder.value = [goal.id, ...listOrder.value.filter((id) => id !== goal.id)]

      return goal
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not create the goal.')

      return null
    } finally {
      saving.value = false
    }
  }

  async function update(id: number, payload: Partial<GoalPayload>): Promise<Goal | null> {
    saving.value = true
    failure.value = null

    try {
      return upsert(await goalsApi.update(id, payload))
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not save the goal.')

      return null
    } finally {
      saving.value = false
    }
  }

  async function complete(id: number): Promise<Goal | null> {
    return runTransition(() => goalsApi.complete(id), 'Could not complete the goal.')
  }

  /** FR-GOAL-04 keeps the row; "archive" here is the abandoned status, not a delete. */
  async function archive(id: number): Promise<Goal | null> {
    return update(id, { status: 'abandoned' })
  }

  async function destroy(id: number): Promise<boolean> {
    saving.value = true
    failure.value = null

    try {
      await goalsApi.destroy(id)
      const next = new Map(byId.value)
      next.delete(id)
      byId.value = next
      listOrder.value = listOrder.value.filter((entry) => entry !== id)

      return true
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not delete the goal.')

      return false
    } finally {
      saving.value = false
    }
  }

  async function fetchCategories(): Promise<void> {
    if (categories.value.length > 0) {
      return
    }

    try {
      categories.value = await categoriesApi.list()
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load categories.')
    }
  }

  async function createCategory(name: string, icon?: string | null): Promise<Category | null> {
    try {
      const category = await categoriesApi.create({ name, icon })
      categories.value = [...categories.value, category]

      return category
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not create the category.')

      return null
    }
  }

  async function runTransition(
    action: () => Promise<Goal>,
    message: string,
  ): Promise<Goal | null> {
    saving.value = true
    failure.value = null

    try {
      return upsert(await action())
    } catch (error) {
      failure.value = toApiFailure(error, message)

      return null
    } finally {
      saving.value = false
    }
  }

  function clearFailure(): void {
    failure.value = null
  }

  return {
    byId,
    listOrder,
    meta,
    filters,
    categories,
    loading,
    saving,
    failure,
    list,
    activeGoals,
    selectableGoals,
    get,
    upsert,
    fetchAll,
    fetchOne,
    create,
    update,
    complete,
    archive,
    destroy,
    fetchCategories,
    createCategory,
    clearFailure,
  }
})
