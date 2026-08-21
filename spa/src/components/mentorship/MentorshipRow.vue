<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { MENTORSHIP_STATUS_STYLES } from '@/utils/colors'
import { formatRelative } from '@/utils/date'
import type { Mentorship } from '@/types/mentorship'

const props = defineProps<{
  mentorship: Mentorship
  /** Which side of the pair the *other* person is on, from the viewer's seat. */
  counterpartRole: 'mentor' | 'mentee'
  saving?: boolean
}>()

const emit = defineEmits<{ accept: []; decline: []; end: [] }>()

const style = computed(() => MENTORSHIP_STATUS_STYLES[props.mentorship.status])

const counterpart = computed(() =>
  props.counterpartRole === 'mentor' ? props.mentorship.mentor : props.mentorship.mentee,
)

const canEnd = computed(() => props.mentorship.status === 'accepted')

/** Pending and *not* requested by the viewer -- the server already worked this out. */
const canRespond = computed(() => props.mentorship.viewer_can_respond)

const waitingOnThem = computed(
  () => props.mentorship.status === 'pending' && !props.mentorship.viewer_can_respond,
)

function initials(name: string | undefined): string {
  return (
    (name ?? '')
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join('') || '?'
  )
}
</script>

<template>
  <li class="flex flex-wrap items-center gap-3 bg-surface p-3.5">
    <span
      class="grid size-9 shrink-0 place-items-center rounded-full border border-line bg-surface-2 text-[11.5px] font-bold text-ink-muted"
      aria-hidden="true"
    >
      {{ initials(counterpart?.name) }}
    </span>

    <div class="min-w-0 flex-1">
      <p class="truncate text-[13.5px] font-medium text-ink">
        {{ counterpart?.name ?? 'Unknown member' }}
      </p>
      <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px] text-ink-faint">
        <span>{{ counterpartRole === 'mentor' ? 'Mentors you' : 'You mentor them' }}</span>
        <span v-if="mentorship.created_at" class="tnum">
          asked {{ formatRelative(mentorship.created_at) }}
        </span>
        <span v-if="mentorship.responded_at" class="tnum">
          answered {{ formatRelative(mentorship.responded_at) }}
        </span>
      </p>
    </div>

    <BaseBadge :tone="style.chip" :dot="style.dot">{{ style.label }}</BaseBadge>

    <div class="flex shrink-0 items-center gap-1.5">
      <template v-if="canRespond">
        <BaseButton variant="primary" size="sm" icon="check" :loading="saving" @click="emit('accept')">
          Accept
        </BaseButton>
        <BaseButton variant="ghost" size="sm" :loading="saving" @click="emit('decline')">
          Decline
        </BaseButton>
      </template>

      <p v-else-if="waitingOnThem" class="flex items-center gap-1.5 text-[11.5px] text-ink-faint">
        <AppIcon name="clock" :size="12" />
        Waiting on them
      </p>

      <BaseButton v-if="canEnd" variant="ghost" size="sm" :loading="saving" @click="emit('end')">
        End
      </BaseButton>
    </div>
  </li>
</template>
