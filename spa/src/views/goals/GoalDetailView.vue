<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseTabs from '@/components/ui/BaseTabs.vue'
import type { TabItem } from '@/components/ui/types'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import HeatmapCalendar from '@/components/analytics/HeatmapCalendar.vue'
import ProjectionBanner from '@/components/analytics/ProjectionBanner.vue'
import StatCard from '@/components/analytics/StatCard.vue'
import VelocityChart from '@/components/analytics/VelocityChart.vue'
import FocusModeSelector from '@/components/focus/FocusModeSelector.vue'
import FocusTimerWidget from '@/components/focus/FocusTimerWidget.vue'
import SprintHistoryList from '@/components/focus/SprintHistoryList.vue'
import GoalForm from '@/components/goals/GoalForm.vue'
import GoalHeader from '@/components/goals/GoalHeader.vue'
import AssignRoadmapItemForm from '@/components/roadmap/AssignRoadmapItemForm.vue'
import ResourceList from '@/components/roadmap/ResourceList.vue'
import ResourceUploader from '@/components/roadmap/ResourceUploader.vue'
import RoadmapItemNode from '@/components/roadmap/RoadmapItemNode.vue'
import RewardCard from '@/components/rewards/RewardCard.vue'
import RewardOfferForm from '@/components/rewards/RewardOfferForm.vue'
import RewardRequestForm from '@/components/rewards/RewardRequestForm.vue'
import { useGoalStats } from '@/composables/useGoalStats'
import { useAnalyticsStore } from '@/stores/analytics'
import { useAuthStore } from '@/stores/auth'
import { useGoalsStore } from '@/stores/goals'
import { useGroupsStore } from '@/stores/groups'
import { useMentorshipsStore } from '@/stores/mentorships'
import { useResourcesStore } from '@/stores/resources'
import { useRewardsStore } from '@/stores/rewards'
import { useRoadmapsStore } from '@/stores/roadmaps'
import { useSprintsStore } from '@/stores/sprints'
import { useToastsStore } from '@/stores/toasts'
import { formatDuration } from '@/utils/formatDuration'
import type { GoalPayload } from '@/types/goal'
import type { RoadmapItem } from '@/types/roadmap'
import type { RewardPayload } from '@/types/reward'
import type { SprintMode } from '@/types/sprint'

const route = useRoute()
const router = useRouter()

const auth = useAuthStore()
const goals = useGoalsStore()
const roadmaps = useRoadmapsStore()
const sprints = useSprintsStore()
const resources = useResourcesStore()
const analytics = useAnalyticsStore()
const rewards = useRewardsStore()
const mentorships = useMentorshipsStore()
const groups = useGroupsStore()
const toasts = useToastsStore()

const goalId = computed(() => Number(route.params.id))
const goal = computed(() => goals.get(goalId.value) ?? null)

const tab = ref<string>('roadmap')
const editing = ref(false)
const assigning = ref<RoadmapItem | null>(null)
const offering = ref(false)
const requesting = ref(false)
const pendingDelete = ref(false)
const notFound = ref(false)

const focusMode = ref<SprintMode>('pomodoro')
const focusMinutes = ref(25)
const focusItemId = ref<number | null>(null)

const { stats, trend, hasStats } = useGoalStats(goalId)

/**
 * `user` is always loaded on the show endpoint, so ownership is a direct
 * comparison rather than a guess. Everything downstream -- edit controls,
 * assignment controls, which reward form appears -- keys off these two.
 */
const isOwner = computed(() => {
  const owner = goal.value?.user?.id

  return owner === undefined || owner === auth.user?.id
})

const isMentorViewer = computed(() => {
  const ownerId = goal.value?.user?.id

  if (isOwner.value || ownerId === undefined) {
    return false
  }

  return mentorships.acceptedAsMentor.some((entry) => entry.mentee?.id === ownerId)
})

const roadmapId = computed(() => goal.value?.roadmap?.id ?? null)
const items = computed(() => roadmaps.items(roadmapId.value))

const goalRewards = computed(() => rewards.forGoal(goalId.value))

const mentorshipsForOffer = computed(() =>
  mentorships.acceptedAsMentor.filter((entry) => entry.mentee?.id === goal.value?.user?.id),
)

const tabs = computed<TabItem[]>(() => [
  { value: 'roadmap', label: 'Roadmap', icon: 'route', count: items.value.length },
  { value: 'focus', label: 'Focus', icon: 'timer' },
  { value: 'resources', label: 'Resources', icon: 'file', count: resources.items('goal', goalId.value).length },
  { value: 'analytics', label: 'Analytics', icon: 'chart' },
  { value: 'rewards', label: 'Rewards', icon: 'gift', count: goalRewards.value.length },
])

