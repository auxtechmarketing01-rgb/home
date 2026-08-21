<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseSwitch from '@/components/ui/BaseSwitch.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import SectionHeader from '@/components/ui/SectionHeader.vue'
import ThemeToggle from '@/components/layout/ThemeToggle.vue'
import { useAuth } from '@/composables/useAuth'
import { usePushNotifications } from '@/composables/usePushNotifications'
import { useAuthStore } from '@/stores/auth'
import { useToastsStore } from '@/stores/toasts'

const auth = useAuthStore()
const { signOut } = useAuth()
const push = usePushNotifications()
const toasts = useToastsStore()

const subscribed = ref(false)

const profile = reactive({
  name: auth.user?.name ?? '',
  timezone: auth.user?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone,
})

/**
 * Gamification is opt-in, so this toggle is the switch the whole feature keys
 * off -- the backend answers `{ enabled: false }` and every panel branches on it.
 */
const settings = reactive({
  gamification_enabled: auth.user?.gamification_enabled ?? false,
  sprint_reminder_hour: null as number | null,
  streak_reminder_hour: null as number | null,
})

onMounted(async () => {
  subscribed.value = (await push.currentSubscription()) !== null
})

/**
 * A short list of common zones plus whatever the browser reports, rather than
 * all ~600 IANA names. `timezone:all` accepts any of them, so a member in an
 * unlisted zone still gets their own correct value from the browser default.
 */
const timezoneOptions = computed(() => {
  const detected = Intl.DateTimeFormat().resolvedOptions().timeZone

  const common = [
    'UTC',
    'Asia/Dhaka',
    'Asia/Kolkata',
    'Asia/Karachi',
    'Asia/Dubai',
    'Asia/Singapore',
    'Asia/Tokyo',
    'Europe/London',
    'Europe/Berlin',
    'Europe/Istanbul',
    'America/New_York',
    'America/Chicago',
    'America/Los_Angeles',
    'Australia/Sydney',
  ]

  const all = [...new Set([detected, auth.user?.timezone ?? detected, ...common])].filter(Boolean)

  return all.map((zone) => ({ value: zone, label: zone as string }))
})

const HOURS = [
  { value: null, label: 'Off' },
  ...Array.from({ length: 24 }, (_, hour) => ({
    value: hour,
    label: `${String(hour).padStart(2, '0')}:00`,
  })),
]

async function saveProfile(): Promise<void> {
  if (await auth.updateProfile({ name: profile.name.trim(), timezone: profile.timezone })) {
    toasts.success('Profile saved')
  }
}

async function saveSettings(): Promise<void> {
  const saved = await auth.updateProfile({
    settings: {
      gamification_enabled: settings.gamification_enabled,
      sprint_reminder_hour: settings.sprint_reminder_hour,
      streak_reminder_hour: settings.streak_reminder_hour,
    },
  })

  if (saved) {
    toasts.success('Preferences saved')
  }
}

async function togglePush(): Promise<void> {
  if (subscribed.value) {
    await push.unsubscribe()
    subscribed.value = false
    toasts.info('Notifications off for this browser')

    return
  }

  if (await push.subscribe()) {
    subscribed.value = true
    toasts.success('Notifications on for this browser')
  } else if (push.error.value) {
    toasts.error('Could not enable', push.error.value)
  }
}
</script>

