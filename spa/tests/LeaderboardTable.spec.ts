import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import LeaderboardTable from '@/components/analytics/LeaderboardTable.vue'
import { GLOBAL_STUBS } from './setup'
import { makeLeaderboardEntry } from './factories'
import type { LeaderboardEntry } from '@/types/analytics'

const ENTRIES: LeaderboardEntry[] = [
  makeLeaderboardEntry('Ayesha', 120, 3, 1, 1),
  makeLeaderboardEntry('Bilal', 300, 1, 0, 2),
  makeLeaderboardEntry('Chowdhury', 60, 9, 4, 3),
]

function mountTable(props: Record<string, unknown> = {}) {
  return mount(LeaderboardTable, {
    props: { entries: ENTRIES, period: 'week', ...props },
    global: { stubs: GLOBAL_STUBS },
  })
}

function rowNames(wrapper: ReturnType<typeof mountTable>): string[] {
  return wrapper.findAll('tbody th').map((cell) => cell.text().replace(/\s+/g, ' ').trim())
}

describe('LeaderboardTable', () => {
  it('sorts by focus time by default, highest first', () => {
    const names = rowNames(mountTable())

    expect(names[0]).toContain('Bilal')
    expect(names[1]).toContain('Ayesha')
    expect(names[2]).toContain('Chowdhury')
  })

  it('re-sorts when a different column header is chosen', async () => {
    const wrapper = mountTable()

    const streakHeader = wrapper
      .findAll('thead button')
      .find((button) => button.text().includes('streak') || button.text().includes('Streak'))

    await streakHeader?.trigger('click')

    const names = rowNames(wrapper)
    expect(names[0]).toContain('Chowdhury')
    expect(names[1]).toContain('Ayesha')
    expect(names[2]).toContain('Bilal')
  })

  it('sorts by goals completed', async () => {
    const wrapper = mountTable()

    const header = wrapper
      .findAll('thead button')
      .find((button) => button.text().includes('completed') || button.text().includes('Done'))

    await header?.trigger('click')

    expect(rowNames(wrapper)[0]).toContain('Chowdhury')
  })

  it('marks the sorted column with aria-sort for screen readers', async () => {
    const wrapper = mountTable()
    const headers = wrapper.findAll('thead th')

    expect(headers[1]?.attributes('aria-sort')).toBe('descending')
    expect(headers[2]?.attributes('aria-sort')).toBe('none')
  })

  it('renders focus time as a duration rather than raw minutes', () => {
    expect(mountTable().text()).toContain('5h')
  })

  it('marks the current member so they can find themselves', () => {
    const wrapper = mountTable({ currentUserId: 2 })

    expect(wrapper.text()).toContain('(you)')
  })

  it('emits a period change instead of filtering locally', async () => {
    const wrapper = mountTable()

    const monthButton = wrapper
      .findAll('button')
      .find((button) => button.text().trim() === 'This month')

    await monthButton?.trigger('click')

    expect(wrapper.emitted('period')?.[0]).toEqual(['month'])
  })

  it('shows an explicit empty state for a brand-new group, not a blank table', () => {
    const wrapper = mountTable({ entries: [] })

    expect(wrapper.find('table').exists()).toBe(false)
    expect(wrapper.text()).toContain('Nothing on the board yet')
  })

  it('reserves space while loading rather than collapsing', () => {
    const wrapper = mountTable({ entries: [], loading: true })

    expect(wrapper.find('[role="status"]').exists()).toBe(true)
  })
})
