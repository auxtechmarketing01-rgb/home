<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AuthLayout from './AuthLayout.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import { useAuth } from '@/composables/useAuth'

const { store, fieldError, formError, redirectAfterAuth } = useAuth()

const form = reactive({ email: '', password: '', remember: true })
const showPassword = ref(false)

async function submit(): Promise<void> {
  if (await store.login({ ...form })) {
    await redirectAfterAuth()
  }
}
</script>

<template>
  <AuthLayout title="Welcome back" subtitle="Sign in to pick up where you left off.">
    <form class="space-y-4" novalidate @submit.prevent="submit">
      <ErrorBanner :message="formError" />

      <BaseInput
        v-model="form.email"
        label="Email"
        type="email"
        icon="mail"
        autocomplete="email"
        placeholder="you@example.com"
        required
        :error="fieldError('email')"
      />

      <div class="space-y-1.5">
        <BaseInput
          v-model="form.password"
          label="Password"
          :type="showPassword ? 'text' : 'password'"
          icon="lock"
          autocomplete="current-password"
          required
          :error="fieldError('password')"
        />
        <div class="flex items-center justify-between gap-3">
          <button
            type="button"
            class="text-[11.5px] font-medium text-ink-muted transition-colors hover:text-ink"
            @click="showPassword = !showPassword"
          >
            {{ showPassword ? 'Hide' : 'Show' }} password
          </button>
          <RouterLink
            :to="{ name: 'forgot-password' }"
            class="text-[11.5px] font-medium text-brand transition-opacity hover:opacity-75"
          >
            Forgot password?
          </RouterLink>
        </div>
      </div>

      <label class="flex items-center gap-2.5 text-[13px] text-ink-muted">
        <input
          v-model="form.remember"
          type="checkbox"
          class="size-4 rounded accent-[var(--pf-brand)]"
        />
        Keep me signed in
      </label>

      <BaseButton type="submit" variant="primary" size="lg" block :loading="store.loading">
        Sign in
      </BaseButton>
    </form>

    <template #footer>
      No account yet?
      <RouterLink
        :to="{ name: 'register' }"
        class="font-medium text-brand transition-opacity hover:opacity-75"
      >
        Create one
      </RouterLink>
    </template>
  </AuthLayout>
</template>
