<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import type { ApiFailure } from '@/types/api'
import type { GroupMember } from '@/types/group'
import type { MentorshipRequestPayload, MentorshipRole } from '@/types/mentorship'

const props = defineProps<{
  /** Only members who share a Group with the acting member (FR-MENT-01). */
  candidates: GroupMember[]
  saving?: boolean
  failure?: ApiFailure | null
  presetUserId?: number | null
}>()

const emit = defineEmits<{ submit: [MentorshipRequestPayload]; cancel: [] }>()

const userId = ref<number | null>(props.presetUserId ?? null)
const targetRole = ref<MentorshipRole>('mentor')

const options = computed(() =>
  props.candidates.map((member) => ({ value: member.id, label: member.name })),
)

const selectedName = computed(
  () => props.candidates.find((member) => member.id === userId.value)?.name ?? 'them',
)

function serverError(field: string): string | null {
  return props.failure?.errors[field]?.[0] ?? null
}

function submit(): void {
  if (userId.value === null) {
    return
  }

  emit('submit', { user_id: userId.value, role: targetRole.value })
}
</script>

<template>
  <div>
    <!--
      A picker, not a search box. The backend permits mentorship only inside a
      shared Group, so a free-text search would advertise a capability that only
      ever returns 403.
    -->
    <EmptyState
      v-if="candidates.length === 0"
      icon="users"
      title="No one to pair with yet"
      body="Mentorship only works between members of a group you both belong to. Join or create a group first."
      compact
    >
      <BaseButton variant="primary" size="sm" to="/groups" icon="users">Go to groups</BaseButton>
    </EmptyState>

    <form v-else class="space-y-4" @submit.prevent="submit">
      <ErrorBanner v-if="failure && Object.keys(failure.errors).length === 0" :failure="failure" />

      <BaseSelect
        v-model="userId"
        label="Member"
        placeholder="Choose someone"
        :options="options"
        required
        hint="Only members of your groups appear here."
        :error="serverError('user_id')"
      />

      <fieldset>
        <legend class="mb-2 text-[13px] font-medium text-ink-muted">What are you asking for?</legend>

        <div class="grid gap-2">
          <label
            v-for="option in [
              {
                value: 'mentor',
                title: `Ask ${selectedName} to mentor me`,
                body: 'They can see your shared goals, set time budgets and due dates, and attach rewards.',
              },
              {
                value: 'mentee',
                title: `Offer to mentor ${selectedName}`,
                body: 'You would get read access to their goals plus the ability to set expectations.',
              },
            ]"
            :key="option.value"
            :class="[
              'flex cursor-pointer gap-2.5 rounded-lg border p-3 transition-colors duration-150',
              targetRole === option.value
                ? 'border-violet bg-violet/10'
                : 'border-line bg-surface-2 hover:border-line-strong',
            ]"
          >
            <input
              v-model="targetRole"
              type="radio"
              name="mentorship-role"
              :value="option.value"
              class="mt-0.5 size-4 shrink-0 accent-[var(--pf-violet)]"
            />
            <span class="min-w-0">
              <span class="block text-[13px] font-semibold text-ink">{{ option.title }}</span>
              <span class="mt-0.5 block text-[11.5px] leading-relaxed text-ink-muted">
                {{ option.body }}
              </span>
            </span>
          </label>
        </div>
      </fieldset>

      <p class="flex items-start gap-2 rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-[11.5px] leading-relaxed text-ink-muted">
        <AppIcon name="shield" :size="14" class="mt-0.5 shrink-0 text-ink-faint" />
        A mentor sets expectations, never content. Only you edit your own steps or mark them done.
      </p>

      <div class="flex justify-end gap-2">
        <BaseButton variant="ghost" size="sm" @click="emit('cancel')">Cancel</BaseButton>
        <BaseButton
          type="submit"
          variant="primary"
          size="sm"
          :loading="saving"
          :disabled="userId === null"
        >
          Send request
        </BaseButton>
      </div>
    </form>
  </div>
</template>
