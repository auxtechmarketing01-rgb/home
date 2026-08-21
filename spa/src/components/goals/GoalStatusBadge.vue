<script setup lang="ts">
import { computed } from 'vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import { GOAL_STATUS_STYLES } from '@/utils/colors'
import type { GoalStatus } from '@/types/goal'

const props = withDefaults(defineProps<{ status: GoalStatus; size?: 'sm' | 'md' }>(), { size: 'sm' })

/** Centralised tokens, never an inline hex -- see utils/colors. */
const style = computed(() => GOAL_STATUS_STYLES[props.status])
</script>

<template>
  <BaseBadge :tone="style.chip" :dot="style.dot" :size="size">{{ style.label }}</BaseBadge>
</template>
