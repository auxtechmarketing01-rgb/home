<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import RewardClaimButton from './RewardClaimButton.vue'
import { REWARD_STATUS_STYLES } from '@/utils/colors'
import { formatDateTime } from '@/utils/date'
import type { Reward, RewardType } from '@/types/reward'
import type { IconName } from '@/components/ui/icons'

const props = defineProps<{ reward: Reward; busy?: boolean }>()

const emit = defineEmits<{
  respond: [{ accepted: boolean; note: string | null }]
  claim: []
  fulfill: [string | null]
  revoke: []
}>()

const fulfilling = ref(false)
const responding = ref(false)
const note = ref('')

const style = computed(() => REWARD_STATUS_STYLES[props.reward.status])

const TYPE_ICONS: Record<RewardType, IconName> = {
  monetary: 'wallet',
  privilege: 'key',
  custom: 'sparkle',
}

/**
 * The server computes the exact set of transitions this viewer may trigger, from
 * the same side-and-state pair the Policy enforces. Reading it means the UI can
 * neither offer a button the API refuses nor hide one it would have allowed.
 */
const can = computed(() => ({
  respond: props.reward.available_actions.includes('respond'),
  revoke: props.reward.available_actions.includes('revoke'),
  fulfill: props.reward.available_actions.includes('fulfill'),
  claim: props.reward.available_actions.includes('claim'),
}))

/** Claim is always rendered for a mentee so its disabled reason stays visible. */
const showsClaim = computed(() => props.reward.viewer_role === 'mentee')

const amount = computed(() => {
  if (props.reward.type !== 'monetary' || props.reward.monetary_amount === null) {
    return null
  }

  const value = Number(props.reward.monetary_amount)
  const formatted = Number.isFinite(value)
    ? value.toLocaleString(undefined, { maximumFractionDigits: 2 })
    : String(props.reward.monetary_amount)

  return props.reward.currency_label ? `${formatted} ${props.reward.currency_label}` : formatted
})

const origin = computed(() =>
  props.reward.requested_by === 'mentee' ? 'Asked for by the mentee' : 'Offered by the mentor',
)

function submitFulfil(): void {
  emit('fulfill', note.value.trim() || null)
  note.value = ''
  fulfilling.value = false
}

function submitResponse(accepted: boolean): void {
  emit('respond', { accepted, note: note.value.trim() || null })
  note.value = ''
  responding.value = false
}
</script>

<template>
  <article class="rounded-xl border border-line bg-surface p-4">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
          <!-- Every one of the seven states gets its own chip; a generic one throws the signal away. -->
          <BaseBadge :tone="style.chip" :dot="style.dot" size="md">{{ style.label }}</BaseBadge>

          <BaseBadge tone="bg-surface-2 text-ink-muted border-line">
            <AppIcon :name="TYPE_ICONS[reward.type]" :size="11" />
            {{ reward.type }}
          </BaseBadge>

          <span class="text-[10.5px] text-ink-faint">{{ origin }}</span>
        </div>

        <h3 class="truncate font-display text-[15px] font-semibold text-ink">{{ reward.title }}</h3>

        <p v-if="amount" class="tnum mt-0.5 text-[14px] font-semibold text-brand">{{ amount }}</p>

        <p
          v-if="reward.description"
          class="mt-1.5 line-clamp-3 text-[12.5px] leading-relaxed text-ink-muted"
        >
          {{ reward.description }}
        </p>
      </div>
    </div>

    <dl class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-ink-faint">
      <div v-if="reward.goal_id" class="flex items-center gap-1.5">
        <AppIcon name="target" :size="11" />
        <dt class="sr-only">Linked goal</dt>
        <dd>
          <RouterLink
            :to="{ name: 'goal-detail', params: { id: reward.goal_id } }"
            class="transition-colors hover:text-ink-muted"
          >
            {{ reward.goal?.title ?? `Goal #${reward.goal_id}` }}
          </RouterLink>
        </dd>
      </div>

      <div v-if="reward.roadmap_item" class="flex items-center gap-1.5">
        <AppIcon name="route" :size="11" />
        <dt class="sr-only">Linked step</dt>
        <dd>{{ reward.roadmap_item.title }}</dd>
      </div>

      <div v-if="reward.claimed_at" class="flex items-center gap-1.5">
        <AppIcon name="gift" :size="11" />
        <dt class="sr-only">Claimed</dt>
        <dd class="tnum">claimed {{ formatDateTime(reward.claimed_at) }}</dd>
      </div>

      <div v-if="reward.fulfilled_at" class="flex items-center gap-1.5">
        <AppIcon name="checkCircle" :size="11" />
        <dt class="sr-only">Fulfilled</dt>
        <dd class="tnum">fulfilled {{ formatDateTime(reward.fulfilled_at) }}</dd>
      </div>
    </dl>

    <p
      v-if="reward.fulfilled_note"
      class="mt-2.5 border-l-2 border-line pl-2.5 text-[12px] italic leading-relaxed text-ink-muted"
    >
      {{ reward.fulfilled_note }}
    </p>

    <footer
      v-if="can.respond || can.revoke || can.fulfill || showsClaim"
      class="mt-3.5 flex flex-wrap items-start gap-2 border-t border-line pt-3.5"
    >
      <BaseButton
        v-if="can.respond"
        variant="primary"
        size="sm"
        icon="check"
        :loading="busy"
        @click="responding = true"
      >
        Respond
      </BaseButton>

      <RewardClaimButton
        v-if="showsClaim"
        :reward="reward"
        :busy="busy"
        @claim="emit('claim')"
      />

      <BaseButton
        v-if="can.fulfill"
        variant="primary"
        size="sm"
        icon="checkCircle"
        :loading="busy"
        @click="fulfilling = true"
      >
        Mark fulfilled
      </BaseButton>

      <BaseButton v-if="can.revoke" variant="ghost" size="sm" :loading="busy" @click="emit('revoke')">
        Revoke
      </BaseButton>
    </footer>

    <BaseModal
      v-model:open="responding"
      :title="`Respond to: ${reward.title}`"
      description="Accepting turns this request into an offer your mentee can earn. Declining closes it."
      size="sm"
    >
      <BaseTextarea v-model="note" label="Note (optional)" :rows="3" :maxlength="2000" />

      <template #footer>
        <BaseButton variant="ghost" size="sm" @click="submitResponse(false)">Decline</BaseButton>
        <BaseButton variant="primary" size="sm" :loading="busy" @click="submitResponse(true)">
          Accept
        </BaseButton>
      </template>
    </BaseModal>

    <BaseModal
      v-model:open="fulfilling"
      :title="`Mark fulfilled: ${reward.title}`"
      description="This records that something happened outside the app. Nothing is paid or transferred here."
      size="sm"
    >
      <BaseTextarea
        v-model="note"
        label="What happened? (optional)"
        placeholder="Transferred on Friday."
        :rows="3"
        :maxlength="2000"
      />

      <template #footer>
        <BaseButton variant="ghost" size="sm" @click="fulfilling = false">Cancel</BaseButton>
        <BaseButton variant="primary" size="sm" :loading="busy" @click="submitFulfil">
          Mark fulfilled
        </BaseButton>
      </template>
    </BaseModal>
  </article>
</template>
