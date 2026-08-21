<script setup lang="ts">
withDefaults(
  defineProps<{
    /** Repeats keep the shimmer phase aligned across a list. */
    rows?: number
    height?: string
    rounded?: string
  }>(),
  { rows: 1, height: 'h-4', rounded: 'rounded-md' },
)
</script>

<template>
  <!--
    Reserves the space the real content will take, so arriving data does not
    shove the page around (CLS is a design problem, not just a metric).
  -->
  <div class="space-y-2" role="status" aria-label="Loading">
    <div
      v-for="row in rows"
      :key="row"
      class="pf-shimmer w-full"
      :class="[height, rounded]"
      :style="{ opacity: 1 - (row - 1) * 0.12 }"
    />
  </div>
</template>
