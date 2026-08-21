<script setup lang="ts">
import { ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { setTheme, storedPreference, type ThemePreference } from '@/utils/theme'
import type { IconName } from '@/components/ui/icons'

const preference = ref<ThemePreference>(storedPreference())

const OPTIONS: Array<{ value: ThemePreference; icon: IconName; label: string }> = [
  { value: 'light', icon: 'sun', label: 'Light' },
  { value: 'dark', icon: 'moon', label: 'Dark' },
  { value: 'system', icon: 'monitor', label: 'Match system' },
]

function choose(next: ThemePreference): void {
  preference.value = next
  setTheme(next)
}
</script>

<template>
  <!--
    A three-state segmented control rather than a two-state flip: "match system"
    is a real preference, and collapsing it into a toggle silently overrides
    whatever the OS was told.
  -->
  <div
    class="flex items-center gap-0.5 rounded-lg border border-line bg-surface-2 p-0.5"
    role="radiogroup"
    aria-label="Colour theme"
  >
    <button
      v-for="option in OPTIONS"
      :key="option.value"
      type="button"
      role="radio"
      :aria-checked="preference === option.value"
      :aria-label="option.label"
      :title="option.label"
      :class="[
        'grid size-7 place-items-center rounded-md transition-colors duration-150',
        preference === option.value
          ? 'bg-surface text-ink'
          : 'text-ink-faint hover:text-ink-muted',
      ]"
      @click="choose(option.value)"
    >
      <AppIcon :name="option.icon" :size="15" />
    </button>
  </div>
</template>
