<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import AssignRoadmapItemForm from '@/components/roadmap/AssignRoadmapItemForm.vue'
import ResourceList from '@/components/roadmap/ResourceList.vue'
import ResourceUploader from '@/components/roadmap/ResourceUploader.vue'
import RoadmapItemForm from '@/components/roadmap/RoadmapItemForm.vue'
import RoadmapKanbanView from '@/components/roadmap/RoadmapKanbanView.vue'
import RoadmapTimelineView from '@/components/roadmap/RoadmapTimelineView.vue'
import { useRoadmapBuilder } from '@/composables/useRoadmapBuilder'
import { useAuthStore } from '@/stores/auth'
import { useGoalsStore } from '@/stores/goals'
import { useMentorshipsStore } from '@/stores/mentorships'
import { useResourcesStore } from '@/stores/resources'
import { useRoadmapsStore } from '@/stores/roadmaps'
import { useSprintsStore } from '@/stores/sprints'
import { useToastsStore } from '@/stores/toasts'
import { formatDuration, formatMinutes } from '@/utils/formatDuration'
import type { RoadmapItem, RoadmapItemUpdatePayload } from '@/types/roadmap'

const route = useRoute()
const router = useRouter()

const auth = useAuthStore()
const goals = useGoalsStore()
const roadmaps = useRoadmapsStore()
const resources = useResourcesStore()
const sprints = useSprintsStore()
const mentorships = useMentorshipsStore()
const toasts = useToastsStore()

const goalId = computed(() => Number(route.params.id))
const goal = computed(() => goals.get(goalId.value) ?? null)
const roadmapId = computed(() => goal.value?.roadmap?.id ?? null)

const builder = useRoadmapBuilder(() => roadmapId.value)

const editing = ref<RoadmapItem | null>(null)
const creating = ref(false)
const assigning = ref<RoadmapItem | null>(null)
const attaching = ref<RoadmapItem | null>(null)
const pendingDelete = ref<RoadmapItem | null>(null)

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

async function load(): Promise<void> {
  const loaded = goal.value ?? (await goals.fetchOne(goalId.value))

  if (loaded?.roadmap?.id) {
    /** Authoritative ordered fetch, rather than trusting whatever the list cached. */
    await roadmaps.fetchItems(loaded.roadmap.id)
  }
}

onMounted(load)
watch(goalId, load)

watch(attaching, (item) => {
  if (item) {
    void resources.fetchFor('item', item.id)
  }
})

async function createItem(payload: RoadmapItemUpdatePayload): Promise<void> {
  if (!roadmapId.value || !payload.title) {
    return
  }

  const created = await roadmaps.createItem(roadmapId.value, {
    ...payload,
    title: payload.title,
    /** Appended, so a new step never lands in the middle of an ordered plan. */
    position: builder.draft.value.length,
  })

  if (created) {
    creating.value = false
    toasts.success('Step added')
  }
}

