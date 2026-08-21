<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import GoalCard from '@/components/goals/GoalCard.vue'
import GoalForm from '@/components/goals/GoalForm.vue'
import { useGoalsStore } from '@/stores/goals'
import { useGroupsStore } from '@/stores/groups'
import { useToastsStore } from '@/stores/toasts'
import { GOAL_STATUSES, type GoalPayload, type GoalStatus } from '@/types/goal'

const goals = useGoalsStore()
const groups = useGroupsStore()
const toasts = useToastsStore()

const creating = ref(false)
const search = ref('')
const status = ref<GoalStatus | null>(null)
const categoryId = ref<number | null>(null)

onMounted(() => {
  void goals.fetchAll({ per_page: 24 })
  void goals.fetchCategories()
})

let searchHandle: number | undefined

/** Debounced so typing a title does not fire a request per keystroke. */
watch(search, () => {
  if (searchHandle !== undefined) {
    window.clearTimeout(searchHandle)
  }

  searchHandle = window.setTimeout(() => {
    void goals.fetchAll({ search: search.value.trim() || undefined, page: 1 })
  }, 350)
})

watch([status, categoryId], () => {
  void goals.fetchAll({
    status: status.value ?? undefined,
    category_id: categoryId.value ?? undefined,
    page: 1,
  })
})

const statusOptions = GOAL_STATUSES.map((value) => ({
  value,
  label: value.charAt(0).toUpperCase() + value.slice(1),
}))

const categoryOptions = computed(() =>
  goals.categories.map((category) => ({ value: category.id, label: category.name })),
)

const hasFilters = computed(
  () => search.value.trim().length > 0 || status.value !== null || categoryId.value !== null,
)

function clearFilters(): void {
  search.value = ''
  status.value = null
  categoryId.value = null
}

async function create(payload: GoalPayload): Promise<void> {
  const goal = await goals.create(payload)

  if (goal) {
    creating.value = false
    toasts.success('Goal created', 'Next: break it into a roadmap.')
  }
}

const meta = computed(() => goals.meta)
</script>

<template>
  <div class="space-y-6">
    <SectionHeader
      eyebrow="Your work"
      title="Goals"
      subtitle="Each goal holds one roadmap, its own focus time and its own stats."
    >
      <template #actions>
        <BaseButton variant="primary" size="sm" icon="plus" @click="creating = true">
          New goal
        </BaseButton>
      </template>
    </SectionHeader>

    <ErrorBanner :failure="goals.failure" dismissible @dismiss="goals.clearFailure()" />

    <form
      class="grid gap-3 rounded-xl border border-line bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4"
      @submit.prevent
    >
      <BaseInput v-model="search" label="Search" icon="search" placeholder="Title or description" />
      <BaseSelect v-model="status" label="Status" placeholder="Any status" :options="statusOptions" />
      <BaseSelect
        v-model="categoryId"
        label="Category"
        placeholder="Any category"
        :options="categoryOptions"
      />
      <div class="flex items-end">
        <BaseButton v-if="hasFilters" variant="ghost" size="sm" icon="x" @click="clearFilters">
          Clear filters
        </BaseButton>
      </div>
    </form>

    <SkeletonBlock
      v-if="goals.loading && goals.list.length === 0"
      :rows="3"
      height="h-44"
      rounded="rounded-xl"
    />

    <EmptyState
      v-else-if="goals.list.length === 0 && hasFilters"
      icon="search"
      title="Nothing matches those filters"
      body="Try a broader search or clear the filters."
    >
      <BaseButton variant="subtle" size="sm" @click="clearFilters">Clear filters</BaseButton>
    </EmptyState>

    <EmptyState
      v-else-if="goals.list.length === 0"
      icon="target"
      title="No goals yet"
      body="A goal is the top of the tree: it gets a roadmap of ordered steps, and every focus sprint you run rolls up into it."
    >
      <BaseButton variant="primary" size="sm" icon="plus" @click="creating = true">
        Create your first goal
      </BaseButton>
    </EmptyState>

    <template v-else>
      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <GoalCard v-for="goal in goals.list" :key="goal.id" :goal="goal" show-owner />
      </div>

      <nav
        v-if="meta && meta.last_page > 1"
        class="flex items-center justify-between gap-3 border-t border-line pt-4"
        aria-label="Goal pages"
      >
        <p class="tnum text-[11.5px] text-ink-faint">
          {{ meta.from ?? 0 }}-{{ meta.to ?? 0 }} of {{ meta.total }}
        </p>

        <div class="flex gap-1.5">
          <BaseButton
            variant="subtle"
            size="sm"
            icon="chevronLeft"
            :disabled="meta.current_page <= 1"
            @click="goals.fetchAll({ page: meta.current_page - 1 })"
          >
            Previous
          </BaseButton>
          <BaseButton
            variant="subtle"
            size="sm"
            trailing-icon="chevronRight"
            :disabled="meta.current_page >= meta.last_page"
            @click="goals.fetchAll({ page: meta.current_page + 1 })"
          >
            Next
          </BaseButton>
        </div>
      </nav>
    </template>

    <BaseModal
      v-model:open="creating"
      title="New goal"
      description="You can change any of this later."
      size="lg"
    >
      <GoalForm
        :categories="goals.categories"
        :groups="groups.groups"
        :saving="goals.saving"
        :failure="goals.failure"
        @submit="create"
        @cancel="creating = false"
      />
    </BaseModal>

    <p
      v-if="goals.list.length > 0"
      class="flex items-center gap-1.5 text-[11px] text-ink-faint"
    >
      <AppIcon name="lock" :size="11" />
      Private goals are visible to you alone. Shared goals are visible to their group.
    </p>
  </div>
</template>
