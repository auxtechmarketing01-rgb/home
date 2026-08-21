import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RoadmapItemNode from '@/components/roadmap/RoadmapItemNode.vue'
import { GLOBAL_STUBS } from './setup'
import { makeRoadmapItem } from './factories'
import type { RoadmapItem } from '@/types/roadmap'

function mountNode(item: RoadmapItem, props: Record<string, unknown> = {}) {
  return mount(RoadmapItemNode, {
    props: { item, ...props },
    global: { stubs: GLOBAL_STUBS },
  })
}

function statusButton(wrapper: ReturnType<typeof mountNode>) {
  return wrapper
    .findAll('button')
    .find((button) => (button.attributes('aria-label') ?? '').includes('Change status'))
}

describe('RoadmapItemNode status changes', () => {
  it('advances todo to in_progress', async () => {
    const wrapper = mountNode(makeRoadmapItem({ status: 'todo' }))

    await statusButton(wrapper)?.trigger('click')

    expect(wrapper.emitted('status')?.[0]).toEqual(['in_progress'])
  })

  it('advances in_progress to done', async () => {
    const wrapper = mountNode(makeRoadmapItem({ status: 'in_progress' }))

    await statusButton(wrapper)?.trigger('click')

    expect(wrapper.emitted('status')?.[0]).toEqual(['done'])
  })

  it('sends a done item back to todo, so a mis-click is reversible', async () => {
    const wrapper = mountNode(makeRoadmapItem({ status: 'done' }))

    await statusButton(wrapper)?.trigger('click')

    expect(wrapper.emitted('status')?.[0]).toEqual(['todo'])
  })

  it('disables the status control when the viewer cannot edit', async () => {
    const wrapper = mountNode(makeRoadmapItem(), { canEdit: false })

    expect(statusButton(wrapper)?.attributes('disabled')).toBeDefined()
  })

  it('gives every status control an accessible name including the status', () => {
    const wrapper = mountNode(makeRoadmapItem({ title: 'Wire the API', status: 'in_progress' }))

    expect(statusButton(wrapper)?.attributes('aria-label')).toBe(
      'Wire the API - In progress. Change status.',
    )
  })
})

describe('RoadmapItemNode assignment affordance (FR-MENT-05/06)', () => {
  function assignButton(wrapper: ReturnType<typeof mountNode>) {
    return wrapper
      .findAll('button')
      .find((button) => (button.attributes('aria-label') ?? '').startsWith('Set an expectation'))
  }

  it('is hidden from the owner', () => {
    const wrapper = mountNode(makeRoadmapItem(), { canEdit: true, canAssign: false })

    expect(assignButton(wrapper)).toBeUndefined()
  })

  it('is hidden from a viewer who is not a mentor', () => {
    const wrapper = mountNode(makeRoadmapItem(), { canEdit: false, canAssign: false })

    expect(assignButton(wrapper)).toBeUndefined()
  })

  it('is shown only to an accepted mentor', async () => {
    const wrapper = mountNode(makeRoadmapItem(), { canEdit: false, canAssign: true })

    expect(assignButton(wrapper)).toBeDefined()

    await assignButton(wrapper)?.trigger('click')
    expect(wrapper.emitted('assign')).toHaveLength(1)
  })

  it('never offers edit or delete to a mentor', () => {
    const wrapper = mountNode(makeRoadmapItem(), { canEdit: false, canAssign: true })

    const labels = wrapper.findAll('button').map((button) => button.attributes('aria-label') ?? '')

    expect(labels.some((label) => label.startsWith('Edit '))).toBe(false)
    expect(labels.some((label) => label.startsWith('Delete '))).toBe(false)
  })

  it('renders the mentor assignment as read-only detail, distinct from the own estimate', () => {
    const wrapper = mountNode(
      makeRoadmapItem({
        estimated_minutes: 30,
        assigned_minutes: 90,
        assigned_by_mentor: { id: 7, name: 'Ayesha' },
      }),
    )

    const text = wrapper.text()
    expect(text).toContain('est 30m')
    expect(text).toContain('Ayesha set')
    expect(text).toContain('1h 30m')
  })
})

describe('RoadmapItemNode keyboard reordering', () => {
  it('offers move controls and disables them at the ends of the list', async () => {
    const wrapper = mountNode(makeRoadmapItem({ title: 'First' }), {
      draggable: true,
      isFirst: true,
      isLast: false,
    })

    const up = wrapper
      .findAll('button')
      .find((button) => button.attributes('aria-label') === 'Move First up')
    const down = wrapper
      .findAll('button')
      .find((button) => button.attributes('aria-label') === 'Move First down')

    expect(up?.attributes('disabled')).toBeDefined()
    expect(down?.attributes('disabled')).toBeUndefined()

    await down?.trigger('click')
    expect(wrapper.emitted('moveDown')).toHaveLength(1)
  })
})
