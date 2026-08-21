<script setup lang="ts">
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
</script>

<template>
  <EmptyState
    icon="search"
    title="There is nothing at this address"
    body="The link may be stale, or the thing it pointed at may have been deleted."
  >
    <div class="flex flex-wrap justify-center gap-2">
      <BaseButton variant="primary" size="sm" to="/">Back to today</BaseButton>
      <BaseButton variant="subtle" size="sm" to="/goals">Your goals</BaseButton>
    </div>
  </EmptyState>
</template>