async function load(): Promise<void> {
  notFound.value = false
  const loaded = await goals.fetchOne(goalId.value)

  if (!loaded) {
    notFound.value = true

    return
  }

  /**
   * The show endpoint already returns the roadmap and its items, so the store is
   * seeded from that response instead of paying for a second round trip.
   */
  if (loaded.roadmap?.id) {
    roadmaps.setItems(loaded.roadmap.id, loaded.roadmap.items ?? [])
  }

  void goals.fetchCategories()
  void resources.fetchFor('goal', goalId.value)
  void sprints.fetchHistory({ goal_id: goalId.value, per_page: 20, page: 1 })
}

onMounted(load)
watch(goalId, load)

async function save(payload: GoalPayload): Promise<void> {
  if (await goals.update(goalId.value, payload)) {
    editing.value = false
    toasts.success('Goal saved')
  }
}

async function changeVisibility(next: {
  visibility: 'private' | 'group'
  group_id: number | null
}): Promise<void> {
  if (await goals.update(goalId.value, next)) {
    toasts.success(
      next.visibility === 'group' ? 'Shared with the group' : 'Back to private',
    )
  }
}

async function complete(): Promise<void> {
  if (await goals.complete(goalId.value)) {
    toasts.success('Goal completed', 'Nice.')
  }
}

async function archive(): Promise<void> {
  if (await goals.archive(goalId.value)) {
    toasts.info('Goal archived')
  }
}

async function destroy(): Promise<void> {
  if (await goals.destroy(goalId.value)) {
    pendingDelete.value = false
    toasts.info('Goal deleted')
    await router.replace('/goals')
  }
}

async function startSprint(item?: RoadmapItem | null): Promise<void> {
  const payload = sprints.buildStartPayload(focusMode.value, {
    goalId: goalId.value,
    roadmapItemId: item?.id ?? focusItemId.value,
    minutes: focusMinutes.value,
  })

  if (await sprints.start(payload)) {
    tab.value = 'focus'
    toasts.success('Sprint running', 'It keeps running even if you close this tab.')
  }
}

async function saveAssignment(payload: {
  assigned_minutes?: number | null
  assigned_due_at?: string | null
}): Promise<void> {
  if (!assigning.value) {
    return
  }

  if (await roadmaps.assign(assigning.value.id, payload)) {
    assigning.value = null
    toasts.success('Expectation saved')
  }
}

async function offerReward(payload: RewardPayload): Promise<void> {
  if (await rewards.offer(payload)) {
    offering.value = false
    toasts.success('Reward offered')
  }
}

async function requestReward(payload: RewardPayload): Promise<void> {
  if (await rewards.request(payload)) {
    requesting.value = false
    toasts.success('Request sent to your mentor')
  }
}

const itemOptions = computed(() =>
  items.value.map((item) => ({ value: item.id, label: item.title })),
)
</script>

