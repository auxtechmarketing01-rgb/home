<script setup lang="ts">
import { ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import { resourcesApi } from '@/api/resources'
import { formatBytes } from '@/utils/formatDuration'
import { formatRelative } from '@/utils/date'
import type { ResourceFile, ResourceType } from '@/types/resource'
import type { IconName } from '@/components/ui/icons'

withDefaults(
  defineProps<{
    items: ResourceFile[]
    loading?: boolean
    canDelete?: boolean
  }>(),
  { canDelete: true },
)

const emit = defineEmits<{ destroy: [ResourceFile] }>()

const expanded = ref<number | null>(null)

const ICONS: Record<ResourceType, IconName> = { file: 'file', link: 'link', note: 'note' }
</script>

<template>
  <div>
    <SkeletonBlock v-if="loading" :rows="3" height="h-14" rounded="rounded-lg" />

    <EmptyState
      v-else-if="items.length === 0"
      icon="file"
      title="Nothing attached"
      body="Files, links and notes you keep here stay next to the work they belong to."
      compact
    />

    <ul v-else class="divide-y divide-line overflow-hidden rounded-xl border border-line">
      <li v-for="item in items" :key="item.id" class="bg-surface">
        <div class="flex items-start gap-3 p-3">
          <span
            class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg border border-line bg-surface-2 text-ink-faint"
            aria-hidden="true"
          >
            <AppIcon :name="ICONS[item.type]" :size="15" />
          </span>

          <div class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-medium text-ink">{{ item.title }}</p>
            <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-ink-faint">
              <span class="capitalize">{{ item.type }}</span>
              <span v-if="item.size_bytes" class="tnum">{{ formatBytes(item.size_bytes) }}</span>
              <span v-if="item.uploaded_by">{{ item.uploaded_by.name }}</span>
              <span v-if="item.created_at" class="tnum">{{ formatRelative(item.created_at) }}</span>
            </p>
          </div>

          <div class="flex shrink-0 items-center gap-0.5">
            <!--
              A file downloads through the API so the Policy still gates the
              bytes; a link opens directly; a note expands in place.
            -->
            <a
              v-if="item.type === 'file'"
              :href="resourcesApi.downloadUrl(item.id)"
              class="grid size-8 place-items-center rounded-md text-ink-faint transition-colors hover:bg-surface-2 hover:text-ink"
              :aria-label="`Download ${item.title}`"
            >
              <AppIcon name="download" :size="15" />
            </a>

            <a
              v-else-if="item.type === 'link' && item.url"
              :href="item.url"
              target="_blank"
              rel="noopener noreferrer"
              class="grid size-8 place-items-center rounded-md text-ink-faint transition-colors hover:bg-surface-2 hover:text-ink"
              :aria-label="`Open ${item.title} in a new tab`"
            >
              <AppIcon name="external" :size="15" />
            </a>

            <button
              v-else-if="item.type === 'note'"
              type="button"
              class="grid size-8 place-items-center rounded-md text-ink-faint transition-colors hover:bg-surface-2 hover:text-ink"
              :aria-expanded="expanded === item.id"
              :aria-label="`${expanded === item.id ? 'Collapse' : 'Expand'} ${item.title}`"
              @click="expanded = expanded === item.id ? null : item.id"
            >
              <AppIcon :name="expanded === item.id ? 'chevronUp' : 'chevronDown'" :size="15" />
            </button>

            <button
              v-if="canDelete"
              type="button"
              class="grid size-8 place-items-center rounded-md text-ink-faint transition-colors hover:bg-danger-soft hover:text-danger"
              :aria-label="`Remove ${item.title}`"
              @click="emit('destroy', item)"
            >
              <AppIcon name="trash" :size="15" />
            </button>
          </div>
        </div>

        <p
          v-if="item.type === 'note' && expanded === item.id && item.body"
          class="whitespace-pre-wrap border-t border-line bg-surface-2 px-3 py-3 text-[12.5px] leading-relaxed text-ink-muted"
        >
          {{ item.body }}
        </p>
      </li>
    </ul>
  </div>
</template>
