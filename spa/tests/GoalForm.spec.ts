import { beforeEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import GoalForm from '@/components/goals/GoalForm.vue'
import { GLOBAL_STUBS } from './setup'
import { makeGoal } from './factories'
import type { ApiFailure } from '@/types/api'

function mountForm(props: Record<string, unknown> = {}) {
  return mount(GoalForm, {
    props: { categories: [], groups: [], ...props },
    global: { stubs: GLOBAL_STUBS },
  })
}

function submitButton(wrapper: ReturnType<typeof mountForm>) {
  return wrapper.findAll('button').find((button) => button.text().match(/Create goal|Save changes/))
}

describe('GoalForm validation states', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('blocks submission until a title is present', async () => {
    const wrapper = mountForm()

    expect(submitButton(wrapper)?.attributes('disabled')).toBeDefined()

    await wrapper.find('input[type="text"]').setValue('Ship the portfolio')

    expect(submitButton(wrapper)?.attributes('disabled')).toBeUndefined()
  })

  it('emits the trimmed payload with a null description when left blank', async () => {
    const wrapper = mountForm()

    await wrapper.find('input[type="text"]').setValue('  Ship the portfolio  ')
    await wrapper.find('form').trigger('submit')

    const emitted = wrapper.emitted('submit')
    expect(emitted).toHaveLength(1)
    expect(emitted?.[0]?.[0]).toMatchObject({
      title: 'Ship the portfolio',
      description: null,
      visibility: 'private',
      group_id: null,
    })
  })

  it('requires a group once visibility is group, mirroring required_if', async () => {
    const wrapper = mountForm({ groups: [{ id: 4, name: 'Siblings', owner_id: 1, is_owner: true, created_at: null }] })

    await wrapper.find('input[type="text"]').setValue('Shared goal')
    expect(submitButton(wrapper)?.attributes('disabled')).toBeUndefined()

    const groupRadio = wrapper.findAll('input[type="radio"]')[1]
    await groupRadio?.setValue(true)

    /** The group select only exists in this branch, and blocks submit until set. */
    expect(wrapper.find('select').exists()).toBe(true)
    expect(submitButton(wrapper)?.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Pick the group this goal is shared with.')
  })

  it('rejects an end date that precedes the start date', async () => {
    const wrapper = mountForm()

    await wrapper.find('input[type="text"]').setValue('Ship it')

    const dates = wrapper.findAll('input[type="date"]')
    await dates[0]?.setValue('2026-05-10')
    await dates[1]?.setValue('2026-05-01')

    expect(wrapper.text()).toContain('The end date cannot come before the start date.')
    expect(submitButton(wrapper)?.attributes('disabled')).toBeDefined()
  })

  it('surfaces server field errors next to their field', async () => {
    const failure: ApiFailure = {
      status: 422,
      message: 'The given data was invalid.',
      errors: { title: ['A goal with that title already exists.'] },
    }

    const wrapper = mountForm({ failure })

    expect(wrapper.text()).toContain('A goal with that title already exists.')
  })

  it('drops a stale group id when switching an existing goal back to private', async () => {
    const wrapper = mountForm({
      goal: makeGoal({ visibility: 'group', group_id: 4, title: 'Shared goal' }),
      groups: [{ id: 4, name: 'Siblings', owner_id: 1, is_owner: true, created_at: null }],
    })

    const privateRadio = wrapper.findAll('input[type="radio"]')[0]
    await privateRadio?.setValue(true)
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')?.[0]?.[0]).toMatchObject({
      visibility: 'private',
      group_id: null,
    })
  })
})
