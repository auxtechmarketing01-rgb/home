<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import type { GroupMember } from '@/types/group'

const props = defineProps<{
  members: GroupMember[]
  isOwner: boolean
  currentUserId?: number | null
  saving?: boolean
}>()

const emit = defineEmits<{
  remove: [GroupMember]
  mentor: [GroupMember]
}>()

const pendingRemoval = ref<GroupMember | null>(null)

const sorted = computed(() =>
  [...props.members].sort((a, b) => {
    if (a.role !== b.role) {
      return a.role === 'owner' ? -1 : 1
    }

    return a.name.localeCompare(b.name)
  }),
)

function initials(name: string): string {
  return (
    name
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase())
      .join('') || '?'
  )
}

function confirmRemoval(): void {
  if (pendingRemoval.value) {
    emit('remove', pendingRemoval.value)
    pendingRemoval.value = null
  }
}
</script>

<template>
  <div>
    <EmptyState
      v-if="members.length === 0"
      icon="users"
      title="No members yet"
      body="Share the invite code and this list fills up."
      compact
    />

    <ul v-else class="divide-y divide-line overflow-hidden rounded-xl border border-line">
      <li
        v-for="member in sorted"
        :key="member.id"
        class="flex items-center gap-3 bg-surface p-3"
      >
        <span
          class="grid size-9 shrink-0 place-items-center rounded-full border border-line bg-surface-2 text-[11.5px] font-bold text-ink-muted"
          aria-hidden="true"
        >
          {{ initials(member.name) }}
        </span>

        <div class="min-w-0 flex-1">
          <p class="truncate text-[13px] font-medium text-ink">
            {{ member.name }}
            <span v-if="member.id === currentUserId" class="text-[11px] font-normal text-brand">
              (you)
            </span>
          </p>
          <p class="text-[11px] capitalize text-ink-faint">{{ member.role }}</p>
        </div>

        <BaseBadge
          v-if="member.role === 'owner'"
          tone="bg-brand-soft text-brand border-brand/25"
        >
          Owner
        </BaseBadge>

        <!--
          FR-MENT-01: mentorship is offered from here rather than from a search
          box, because a shared group is the only place it is ever permitted.
        -->
        <button
          v-if="member.id !== currentUserId"
          type="button"
          class="grid size-8 place-items-center rounded-md text-ink-faint transition-colors hover:bg-surface-2 hover:text-violet"
          :aria-label="`Start a mentorship with ${member.name}`"
          title="Mentorship"
          @click="emit('mentor', member)"
        >
          <AppIcon name="handshake" :size="15" />
        </button>

        <button
          v-if="isOwner && member.id !== currentUserId && member.role !== 'owner'"
          type="button"
          class="grid size-8 place-items-center rounded-md text-ink-faint transition-colors hover:bg-danger-soft hover:text-danger"
          :aria-label="`Remove ${member.name} from the group`"
          @click="pendingRemoval = member"
        >
          <AppIcon name="x" :size="15" />
        </button>
      </li>
    </ul>

    <ConfirmDialog
      :open="pendingRemoval !== null"
      title="Remove this member?"
      :body="`${pendingRemoval?.name ?? 'They'} will lose access to goals shared with this group. Their own goals and history are untouched.`"
      confirm-label="Remove"
      :busy="saving"
      @update:open="(value) => !value && (pendingRemoval = null)"
      @confirm="confirmRemoval"
    />
  </div>
</template>
