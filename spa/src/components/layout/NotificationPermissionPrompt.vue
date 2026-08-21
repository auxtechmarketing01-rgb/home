<script setup lang="ts">
import { onMounted, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { usePushNotifications } from '@/composables/usePushNotifications'
import { useToastsStore } from '@/stores/toasts'

const push = usePushNotifications()
const toasts = useToastsStore()

const visible = ref(false)
const expanded = ref(false)

onMounted(async () => {
  if (!push.isSupported() || push.promptDismissed() || Notification.permission === 'denied') {
    return
  }

  /** Already subscribed on this browser: nothing to ask for. */
  if (Notification.permission === 'granted' && (await push.currentSubscription())) {
    return
  }

  visible.value = true
})

async function enable(): Promise<void> {
  const ok = await push.subscribe()

  if (ok) {
    visible.value = false
    toasts.success('Notifications on', 'You will be told when a sprint passes its plan.')
  } else if (push.error.value) {
    toasts.error('Could not enable notifications', push.error.value)
  }
}

function dismiss(): void {
  push.dismissPrompt()
  visible.value = false
}
</script>

<template>
  <!--
    Says what push actually does and does not do. Implying "notifications always
    arrive instantly everywhere" earns a permission grant and then breaks trust
    the first time a phone is asleep -- the caveats belong in the ask.
  -->
  <section
    v-if="visible"
    class="rounded-xl border border-line bg-surface p-4"
    aria-labelledby="push-prompt-title"
  >
    <div class="flex items-start gap-3">
      <span
        class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-lg border border-brand/30 bg-brand-soft text-brand"
        aria-hidden="true"
      >
        <AppIcon name="bell" :size="17" />
      </span>

      <div class="min-w-0 flex-1">
        <h2 id="push-prompt-title" class="text-[14px] font-semibold text-ink">
          Get told when a sprint passes its plan
        </h2>
        <p class="mt-1 text-[13px] leading-relaxed text-ink-muted">
          Your sprint keeps running on the server whether this tab is open or not. A notification is
          how you find out about it when it is not.
        </p>

        <button
          type="button"
          class="mt-2 inline-flex items-center gap-1 text-[12px] font-medium text-brand transition-opacity hover:opacity-75"
          :aria-expanded="expanded"
          @click="expanded = !expanded"
        >
          What this can and cannot promise
          <AppIcon :name="expanded ? 'chevronUp' : 'chevronDown'" :size="13" />
        </button>

        <ul
          v-if="expanded"
          class="mt-2 space-y-1.5 border-l-2 border-line pl-3 text-[12px] leading-relaxed text-ink-muted"
        >
          <li>
            Reaches you with this tab and window both closed, as long as your browser is still
            running in the background - the default on desktop Chrome, Firefox and Edge.
          </li>
          <li>Reliable on Android through the OS, whatever state the browser is in.</li>
          <li>
            If you fully quit the browser, the notification is queued and arrives when you reopen it.
            Delayed, not lost.
          </li>
          <li>
            On iOS this only works if you add Pathforge to your home screen (iOS 16.4+). A plain
            Safari tab cannot receive push at all - that is an Apple limitation, not something we can
            code around.
          </li>
        </ul>

        <div class="mt-3.5 flex flex-wrap gap-2">
          <BaseButton variant="primary" size="sm" :loading="push.busy.value" @click="enable">
            Enable notifications
          </BaseButton>
          <BaseButton variant="ghost" size="sm" @click="dismiss">Not now</BaseButton>
        </div>
      </div>

      <button
        type="button"
        class="-mr-1 -mt-1 grid size-7 shrink-0 place-items-center rounded-md text-ink-faint transition-colors hover:bg-surface-2 hover:text-ink"
        aria-label="Dismiss"
        @click="dismiss"
      >
        <AppIcon name="x" :size="15" />
      </button>
    </div>
  </section>
</template>
