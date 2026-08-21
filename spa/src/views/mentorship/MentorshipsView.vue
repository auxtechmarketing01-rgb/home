<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTabs from '@/components/ui/BaseTabs.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import MenteeList from '@/components/mentorship/MenteeList.vue'
import MentorDashboard from '@/components/mentorship/MentorDashboard.vue'
import MentorList from '@/components/mentorship/MentorList.vue'
import MentorRequestForm from '@/components/mentorship/MentorRequestForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useGroupsStore } from '@/stores/groups'
import { useMentorshipsStore } from '@/stores/mentorships'
import { useToastsStore } from '@/stores/toasts'
import type { TabItem } from '@/components/ui/types'
import type { MentorshipRequestPayload } from '@/types/mentorship'

const auth = useAuthStore()
const mentorships = useMentorshipsStore()
const groups = useGroupsStore()
const toasts = useToastsStore()

const tab = ref('relationships')
const requesting = ref(false)

onMounted(() => {
  void mentorships.fetchAll()
  void groups.fetchAll()
})

/**
 * Candidates come from the groups the member already belongs to. That *is* the
 * permitted universe (FR-MENT-01), so there is nothing to search beyond it.
 */
const candidates = computed(() => groups.shareableMembers(auth.user?.id))

const tabs = computed<TabItem[]>(() => [
  {
    value: 'relationships',
    label: 'Relationships',
    icon: 'handshake',
    count: mentorships.items.length,
  },
  {
    value: 'dashboard',
    label: 'Mentor dashboard',
    icon: 'chart',
    count: mentorships.dashboard.length || null,
    hidden: mentorships.acceptedAsMentor.length === 0,
  },
])

watch(tab, (next) => {
  if (next === 'dashboard' && mentorships.dashboard.length === 0) {
    void mentorships.fetchDashboard()
  }
})

async function request(payload: MentorshipRequestPayload): Promise<void> {
  if (await mentorships.request(payload)) {
    requesting.value = false
    toasts.success('Request sent', 'They will see it on their mentorship page.')
  }
}

async function accept(id: number): Promise<void> {
  if (await mentorships.accept(id)) {
    toasts.success('Accepted', 'Shared goals are now visible to the mentor.')
    void mentorships.fetchDashboard()
  }
}

async function decline(id: number): Promise<void> {
  if (await mentorships.decline(id)) {
    toasts.info('Declined')
  }
}

async function end(id: number): Promise<void> {
  if (await mentorships.end(id)) {
    toasts.info('Mentorship ended', 'Access is revoked from here on.')
  }
}
</script>

<template>
  <div class="space-y-6">
    <SectionHeader
      eyebrow="Support"
      title="Mentorship"
      subtitle="A mentor gets read access to shared goals plus the ability to set time budgets, due dates and rewards. They never edit your steps or mark anything done."
    >
      <template #actions>
        <BaseButton variant="primary" size="sm" icon="plus" @click="requesting = true">
          New mentorship
        </BaseButton>
      </template>
    </SectionHeader>

    <ErrorBanner :failure="mentorships.failure" dismissible @dismiss="mentorships.clearFailure()" />

    <section
      v-if="mentorships.pendingForMe.length > 0"
      class="rounded-xl border border-warn/30 bg-warn/10 px-4 py-3"
      role="status"
    >
      <p class="text-[13px] text-ink">
        <span class="font-semibold">
          {{ mentorships.pendingForMe.length }} request{{
            mentorships.pendingForMe.length === 1 ? '' : 's'
          }}
        </span>
        waiting on your answer.
      </p>
    </section>

    <BaseTabs v-model="tab" :tabs="tabs" aria-label="Mentorship sections" />

    <SkeletonBlock
      v-if="mentorships.loading && mentorships.items.length === 0"
      :rows="3"
      height="h-16"
      rounded="rounded-xl"
    />

    <!--
      Two lists, not one merged list: the actions differ per side, so merging
      would mean re-deriving `viewer_role` on every row.
    -->
    <div v-else-if="tab === 'relationships'" class="grid gap-6 pt-2 lg:grid-cols-2">
      <section class="space-y-3">
        <SectionHeader eyebrow="Your corner" title="Mentors you have" />
        <MentorList
          :mentorships="mentorships.asMentee"
          :saving="mentorships.saving"
          @accept="accept($event.id)"
          @decline="decline($event.id)"
          @end="end($event.id)"
        />
      </section>

      <section class="space-y-3">
        <SectionHeader eyebrow="Your people" title="People you mentor" />
        <MenteeList
          :mentorships="mentorships.asMentor"
          :saving="mentorships.saving"
          @accept="accept($event.id)"
          @decline="decline($event.id)"
          @end="end($event.id)"
        />
      </section>
    </div>

    <section v-else class="space-y-4 pt-2">
      <SectionHeader
        eyebrow="Rollup"
        title="Every mentee at a glance"
        subtitle="Streaks and goal progress across everyone who accepted you as their mentor. Read only."
      />

      <MentorDashboard
        :rows="mentorships.dashboard"
        :loading="mentorships.dashboardLoading"
      />
    </section>

    <BaseModal
      v-model:open="requesting"
      title="New mentorship"
      description="Only members of your groups can appear here - Pathforge has no cross-group search."
      size="md"
    >
      <MentorRequestForm
        :candidates="candidates"
        :saving="mentorships.saving"
        :failure="mentorships.failure"
        @submit="request"
        @cancel="requesting = false"
      />
    </BaseModal>
  </div>
</template>
