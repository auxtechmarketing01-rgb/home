<script setup lang="ts">
import { computed, ref } from 'vue'
import { onClickOutside } from '@vueuse/core'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import GoalStatusBadge from './GoalStatusBadge.vue'
import GoalVisibilityToggle from './GoalVisibilityToggle.vue'
import { formatShortDate } from '@/utils/date'
import type { Goal } from '@/types/goal'
import type { Group } from '@/types/group'

const props = defineProps<{
  goal: Goal
  groups: Group[]
  /** False when the viewer reached this goal through a mentorship, not ownership. */
  canEdit: boolean
  saving?: boolean
}>()

const emit = defineEmits<{
  edit: []
  complete: []
  archive: []
  destroy: []
  visibility: [{ visibility: 'private' | 'group'; group_id: number | null }]
}>()

const menuOpen = ref(false)
const menu = ref<HTMLElement | null>(null)

onClickOutside(menu, () => {
  menuOpen.value = false
})

const window = computed(() => {
  const { target_start_date: start, target_end_date: end } = props.goal

  if (!start && !end) {
    return null
  }

  if (start && end) {
    return `${formatShortDate(start)} - ${formatShortDate(end)}`
  }

  return start ? `Started ${formatShortDate(start)}` : `Due ${formatShortDate(end)}`
})

const isFinished = computed(
  () => props.goal.status === 'completed' || props.goal.status === 'abandoned',
)
</script>

<template>
  <header class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <div class="mb-1.5 flex flex-wrap items-center gap-2 text-[11.5px] text-ink-faint">
          <RouterLink to="/goals" class="transition-colors hover:text-ink-muted">Goals</RouterLink>
          <AppIcon name="chevronRight" :size="11" />
          <span v-if="goal.category" class="uppercase tracking-[0.1em]">{{ goal.category.name }}</span>
          <span v-else class="uppercase tracking-[0.1em]">Uncategorised</span>
          <template v-if="!canEdit && goal.user">
            <AppIcon name="chevronRight" :size="11" />
            <span class="inline-flex items-center gap-1 text-brand">
              <AppIcon name="handshake" :size="11" />
              Mentoring {{ goal.user.name }}
            </span>
          </template>
        </div>

        <h1 class="font-display text-[22px] font-semibold leading-tight text-ink sm:text-[26px]">
          {{ goal.title }}
        </h1>

        <p v-if="goal.description" class="mt-2 max-w-3xl text-[13.5px] leading-relaxed text-ink-muted">
          {{ goal.description }}
        </p>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <GoalStatusBadge :status="goal.status" size="md" />

        <GoalVisibilityToggle
          v-if="canEdit"
          :goal="goal"
          :groups="groups"
          :saving="saving"
          @change="emit('visibility', $event)"
        />

        <BaseButton
          v-if="canEdit"
          variant="subtle"
          size="sm"
          icon="route"
          :to="{ name: 'roadmap-builder', params: { id: goal.id } }"
        >
          Roadmap
        </BaseButton>

        <div v-if="canEdit" ref="menu" class="relative">
          <BaseButton
            variant="ghost"
            size="icon"
            icon="sliders"
            label="Goal actions"
            @click="menuOpen = !menuOpen"
          />

          <div
            v-if="menuOpen"
            class="pf-rise absolute right-0 top-11 z-30 w-52 overflow-hidden rounded-xl border border-line bg-surface py-1"
            role="menu"
          >
            <button
              type="button"
              role="menuitem"
              class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink transition-colors hover:bg-surface-2"
              @click="
                menuOpen = false;
                emit('edit')
              "
            >
              <AppIcon name="pencil" :size="15" class="text-ink-faint" />
              Edit details
            </button>

            <button
              v-if="!isFinished"
              type="button"
              role="menuitem"
              class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink transition-colors hover:bg-surface-2"
              @click="
                menuOpen = false;
                emit('complete')
              "
            >
              <AppIcon name="checkCircle" :size="15" class="text-brand" />
              Mark complete
            </button>

            <button
              v-if="goal.status !== 'abandoned'"
              type="button"
              role="menuitem"
              class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-ink transition-colors hover:bg-surface-2"
              @click="
                menuOpen = false;
                emit('archive')
              "
            >
              <AppIcon name="layers" :size="15" class="text-ink-faint" />
              Archive (abandon)
            </button>

            <div class="my-1 h-px bg-line" role="separator" />

            <button
              type="button"
              role="menuitem"
              class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-[13px] text-danger transition-colors hover:bg-danger-soft"
              @click="
                menuOpen = false;
                emit('destroy')
              "
            >
              <AppIcon name="trash" :size="15" />
              Delete permanently
            </button>
          </div>
        </div>
      </div>
    </div>

    <dl v-if="window || goal.completed_at" class="flex flex-wrap gap-x-5 gap-y-2 text-[12px]">
      <div v-if="window" class="flex items-center gap-1.5">
        <AppIcon name="calendar" :size="13" class="text-ink-faint" />
        <dt class="sr-only">Target window</dt>
        <dd class="text-ink-muted">{{ window }}</dd>
      </div>
      <div v-if="goal.completed_at" class="flex items-center gap-1.5">
        <AppIcon name="checkCircle" :size="13" class="text-ok" />
        <dt class="sr-only">Completed</dt>
        <dd class="text-ok">Completed {{ formatShortDate(goal.completed_at) }}</dd>
      </div>
    </dl>
  </header>
</template>
