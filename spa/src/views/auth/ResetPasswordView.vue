<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthLayout from './AuthLayout.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import { authApi } from '@/api/auth'
import { toApiFailure } from '@/api/client'
import { useToastsStore } from '@/stores/toasts'
import type { ApiFailure } from '@/types/api'

const route = useRoute()
const router = useRouter()
const toasts = useToastsStore()

/** The backend points its reset mail at this route with `token` and `email` in the query. */
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))

const form = reactive({
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  password_confirmation: '',
})

const busy = ref(false)
const failure = ref<ApiFailure | null>(null)

const mismatch = computed(() =>
  form.password_confirmation.length > 0 && form.password !== form.password_confirmation
    ? 'The two passwords do not match.'
    : null,
)

async function submit(): Promise<void> {
  if (mismatch.value) {
    return
  }

  busy.value = true
  failure.value = null

  try {
    await authApi.resetPassword({ token: token.value, ...form })
    toasts.success('Password changed', 'Sign in with your new password.')
    await router.replace({ name: 'login' })
  } catch (error) {
    failure.value = toApiFailure(error, 'Could not reset your password.')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <AuthLayout title="Choose a new password" subtitle="This link works once.">
    <div
      v-if="!token"
      class="flex items-start gap-3 rounded-xl border border-danger/30 bg-danger-soft px-4 py-3.5"
      role="alert"
    >
      <AppIcon name="alert" :size="17" class="mt-0.5 shrink-0 text-danger" />
      <div>
        <p class="text-[13.5px] font-semibold text-ink">This link is incomplete</p>
        <p class="mt-0.5 text-[12.5px] leading-relaxed text-ink-muted">
          Open the link from your email again, or request a fresh one.
        </p>
      </div>
    </div>

    <form v-else class="space-y-4" novalidate @submit.prevent="submit">
      <ErrorBanner :failure="failure" />

      <BaseInput
        v-model="form.email"
        label="Email"
        type="email"
        icon="mail"
        autocomplete="email"
        required
        :error="failure?.errors.email?.[0] ?? null"
      />

      <BaseInput
        v-model="form.password"
        label="New password"
        type="password"
        icon="lock"
        autocomplete="new-password"
        required
        hint="At least 8 characters."
        :error="failure?.errors.password?.[0] ?? null"
      />

      <BaseInput
        v-model="form.password_confirmation"
        label="Confirm new password"
        type="password"
        icon="lock"
        autocomplete="new-password"
        required
        :error="mismatch"
      />

      <BaseButton type="submit" variant="primary" size="lg" block :loading="busy">
        Change password
      </BaseButton>
    </form>

    <template #footer>
      <RouterLink
        :to="{ name: 'forgot-password' }"
        class="font-medium text-brand transition-opacity hover:opacity-75"
      >
        Request a new link
      </RouterLink>
    </template>
  </AuthLayout>
</template>
