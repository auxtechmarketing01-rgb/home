<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
  /** Small mono label above the title -- the product's section eyebrow. */
  eyebrow?: string
}>()
</script>

<template>
  <div class="flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
      <p
        v-if="eyebrow"
        class="tnum mb-1 text-[10.5px] font-medium uppercase tracking-[0.14em] text-ink-faint"
      >
        {{ eyebrow }}
      </p>
      <h2 class="font-display text-[17px] font-semibold text-ink">{{ title }}</h2>
      <p v-if="subtitle" class="mt-1 max-w-2xl text-[13px] leading-relaxed text-ink-muted">
        {{ subtitle }}
      </p>
    </div>
    <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>
