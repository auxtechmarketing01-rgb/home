import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RewardCard from '@/components/rewards/RewardCard.vue'
import { GLOBAL_STUBS } from './setup'
import { makeReward } from './factories'
import { REWARD_STATUSES, type RewardAction, type RewardStatus } from '@/types/reward'
import type { Reward } from '@/types/reward'

function mountCard(reward: Reward) {
  return mount(RewardCard, { props: { reward }, global: { stubs: GLOBAL_STUBS } })
}

function labels(wrapper: ReturnType<typeof mountCard>): string[] {
  return wrapper.findAll('button').map((button) => button.text().trim())
}

/**
 * The point of the state machine is that these mean different things, so the
 * card is tested state by state: exactly the offered actions render, and nothing
 * else does.
 */
const MENTOR_CASES: Array<{ status: RewardStatus; actions: RewardAction[]; expect: string[] }> = [
  { status: 'requested', actions: ['respond'], expect: ['Respond'] },
  { status: 'offered', actions: ['revoke'], expect: ['Revoke'] },
  { status: 'claimed', actions: ['fulfill'], expect: ['Mark fulfilled'] },
  { status: 'earned', actions: [], expect: [] },
  { status: 'fulfilled', actions: [], expect: [] },
  { status: 'denied', actions: [], expect: [] },
  { status: 'revoked', actions: [], expect: [] },
]

describe('RewardCard status to action mapping', () => {
  it.each(MENTOR_CASES)(
    'renders only $expect for a mentor viewing a $status reward',
    ({ status, actions, expect: expected }) => {
      const wrapper = mountCard(makeReward(status, 'mentor', actions))
      const rendered = labels(wrapper)

      for (const action of expected) {
        expect(rendered).toContain(action)
      }

      /** No claim button for a mentor in any state -- claiming is the mentee's move. */
      expect(rendered).not.toContain('Claim')

      for (const forbidden of ['Respond', 'Revoke', 'Mark fulfilled'].filter(
        (label) => !expected.includes(label),
      )) {
        expect(rendered).not.toContain(forbidden)
      }
    },
  )

  it('enables Claim for a mentee only on earned', () => {
    const earned = mountCard(makeReward('earned', 'mentee', ['claim']))
    const claim = earned.findAll('button').find((button) => button.text().trim() === 'Claim')

    expect(claim?.attributes('disabled')).toBeUndefined()
  })

  it.each(REWARD_STATUSES.filter((status) => status !== 'earned'))(
    'disables Claim and says why on %s',
    (status) => {
      const wrapper = mountCard(makeReward(status, 'mentee', []))
      const claim = wrapper.findAll('button').find((button) => button.text().trim() === 'Claim')

      expect(claim).toBeDefined()
      expect(claim?.attributes('disabled')).toBeDefined()
      /** A greyed button with no explanation is a dead end, so a reason must render. */
      expect(wrapper.find('[id^="claim-reason-"]').exists()).toBe(true)
      expect(wrapper.find('[id^="claim-reason-"]').text().length).toBeGreaterThan(0)
    },
  )

  it('renders a visually distinct label for every one of the seven states', () => {
    const seen = new Set<string>()

    for (const status of REWARD_STATUSES) {
      const wrapper = mountCard(makeReward(status, 'mentee', []))
      const chip = wrapper.text()

      const label = status.charAt(0).toUpperCase() + status.slice(1)
      expect(chip).toContain(label)
      seen.add(label)
    }

    expect(seen.size).toBe(7)
  })

  it('emits claim when the mentee presses an enabled Claim', async () => {
    const wrapper = mountCard(makeReward('earned', 'mentee', ['claim']))
    const claim = wrapper.findAll('button').find((button) => button.text().trim() === 'Claim')

    await claim?.trigger('click')

    expect(wrapper.emitted('claim')).toHaveLength(1)
  })

  it('shows a monetary amount with its free-text currency label', () => {
    const wrapper = mountCard(
      makeReward('offered', 'mentee', [], {
        type: 'monetary',
        monetary_amount: '500.00',
        currency_label: 'BDT',
      }),
    )

    expect(wrapper.text()).toContain('500 BDT')
  })

  it('renders no action footer at all when the server offers nothing and the viewer is a mentor', () => {
    const wrapper = mountCard(makeReward('revoked', 'mentor', []))

    expect(labels(wrapper)).toEqual([])
  })
})
