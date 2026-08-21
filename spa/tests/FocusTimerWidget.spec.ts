import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import FocusTimerWidget from '@/components/focus/FocusTimerWidget.vue'
import { useSprintsStore } from '@/stores/sprints'
import { GLOBAL_STUBS } from './setup'
import { makeSprint } from './factories'
import type { Sprint } from '@/types/sprint'

vi.mock('@/api/sprints', () => ({
  sprintsApi: {
    history: vi.fn(),
    active: vi.fn().mockResolvedValue(null),
    start: vi.fn(),
    pause: vi.fn(),
    resume: vi.fn(),
    complete: vi.fn(),
    cancel: vi.fn(),
    exportUrl: vi.fn().mockReturnValue('/export'),
  },
}))

const { sprintsApi } = await import('@/api/sprints')

const BASE = new Date('2026-03-01T10:00:00.000Z').getTime()

function mountWidget(sprint: Sprint) {
  const store = useSprintsStore()
  store.activeSprint = sprint

  const wrapper = mount(FocusTimerWidget, {
    props: { sprint },
    global: { stubs: GLOBAL_STUBS },
  })

  return { wrapper, store }
}

function button(wrapper: ReturnType<typeof mount>, label: string) {
  return wrapper.findAll('button').find((entry) => entry.text().trim() === label)
}

describe('FocusTimerWidget interactions', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    vi.useFakeTimers({
      toFake: ['Date', 'requestAnimationFrame', 'cancelAnimationFrame', 'setTimeout', 'clearTimeout'],
    })
    vi.setSystemTime(BASE)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders the remaining time and offers Pause while running', () => {
    const sprint = makeSprint({
      started_at: new Date(BASE - 300 * 1000).toISOString(),
      planned_duration_seconds: 1500,
    })

    const { wrapper } = mountWidget(sprint)

    expect(wrapper.text()).toContain('20:00')
    expect(wrapper.text()).toContain('Remaining')
    expect(button(wrapper, 'Pause')).toBeDefined()
    expect(button(wrapper, 'Resume')).toBeUndefined()
  })

  it('calls pause on the API when Pause is pressed', async () => {
    vi.mocked(sprintsApi.pause).mockResolvedValue(
      makeSprint({ status: 'paused', paused_at: new Date(BASE).toISOString() }),
    )

    const sprint = makeSprint({ started_at: new Date(BASE - 60 * 1000).toISOString() })
    const { wrapper } = mountWidget(sprint)

    await button(wrapper, 'Pause')?.trigger('click')

    expect(sprintsApi.pause).toHaveBeenCalledWith(sprint.id)
  })

  it('offers Resume, not Pause, on a paused sprint', async () => {
    vi.mocked(sprintsApi.resume).mockResolvedValue(makeSprint({ status: 'running' }))

    const sprint = makeSprint({
      status: 'paused',
      started_at: new Date(BASE - 600 * 1000).toISOString(),
      paused_at: new Date(BASE - 120 * 1000).toISOString(),
    })

    const { wrapper } = mountWidget(sprint)

    expect(wrapper.text()).toContain('Paused')
    expect(button(wrapper, 'Pause')).toBeUndefined()

    await button(wrapper, 'Resume')?.trigger('click')
    expect(sprintsApi.resume).toHaveBeenCalledWith(sprint.id)
  })

  it('asks for an optional note before completing, then completes with it', async () => {
    vi.mocked(sprintsApi.complete).mockResolvedValue(
      makeSprint({ status: 'completed', actual_duration_seconds: 900 }),
    )

    const sprint = makeSprint({ started_at: new Date(BASE - 900 * 1000).toISOString() })
    const { wrapper } = mountWidget(sprint)

    /** First press reveals the note field rather than ending the sprint blind. */
    await button(wrapper, 'Stop')?.trigger('click')
    expect(wrapper.find('#sprint-notes').exists()).toBe(true)
    expect(sprintsApi.complete).not.toHaveBeenCalled()

    await wrapper.find('#sprint-notes').setValue('Got the layout working.')
    await button(wrapper, 'Stop and save')?.trigger('click')

    expect(sprintsApi.complete).toHaveBeenCalledWith(sprint.id, 'Got the layout working.')
  })

  it('cancels without touching complete', async () => {
    vi.mocked(sprintsApi.cancel).mockResolvedValue(makeSprint({ status: 'cancelled' }))

    const sprint = makeSprint({ started_at: new Date(BASE).toISOString() })
    const { wrapper } = mountWidget(sprint)

    await button(wrapper, 'Discard')?.trigger('click')

    expect(sprintsApi.cancel).toHaveBeenCalledWith(sprint.id)
    expect(sprintsApi.complete).not.toHaveBeenCalled()
  })

  it('shows overtime without disabling, hiding or auto-stopping anything (FR-SPR-09)', () => {
    const sprint = makeSprint({
      started_at: new Date(BASE - 1600 * 1000).toISOString(),
      planned_duration_seconds: 1500,
    })

    const { wrapper } = mountWidget(sprint)

    expect(wrapper.text()).toContain('Overtime')
    expect(wrapper.text()).toContain('01:40')
    expect(wrapper.text()).toContain('Past your plan by')

    /** Every control is still live, and nothing called complete on its own. */
    expect(button(wrapper, 'Pause')?.attributes('disabled')).toBeUndefined()
    expect(button(wrapper, 'Stop')?.attributes('disabled')).toBeUndefined()
    expect(sprintsApi.complete).not.toHaveBeenCalled()
  })

  it('stops from the overtime banner', async () => {
    vi.mocked(sprintsApi.complete).mockResolvedValue(makeSprint({ status: 'completed' }))

    const sprint = makeSprint({
      started_at: new Date(BASE - 1600 * 1000).toISOString(),
      planned_duration_seconds: 1500,
    })

    const { wrapper } = mountWidget(sprint)

    await button(wrapper, 'Stop and bank it')?.trigger('click')

    expect(sprintsApi.complete).toHaveBeenCalledWith(sprint.id, null)
  })

  it('counts up and shows Elapsed for a stopwatch, never overtime', () => {
    const sprint = makeSprint({
      mode: 'stopwatch',
      planned_duration_seconds: null,
      started_at: new Date(BASE - 125 * 1000).toISOString(),
    })

    const { wrapper } = mountWidget(sprint)

    expect(wrapper.text()).toContain('02:05')
    expect(wrapper.text()).toContain('Elapsed')
    expect(wrapper.text()).not.toContain('Overtime')
    expect(wrapper.text()).not.toContain('Past your plan by')
  })
})
