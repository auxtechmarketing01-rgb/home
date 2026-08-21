<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import ProgressBar from '@/components/ui/ProgressBar.vue'
import { useFileUpload } from '@/composables/useFileUpload'
import { formatBytes } from '@/utils/formatDuration'
import type { ApiFailure } from '@/types/api'
import type { ResourcePayload, ResourceType } from '@/types/resource'
import type { IconName } from '@/components/ui/icons'

const props = defineProps<{
  uploading?: boolean
  progress?: number
  failure?: ApiFailure | null
}>()

const emit = defineEmits<{ submit: [ResourcePayload] }>()

const upload = useFileUpload()

const type = ref<ResourceType>('file')
const form = reactive({ title: '', url: '', body: '' })
const dragging = ref(false)

const TABS: Array<{ value: ResourceType; label: string; icon: IconName }> = [
  { value: 'file', label: 'File', icon: 'file' },
  { value: 'link', label: 'Link', icon: 'link' },
  { value: 'note', label: 'Note', icon: 'note' },
]

watch(type, () => {
  upload.reset()
  form.url = ''
  form.body = ''
})

/** Mirrors the backend's required_if rules so the button explains itself. */
const canSubmit = computed(() => {
  if (form.title.trim().length === 0) {
    return false
  }

  if (type.value === 'file') {
    return upload.file.value !== null
  }

  if (type.value === 'link') {
    return form.url.trim().length > 0
  }

  return form.body.trim().length > 0
})

function serverError(field: string): string | null {
  return props.failure?.errors[field]?.[0] ?? null
}

function onDrop(event: DragEvent): void {
  dragging.value = false
  const dropped = event.dataTransfer?.files?.[0]

  if (dropped) {
    upload.select(dropped)
  }
}

function submit(): void {
  if (!canSubmit.value) {
    return
  }

  emit('submit', {
    type: type.value,
    title: form.title.trim(),
    file: type.value === 'file' ? upload.file.value : null,
    url: type.value === 'link' ? form.url.trim() : null,
    body: type.value === 'note' ? form.body.trim() : null,
  })

  form.title = ''
  form.url = ''
  form.body = ''
  upload.reset()
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <div
      class="inline-flex rounded-lg border border-line bg-surface-2 p-0.5"
      role="tablist"
      aria-label="Attachment type"
    >
      <button
        v-for="tab in TABS"
        :key="tab.value"
        type="button"
        role="tab"
        :aria-selected="type === tab.value"
        :class="[
          'inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-[12.5px] transition-colors duration-150',
          type === tab.value ? 'bg-surface font-semibold text-ink' : 'font-medium text-ink-muted hover:text-ink',
        ]"
        @click="type = tab.value"
      >
        <AppIcon :name="tab.icon" :size="14" />
        {{ tab.label }}
      </button>
    </div>

    <ErrorBanner v-if="failure && Object.keys(failure.errors).length === 0" :failure="failure" />

    <BaseInput
      v-model="form.title"
      label="Title"
      placeholder="What is this?"
      required
      :error="serverError('title')"
    />

    <template v-if="type === 'file'">
      <div
        :class="[
          'rounded-xl border border-dashed p-5 text-center transition-colors duration-150',
          dragging ? 'border-brand bg-brand-soft' : 'border-line bg-surface-2',
        ]"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
      >
        <AppIcon name="upload" :size="20" class="mx-auto text-ink-faint" />

        <p v-if="upload.file.value" class="mt-2 text-[13px] font-medium text-ink">
          {{ upload.file.value.name }}
          <span class="tnum ml-1 text-[11.5px] font-normal text-ink-faint">
            {{ formatBytes(upload.file.value.size) }}
          </span>
        </p>
        <p v-else class="mt-2 text-[13px] text-ink-muted">Drop a file here, or</p>

        <label
          class="mt-2 inline-flex h-8 cursor-pointer items-center rounded-md border border-line bg-surface px-3 text-[12.5px] font-medium text-ink transition-colors hover:bg-surface-3"
        >
          {{ upload.file.value ? 'Choose a different file' : 'Browse' }}
          <input
            type="file"
            class="sr-only"
            :accept="upload.accept.value"
            @change="upload.onInputChange"
          />
        </label>

        <!--
          Stated plainly: this check is a courtesy. The server allow-list is the
          gate, so nothing here should read as a security guarantee.
        -->
        <p class="mt-2.5 text-[11px] leading-relaxed text-ink-faint">
          Up to {{ Math.round(upload.maxBytes / 1024 / 1024) }} MB. The server checks the type and
          contents again on arrival - this is just a quick sanity check.
        </p>
      </div>

      <p v-if="upload.localError.value" class="flex items-start gap-1.5 text-xs text-danger">
        <AppIcon name="alert" :size="13" class="mt-0.5" />
        {{ upload.localError.value }}
      </p>
      <p v-else-if="serverError('file')" class="flex items-start gap-1.5 text-xs text-danger">
        <AppIcon name="alert" :size="13" class="mt-0.5" />
        {{ serverError('file') }}
      </p>

      <div v-if="uploading" class="space-y-1.5">
        <ProgressBar :value="progress ?? 0" label="Upload progress" />
        <p class="tnum text-[11.5px] text-ink-faint" role="status" aria-live="polite">
          Uploading {{ progress ?? 0 }}%
        </p>
      </div>
    </template>

    <BaseInput
      v-else-if="type === 'link'"
      v-model="form.url"
      label="URL"
      type="url"
      icon="link"
      placeholder="https://"
      required
      :error="serverError('url')"
    />

    <BaseTextarea
      v-else
      v-model="form.body"
      label="Note"
      placeholder="Paste or write anything worth keeping next to this."
      :rows="5"
      :maxlength="20000"
      required
      :error="serverError('body')"
    />

    <div class="flex justify-end">
      <BaseButton
        type="submit"
        variant="primary"
        size="sm"
        icon="plus"
        :loading="uploading"
        :disabled="!canSubmit"
      >
        Attach
      </BaseButton>
    </div>
  </form>
</template>
