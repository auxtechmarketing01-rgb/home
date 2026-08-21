import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ProjectionBanner from '@/components/analytics/ProjectionBanner.vue'
import { GLOBAL_STUBS } from './setup'

function mountBanner(props: Record<string, unknown>) {
  return mount(ProjectionBanner, {
    props: { projectedCompletionDate: null, ...props },
    global: { stubs: GLOBAL_STUBS },
  })
}

describe('ProjectionBanner null handling', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-06-01T09:00:00.000Z'))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('renders an explicit "not enough data" state rather than hiding itself', () => {
    const wrapper = mountBanner({ projectedCompletionDate: null })

    /** The component must still be in the DOM -- hiding it looks like a bug. */
    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('Not enough data yet')
    expect(wrapper.text()).toContain('Nothing is wrong.')
  })

  it('never invents a date when the projection is null', () => {
    const wrapper = mountBanner({ projectedCompletionDate: null, targetEndDate: '2026-07-01' })

    expect(wrapper.text()).not.toContain('2026')
    expect(wrapper.text()).not.toContain('Jul')
  })

  it('shows the projected date when one exists', () => {
    const wrapper = mountBanner({ projectedCompletionDate: '2026-06-21' })

    expect(wrapper.text()).toContain('21 Jun 2026')
    expect(wrapper.text()).toContain('days out')
  })

  it('flags a projection that slips past the member target', () => {
    const wrapper = mountBanner({
      projectedCompletionDate: '2026-07-10',
      targetEndDate: '2026-07-01',
    })

    expect(wrapper.text()).toContain('9 days past your target')
  })

  it('reports being ahead of the target', () => {
    const wrapper = mountBanner({
      projectedCompletionDate: '2026-06-20',
      targetEndDate: '2026-07-01',
    })

    expect(wrapper.text()).toContain('11 days ahead of your target')
  })

  it('shows a loading state without claiming there is no data', () => {
    const wrapper = mountBanner({ projectedCompletionDate: null, loading: true })

    expect(wrapper.text()).toContain('Working out a projection')
    expect(wrapper.text()).not.toContain('Not enough data yet')
  })
})
