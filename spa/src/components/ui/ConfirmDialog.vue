<script setup lang="ts">
import BaseButton from './BaseButton.vue'
import BaseModal from './BaseModal.vue'

const props = withDefaults(
  defineProps<{
    title: string
    body: string
    confirmLabel?: string
    cancelLabel?: string
    tone?: 'danger' | 'primary'
    busy?: boolean
  }>(),
  { confirmLabel: 'Confirm', cancelLabel: 'Cancel', tone: 'danger' },
)

const open = defineModel<boolean>('open', { default: false })

const emit = defineEmits<{ confirm: [] }>()

function confirm(): void {
  emit('confirm')
}
</script>

<template>
  <BaseModal v-model:open="open" :title="props.title" size="sm">
    <p class="text-[13px] leading-relaxed text-ink-muted">{{ props.body }}</p>

    <template #footer>
      <BaseButton variant="ghost" size="sm" @click="open = false">{{ props.cancelLabel }}</BaseButton>
      <BaseButton
        :variant="props.tone === 'danger' ? 'danger' : 'primary'"
        size="sm"
        :loading="props.busy"
        data-autofocus
        @click="confirm"
      >
        {{ props.confirmLabel }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
