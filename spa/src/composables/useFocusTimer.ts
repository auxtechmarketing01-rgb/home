import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useSprintsStore } from '@/stores/sprints'
import type { Sprint } from '@/types/sprint'

export interface FocusTimerState {
  /** Seconds left before the plan is reached; null for an open-ended stopwatch. */
  remainingSeconds: ReturnType<typeof computed<number | null>>
  isExpired: ReturnType<typeof computed<boolean>>
  overtimeSeconds: ReturnType<typeof computed<number>>
  elapsedSeconds: ReturnType<typeof computed<number>>
  progress: ReturnType<typeof computed<number>>
  start: () => void
  stop: () => void
}

/**
 * The single most failure-prone piece of a Pomodoro feature, so it is built the
 * one way that survives reality: the deadline is a **wall-clock timestamp**
 * derived from the server row, never an in-memory counter being decremented.
 *
 * That is what makes it immune to tab throttling, to a refresh, and to the
 * browser being closed entirely -- there is no client-side count to lose,
 * because the sprint was never running in the client. On (re)mount it recomputes
 * `deadline - Date.now()` from `started_at`, whether 30 seconds or 6 hours have
 * passed.
 *
 * `now` ticks only to trigger re-renders. Elapsed time is always *measured*,
 * never accumulated.
 */
export function useFocusTimer(sprintOverride?: () => Sprint | null) {
  const sprintsStore = useSprintsStore()
  const now = ref(Date.now())
  let handle: number | undefined

  const sprint = computed(() => (sprintOverride ? sprintOverride() : sprintsStore.activeSprint))

  const startedAtMs = computed(() => {
    const startedAt = sprint.value?.started_at

    return startedAt ? new Date(startedAt).getTime() : null
  })

  /**
   * Paused time is excluded, so a sprint paused for an hour does not silently
   * burn an hour of its plan. `paused_at` is the open pause; the totalled
   * seconds cover every closed one.
   */
  const pausedMs = computed(() => {
    const active = sprint.value

    if (!active) {
      return 0
    }

    const closed = (active.paused_seconds_total ?? 0) * 1000
    const open = active.paused_at ? Math.max(0, now.value - new Date(active.paused_at).getTime()) : 0

    return closed + open
  })

  const deadline = computed<number | null>(() => {
    const active = sprint.value

    if (!active || !active.planned_duration_seconds || startedAtMs.value === null) {
      return null
    }

    return startedAtMs.value + active.planned_duration_seconds * 1000 + pausedMs.value
  })

  const elapsedSeconds = computed(() => {
    if (startedAtMs.value === null) {
      return 0
    }

    return Math.max(0, Math.floor((now.value - startedAtMs.value - pausedMs.value) / 1000))
  })

  const remainingSeconds = computed<number | null>(() => {
    if (deadline.value === null) {
      return null
    }

    return Math.max(0, Math.round((deadline.value - now.value) / 1000))
  })

  /** True the instant the plan is reached -- and it stays true, it does not end anything. */
  const isExpired = computed(() => remainingSeconds.value === 0)

  /**
   * FR-SPR-09: reaching the deadline does NOT end the session, it enters
   * overtime. The sprint stays `running` server-side until the member stops it;
   * this just renders that truth instead of hiding or fighting it.
   */
  const overtimeSeconds = computed(() => {
    if (deadline.value === null || !isExpired.value) {
      return 0
    }

    return Math.max(0, Math.round((now.value - deadline.value) / 1000))
  })

  /** 0..1 of the plan consumed. Clamped, so overtime does not overdraw the ring. */
  const progress = computed(() => {
    const planned = sprint.value?.planned_duration_seconds

    if (!planned) {
      return 0
    }

    return Math.min(1, elapsedSeconds.value / planned)
  })

  function tick(): void {
    now.value = Date.now()
    handle = window.requestAnimationFrame(tick)
  }

  function start(): void {
    stop()
    now.value = Date.now()
    handle = window.requestAnimationFrame(tick)
  }

  function stop(): void {
    if (handle !== undefined) {
      window.cancelAnimationFrame(handle)
      handle = undefined
    }
  }

  /**
   * A backgrounded tab has its rAF throttled to a halt, so returning to it must
   * re-read the server row rather than assume the local view is still right.
   */
  function onVisibilityChange(): void {
    if (!document.hidden) {
      now.value = Date.now()
      void sprintsStore.fetchActive()
    }
  }

  onMounted(() => {
    start()
    document.addEventListener('visibilitychange', onVisibilityChange)
  })

  onUnmounted(() => {
    stop()
    document.removeEventListener('visibilitychange', onVisibilityChange)
  })

  return {
    sprint,
    now,
    deadline,
    elapsedSeconds,
    remainingSeconds,
    isExpired,
    overtimeSeconds,
    progress,
    start,
    stop,
  }
}
