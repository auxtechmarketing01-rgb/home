<script setup lang="ts">
import { computed, reactive } from 'vue'
import { RouterLink } from 'vue-router'
import AuthLayout from './AuthLayout.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import { useAuth } from '@/composables/useAuth'

const { store, fieldError, formError, redirectAfterAuth } = useAuth()

/**
 * The browser's zone is sent at signup because day boundaries are resolved per
 * member -- two siblings in different countries must each get their own midnight
 * for streaks to mean anything.
 */
const detectedTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const mismatch = computed(() =>
  form.password_confirmation.length > 0 && form.password !== form.password_confirmation
    ? 'The two passwords do not match.'
    : null,
)

const tooShort = computed(() =>
  form.password.length > 0 && form.password.length < 8
    ? 'Use at least 8 characters.'
    : null,
)

async function submit(): Promise<void> {
  if (mismatch.value || tooShort.value) {
    return
  }

  const created = await store.register({ ...form, timezone: detectedTimezone })

  if (created) {
    await redirectAfterAuth()
  }
}
</script>

<template>
  <AuthLayout
    title="Create your account"
    subtitle="One account, then join your circle with an invite code."
  >
    <form class="space-y-4" novalidate @submit.prevent="submit">
      <ErrorBanner :message="formError" />

      <BaseInput
        v-model="form.name"
        label="Name"
        autocomplete="name"
        placeholder="How your circle knows you"
        required
        :error="fieldError('name')"
      />

      <BaseInput
        v-model="form.email"
        label="Email"
        type="email"
        icon="mail"
        autocomplete="email"
        required
        :error="fieldError('email')"
      />

      <BaseInput
        v-model="form.password"
        label="Password"
        type="password"
        icon="lock"
        autocomplete="new-password"
        required
        hint="At least 8 characters."
        :error="fieldError('password') ?? tooShort"
      />

      <BaseInput
        v-model="form.password_confirmation"
        label="Confirm password"
        type="password"
        icon="lock"
        autocomplete="new-password"
        required
        :error="mismatch"
      />

      <p class="rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-[11.5px] leading-relaxed text-ink-muted">
        Your timezone is set to
        <span class="tnum font-medium text-ink">{{ detectedTimezone }}</span>
        so streaks and daily totals follow your own midnight. You can change it in settings.
      </p>

      <BaseButton type="submit" variant="primary" size="lg" block :loading="store.loading">
        Create account
      </BaseButton>
    </form>

    <template #footer>
      Already have an account?
      <RouterLink
        :to="{ name: 'login' }"
        class="font-medium text-brand transition-opacity hover:opacity-75"
      >
        Sign in
      </RouterLink>
    </template>
  </AuthLayout>
</template>
