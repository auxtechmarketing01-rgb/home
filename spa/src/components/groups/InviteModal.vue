<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import { useToastsStore } from '@/stores/toasts'
import type { ApiFailure } from '@/types/api'
import type { Group } from '@/types/group'

const props = defineProps<{
  group: Group
  saving?: boolean
  failure?: ApiFailure | null
}>()

const emit = defineEmits<{ invite: [string | null]; regenerate: [] }>()

const open = defineModel<boolean>('open', { default: false })

const toasts = useToastsStore()
const email = ref('')
const copied = ref(false)

const code = computed(() => props.group.invite_code ?? null)

async function copyCode(): Promise<void> {
  if (!code.value) {
    return
  }

  try {
    await navigator.clipboard.writeText(code.value)
    copied.value = true
    window.setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    toasts.warn('Could not copy', 'Select the code and copy it manually.')
  }
}

function sendInvite(): void {
  emit('invite', email.value.trim() || null)
  email.value = ''
}
</script>

<template>
  <BaseModal
    v-model:open="open"
    title="Invite to this group"
    description="Pathforge has no public directory - a code or an emailed invite is the only way in."
  >
    <div class="space-y-5">
      <ErrorBanner v-if="failure" :failure="failure" />

      <section v-if="code" class="space-y-2">
        <p class="text-[13px] font-medium text-ink-muted">Invite code</p>

        <div class="flex items-center gap-2">
          <code
            class="tnum flex-1 truncate rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-[14px] tracking-wide text-ink"
          >
            {{ code }}
          </code>
          <BaseButton
            variant="subtle"
            size="icon"
            :icon="copied ? 'check' : 'copy'"
            :label="copied ? 'Copied' : 'Copy invite code'"
            @click="copyCode"
          />
        </div>

        <p class="text-[11.5px] leading-relaxed text-ink-faint">
          Anyone with this code can join. Regenerate it to cut off everyone who has the old one.
        </p>

        <BaseButton variant="ghost" size="sm" icon="key" :loading="saving" @click="emit('regenerate')">
          Regenerate code
        </BaseButton>
      </section>

      <div class="flex items-center gap-3">
        <span class="h-px flex-1 bg-line" aria-hidden="true" />
        <span class="text-[11px] uppercase tracking-[0.12em] text-ink-faint">or</span>
        <span class="h-px flex-1 bg-line" aria-hidden="true" />
      </div>

      <form class="space-y-3" @submit.prevent="sendInvite">
        <BaseInput
          v-model="email"
          label="Invite by email"
          type="email"
          icon="mail"
          placeholder="sibling@example.com"
          hint="They get the code by email. Leave blank to just fetch the code above."
        />

        <BaseButton
          type="submit"
          variant="primary"
          size="sm"
          icon="mail"
          :loading="saving"
          :disabled="email.trim().length === 0"
        >
          Send invite
        </BaseButton>
      </form>

      <p class="flex items-start gap-2 rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-[11.5px] leading-relaxed text-ink-muted">
        <AppIcon name="shield" :size="14" class="mt-0.5 shrink-0 text-ink-faint" />
        Joining a group lets members see goals you explicitly mark as shared. Private goals stay
        private.
      </p>
    </div>
  </BaseModal>
</template>