<template>
  <div class="max-w-3xl space-y-8">
    <SectionHeader
      eyebrow="Account"
      title="Settings"
      subtitle="Your timezone decides when your day starts, which is what makes streaks mean the same thing for everyone in your circle."
    />

    <ErrorBanner :failure="auth.failure" />

    <section class="space-y-4 rounded-xl border border-line bg-surface p-5">
      <SectionHeader title="Profile" />

      <div class="grid gap-4 sm:grid-cols-2">
        <BaseInput
          v-model="profile.name"
          label="Name"
          required
          :error="auth.failure?.errors.name?.[0] ?? null"
        />
        <BaseSelect
          v-model="profile.timezone"
          label="Timezone"
          :options="timezoneOptions"
          hint="Day boundaries and reminders follow this."
          :error="auth.failure?.errors.timezone?.[0] ?? null"
        />
      </div>

      <div class="flex items-center justify-between gap-3 border-t border-line pt-4">
        <p class="text-[12px] text-ink-faint">
          Signed in as <span class="text-ink-muted">{{ auth.user?.email }}</span>
        </p>
        <BaseButton
          variant="primary"
          size="sm"
          :loading="auth.loading"
          :disabled="profile.name.trim().length === 0"
          @click="saveProfile"
        >
          Save profile
        </BaseButton>
      </div>
    </section>

    <section class="space-y-5 rounded-xl border border-line bg-surface p-5">
      <SectionHeader
        title="Reminders"
        subtitle="Sent in your own timezone. Set either to Off to stop it entirely."
      />

      <div class="grid gap-4 sm:grid-cols-2">
        <BaseSelect
          v-model="settings.sprint_reminder_hour"
          label="Daily sprint nudge"
          :options="HOURS"
          hint="A prompt to start a focus sprint."
        />
        <BaseSelect
          v-model="settings.streak_reminder_hour"
          label="Streak-at-risk warning"
          :options="HOURS"
          hint="Sent if nothing is logged by this hour."
        />
      </div>

      <div class="border-t border-line pt-4">
        <BaseSwitch
          v-model="settings.gamification_enabled"
          label="XP, levels and badges"
          description="Off by default. When off, the server sends no gamification data at all rather than zeros, so nothing is shown anywhere."
        />
      </div>

      <div class="flex justify-end border-t border-line pt-4">
        <BaseButton variant="primary" size="sm" :loading="auth.loading" @click="saveSettings">
          Save preferences
        </BaseButton>
      </div>
    </section>

    <section class="space-y-4 rounded-xl border border-line bg-surface p-5">
      <SectionHeader
        title="Push notifications"
        subtitle="Per browser, not per account - enable it on each device you want reached."
      />

      <BaseSwitch
        :model-value="subscribed"
        label="Notify this browser"
        description="Reaches you with the tab and window closed, as long as the browser is still running. Fully quit it and the notification is queued, not lost. On iOS this needs Pathforge added to your home screen first."
        @update:model-value="togglePush"
      />

      <p
        v-if="!push.isSupported()"
        class="flex items-start gap-2 rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-[11.5px] leading-relaxed text-ink-muted"
      >
        <AppIcon name="alert" :size="14" class="mt-0.5 shrink-0 text-warn" />
        This browser cannot receive push notifications. Everything else keeps working - you will see
        updates live while a tab is open.
      </p>

      <p
        v-else-if="push.permission.value === 'denied'"
        class="flex items-start gap-2 rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-[11.5px] leading-relaxed text-ink-muted"
      >
        <AppIcon name="lock" :size="14" class="mt-0.5 shrink-0 text-warn" />
        Notifications are blocked for this site in your browser settings. Unblock them there first -
        the site cannot ask again on its own.
      </p>
    </section>

    <section class="space-y-4 rounded-xl border border-line bg-surface p-5">
      <SectionHeader title="Appearance" />

      <div class="flex items-center justify-between gap-4">
        <div>
          <p class="text-sm font-medium text-ink">Theme</p>
          <p class="mt-0.5 text-xs text-ink-muted">
            Dark is the default look. "Match system" follows your OS.
          </p>
        </div>
        <ThemeToggle />
      </div>
    </section>

    <section class="rounded-xl border border-line bg-surface p-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-sm font-medium text-ink">Sign out</p>
          <p class="mt-0.5 text-xs text-ink-muted">
            Ends this session. A running sprint keeps running on the server.
          </p>
        </div>
        <BaseButton variant="subtle" size="sm" icon="logout" @click="signOut">Sign out</BaseButton>
      </div>
    </section>
  </div>
</template>
