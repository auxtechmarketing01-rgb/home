<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTabs from '@/components/ui/BaseTabs.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import RewardCard from '@/components/rewards/RewardCard.vue'
import RewardLedgerTable from '@/components/rewards/RewardLedgerTable.vue'
import RewardOfferForm from '@/components/rewards/RewardOfferForm.vue'
import RewardRequestForm from '@/components/rewards/RewardRequestForm.vue'
import { useGoalsStore } from '@/stores/goals'
import { useMentorshipsStore } from '@/stores/mentorships'
import { useRewardsStore } from '@/stores/rewards'
import { useToastsStore } from '@/stores/toasts'
import type { TabItem } from '@/components/ui/types'
import { REWARD_STATUSES, type RewardPayload, type RewardStatus } from '@/types/reward'

const rewards = useRewardsStore()
const mentorships = useMentorshipsStore()
const goals = useGoalsStore()
const toasts = useToastsStore()

const tab = ref('mine')
const statusFilter = ref<RewardStatus | null>(null)
const offering = ref(false)
const requesting = ref(false)

onMounted(() => {
  void rewards.fetchAll({ per_page: 100 })
  void mentorships.fetchAll()
  void goals.fetchAll({ per_page: 100 })
})

watch(tab, (next) => {
  if (next === 'ledger' && rewards.ledger.length === 0) {
    void rewards.fetchLedger()
  }
})

const tabs = computed<TabItem[]>(() => [
  { value: 'mine', label: 'Mine to earn', icon: 'gift', count: rewards.asMentee.length },
  { value: 'offered', label: 'I offered', icon: 'handshake', count: rewards.asMentor.length },
  { value: 'ledger', label: 'Ledger', icon: 'wallet', count: rewards.ledger.length || null },
])

function filtered(list: typeof rewards.items) {
  return statusFilter.value === null
    ? list
    : list.filter((reward) => reward.status === statusFilter.value)
}

const visible = computed(() =>
  filtered(tab.value === 'offered' ? rewards.asMentor : rewards.asMentee),
)

/** Only statuses actually present get a filter chip -- no dead controls. */
const availableStatuses = computed(() => {
  const present = new Set(
    (tab.value === 'offered' ? rewards.asMentor : rewards.asMentee).map((reward) => reward.status),
  )

  return REWARD_STATUSES.filter((status) => present.has(status))
})

async function offer(payload: RewardPayload): Promise<void> {
  if (await rewards.offer(payload)) {
    offering.value = false
    toasts.success('Reward offered')
  }
}

async function request(payload: RewardPayload): Promise<void> {
  if (await rewards.request(payload)) {
    requesting.value = false
    toasts.success('Request sent')
  }
}
</script>

