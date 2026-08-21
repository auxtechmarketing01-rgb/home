<script setup lang="ts">
import EmptyState from '@/components/ui/EmptyState.vue'
import MentorshipRow from './MentorshipRow.vue'
import type { Mentorship } from '@/types/mentorship'

/** The people the viewer mentors. */
defineProps<{ mentorships: Mentorship[]; saving?: boolean }>()

const emit = defineEmits<{
  accept: [Mentorship]
  decline: [Mentorship]
  end: [Mentorship]
}>()
</script>

<template>
  <div>
    <EmptyState
      v-if="mentorships.length === 0"
      icon="users"
      title="You are not mentoring anyone"
      body="Offer to mentor someone in one of your groups, or wait for a request to come in."
      compact
    />

    <ul v-else class="divide-y divide-line overflow-hidden rounded-xl border border-line">
      <MentorshipRow
        v-for="mentorship in mentorships"
        :key="mentorship.id"
        :mentorship="mentorship"
        counterpart-role="mentee"
        :saving="saving"
        @accept="emit('accept', mentorship)"
        @decline="emit('decline', mentorship)"
        @end="emit('end', mentorship)"
      />
    </ul>
  </div>
</template>
