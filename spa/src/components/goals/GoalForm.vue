<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import ErrorBanner from '@/components/ui/ErrorBanner.vue'
import type { ApiFailure } from '@/types/api'
import type { Category, Goal, GoalPayload, GoalStatus, GoalVisibility } from '@/types/goal'
import type { Group } from '@/types/group'

const props = defineProps<{
  goal?: Goal | null
  categories: Category[]
  groups: Group[]
  saving?: boolean
  failure?: ApiFailure | null
}>()

const emit = defineEmits<{ submit: [GoalPayload]; cancel: [] }>()

interface FormState {
  title: string
  description: string
  category_id: number | null
  status: GoalStatus
  visibility: GoalVisibility
  group_id: number | null
  target_start_date: string
  target_end_date: string
}

const form = reactive<FormState>({
  title: '',
  description: '',
  category_id: null,
  status: 'draft',
  visibility: 'private',
  group_id: null,
  target_start_date: '',
  target_end_date: '',
})

watch(
  () => props.goal,
  (goal) => {
    form.title = goal?.title ?? ''
    form.description = goal?.description ?? ''
    form.category_id = goal?.category?.id ?? null
    form.status = goal?.status ?? 'draft'
    form.visibility = goal?.visibility ?? 'private'
    form.group_id = goal?.group_id ?? null
    form.target_start_date = goal?.target_start_date ?? ''
    form.target_end_date = goal?.target_end_date ?? ''
  },
  { immediate: true },
)

const statusOptions = [
  { value: 'draft', label: 'Draft - still shaping it' },
  { value: 'active', label: 'Active - working on it' },
  { value: 'paused', label: 'Paused' },
  { value: 'completed', label: 'Completed' },
  { value: 'abandoned', label: 'Abandoned' },
]

const categoryOptions = computed(() =>
  props.categories.map((category) => ({ value: category.id, label: category.name })),
)

const groupOptions = computed(() =>
  props.groups.map((group) => ({ value: group.id, label: group.name })),
)

/** Client-side mirror of `required_if:visibility,group` so the field explains itself. */
const groupRequired = computed(() => form.visibility === 'group')

const localTitleError = computed(() => {
  if (form.title.trim().length === 0) {
    return 'Give the goal a title.'
  }

  if (form.title.length > 255) {
    return 'Keep the title under 255 characters.'
  }

  return null
})

const localDateError = computed(() => {
  if (!form.target_start_date || !form.target_end_date) {
    return null
  }

  return form.target_end_date < form.target_start_date
    ? 'The end date cannot come before the start date.'
    : null
})

const localGroupError = computed(() =>
  groupRequired.value && form.group_id === null
    ? 'Pick the group this goal is shared with.'
    : null,
)

const canSubmit = computed(
  () => !localTitleError.value && !localDateError.value && !localGroupError.value,
)

function serverError(field: string): string | null {
  return props.failure?.errors[field]?.[0] ?? null
}

function submit(): void {
  if (!canSubmit.value) {
    return
  }

  emit('submit', {
    title: form.title.trim(),
    description: form.description.trim() || null,
    category_id: form.category_id,
    status: form.status,
    visibility: form.visibility,
    /** A private goal must not carry a stale group id from an earlier edit. */
    group_id: form.visibility === 'group' ? form.group_id : null,
    target_start_date: form.target_start_date || null,
    target_end_date: form.target_end_date || null,
  })
}
</script>

<template>
  <form class="space-y-4" novalidate @submit.prevent="submit">
    <ErrorBanner v-if="failure && Object.keys(failure.errors).length === 0" :failure="failure" />

    <BaseInput
      v-model="form.title"
      label="Title"
      placeholder="Ship the portfolio site"
      required
      :error="serverError('title') ?? (form.title.length > 0 ? localTitleError : null)"
    />

    <BaseTextarea
      v-model="form.description"
      label="Description"
      placeholder="What does done look like?"
      :rows="3"
      :maxlength="5000"
      :error="serverError('description')"
    />

    <div class="grid gap-4 sm:grid-cols-2">
      <BaseSelect
        v-model="form.category_id"
        label="Category"
        placeholder="No category"
        :options="categoryOptions"
        :error="serverError('category_id')"
      />
      <BaseSelect
        v-model="form.status"
        label="Status"
        :options="statusOptions"
        :error="serverError('status')"
      />
    </div>

    <fieldset class="space-y-3">
      <legend class="text-[13px] font-medium text-ink-muted">Visibility</legend>

      <div class="grid gap-2 sm:grid-cols-2">
        <label
          v-for="option in [
            {
              value: 'private',
              title: 'Private',
              body: 'Only you can see this goal and its progress.',
            },
            {
              value: 'group',
              title: 'Shared with a group',
              body: 'Group members see progress and it counts on the leaderboard.',
            },
          ]"
          :key="option.value"
          :class="[
            'flex cursor-pointer gap-2.5 rounded-lg border p-3 transition-colors duration-150',
            form.visibility === option.value
              ? 'border-brand bg-brand-soft'
              : 'border-line bg-surface-2 hover:border-line-strong',
          ]"
        >
          <input
            v-model="form.visibility"
            type="radio"
            name="visibility"
            :value="option.value"
            class="mt-0.5 size-4 shrink-0 accent-[var(--pf-brand)]"
          />
          <span class="min-w-0">
            <span class="block text-[13px] font-semibold text-ink">{{ option.title }}</span>
            <span class="mt-0.5 block text-[11.5px] leading-relaxed text-ink-muted">
              {{ option.body }}
            </span>
          </span>
        </label>
      </div>

      <BaseSelect
        v-if="groupRequired"
        v-model="form.group_id"
        label="Group"
        placeholder="Choose a group"
        :options="groupOptions"
        required
        :hint="
          groupOptions.length === 0
            ? 'You are not in a group yet - create or join one first.'
            : undefined
        "
        :error="serverError('group_id') ?? localGroupError"
      />
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
      <BaseInput
        v-model="form.target_start_date"
        label="Target start"
        type="date"
        :error="serverError('target_start_date')"
      />
      <BaseInput
        v-model="form.target_end_date"
        label="Target end"
        type="date"
        :error="serverError('target_end_date') ?? localDateError"
      />
    </div>

    <div class="flex justify-end gap-2 pt-1">
      <BaseButton variant="ghost" size="sm" @click="emit('cancel')">Cancel</BaseButton>
      <BaseButton type="submit" variant="primary" size="sm" :loading="saving" :disabled="!canSubmit">
        {{ goal ? 'Save changes' : 'Create goal' }}
      </BaseButton>
    </div>
  </form>
</template>
