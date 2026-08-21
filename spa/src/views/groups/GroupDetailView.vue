<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTabs from '@/components/ui/BaseTabs.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import ComparisonChart from '@/components/analytics/ComparisonChart.vue'
import LeaderboardTable from '@/components/analytics/LeaderboardTable.vue'
import ChallengeCard from '@/components/groups/ChallengeCard.vue'
import GroupMemberList from '@/components/groups/GroupMemberList.vue'
import InviteModal from '@/components/groups/InviteModal.vue'
import MentorRequestForm from '@/components/mentorship/MentorRequestForm.vue'
import { useAuthStore } from '@/stores/auth'
import { useGoalsStore } from '@/stores/goals'
import { useGroupsStore } from '@/stores/groups'
import { useMentorshipsStore } from '@/stores/mentorships'
import { useToastsStore } from '@/stores/toasts'
import type { TabItem } from '@/components/ui/types'
import type { LeaderboardPeriod } from '@/types/analytics'
import type { ChallengePayload, Challenge, GroupMember } from '@/types/group'
import type { MentorshipRequestPayload } from '@/types/mentorship'

const route = useRoute()
const router = useRouter()

const auth = useAuthStore()
const groups = useGroupsStore()
const goals = useGoalsStore()
const mentorships = useMentorshipsStore()
const toasts = useToastsStore()

const groupId = computed(() => Number(route.params.id))
const group = computed(() => groups.detail)

const tab = ref('leaderboard')
const period = ref<LeaderboardPeriod>('week')
const inviting = ref(false)
const renaming = ref(false)
const newName = ref('')
const creatingChallenge = ref(false)
const mentorTarget = ref<GroupMember | null>(null)
const pendingLeave = ref(false)
const pendingDelete = ref(false)
const pendingChallengeDelete = ref<Challenge | null>(null)
const notFound = ref(false)

const challengeForm = ref<ChallengePayload>({
  title: '',
  description: '',
  starts_on: '',
  ends_on: '',
})

const challenges = computed(() => groups.challenges[groupId.value] ?? [])
const leaderboard = computed(() => groups.leaderboards[groupId.value]?.entries ?? [])
const trend = computed(() => groups.trends[groupId.value] ?? [])

const tabs = computed<TabItem[]>(() => [
  { value: 'leaderboard', label: 'Leaderboard', icon: 'chart' },
  { value: 'compare', label: 'Compare', icon: 'trend' },
  {
    value: 'challenges',
    label: 'Challenges',
    icon: 'award',
    count: challenges.value.length,
  },
  {
    value: 'members',
    label: 'Members',
    icon: 'users',
    count: group.value?.members?.length ?? group.value?.members_count ?? null,
  },
])

async function load(): Promise<void> {
  notFound.value = false

  if (!(await groups.fetchOne(groupId.value))) {
    notFound.value = true

    return
  }

  void groups.fetchLeaderboard(groupId.value, period.value)
  void groups.fetchChallenges(groupId.value)
  void goals.fetchAll({ per_page: 100 })
}

onMounted(load)
watch(groupId, load)

watch(period, (next) => {
  void groups.fetchLeaderboard(groupId.value, next, true)
})

watch(tab, (next) => {
  if (next === 'compare' && trend.value.length === 0) {
    void groups.fetchTrend(groupId.value, 28)
  }
})

async function rename(): Promise<void> {
  if (await groups.rename(groupId.value, newName.value.trim())) {
    renaming.value = false
    toasts.success('Group renamed')
  }
}

async function invite(email: string | null): Promise<void> {
  const message = await groups.invite(groupId.value, email)

  if (message) {
    toasts.success('Invite handled', message)
  }
}

async function regenerate(): Promise<void> {
  if (await groups.regenerateInviteCode(groupId.value)) {
    toasts.info('New code issued', 'The old code no longer works.')
  }
}

async function createChallenge(): Promise<void> {
  const payload: ChallengePayload = {
    title: challengeForm.value.title.trim(),
    description: challengeForm.value.description?.trim() || null,
    starts_on: challengeForm.value.starts_on || null,
    ends_on: challengeForm.value.ends_on || null,
  }

  if (await groups.createChallenge(groupId.value, payload)) {
    creatingChallenge.value = false
    challengeForm.value = { title: '', description: '', starts_on: '', ends_on: '' }
    toasts.success('Challenge created')
  }
}

