<script setup lang="ts">
import EmptyState from '@/components/ui/EmptyState.vue'
import MentorshipRow from './MentorshipRow.vue'
import type { Mentorship } from '@/types/mentorship'

/** The people who mentor the viewer. Separate from MenteeList because the available actions differ. */
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
      icon="handshake"
      title="No mentors yet"
      body="Ask a member of one of your groups to mentor you. They get read access to your shared goals plus the ability to set time budgets and due dates."
      compact
    />

    <ul v-else class="divide-y divide-line overflow-hidden rounded-xl border border-line">
      <MentorshipRow
        v-for="mentorship in mentorships"
        :key="mentorship.id"
        :mentorship="mentorship"
        counterpart-role="mentor"
        :saving="saving"
        @accept="emit('accept', mentorship)"
        @decline="emit('decline', mentorship)"
        @end="emit('end', mentorship)"
      />
    </ul>
  </div>
</template>
