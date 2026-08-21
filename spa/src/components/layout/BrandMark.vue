<script setup lang="ts">
withDefaults(defineProps<{ size?: number; withWordmark?: boolean }>(), { size: 28 })
</script>

<template>
  <span class="inline-flex items-center gap-2.5">
    <!--
      The mark is the product's one piece of illustration: a path stepping up
      through three nodes, with the final node filled. Same rail-and-node
      language as the roadmap timeline, so the logo is a small copy of the thing
      the app actually does.
    -->
    <svg
      :width="size"
      :height="size"
      viewBox="0 0 32 32"
      fill="none"
      role="img"
      aria-label="Pathforge"
      class="shrink-0"
    >
      <rect
        x="1"
        y="1"
        width="30"
        height="30"
        rx="9"
        fill="var(--pf-brand-soft)"
        stroke="var(--pf-brand)"
        stroke-opacity="0.35"
      />
      <path
        d="M8 23.5 13.5 18 18.5 21 24 9"
        stroke="var(--pf-brand)"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <circle cx="8" cy="23.5" r="1.9" fill="var(--pf-canvas)" stroke="var(--pf-brand)" stroke-width="1.6" />
      <circle cx="18.5" cy="21" r="1.9" fill="var(--pf-canvas)" stroke="var(--pf-brand)" stroke-width="1.6" />
      <circle cx="24" cy="9" r="2.6" fill="var(--pf-ember)" />
    </svg>

    <span
      v-if="withWordmark"
      class="font-display text-[15px] font-semibold tracking-[-0.02em] text-ink"
    >
      Pathforge
    </span>
  </span>
</template>
