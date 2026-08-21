<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import FocusModeSelector from '@/components/focus/FocusModeSelector.vue'
import FocusTimerWidget from '@/components/focus/FocusTimerWidget.vue'
import SprintHistoryFilters from '@/components/focus/SprintHistoryFilters.vue'
import SprintHistoryList from '@/components/focus/SprintHistoryList.vue'
import { useGoalsStore } from '@/stores/goals'
import { useRoadmapsStore } from '@/stores/roadmaps'
import { useSprintsStore } from '@/stores/sprints'
import { useToastsStore } from '@/stores/toasts'
import type { SprintFilters, SprintMode } from '@/types/sprint'

const sprints = useSprintsStore()
const goals = useGoalsStore()
const roadmaps = useRoadmapsStore()
const toasts = useToastsStore()

const mode = ref<SprintMode>('pomodoro')
const minutes = ref(25)
const goalId = ref<number | null>(null)
const itemId = ref<number | null>(null)

onMounted(() => {
  void sprints.fetchActive()
  void sprints.fetchHistory({ per_page: 20, page: 1 })
  void goals.fetchAll({ per_page: 100 })
})

const goalOptions = computed(() =>
  goals.selectableGoals.map((goal) => ({ value: goal.id, label: goal.title })),
)

/** Steps only appear once a goal is chosen, and only for that goal's roadmap. */
const itemOptions = computed(() => {
  if (goalId.value === null) {
    return []
  }

  const roadmapId = goals.get(goalId.value)?.roadmap?.id ?? null

  return roadmaps
    .items(roadmapId)
    .map((item) => ({ value: item.id, label: item.title }))
})

async function loadItems(): Promise<void> {
  itemId.value = null

  if (goalId.value === null) {
    return
  }

  const goal = goals.get(goalId.value) ?? (await goals.fetchOne(goalId.value))

  if (goal?.roadmap?.id) {
    await roadmaps.fetchItems(goal.roadmap.id)
  }
}

async function start(): Promise<void> {
  const payload = sprints.buildStartPayload(mode.value, {
    goalId: goalId.value,
    roadmapItemId: itemId.value,
    minutes: minutes.value,
  })

  if (await sprints.start(payload)) {
    toasts.success(
      'Sprint running',
      'It runs on the server - close the tab and it keeps going.',
    )
  }
}

function applyFilters(next: SprintFilters): void {
  void sprints.fetchHistory(next)
}

function resetFilters(): void {
  sprints.filters = { per_page: 20, page: 1 }
  void sprints.fetchHistory()
}
</script>

<template>
  <div class="space-y-8">
    <SectionHeader
      eyebrow="Focus"
      title="One sprint at a time"
      subtitle="The running sprint lives on the server. Refresh, switch device, or close the browser entirely - it is still going when you come back."
    />

    <ErrorBanner :failure="sprints.failure" dismissible @dismiss="sprints.clearFailure()" />

    <FocusTimerWidget v-if="sprints.activeSprint" :sprint="sprints.activeSprint" />

    <section v-else class="space-y-5 rounded-2xl border border-line bg-surface p-5 sm:p-6">
      <FocusModeSelector v-model:mode="mode" v-model:minutes="minutes" />

      <div class="grid gap-4 sm:grid-cols-2">
        <BaseSelect
          v-model="goalId"
          label="Against a goal (optional)"
          placeholder="Unassigned focus"
          :options="goalOptions"
          hint="Only time logged against a goal rolls up into its stats."
          @update:model-value="loadItems"
        />

        <BaseSelect
          v-model="itemId"
          label="Against a step (optional)"
          :placeholder="goalId === null ? 'Pick a goal first' : 'The goal as a whole'"
          :options="itemOptions"
          :disabled="goalId === null"
        />
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <BaseButton
          variant="primary"
          size="lg"
          icon="play"
          :loading="sprints.loading"
          @click="start"
        >
          Start sprint
        </BaseButton>

        <p class="flex items-center gap-1.5 text-[11.5px] text-ink-faint">
          <AppIcon name="info" :size="12" />
          Reaching the planned time does not stop the sprint - it goes into overtime until you stop
          it.
        </p>
      </div>
    </section>

    <section class="space-y-4">
      <SectionHeader
        eyebrow="History"
        title="Everything you have logged"
        subtitle="Filter it, then export exactly what you filtered."
      />

      <SprintHistoryFilters
        :filters="sprints.filters"
        :goals="goals.list"
        :export-url="sprints.exportUrl()"
        @apply="applyFilters"
        @reset="resetFilters"
      />

      <SprintHistoryList
        :sprints="sprints.history"
        :loading="sprints.historyLoading"
        :meta="sprints.historyMeta"
        @page="sprints.fetchHistory({ page: $event })"
      />
    </section>
  </div>
</template>
