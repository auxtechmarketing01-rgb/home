<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { useGroupsStore } from '@/stores/groups'
import { useToastsStore } from '@/stores/toasts'
import { formatDate } from '@/utils/date'

const groups = useGroupsStore()
const toasts = useToastsStore()

const creating = ref(false)
const joining = ref(false)
const name = ref('')
const inviteCode = ref('')

onMounted(() => {
  void groups.fetchAll()
})

async function create(): Promise<void> {
  const group = await groups.create(name.value.trim())

  if (group) {
    creating.value = false
    name.value = ''
    toasts.success('Group created', 'Share the invite code to bring people in.')
  }
}

async function join(): Promise<void> {
  const group = await groups.join(inviteCode.value.trim())

  if (group) {
    joining.value = false
    inviteCode.value = ''
    toasts.success('Joined', `You are now in ${group.name}.`)
  }
}
</script>

<template>
  <div class="space-y-6">
    <SectionHeader
      eyebrow="Your circle"
      title="Groups"
      subtitle="A group is the only place progress is ever compared, and the only place a mentorship can start."
    >
      <template #actions>
        <BaseButton variant="subtle" size="sm" icon="key" @click="joining = true">
          Join with a code
        </BaseButton>
        <BaseButton variant="primary" size="sm" icon="plus" @click="creating = true">
          New group
        </BaseButton>
      </template>
    </SectionHeader>

    <ErrorBanner :failure="groups.failure" dismissible @dismiss="groups.clearFailure()" />

    <SkeletonBlock
      v-if="groups.loading && groups.groups.length === 0"
      :rows="2"
      height="h-28"
      rounded="rounded-xl"
    />

    <EmptyState
      v-else-if="groups.groups.length === 0"
      icon="users"
      title="You are not in a group yet"
      body="Create one and invite your people, or join an existing one with a code. Pathforge has no public directory, so a code or an emailed invite is the only way in."
    >
      <div class="flex flex-wrap justify-center gap-2">
        <BaseButton variant="primary" size="sm" icon="plus" @click="creating = true">
          Create a group
        </BaseButton>
        <BaseButton variant="subtle" size="sm" icon="key" @click="joining = true">
          I have a code
        </BaseButton>
      </div>
    </EmptyState>

    <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
      <RouterLink
        v-for="group in groups.groups"
        :key="group.id"
        :to="{ name: 'group-detail', params: { id: group.id } }"
        class="group flex flex-col gap-3 rounded-xl border border-line bg-surface p-4 transition-colors duration-150 hover:border-line-strong hover:bg-surface-2"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h3 class="truncate font-display text-[15px] font-semibold text-ink">
              {{ group.name }}
            </h3>
            <p class="tnum mt-0.5 text-[11.5px] text-ink-faint">
              {{ group.members_count ?? group.members?.length ?? 0 }} member{{
                (group.members_count ?? 0) === 1 ? '' : 's'
              }}
              <template v-if="group.created_at"> - since {{ formatDate(group.created_at) }}</template>
            </p>
          </div>

          <span
            v-if="group.is_owner"
            class="inline-flex items-center gap-1 rounded-md border border-brand/25 bg-brand-soft px-2 py-0.5 text-[10.5px] font-medium text-brand"
          >
            <AppIcon name="key" :size="10" />
            Owner
          </span>
        </div>

        <!-- Avatar stack: who is in here, readable at a glance without a list. -->
        <ul v-if="group.members?.length" class="flex flex-wrap items-center gap-1">
          <li
            v-for="member in group.members.slice(0, 6)"
            :key="member.id"
            class="grid size-7 place-items-center rounded-full border border-line bg-surface-2 text-[10px] font-bold text-ink-muted"
            :title="member.name"
          >
            {{
              member.name
                .split(' ')
                .filter(Boolean)
                .slice(0, 2)
                .map((part) => part[0]?.toUpperCase())
                .join('')
            }}
          </li>
          <li
            v-if="group.members.length > 6"
            class="tnum grid size-7 place-items-center rounded-full border border-dashed border-line text-[10px] text-ink-faint"
          >
            +{{ group.members.length - 6 }}
          </li>
        </ul>

        <span class="mt-auto flex items-center gap-1.5 border-t border-line pt-3 text-[12px] text-brand">
          Open group
          <AppIcon name="arrowRight" :size="13" />
        </span>
      </RouterLink>
    </div>

    <BaseModal
      v-model:open="creating"
      title="New group"
      description="You will be its owner, and you get an invite code to share."
      size="sm"
    >
      <BaseInput
        v-model="name"
        label="Group name"
        placeholder="The Rahman siblings"
        required
        :error="groups.failure?.errors.name?.[0] ?? null"
      />

      <template #footer>
        <BaseButton variant="ghost" size="sm" @click="creating = false">Cancel</BaseButton>
        <BaseButton
          variant="primary"
          size="sm"
          :loading="groups.saving"
          :disabled="name.trim().length === 0"
          @click="create"
        >
          Create group
        </BaseButton>
      </template>
    </BaseModal>

    <BaseModal
      v-model:open="joining"
      title="Join a group"
      description="Ask an existing member for the group's invite code."
      size="sm"
    >
      <BaseInput
        v-model="inviteCode"
        label="Invite code"
        icon="key"
        placeholder="Paste the code"
        required
        :error="groups.failure?.errors.invite_code?.[0] ?? null"
      />

      <template #footer>
        <BaseButton variant="ghost" size="sm" @click="joining = false">Cancel</BaseButton>
        <BaseButton
          variant="primary"
          size="sm"
          :loading="groups.saving"
          :disabled="inviteCode.trim().length === 0"
          @click="join"
        >
          Join
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
