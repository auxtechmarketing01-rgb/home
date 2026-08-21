import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { toApiFailure } from '@/api/client'
import { sprintsApi } from '@/api/sprints'
import type { ApiFailure, PaginationMeta } from '@/types/api'
import type { Sprint, SprintFilters, SprintMode, StartSprintPayload } from '@/types/sprint'

/** Pomodoro defaults, matching StartSprintRequest's server-side prepare step. */
export const POMODORO_FOCUS_SECONDS = 25 * 60
export const POMODORO_BREAK_SECONDS = 5 * 60

/**
 * Deliberately global rather than scoped to a view: the timer must keep running
 * while the member navigates, which is what PersistentFocusBar reads regardless
 * of route. The store holds *the server row* -- it never counts ticks itself.
 */
export const useSprintsStore = defineStore('sprints', () => {
  const activeSprint = ref<Sprint | null>(null)
  const history = ref<Sprint[]>([])
  const historyMeta = ref<PaginationMeta | null>(null)
  const filters = ref<SprintFilters>({ per_page: 20 })

  const loading = ref(false)
  const historyLoading = ref(false)
  const failure = ref<ApiFailure | null>(null)

  const hasActiveSprint = computed(() => activeSprint.value !== null)
  const isPaused = computed(() => activeSprint.value?.status === 'paused')

  /** A stopwatch is open-ended by definition, so it has no deadline to reach. */
  const isOpenEnded = computed(
    () => activeSprint.value !== null && activeSprint.value.planned_duration_seconds === null,
  )

  /**
   * Refetched on app bootstrap and whenever the tab regains focus. This one call
   * is what makes a reopened browser resume the correct countdown: the sprint
   * lives on the server, so there is nothing local to have "kept running".
   */
  async function fetchActive(): Promise<Sprint | null> {
    try {
      activeSprint.value = await sprintsApi.active()

      return activeSprint.value
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not check for a running sprint.')

      return null
    }
  }

  async function fetchHistory(next?: SprintFilters): Promise<void> {
    historyLoading.value = true
    failure.value = null

    if (next) {
      filters.value = { ...filters.value, ...next }
    }

    try {
      const page = await sprintsApi.history(filters.value)
      history.value = page.items
      historyMeta.value = page.meta
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not load your sprint history.')
    } finally {
      historyLoading.value = false
    }
  }

  /** Builds the mode-correct payload so a stopwatch never sends a duration. */
  function buildStartPayload(
    mode: SprintMode,
    options: {
      goalId?: number | null
      roadmapItemId?: number | null
      minutes?: number
      breakSeconds?: number
      notes?: string | null
    } = {},
  ): StartSprintPayload {
    const payload: StartSprintPayload = {
      mode,
      goal_id: options.goalId ?? null,
      roadmap_item_id: options.roadmapItemId ?? null,
      notes: options.notes ?? null,
    }

    if (mode !== 'stopwatch') {
      payload.planned_duration_seconds = options.minutes
        ? Math.round(options.minutes * 60)
        : POMODORO_FOCUS_SECONDS
    }

    if (mode === 'pomodoro') {
      payload.break_seconds = options.breakSeconds ?? POMODORO_BREAK_SECONDS
    }

    return payload
  }

  async function start(payload: StartSprintPayload): Promise<Sprint | null> {
    loading.value = true
    failure.value = null

    try {
      activeSprint.value = await sprintsApi.start(payload)

      return activeSprint.value
    } catch (error) {
      failure.value = toApiFailure(error, 'Could not start the sprint.')

      return null
    } finally {
      loading.value = false
    }
  }

  async function pause(): Promise<Sprint | null> {
    return transition((id) => sprintsApi.pause(id), 'Could not pause the sprint.')
  }

  async function resume(): Promise<Sprint | null> {
    return transition((id) => sprintsApi.resume(id), 'Could not resume the sprint.')
  }

  /**
   * Stopping is the *only* thing that ends a sprint (FR-SPR-03). Reaching the
   * planned duration produces overtime and a notification, never this call.
   */
  async function complete(notes?: string | null): Promise<Sprint | null> {
    return transition((id) => sprintsApi.complete(id, notes), 'Could not complete the sprint.', true)
  }

  async function cancel(): Promise<Sprint | null> {
    return transition((id) => sprintsApi.cancel(id), 'Could not cancel the sprint.', true)
  }

  async function transition(
    action: (id: number) => Promise<Sprint>,
    message: string,
    ends = false,
  ): Promise<Sprint | null> {
    const current = activeSprint.value

    if (!current) {
      return null
    }

    loading.value = true
    failure.value = null

    try {
      const sprint = await action(current.id)

      if (ends) {
        activeSprint.value = null
        history.value = [sprint, ...history.value]
      } else {
        activeSprint.value = sprint
      }

      return sprint
    } catch (error) {
      failure.value = toApiFailure(error, message)

      return null
    } finally {
      loading.value = false
    }
  }

  function exportUrl(): string {
    return sprintsApi.exportUrl(filters.value)
  }

  function clearFailure(): void {
    failure.value = null
  }

  return {
    activeSprint,
    history,
    historyMeta,
    filters,
    loading,
    historyLoading,
    failure,
    hasActiveSprint,
    isPaused,
    isOpenEnded,
    fetchActive,
    fetchHistory,
    buildStartPayload,
    start,
    pause,
    resume,
    complete,
    cancel,
    exportUrl,
    clearFailure,
  }
})
