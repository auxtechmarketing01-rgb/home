import { computed, onUnmounted, ref, watch, type Ref } from 'vue'

/**
 * Generic wall-clock countdown to an ISO deadline, for the things that are not
 * the focus sprint -- an assigned due date, a challenge end. Same discipline as
 * useFocusTimer: measure against a timestamp, never decrement a counter.
 */
export function useCountdown(target: Ref<string | null | undefined>, intervalMs = 1000) {
  const now = ref(Date.now())
  let handle: number | undefined

  const deadline = computed(() => {
    const value = target.value

    return value ? new Date(value).getTime() : null
  })

  const secondsRemaining = computed(() => {
    if (deadline.value === null) {
      return null
    }

    return Math.round((deadline.value - now.value) / 1000)
  })

  const isOverdue = computed(() => (secondsRemaining.value ?? 1) < 0)

  function stop(): void {
    if (handle !== undefined) {
      window.clearInterval(handle)
      handle = undefined
    }
  }

  function start(): void {
    stop()
    now.value = Date.now()
    handle = window.setInterval(() => {
      now.value = Date.now()
    }, intervalMs)
  }

  watch(
    deadline,
    (value) => {
      if (value === null) {
        stop()
      } else {
        start()
      }
    },
    { immediate: true },
  )

  onUnmounted(stop)

  return { secondsRemaining, isOverdue, start, stop }
}
