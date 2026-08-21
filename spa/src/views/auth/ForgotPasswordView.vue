<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import AuthLayout from './AuthLayout.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import { authApi } from '@/api/auth'
import { toApiFailure } from '@/api/client'
import type { ApiFailure } from '@/types/api'

const email = ref('')
const sent = ref<string | null>(null)
const busy = ref(false)
const failure = ref<ApiFailure | null>(null)

async function submit(): Promise<void> {
  busy.value = true
  failure.value = null

  try {
    sent.value = await authApi.forgotPassword(email.value.trim())
  } catch (error) {
    failure.value = toApiFailure(error, 'Could not send a reset link.')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <AuthLayout
    title="Reset your password"
    subtitle="We will email you a link that takes you straight back here."
  >
    <div
      v-if="sent"
      class="flex items-start gap-3 rounded-xl border border-brand/25 bg-brand-soft px-4 py-3.5"
      role="status"
    >
      <AppIcon name="mail" :size="17" class="mt-0.5 shrink-0 text-brand" />
      <div>
        <p class="text-[13.5px] font-semibold text-ink">Check your inbox</p>
        <p class="mt-0.5 text-[12.5px] leading-relaxed text-ink-muted">{{ sent }}</p>
      </div>
    </div>

    <form v-else class="space-y-4" novalidate @submit.prevent="submit">
      <ErrorBanner :failure="failure" />

      <BaseInput
        v-model="email"
        label="Email"
        type="email"
        icon="mail"
        autocomplete="email"
        required
        :error="failure?.errors.email?.[0] ?? null"
      />

      <BaseButton
        type="submit"
        variant="primary"
        size="lg"
        block
        :loading="busy"
        :disabled="email.trim().length === 0"
      >
        Send reset link
      </BaseButton>
    </form>

    <template #footer>
      <RouterLink
        :to="{ name: 'login' }"
        class="font-medium text-brand transition-opacity hover:opacity-75"
      >
        Back to sign in
      </RouterLink>
    </template>
  </AuthLayout>
</template>
