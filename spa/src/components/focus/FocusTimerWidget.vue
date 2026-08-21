<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ProgressRing from '@/components/ui/ProgressRing.vue'
import OvertimeBanner from './OvertimeBanner.vue'
import { useFocusTimer } from '@/composables/useFocusTimer'
import { useSprintsStore } from '@/stores/sprints'
import { formatClock, formatDuration } from '@/utils/formatDuration'
import type { Sprint } from '@/types/sprint'

const props = defineProps<{ sprint: Sprint }>()

const sprints = useSprintsStore()

/**
 * Reads the sprint handed in rather than the store directly, so the widget can
 * render a specific sprint (a goal's Focus tab) or the global active one without
 * two code paths.
 */
const timer = useFocusTimer(() => props.sprint)

const notes = ref('')
const showNotes = ref(false)

const isOpenEnded = computed(() => props.sprint.planned_duration_seconds === null)
const isPaused = computed(() => props.sprint.status === 'paused')

const primaryDisplay = computed(() => {
  if (isOpenEnded.value) {
    return formatClock(timer.elapsedSeconds.value)
  }

  return timer.isExpired.value
    ? formatClock(timer.overtimeSeconds.value)
    : formatClock(timer.remainingSeconds.value)
})

const caption = computed(() => {
  if (isPaused.value) {
    return 'Paused'
  }

  if (isOpenEnded.value) {
    return 'Elapsed'
  }

  return timer.isExpired.value ? 'Overtime' : 'Remaining'
})

const target = computed(() =>
  props.sprint.planned_duration_seconds
    ? formatDuration(props.sprint.planned_duration_seconds)
    : 'open ended',
)

const subject = computed(
  () => props.sprint.roadmap_item?.title ?? props.sprint.goal?.title ?? 'Unassigned focus',
)

async function stop(): Promise<void> {
  await sprints.complete(notes.value.trim() || null)
  notes.value = ''
  showNotes.value = false
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-col items-center gap-6 rounded-2xl border border-line bg-surface p-6 sm:p-8">
      <div class="text-center">
        <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-ink-faint">
          {{ sprint.mode }} - {{ target }}
        </p>
        <p class="mt-1.5 max-w-md truncate font-display text-[15px] font-semibold text-ink">
          {{ subject }}
        </p>
      </div>

      <!--
        The ring is a rendering of the server row, not a source of truth. Elapsed
        time is always measured against `started_at`, so this is correct after a
        refresh, a six-hour close, or a switch to another device.
      -->
      <div class="relative grid place-items-center">
        <ProgressRing
          :value="timer.progress.value"
          :size="240"
          :thickness="5"
          :overtime="timer.isExpired.value && !isOpenEnded"
          :label="`${Math.round(timer.progress.value * 100)} percent of the plan elapsed`"
          class="hidden sm:block"
        />
        <ProgressRing
          :value="timer.progress.value"
          :size="190"
          :thickness="5"
          :overtime="timer.isExpired.value && !isOpenEnded"
          class="sm:hidden"
        />

        <div class="absolute inset-0 flex flex-col items-center justify-center gap-1">
          <p
            class="tnum text-[42px] font-semibold leading-none tracking-tight sm:text-[52px]"
            :class="
              isPaused
                ? 'text-ink-muted'
                : timer.isExpired.value && !isOpenEnded
                  ? 'text-ember'
                  : 'text-ink'
            "
            :aria-live="'off'"
          >
            <span v-if="timer.isExpired.value && !isOpenEnded" aria-hidden="true">+</span
            >{{ primaryDisplay }}
          </p>
          <p class="text-[11px] font-medium uppercase tracking-[0.14em] text-ink-faint">
            {{ caption }}
          </p>
          <p v-if="!isOpenEnded" class="tnum mt-1 text-[11.5px] text-ink-faint">
            {{ formatDuration(timer.elapsedSeconds.value) }} focused
          </p>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-center gap-2">
        <BaseButton
          variant="subtle"
          size="md"
          :icon="isPaused ? 'play' : 'pause'"
          :loading="sprints.loading"
          @click="isPaused ? sprints.resume() : sprints.pause()"
        >
          {{ isPaused ? 'Resume' : 'Pause' }}
        </BaseButton>

        <BaseButton
          :variant="timer.isExpired.value && !isOpenEnded ? 'ember' : 'primary'"
          size="md"
          icon="stop"
          :loading="sprints.loading"
          @click="showNotes ? stop() : (showNotes = true)"
        >
          Stop
        </BaseButton>

        <BaseButton
          variant="ghost"
          size="md"
          icon="xCircle"
          :loading="sprints.loading"
          @click="sprints.cancel()"
        >
          Discard
        </BaseButton>
      </div>

      <div v-if="showNotes" class="w-full max-w-md space-y-2">
        <label for="sprint-notes" class="text-[12.5px] font-medium text-ink-muted">
          Anything worth remembering? (optional)
        </label>
        <textarea
          id="sprint-notes"
          v-model="notes"
          rows="2"
          maxlength="2000"
          placeholder="Got the layout working, stuck on the API shape."
          class="w-full resize-y rounded-lg border border-line bg-surface-2 px-3 py-2 text-[13px] text-ink outline-none transition-colors focus:border-brand focus:bg-surface"
        />
        <div class="flex justify-end gap-2">
          <BaseButton variant="ghost" size="sm" @click="showNotes = false">Keep going</BaseButton>
          <BaseButton variant="primary" size="sm" :loading="sprints.loading" @click="stop">
            Stop and save
          </BaseButton>
        </div>
      </div>
    </div>

    <OvertimeBanner
      v-if="timer.isExpired.value && !isOpenEnded"
      :overtime-seconds="timer.overtimeSeconds.value"
      :busy="sprints.loading"
      @stop="stop"
    />

    <p
      v-if="sprint.paused_seconds_total > 0"
      class="flex items-center justify-center gap-1.5 text-[11.5px] text-ink-faint"
    >
      <AppIcon name="pause" :size="12" />
      <span class="tnum">{{ formatDuration(sprint.paused_seconds_total) }}</span>
      paused so far - excluded from the plan
    </p>
  </div>
</template>
