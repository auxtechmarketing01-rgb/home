<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import type { Reward } from '@/types/reward'

const props = defineProps<{ reward: Reward; busy?: boolean }>()

const emit = defineEmits<{ claim: [] }>()

/**
 * Enabled only on `earned`, and only for the mentee -- the server says so via
 * `available_actions`, and this button trusts that rather than re-deriving the
 * state machine on the client.
 */
const enabled = computed(() => props.reward.available_actions.includes('claim'))

/**
 * A greyed-out button with no explanation is a dead end. Each blocked state says
 * which one it is, so "why can't I press this" is answered in place.
 */
const reason = computed(() => {
  if (enabled.value) {
    return null
  }

  switch (props.reward.status) {
    case 'requested':
      return 'Waiting on your mentor to respond'
    case 'offered':
      return 'Not yet earned - finish the linked work first'
    case 'claimed':
      return 'Claimed - waiting on your mentor to fulfil it'
    case 'fulfilled':
      return 'Already fulfilled'
    case 'denied':
      return 'Your mentor declined this one'
    case 'revoked':
      return 'Revoked by your mentor'
    default:
      return props.reward.viewer_role === 'mentor'
        ? 'Only the mentee can claim a reward'
        : 'Not claimable yet'
  }
})
</script>

<template>
  <div class="flex flex-col items-start gap-1">
    <button
      type="button"
      class="inline-flex h-8 items-center gap-1.5 rounded-md border px-3 text-[12.5px] font-semibold transition-colors duration-150"
      :class="
        enabled
          ? 'border-transparent bg-brand text-brand-ink hover:bg-brand-hover'
          : 'cursor-not-allowed border-line bg-surface-2 text-ink-faint'
      "
      :disabled="!enabled || busy"
      :aria-disabled="!enabled"
      :aria-describedby="reason ? `claim-reason-${reward.id}` : undefined"
      @click="enabled && emit('claim')"
    >
      <AppIcon name="gift" :size="14" />
      Claim
    </button>

    <p v-if="reason" :id="`claim-reason-${reward.id}`" class="text-[10.5px] text-ink-faint">
      {{ reason }}
    </p>
  </div>
</template>
