<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppShell from '@/components/layout/AppShell.vue'
import ToastHost from '@/components/ui/ToastHost.vue'

const route = useRoute()

/** Auth screens get the full viewport; everything else lives inside the shell. */
const isBare = computed(() => route.meta.layout === 'bare')
</script>

<template>
  <ToastHost />
  <RouterView v-if="isBare" />
  <AppShell v-else />
</template>