async function saveItem(payload: RoadmapItemUpdatePayload): Promise<void> {
  if (!editing.value) {
    return
  }

  if (await roadmaps.updateItem(editing.value.id, payload)) {
    editing.value = null
    toasts.success('Step saved')
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

async function confirmDelete(): Promise<void> {
  if (!pendingDelete.value) {
    return
  }

  if (await roadmaps.destroyItem(pendingDelete.value)) {
    pendingDelete.value = null
    toasts.info('Step removed')
  }
}

async function focusOn(item: RoadmapItem): Promise<void> {
  const payload = sprints.buildStartPayload('pomodoro', {
    goalId: goalId.value,
    roadmapItemId: item.id,
  })

  if (await sprints.start(payload)) {
    toasts.success('Sprint running', `25 minutes on "${item.title}".`)
  }
}
</script>

<template>
  <div class="space-y-6">
    <SkeletonBlock v-if="!goal" :rows="4" height="h-20" rounded="rounded-xl" />

    <template v-else>
      <header class="space-y-2">
        <div class="flex flex-wrap items-center gap-2 text-[11.5px] text-ink-faint">
          <RouterLink to="/goals" class="transition-colors hover:text-ink-muted">Goals</RouterLink>
          <AppIcon name="chevronRight" :size="11" />
          <RouterLink
            :to="{ name: 'goal-detail', params: { id: goal.id } }"
            class="max-w-[16rem] truncate transition-colors hover:text-ink-muted"
          >
            {{ goal.title }}
          </RouterLink>
          <AppIcon name="chevronRight" :size="11" />
          <span>Roadmap</span>
        </div>

        <SectionHeader
          title="Roadmap builder"
          :subtitle="
            isOwner
              ? 'Reorder by dragging the handle, or with the arrow buttons on each step. Order saves automatically.'
              : 'Read only. As their mentor you can set a time budget and a due date on any step.'
          "
        >
          <template #actions>
            <div
              class="inline-flex rounded-lg border border-line bg-surface-2 p-0.5"
              role="group"
              aria-label="Roadmap view"
            >
              <button
                v-for="view in [
                  { value: 'timeline', label: 'Timeline', icon: 'list' as const },
                  { value: 'kanban', label: 'Board', icon: 'columns' as const },
                ]"
                :key="view.value"
                type="button"
                :aria-pressed="builder.renderMode.value === view.value"
                :class="[
                  'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-[12.5px] transition-colors duration-150',
                  builder.renderMode.value === view.value
                    ? 'bg-surface font-semibold text-ink'
                    : 'font-medium text-ink-muted hover:text-ink',
                ]"
                @click="builder.renderMode.value = view.value as 'timeline' | 'kanban'"
              >
                <AppIcon :name="view.icon" :size="14" />
                {{ view.label }}
              </button>
            </div>

            <BaseButton v-if="isOwner" variant="primary" size="sm" icon="plus" @click="creating = true">
              Add step
            </BaseButton>
          </template>
        </SectionHeader>
      </header>

      <ErrorBanner :failure="roadmaps.failure" dismissible @dismiss="roadmaps.clearFailure()" />

      <!-- A rollup of the plan, so the numbers are visible without opening analytics. -->
      <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-line bg-surface px-4 py-3">
          <dt class="text-[10.5px] font-medium uppercase tracking-[0.12em] text-ink-faint">Steps</dt>
          <dd class="tnum mt-1 text-[19px] font-semibold text-ink">{{ builder.totals.value.count }}</dd>
        </div>
        <div class="rounded-xl border border-line bg-surface px-4 py-3">
          <dt class="text-[10.5px] font-medium uppercase tracking-[0.12em] text-ink-faint">Done</dt>
          <dd class="tnum mt-1 text-[19px] font-semibold text-brand">
            {{ builder.totals.value.done }}
          </dd>
        </div>
        <div class="rounded-xl border border-line bg-surface px-4 py-3">
          <dt class="text-[10.5px] font-medium uppercase tracking-[0.12em] text-ink-faint">
            Estimated
          </dt>
          <dd class="tnum mt-1 text-[19px] font-semibold text-ink">
            {{ formatMinutes(builder.totals.value.estimatedMinutes) }}
          </dd>
        </div>
        <div class="rounded-xl border border-line bg-surface px-4 py-3">
          <dt class="text-[10.5px] font-medium uppercase tracking-[0.12em] text-ink-faint">Logged</dt>
          <dd class="tnum mt-1 text-[19px] font-semibold text-ink">
            {{ formatDuration(builder.totals.value.spentSeconds) }}
          </dd>
        </div>
      </dl>

      <SkeletonBlock
        v-if="roadmaps.loading && builder.draft.value.length === 0"
        :rows="4"
        height="h-20"
        rounded="rounded-xl"
      />

      <EmptyState
        v-else-if="!roadmapId"
        icon="route"
        title="This goal has no roadmap"
        body="A roadmap is created with the goal. If this persists, reload the goal."
      >
        <BaseButton variant="subtle" size="sm" @click="load">Reload</BaseButton>
      </EmptyState>

      <RoadmapTimelineView
        v-else-if="builder.renderMode.value === 'timeline'"
        v-model:items="builder.draft.value"
        :can-edit="isOwner"
        :can-assign="isMentorViewer"
        :reordering="builder.committing.value"
        @drop="builder.onDrop"
        @status="(item, status) => roadmaps.setStatus(item, status)"
        @edit="editing = $event"
        @destroy="pendingDelete = $event"
        @focus="focusOn"
        @assign="assigning = $event"
        @move-up="builder.moveUp($event.id)"
        @move-down="builder.moveDown($event.id)"
        @create="creating = true"
      />

      <RoadmapKanbanView
        v-else
        :columns="builder.columns.value"
        :can-edit="isOwner"
        :can-assign="isMentorViewer"
        @status="(item, status) => roadmaps.setStatus(item, status)"
        @edit="editing = $event"
        @destroy="pendingDelete = $event"
        @focus="focusOn"
        @assign="assigning = $event"
      />

      <p
        v-if="isOwner && builder.draft.value.length > 0"
        class="flex items-center gap-1.5 text-[11px] text-ink-faint"
      >
        <AppIcon name="info" :size="11" />
        Reordering saves on its own. Use the up and down arrows on a step if you would rather not
        drag.
      </p>

      <!-- Modals ------------------------------------------------------------- -->
      <BaseModal v-model:open="creating" title="Add a step" size="lg">
        <RoadmapItemForm
          :saving="roadmaps.saving"
          :failure="roadmaps.failure"
          @submit="createItem"
          @cancel="creating = false"
        />
      </BaseModal>

      <BaseModal
        :open="editing !== null"
        title="Edit step"
        size="lg"
        @update:open="(value) => !value && (editing = null)"
      >
        <div v-if="editing" class="space-y-5">
          <RoadmapItemForm
            :item="editing"
            :saving="roadmaps.saving"
            :failure="roadmaps.failure"
            @submit="saveItem"
            @cancel="editing = null"
          />

          <div class="border-t border-line pt-4">
            <BaseButton
              variant="ghost"
              size="sm"
              icon="file"
              @click="
                attaching = editing;
                editing = null
              "
            >
              Attachments on this step
            </BaseButton>
          </div>
        </div>
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

      <BaseModal
        :open="attaching !== null"
        :title="attaching ? `Attachments: ${attaching.title}` : 'Attachments'"
        size="lg"
        @update:open="(value) => !value && (attaching = null)"
      >
        <div v-if="attaching" class="space-y-5">
          <ResourceUploader
            v-if="isOwner"
            :uploading="resources.uploading"
            :progress="resources.progress"
            :failure="resources.failure"
            @submit="resources.create('item', attaching.id, $event)"
          />

          <ResourceList
            :items="resources.items('item', attaching.id)"
            :loading="resources.loading"
            :can-delete="isOwner"
            @destroy="resources.destroy('item', attaching.id, $event.id)"
          />
        </div>
      </BaseModal>

      <ConfirmDialog
        :open="pendingDelete !== null"
        title="Remove this step?"
        :body="`“${pendingDelete?.title ?? 'This step'}” and its attachments go with it. Logged focus time stays on the goal.`"
        confirm-label="Remove step"
        :busy="roadmaps.saving"
        @update:open="(value) => !value && (pendingDelete = null)"
        @confirm="confirmDelete"
      />
    </template>

    <BaseButton
      variant="ghost"
      size="sm"
      icon="chevronLeft"
      @click="router.push({ name: 'goal-detail', params: { id: goalId } })"
    >
      Back to goal
    </BaseButton>
  </div>
</template>
