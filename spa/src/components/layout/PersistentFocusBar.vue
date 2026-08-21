<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useFocusTimer } from '@/composables/useFocusTimer'
import { useSprintsStore } from '@/stores/sprints'
import { formatClock } from '@/utils/formatDuration'

const sprints = useSprintsStore()
const timer = useFocusTimer()

const sprint = computed(() => sprints.activeSprint)

const label = computed(() => {
  const active = sprint.value

  if (!active) {
    return ''
  }

  return active.roadmap_item?.title ?? active.goal?.title ?? 'Unassigned focus'
})

/**
 * A stopwatch counts up and has no deadline, so it shows elapsed. Everything
 * else counts down, and once it hits zero it keeps counting -- in overtime,
 * upward, in ember. It never stops on its own (FR-SPR-09).
 */
const display = computed(() => {
  if (sprints.isOpenEnded) {
    return formatClock(timer.elapsedSeconds.value)
  }

  return timer.isExpired.value
    ? `+${formatClock(timer.overtimeSeconds.value)}`
    : formatClock(timer.remainingSeconds.value)
})

const progressPct = computed(() =>
  timer.isExpired.value ? 100 : Math.round(timer.progress.value * 100),
)

async function toggle(): Promise<void> {
  if (sprints.isPaused) {
    await sprints.resume()
  } else {
    await sprints.pause()
  }
}
</script>

<template>
  <Transition
    enter-active-class="transition-transform duration-250 ease-out"
    leave-active-class="transition-transform duration-150 ease-out"
    enter-from-class="translate-y-full"
    leave-to-class="translate-y-full"
  >
    <div
      v-if="sprint"
      class="fixed inset-x-0 bottom-14 z-30 lg:bottom-0"
      role="region"
      aria-label="Active focus sprint"
    >
      <div class="border-t border-line bg-surface/95 backdrop-blur-md lg:pl-60">
        <!--
          Signature detail: a hairline that spans the entire viewport width and
          fills as the plan is consumed. It is the only always-visible progress
          indicator in the product, so it gets the whole edge rather than a chip.
        -->
        <div class="h-[2px] w-full bg-surface-3" aria-hidden="true">
          <div
            class="h-full transition-[width] duration-500 ease-out"
            :class="timer.isExpired.value ? 'bg-ember pf-pulse-ember' : 'bg-brand'"
            :style="{ width: `${progressPct}%` }"
          />
        </div>

        <div
          class="mx-auto flex w-full max-w-[84rem] items-center gap-3 px-4 py-2.5 sm:gap-4 sm:px-6 lg:px-8"
        >
          <span
            class="grid size-9 shrink-0 place-items-center rounded-lg border"
            :class="
              timer.isExpired.value
                ? 'border-ember/30 bg-ember-soft text-ember'
                : 'border-brand/30 bg-brand-soft text-brand'
            "
            aria-hidden="true"
          >
            <AppIcon :name="timer.isExpired.value ? 'hourglass' : 'timer'" :size="17" />
          </span>

          <div class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-medium leading-snug text-ink">{{ label }}</p>
            <p class="text-[11px] capitalize text-ink-faint">
              {{ sprint.mode }}
              <template v-if="sprints.isPaused"> - paused</template>
              <template v-else-if="timer.isExpired.value"> - overtime</template>
            </p>
          </div>

          <p
            class="tnum shrink-0 text-[19px] font-semibold tabular-nums sm:text-[22px]"
            :class="timer.isExpired.value ? 'text-ember' : 'text-ink'"
            :aria-label="timer.isExpired.value ? 'Time in overtime' : 'Time remaining'"
          >
            {{ display }}
          </p>

          <div class="flex shrink-0 items-center gap-1.5">
            <button
              type="button"
              class="grid size-9 place-items-center rounded-lg border border-line bg-surface-2 text-ink-muted transition-colors hover:bg-surface-3 hover:text-ink disabled:opacity-45"
              :disabled="sprints.loading"
              :aria-label="sprints.isPaused ? 'Resume sprint' : 'Pause sprint'"
              @click="toggle"
            >
              <AppIcon :name="sprints.isPaused ? 'play' : 'pause'" :size="16" />
            </button>

            <!--
              Stopping is the only thing that ends a sprint, so it is the only
              button that is always available and always styled as the primary
              action once overtime starts.
            -->
            <button
              type="button"
              class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-[13px] font-semibold transition-colors disabled:opacity-45"
              :class="
                timer.isExpired.value
                  ? 'border-transparent bg-ember text-ember-ink hover:bg-ember-hover'
                  : 'border-transparent bg-brand text-brand-ink hover:bg-brand-hover'
              "
              :disabled="sprints.loading"
              @click="sprints.complete()"
            >
              <AppIcon name="stop" :size="14" />
              <span class="hidden sm:inline">Stop</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