<template>
  <div class="space-y-6">
    <EmptyState
      v-if="notFound"
      icon="search"
      title="That goal is not available"
      body="It may have been deleted, or it belongs to someone whose circle you are not in."
    >
      <BaseButton variant="primary" size="sm" to="/goals">Back to goals</BaseButton>
    </EmptyState>

    <SkeletonBlock v-else-if="!goal" :rows="4" height="h-20" rounded="rounded-xl" />

    <template v-else>
      <GoalHeader
        :goal="goal"
        :groups="groups.groups"
        :can-edit="isOwner"
        :saving="goals.saving"
        @edit="editing = true"
        @complete="complete"
        @archive="archive"
        @destroy="pendingDelete = true"
        @visibility="changeVisibility"
      />

      <ErrorBanner :failure="goals.failure" dismissible @dismiss="goals.clearFailure()" />

      <BaseTabs v-model="tab" :tabs="tabs" aria-label="Goal sections" />

      <!-- Roadmap ------------------------------------------------------------ -->
      <section v-if="tab === 'roadmap'" class="space-y-4 pt-2">
        <SectionHeader
          title="Roadmap"
          :subtitle="
            isOwner
              ? 'Ordered steps. Drag to reorder, or use the arrows.'
              : 'Read only. You can set a time budget and a due date on any step.'
          "
        >
          <template #actions>
            <BaseButton
              v-if="isOwner"
              variant="primary"
              size="sm"
              icon="route"
              :to="{ name: 'roadmap-builder', params: { id: goal.id } }"
            >
              Open builder
            </BaseButton>
          </template>
        </SectionHeader>

        <EmptyState
          v-if="items.length === 0"
          icon="route"
          title="No steps yet"
          :body="
            isOwner
              ? 'Open the builder to break this goal into ordered steps.'
              : 'They have not added any steps to this goal yet.'
          "
        >
          <BaseButton
            v-if="isOwner"
            variant="primary"
            size="sm"
            :to="{ name: 'roadmap-builder', params: { id: goal.id } }"
          >
            Open the builder
          </BaseButton>
        </EmptyState>

        <ol v-else class="relative space-y-2.5 pl-7">
          <span class="pf-rail absolute bottom-4 left-[9px] top-4 w-px" aria-hidden="true" />

          <li v-for="item in items" :key="item.id" class="relative">
            <span
              class="absolute -left-7 top-5 grid size-[18px] place-items-center rounded-full border-2 bg-canvas"
              :class="{
                'border-status-done': item.status === 'done',
                'border-status-progress': item.status === 'in_progress',
                'border-line-strong': item.status === 'todo',
                'border-line': item.status === 'skipped',
              }"
              aria-hidden="true"
            >
              <span v-if="item.status === 'done'" class="size-1.5 rounded-full bg-status-done" />
            </span>

            <RoadmapItemNode
              :item="item"
              :can-edit="isOwner"
              :can-assign="isMentorViewer"
              @status="roadmaps.setStatus(item, $event)"
              @edit="router.push({ name: 'roadmap-builder', params: { id: goal.id } })"
              @destroy="roadmaps.destroyItem(item)"
              @focus="startSprint(item)"
              @assign="assigning = item"
            />
          </li>
        </ol>
      </section>

      <!-- Focus -------------------------------------------------------------- -->
      <section v-else-if="tab === 'focus'" class="space-y-6 pt-2">
        <FocusTimerWidget v-if="sprints.activeSprint" :sprint="sprints.activeSprint" />

        <div v-else class="space-y-4 rounded-xl border border-line bg-surface p-5">
          <SectionHeader
            title="Start a sprint on this goal"
            subtitle="The sprint lives on the server, so it survives a refresh, a closed tab, or a different device."
          />

          <FocusModeSelector v-model:mode="focusMode" v-model:minutes="focusMinutes" />

          <label class="flex flex-col gap-1.5">
            <span class="text-[13px] font-medium text-ink-muted">Against a step (optional)</span>
            <select
              v-model="focusItemId"
              class="h-10 rounded-lg border border-line bg-surface-2 px-3 text-sm text-ink outline-none transition-colors focus:border-brand focus:bg-surface"
            >
              <option :value="null">The goal as a whole</option>
              <option v-for="option in itemOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <BaseButton
            variant="primary"
            size="md"
            icon="play"
            :loading="sprints.loading"
            @click="startSprint(null)"
          >
            Start focus sprint
          </BaseButton>
        </div>

        <div class="space-y-3">
          <SectionHeader
            eyebrow="History"
            title="Sprints on this goal"
            subtitle="Grouped by day, newest first."
          />

          <SprintHistoryList
            :sprints="sprints.history"
            :loading="sprints.historyLoading"
            :meta="sprints.historyMeta"
            @page="sprints.fetchHistory({ goal_id: goalId, page: $event })"
          />
        </div>
      </section>

      <!-- Resources ---------------------------------------------------------- -->
      <section v-else-if="tab === 'resources'" class="space-y-5 pt-2">
        <SectionHeader
          title="Attachments"
          subtitle="Files, links and notes that belong to this goal. Steps can hold their own too."
        />

        <div class="grid gap-5 lg:grid-cols-[1fr_1.2fr]">
          <div v-if="isOwner" class="rounded-xl border border-line bg-surface p-4">
            <ResourceUploader
              :uploading="resources.uploading"
              :progress="resources.progress"
              :failure="resources.failure"
              @submit="resources.create('goal', goalId, $event)"
            />
          </div>

          <ResourceList
            :items="resources.items('goal', goalId)"
            :loading="resources.loading"
            :can-delete="isOwner"
            @destroy="resources.destroy('goal', goalId, $event.id)"
          />
        </div>
      </section>

      <!-- Analytics ---------------------------------------------------------- -->
      <section v-else-if="tab === 'analytics'" class="space-y-5 pt-2">
        <ProjectionBanner
          :projected-completion-date="stats?.projected_completion_date ?? null"
          :target-end-date="goal.target_end_date"
          :loading="analytics.goalLoading && !hasStats"
        />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard
            label="Focus logged"
            :value="formatDuration(stats?.total_focus_seconds ?? 0)"
            icon="timer"
            tone="brand"
            :hint="`${stats?.sessions_count ?? 0} sprints`"
          />
          <StatCard
            label="Completion"
            :value="`${Math.round(stats?.completion_percentage ?? 0)}%`"
            icon="checkCircle"
            tone="ok"
          />
          <StatCard
            label="Current streak"
            :value="`${stats?.current_streak ?? 0}d`"
            icon="flame"
            tone="ember"
            :hint="`Longest ${stats?.longest_streak ?? 0} days`"
          />
          <StatCard
            label="Steps"
            :value="items.length"
            icon="route"
            :hint="`${items.filter((item) => item.status === 'done').length} done`"
          />
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
          <div class="space-y-3 rounded-xl border border-line bg-surface p-4">
            <SectionHeader eyebrow="Velocity" title="Focus minutes per day" />
            <VelocityChart :trend="trend" :height="200" />
          </div>

          <div class="space-y-3 rounded-xl border border-line bg-surface p-4">
            <SectionHeader eyebrow="Consistency" title="This goal, day by day" />
            <HeatmapCalendar :trend="trend" :weeks="12" />
          </div>
        </div>
      </section>

      <!-- Rewards ------------------------------------------------------------ -->
      <section v-else class="space-y-4 pt-2">
        <SectionHeader
          title="Rewards on this goal"
          subtitle="Bookkeeping only - nothing here is paid or spendable inside Pathforge."
        >
          <template #actions>
            <BaseButton
              v-if="isMentorViewer"
              variant="primary"
              size="sm"
              icon="gift"
              @click="offering = true"
            >
              Offer a reward
            </BaseButton>
            <BaseButton
              v-else-if="isOwner && mentorships.acceptedAsMentee.length > 0"
              variant="primary"
              size="sm"
              icon="gift"
              @click="requesting = true"
            >
              Ask for a reward
            </BaseButton>
          </template>
        </SectionHeader>

        <EmptyState
          v-if="goalRewards.length === 0"
          icon="gift"
          title="No rewards attached"
          :body="
            isMentorViewer
              ? 'Offer something worth finishing for. It becomes claimable once the linked work is done.'
              : 'Rewards are agreed with a mentor. Ask for one, or wait for an offer.'
          "
        />

        <div v-else class="grid gap-3 lg:grid-cols-2">
          <RewardCard
            v-for="reward in goalRewards"
            :key="reward.id"
            :reward="reward"
            :busy="rewards.saving"
            @respond="rewards.respond(reward.id, $event.accepted, $event.note)"
            @claim="rewards.claim(reward.id)"
            @fulfill="rewards.fulfill(reward.id, $event)"
            @revoke="rewards.revoke(reward.id)"
          />
        </div>
      </section>

      <!-- Modals ------------------------------------------------------------- -->
      <BaseModal v-model:open="editing" title="Edit goal" size="lg">
        <GoalForm
          :goal="goal"
          :categories="goals.categories"
          :groups="groups.groups"
          :saving="goals.saving"
          :failure="goals.failure"
          @submit="save"
          @cancel="editing = false"
        />
      </BaseModal>

      <BaseModal
        :open="assigning !== null"
        title="Set an expectation"
        size="sm"
        @update:open="(value) => !value && (assigning = null)"
      >
        <AssignRoadmapItemForm
          v-if="assigning"
          :item="assigning"
          :saving="roadmaps.saving"
          :failure="roadmaps.failure"
          @submit="saveAssignment"
          @cancel="assigning = null"
        />
      </BaseModal>

      <BaseModal v-model:open="offering" title="Offer a reward" size="lg">
        <RewardOfferForm
          :mentorships="mentorshipsForOffer"
          :goals="[goal]"
          :items="items"
          :preset-mentorship-id="mentorshipsForOffer[0]?.id ?? null"
          :preset-goal-id="goal.id"
          :saving="rewards.saving"
          :failure="rewards.failure"
          @submit="offerReward"
          @cancel="offering = false"
        />
      </BaseModal>

      <BaseModal v-model:open="requesting" title="Ask for a reward" size="lg">
        <RewardRequestForm
          :mentorships="mentorships.acceptedAsMentee"
          :goals="[goal]"
          :preset-goal-id="goal.id"
          :saving="rewards.saving"
          :failure="rewards.failure"
          @submit="requestReward"
          @cancel="requesting = false"
        />
      </BaseModal>

      <ConfirmDialog
        v-model:open="pendingDelete"
        title="Delete this goal?"
        body="Its roadmap, attachments and logged sprints go with it. This cannot be undone - archiving keeps the history instead."
        confirm-label="Delete permanently"
        :busy="goals.saving"
        @confirm="destroy"
      />
    </template>
  </div>
</template>
