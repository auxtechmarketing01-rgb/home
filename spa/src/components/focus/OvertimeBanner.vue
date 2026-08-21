<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { formatClock } from '@/utils/formatDuration'

defineProps<{ overtimeSeconds: number; busy?: boolean }>()

const emit = defineEmits<{ stop: [] }>()
</script>

<template>
  <!--
    FR-SPR-09. Overtime is a first-class state, not an error: nothing here is
    disabled, hidden or auto-stopped. The sprint is still running server-side and
    the only thing that ends it is the member pressing Stop.

    `aria-live="polite"` so crossing the deadline is announced once, without
    interrupting.
  -->
  <div
    class="flex flex-wrap items-center gap-3 rounded-xl border border-ember/30 bg-ember-soft px-4 py-3"
    role="status"
    aria-live="polite"
  >
    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-ember/15 text-ember" aria-hidden="true">
      <AppIcon name="hourglass" :size="17" class="pf-pulse-ember" />
    </span>

    <div class="min-w-0 flex-1">
      <p class="text-[13.5px] font-semibold text-ink">
        Past your plan by
        <span class="tnum text-ember">{{ formatClock(overtimeSeconds) }}</span>
      </p>
      <p class="mt-0.5 text-[12px] leading-relaxed text-ink-muted">
        Still running - reaching the plan does not end a sprint. Keep going, or stop and bank the
        time.
      </p>
    </div>

    <BaseButton variant="ember" size="sm" icon="stop" :loading="busy" @click="emit('stop')">
      Stop and bank it
    </BaseButton>
  </div>
</template>
