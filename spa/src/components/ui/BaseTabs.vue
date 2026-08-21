<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'
import type { TabItem } from './types'

const props = defineProps<{
  tabs: TabItem[]
  ariaLabel?: string
}>()

const model = defineModel<string>({ required: true })

const visible = computed(() => props.tabs.filter((tab) => !tab.hidden))

/** Arrow keys move between tabs, which is what makes a tablist a tablist. */
function onKeydown(event: KeyboardEvent): void {
  const keys = ['ArrowRight', 'ArrowLeft', 'Home', 'End']

  if (!keys.includes(event.key)) {
    return
  }

  event.preventDefault()

  const index = visible.value.findIndex((tab) => tab.value === model.value)
  const last = visible.value.length - 1

  const next =
    event.key === 'ArrowRight'
      ? Math.min(last, index + 1)
      : event.key === 'ArrowLeft'
        ? Math.max(0, index - 1)
        : event.key === 'Home'
          ? 0
          : last

  const target = visible.value[next]

  if (target) {
    model.value = target.value
  }
}
</script>

<template>
  <!--
    A rail rather than a pill row: the active tab is marked by a 2px underline
    sitting on the same hairline that separates the tabs from their panel, so the
    selection reads as part of the structure instead of a floating chip.
  -->
  <div
    role="tablist"
    :aria-label="ariaLabel"
    class="-mb-px flex gap-1 overflow-x-auto border-b border-line"
    @keydown="onKeydown"
  >
    <button
      v-for="tab in visible"
      :key="tab.value"
      type="button"
      role="tab"
      :aria-selected="model === tab.value"
      :tabindex="model === tab.value ? 0 : -1"
      :class="[
        'relative inline-flex shrink-0 items-center gap-2 px-3 pb-2.5 pt-2 text-sm transition-colors duration-150',
        model === tab.value
          ? 'font-semibold text-ink'
          : 'font-medium text-ink-muted hover:text-ink',
      ]"
      @click="model = tab.value"
    >
      <AppIcon v-if="tab.icon" :name="tab.icon" :size="16" />
      <span>{{ tab.label }}</span>
      <span
        v-if="tab.count !== null && tab.count !== undefined"
        class="tnum rounded-full bg-surface-2 px-1.5 py-0.5 text-[10.5px] text-ink-muted"
      >
        {{ tab.count }}
      </span>
      <span
        v-if="model === tab.value"
        class="absolute inset-x-1 -bottom-px h-0.5 rounded-full bg-brand"
        aria-hidden="true"
      />
    </button>
  </div>
</template>
