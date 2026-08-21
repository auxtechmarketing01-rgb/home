import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useFocusTimer } from '@/composables/useFocusTimer'
import { makeSprint } from './factories'
import type { Sprint } from '@/types/sprint'

/**
 * The composable relies on lifecycle hooks, so it is exercised through a real
 * component rather than called bare.
 */
function mountTimer(sprint: Sprint) {
  const captured: { timer: ReturnType<typeof useFocusTimer> | null } = { timer: null }

  const wrapper = mount(
    defineComponent({
      setup() {
        captured.timer = useFocusTimer(() => sprint)

        return () => h('div')
      },
    }),
  )

  return { wrapper, timer: captured.timer as ReturnType<typeof useFocusTimer> }
}

const BASE = new Date('2026-03-01T10:00:00.000Z').getTime()

describe('useFocusTimer', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.useFakeTimers({
      toFake: ['Date', 'requestAnimationFrame', 'cancelAnimationFrame', 'setTimeout', 'clearTimeout'],
    })
    vi.setSystemTime(BASE)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('derives remaining time from the wall-clock deadline, not from counted ticks', () => {
    const sprint = makeSprint({
      started_at: new Date(BASE).toISOString(),
      planned_duration_seconds: 1500,
    })

    const { timer, wrapper } = mountTimer(sprint)

    expect(timer.remainingSeconds.value).toBe(1500)

    /**
     * A frame lands a fraction under the requested advance, so this asks for a
     * little past the minute rather than asserting on frame timing.
     */
    vi.advanceTimersByTime(60_100)

    expect(timer.remainingSeconds.value).toBe(1440)
    expect(timer.elapsedSeconds.value).toBe(60)
    expect(timer.isExpired.value).toBe(false)

    wrapper.unmount()
  })

  it('recomputes from the server row when the app is reopened hours later', () => {
    const sprint = makeSprint({
      started_at: new Date(BASE).toISOString(),
      planned_duration_seconds: 1500,
    })

    /** A normal session: the timer ticks down for a while. */
    const first = mountTimer(sprint)
    vi.advanceTimersByTime(30_000)
    expect(first.timer.remainingSeconds.value).toBe(1470)

    /** The tab is closed -- every frame and every bit of local state goes away. */
    first.wrapper.unmount()

    /**
     * Six hours pass with nothing running client-side, then the app is reopened.
     * A composable that had been decrementing a counter would have nothing to
     * resume from; this one measures against `started_at` and is simply right.
     */
    vi.setSystemTime(BASE + 6 * 60 * 60 * 1000)

    const second = mountTimer(sprint)

    expect(second.timer.remainingSeconds.value).toBe(0)
    expect(second.timer.isExpired.value).toBe(true)
    expect(second.timer.elapsedSeconds.value).toBe(6 * 60 * 60)
    expect(second.timer.overtimeSeconds.value).toBe(6 * 60 * 60 - 1500)

    second.wrapper.unmount()
  })

  it('enters overtime and keeps counting up rather than ending the sprint (FR-SPR-09)', () => {
    const sprint = makeSprint({
      started_at: new Date(BASE - 1500 * 1000).toISOString(),
      planned_duration_seconds: 1500,
    })

    const { timer, wrapper } = mountTimer(sprint)

    expect(timer.isExpired.value).toBe(true)
    expect(timer.overtimeSeconds.value).toBe(0)
    /** Progress is clamped, so overtime never overdraws the ring. */
    expect(timer.progress.value).toBe(1)

    vi.advanceTimersByTime(90_000)

    expect(timer.overtimeSeconds.value).toBe(90)
    expect(timer.remainingSeconds.value).toBe(0)
    expect(timer.progress.value).toBe(1)
    /** The row is untouched: overtime is a rendered state, not a status change. */
    expect(sprint.status).toBe('running')

    wrapper.unmount()
  })

  it('excludes paused time so a pause does not burn the plan', () => {
    const sprint = makeSprint({
      started_at: new Date(BASE - 600 * 1000).toISOString(),
      planned_duration_seconds: 1500,
      paused_seconds_total: 300,
    })

    const { timer, wrapper } = mountTimer(sprint)

    expect(timer.elapsedSeconds.value).toBe(300)
    expect(timer.remainingSeconds.value).toBe(1200)

    wrapper.unmount()
  })

  it('counts an open pause as it grows', () => {
    const sprint = makeSprint({
      started_at: new Date(BASE - 600 * 1000).toISOString(),
      planned_duration_seconds: 1500,
      paused_at: new Date(BASE - 120 * 1000).toISOString(),
    })

    const { timer, wrapper } = mountTimer(sprint)

    expect(timer.elapsedSeconds.value).toBe(480)

    vi.advanceTimersByTime(60_000)

    /** Still 480: the whole extra minute was spent paused. */
    expect(timer.elapsedSeconds.value).toBe(480)

    wrapper.unmount()
  })

  it('reports no deadline for an open-ended stopwatch', () => {
    const sprint = makeSprint({
      mode: 'stopwatch',
      planned_duration_seconds: null,
      started_at: new Date(BASE - 45 * 1000).toISOString(),
    })

    const { timer, wrapper } = mountTimer(sprint)

    expect(timer.remainingSeconds.value).toBeNull()
    expect(timer.isExpired.value).toBe(false)
    expect(timer.overtimeSeconds.value).toBe(0)
    expect(timer.elapsedSeconds.value).toBe(45)

    wrapper.unmount()
  })
})