async function requestMentorship(payload: MentorshipRequestPayload): Promise<void> {
  if (await mentorships.request(payload)) {
    mentorTarget.value = null
    toasts.success('Request sent', 'They will see it on their mentorship page.')
  }
}

async function leave(): Promise<void> {
  if (await groups.leave(groupId.value)) {
    pendingLeave.value = false
    toasts.info('You left the group')
    await router.replace('/groups')
  }
}

async function destroy(): Promise<void> {
  if (await groups.destroy(groupId.value)) {
    pendingDelete.value = false
    toasts.info('Group deleted')
    await router.replace('/groups')
  }
}

async function confirmChallengeDelete(): Promise<void> {
  if (!pendingChallengeDelete.value) {
    return
  }

  const { challengesApi } = await import('@/api/groups')

  try {
    await challengesApi.destroy(pendingChallengeDelete.value.id)
    await groups.fetchChallenges(groupId.value)
    toasts.info('Challenge removed')
  } catch {
    toasts.error('Could not remove that challenge')
  } finally {
    pendingChallengeDelete.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <EmptyState
      v-if="notFound"
      icon="search"
      title="That group is not available"
      body="You may have left it, or it may have been deleted."
    >
      <BaseButton variant="primary" size="sm" to="/groups">Back to groups</BaseButton>
    </EmptyState>

    <SkeletonBlock v-else-if="!group" :rows="3" height="h-20" rounded="rounded-xl" />

    <template v-else>
      <header class="space-y-3">
        <div class="flex flex-wrap items-center gap-2 text-[11.5px] text-ink-faint">
          <RouterLink to="/groups" class="transition-colors hover:text-ink-muted">Groups</RouterLink>
          <AppIcon name="chevronRight" :size="11" />
          <span>{{ group.name }}</span>
        </div>

        <SectionHeader
          :title="group.name"
          :subtitle="`${group.members_count ?? group.members?.length ?? 0} members. Only goals explicitly shared with this group are visible here.`"
        >
          <template #actions>
            <BaseButton
              v-if="group.is_owner"
              variant="subtle"
              size="sm"
              icon="mail"
              @click="inviting = true"
            >
              Invite
            </BaseButton>
            <BaseButton
              v-if="group.is_owner"
              variant="ghost"
              size="sm"
              icon="pencil"
              @click="
                newName = group.name;
                renaming = true
              "
            >
              Rename
            </BaseButton>
            <BaseButton
              v-if="group.is_owner"
              variant="ghost"
              size="sm"
              icon="trash"
              @click="pendingDelete = true"
            >
              Delete
            </BaseButton>
            <BaseButton v-else variant="ghost" size="sm" icon="logout" @click="pendingLeave = true">
              Leave
            </BaseButton>
          </template>
        </SectionHeader>
      </header>

      <ErrorBanner :failure="groups.failure" dismissible @dismiss="groups.clearFailure()" />

      <BaseTabs v-model="tab" :tabs="tabs" aria-label="Group sections" />

      <section v-if="tab === 'leaderboard'" class="pt-2">
        <LeaderboardTable
          :entries="leaderboard"
          :period="period"
          :loading="groups.leaderboardLoading"
          :current-user-id="auth.user?.id ?? null"
          @period="period = $event"
        />
      </section>

      <section v-else-if="tab === 'compare'" class="space-y-3 pt-2">
        <SectionHeader
          eyebrow="Side by side"
          title="Focus minutes per member"
          subtitle="Last 28 days, bounded by the same shared-goal rule as the leaderboard."
        />

        <div class="rounded-xl border border-line bg-surface p-4 sm:p-5">
          <SkeletonBlock v-if="groups.trendLoading && trend.length === 0" :rows="3" height="h-16" />
          <ComparisonChart v-else :series="trend" :height="280" />
        </div>
      </section>

      <section v-else-if="tab === 'challenges'" class="space-y-4 pt-2">
        <SectionHeader
          eyebrow="Squad challenges"
          title="Shared pushes"
          subtitle="Enter with a goal you have shared with this group, and standings track its completion."
        >
          <template #actions>
            <BaseButton variant="primary" size="sm" icon="plus" @click="creatingChallenge = true">
              New challenge
            </BaseButton>
          </template>
        </SectionHeader>

        <EmptyState
          v-if="challenges.length === 0"
          icon="award"
          title="No challenges yet"
          body="Set a shared push with a start and end date. Everyone enters a goal and the standings follow its completion."
        >
          <BaseButton variant="primary" size="sm" icon="plus" @click="creatingChallenge = true">
            Create one
          </BaseButton>
        </EmptyState>

        <div v-else class="grid gap-3 lg:grid-cols-2">
          <ChallengeCard
            v-for="challenge in challenges"
            :key="challenge.id"
            :challenge="challenge"
            :goals="goals.list"
            :can-delete="challenge.created_by === auth.user?.id || group.is_owner"
            :saving="groups.saving"
            :current-user-id="auth.user?.id ?? null"
            @toggle="groups.toggleChallenge(challenge, $event)"
            @destroy="pendingChallengeDelete = challenge"
          />
        </div>
      </section>

      <section v-else class="space-y-4 pt-2">
        <SectionHeader
          eyebrow="People"
          title="Members"
          subtitle="Mentorship starts here - it is only ever permitted between members of a shared group."
        />

        <GroupMemberList
          :members="group.members ?? []"
          :is-owner="group.is_owner"
          :current-user-id="auth.user?.id ?? null"
          :saving="groups.saving"
          @remove="groups.removeMember(groupId, $event.id)"
          @mentor="mentorTarget = $event"
        />
      </section>

      <!-- Modals ------------------------------------------------------------- -->
      <InviteModal
        v-model:open="inviting"
        :group="group"
        :saving="groups.saving"
        :failure="groups.failure"
        @invite="invite"
        @regenerate="regenerate"
      />

      <BaseModal v-model:open="renaming" title="Rename group" size="sm">
        <BaseInput v-model="newName" label="Group name" required />

        <template #footer>
          <BaseButton variant="ghost" size="sm" @click="renaming = false">Cancel</BaseButton>
          <BaseButton
            variant="primary"
            size="sm"
            :loading="groups.saving"
            :disabled="newName.trim().length === 0"
            @click="rename"
          >
            Save
          </BaseButton>
        </template>
      </BaseModal>

      <BaseModal v-model:open="creatingChallenge" title="New squad challenge" size="md">
        <div class="space-y-4">
          <BaseInput
            v-model="challengeForm.title"
            label="Title"
            placeholder="February deep work push"
            required
          />
          <BaseTextarea
            v-model="challengeForm.description"
            label="Description"
            :rows="3"
            :maxlength="2000"
          />
          <div class="grid gap-4 sm:grid-cols-2">
            <BaseInput v-model="challengeForm.starts_on" label="Starts" type="date" />
            <BaseInput v-model="challengeForm.ends_on" label="Ends" type="date" />
          </div>
        </div>

        <template #footer>
          <BaseButton variant="ghost" size="sm" @click="creatingChallenge = false">Cancel</BaseButton>
          <BaseButton
            variant="primary"
            size="sm"
            :loading="groups.saving"
            :disabled="challengeForm.title.trim().length === 0"
            @click="createChallenge"
          >
            Create
          </BaseButton>
        </template>
      </BaseModal>

      <BaseModal
        :open="mentorTarget !== null"
        :title="mentorTarget ? `Mentorship with ${mentorTarget.name}` : 'Mentorship'"
        size="md"
        @update:open="(value) => !value && (mentorTarget = null)"
      >
        <MentorRequestForm
          v-if="mentorTarget"
          :candidates="[mentorTarget]"
          :preset-user-id="mentorTarget.id"
          :saving="mentorships.saving"
          :failure="mentorships.failure"
          @submit="requestMentorship"
          @cancel="mentorTarget = null"
        />
      </BaseModal>

      <ConfirmDialog
        v-model:open="pendingLeave"
        title="Leave this group?"
        body="You will lose sight of goals shared with it, and goals you shared here become private again. Your own history is untouched."
        confirm-label="Leave group"
        :busy="groups.saving"
        @confirm="leave"
      />

      <ConfirmDialog
        v-model:open="pendingDelete"
        title="Delete this group?"
        body="Every member loses the shared view and its challenges go with it. Individual goals and history survive."
        confirm-label="Delete group"
        :busy="groups.saving"
        @confirm="destroy"
      />

      <ConfirmDialog
        :open="pendingChallengeDelete !== null"
        title="Remove this challenge?"
        :body="`“${pendingChallengeDelete?.title ?? 'It'}” and its standings disappear for everyone.`"
        confirm-label="Remove"
        :busy="groups.saving"
        @update:open="(value) => !value && (pendingChallengeDelete = null)"
        @confirm="confirmChallengeDelete"
      />
    </template>
  </div>
</template>