<template>
  <div class="space-y-6">
    <SectionHeader
      eyebrow="Stakes"
      title="Rewards"
      subtitle="Something worth finishing for, agreed with a mentor. Pathforge records that it happened - it never moves money."
    >
      <template #actions>
        <BaseButton
          v-if="mentorships.acceptedAsMentee.length > 0"
          variant="subtle"
          size="sm"
          icon="plus"
          @click="requesting = true"
        >
          Ask for one
        </BaseButton>
        <BaseButton
          v-if="mentorships.acceptedAsMentor.length > 0"
          variant="primary"
          size="sm"
          icon="gift"
          @click="offering = true"
        >
          Offer one
        </BaseButton>
      </template>
    </SectionHeader>

    <ErrorBanner :failure="rewards.failure" dismissible @dismiss="rewards.clearFailure()" />

    <BaseTabs v-model="tab" :tabs="tabs" aria-label="Reward sections" />

    <section v-if="tab === 'ledger'" class="pt-2">
      <RewardLedgerTable :rows="rewards.ledger" :loading="rewards.ledgerLoading" />
    </section>

    <template v-else>
      <div v-if="availableStatuses.length > 1" class="flex flex-wrap items-center gap-1.5 pt-2">
        <button
          type="button"
          :aria-pressed="statusFilter === null"
          :class="[
            'h-7 rounded-md border px-2.5 text-[12px] font-medium transition-colors duration-150',
            statusFilter === null
              ? 'border-brand bg-brand-soft text-brand'
              : 'border-line bg-surface-2 text-ink-muted hover:text-ink',
          ]"
          @click="statusFilter = null"
        >
          All
        </button>
        <button
          v-for="status in availableStatuses"
          :key="status"
          type="button"
          :aria-pressed="statusFilter === status"
          :class="[
            'h-7 rounded-md border px-2.5 text-[12px] font-medium capitalize transition-colors duration-150',
            statusFilter === status
              ? 'border-brand bg-brand-soft text-brand'
              : 'border-line bg-surface-2 text-ink-muted hover:text-ink',
          ]"
          @click="statusFilter = status"
        >
          {{ status }}
        </button>
      </div>

      <SkeletonBlock
        v-if="rewards.loading && rewards.items.length === 0"
        :rows="2"
        height="h-40"
        rounded="rounded-xl"
      />

      <EmptyState
        v-else-if="visible.length === 0 && statusFilter !== null"
        icon="filter"
        title="Nothing with that status"
        body="Clear the filter to see the rest."
        compact
      >
        <BaseButton variant="subtle" size="sm" @click="statusFilter = null">Clear filter</BaseButton>
      </EmptyState>

      <EmptyState
        v-else-if="visible.length === 0 && tab === 'mine'"
        icon="gift"
        title="No rewards yet"
        :body="
          mentorships.acceptedAsMentee.length > 0
            ? 'Ask your mentor for one, or wait for an offer. Once earned, you claim it here.'
            : 'Rewards are agreed between a mentor and a mentee. Set up a mentorship first.'
        "
      >
        <BaseButton
          v-if="mentorships.acceptedAsMentee.length > 0"
          variant="primary"
          size="sm"
          icon="plus"
          @click="requesting = true"
        >
          Ask for a reward
        </BaseButton>
        <BaseButton v-else variant="primary" size="sm" to="/mentorships" icon="handshake">
          Set up a mentorship
        </BaseButton>
      </EmptyState>

      <EmptyState
        v-else-if="visible.length === 0"
        icon="handshake"
        title="You have not offered any"
        :body="
          mentorships.acceptedAsMentor.length > 0
            ? 'Offer a mentee something worth finishing for. It becomes claimable once the linked work is done.'
            : 'Once someone accepts you as their mentor you can offer them a reward.'
        "
      >
        <BaseButton
          v-if="mentorships.acceptedAsMentor.length > 0"
          variant="primary"
          size="sm"
          icon="gift"
          @click="offering = true"
        >
          Offer a reward
        </BaseButton>
      </EmptyState>

      <div v-else class="grid gap-3 lg:grid-cols-2">
        <RewardCard
          v-for="reward in visible"
          :key="reward.id"
          :reward="reward"
          :busy="rewards.saving"
          @respond="rewards.respond(reward.id, $event.accepted, $event.note)"
          @claim="rewards.claim(reward.id)"
          @fulfill="rewards.fulfill(reward.id, $event)"
          @revoke="rewards.revoke(reward.id)"
        />
      </div>
    </template>

    <BaseModal v-model:open="offering" title="Offer a reward" size="lg">
      <RewardOfferForm
        :mentorships="mentorships.acceptedAsMentor"
        :goals="goals.list"
        :saving="rewards.saving"
        :failure="rewards.failure"
        @submit="offer"
        @cancel="offering = false"
      />
    </BaseModal>

    <BaseModal v-model:open="requesting" title="Ask for a reward" size="lg">
      <RewardRequestForm
        :mentorships="mentorships.acceptedAsMentee"
        :goals="goals.list"
        :saving="rewards.saving"
        :failure="rewards.failure"
        @submit="request"
        @cancel="requesting = false"
      />
    </BaseModal>
  </div>
</template>
