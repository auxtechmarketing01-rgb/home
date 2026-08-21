<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import type { Goal } from '@/types/goal'
import type { Group } from '@/types/group'

const props = defineProps<{
  goal: Goal
  groups: Group[]
  disabled?: boolean
  saving?: boolean
}>()

const emit = defineEmits<{
  change: [{ visibility: 'private' | 'group'; group_id: number | null }]
}>()

const picking = ref(false)
const selectedGroupId = ref<number | null>(props.goal.group_id ?? null)

const isShared = computed(() => props.goal.visibility === 'group')

const groupName = computed(
  () => props.groups.find((group) => group.id === props.goal.group_id)?.name ?? props.goal.group?.name,
)

const groupOptions = computed(() =>
  props.groups.map((group) => ({ value: group.id, label: group.name })),
)

function toggle(): void {
  if (isShared.value) {
    emit('change', { visibility: 'private', group_id: null })

    return
  }

  /** Going public needs a destination, so it asks rather than guessing. */
  if (props.groups.length === 1) {
    emit('change', { visibility: 'group', group_id: props.groups[0]?.id ?? null })

    return
  }

  selectedGroupId.value = props.goal.group_id ?? null
  picking.value = true
}

function confirmShare(): void {
  if (selectedGroupId.value === null) {
    return
  }

  emit('change', { visibility: 'group', group_id: selectedGroupId.value })
  picking.value = false
}
</script>

<template>
  <div>
    <button
      type="button"
      class="inline-flex h-8 items-center gap-1.5 rounded-md border px-2.5 text-[12.5px] font-medium transition-colors duration-150 disabled:opacity-45"
      :class="
        isShared
          ? 'border-brand/30 bg-brand-soft text-brand hover:bg-brand/20'
          : 'border-line bg-surface-2 text-ink-muted hover:bg-surface-3 hover:text-ink'
      "
      :disabled="disabled || saving || (!isShared && groups.length === 0)"
      :title="
        !isShared && groups.length === 0
          ? 'Join a group before sharing a goal'
          : isShared
            ? 'Make this goal private again'
            : 'Share this goal with a group'
      "
      :aria-pressed="isShared"
      @click="toggle"
    >
      <AppIcon :name="isShared ? 'users' : 'lock'" :size="14" />
      <span>{{ isShared ? (groupName ?? 'Shared') : 'Private' }}</span>
    </button>

    <BaseModal
      v-model:open="picking"
      title="Share this goal"
      description="Members of the group will see its progress and it will count on the leaderboard."
      size="sm"
    >
      <BaseSelect
        v-model="selectedGroupId"
        label="Group"
        placeholder="Choose a group"
        :options="groupOptions"
        required
      />

      <template #footer>
        <BaseButton variant="ghost" size="sm" @click="picking = false">Cancel</BaseButton>
        <BaseButton
          variant="primary"
          size="sm"
          :disabled="selectedGroupId === null"
          :loading="saving"
          @click="confirmShare"
        >
          Share
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
