<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonBlock from '@/components/ui/SkeletonBlock.vue'
import type { RewardLedgerRow } from '@/types/reward'

defineProps<{ rows: RewardLedgerRow[]; loading?: boolean }>()
</script>

<template>
  <div class="space-y-3">
    <!--
      FR-RWD-06. Labelled as a record in the UI, not just in a doc comment: the
      one thing a member must never conclude from this table is that it is a
      balance they can spend.
    -->
    <p
      class="flex items-start gap-2 rounded-lg border border-line bg-surface-2 px-3 py-2.5 text-[11.5px] leading-relaxed text-ink-muted"
    >
      <AppIcon name="info" :size="14" class="mt-0.5 shrink-0 text-ink-faint" />
      A record of monetary rewards marked fulfilled outside the app. Nothing here can be spent in
      Pathforge, and amounts are grouped by their currency label rather than added together - the
      label is free text, so summing "500 BDT" with "20 USD" would be meaningless.
    </p>

    <SkeletonBlock v-if="loading" :rows="3" height="h-12" rounded="rounded-lg" />

    <EmptyState
      v-else-if="rows.length === 0"
      icon="wallet"
      title="Nothing recorded yet"
      body="Monetary rewards appear here once a mentor marks them fulfilled."
      compact
    />

    <div v-else class="overflow-x-auto">
      <table class="w-full min-w-[32rem] border-separate border-spacing-0 text-left">
        <caption class="sr-only">Fulfilled monetary rewards, grouped per mentorship</caption>

        <thead>
          <tr>
            <th
              v-for="heading in ['Mentor', 'Mentee', 'Fulfilled', 'Recorded totals']"
              :key="heading"
              scope="col"
              class="border-b border-line pb-2 pl-1 text-[11px] font-medium uppercase tracking-[0.1em] text-ink-faint"
              :class="heading === 'Fulfilled' ? 'text-right' : ''"
            >
              {{ heading }}
            </th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="row in rows" :key="row.mentorship_id">
            <td class="border-b border-line py-2.5 pl-1 text-[13px] text-ink">
              {{ row.mentor.name ?? 'Unknown' }}
            </td>
            <td class="border-b border-line py-2.5 pl-1 text-[13px] text-ink">
              {{ row.mentee.name ?? 'Unknown' }}
            </td>
            <td class="tnum border-b border-line py-2.5 pl-1 text-right text-[13px] text-ink-muted">
              {{ row.fulfilled_count }}
            </td>
            <td class="border-b border-line py-2.5 pl-1">
              <ul class="flex flex-wrap gap-1.5">
                <li
                  v-for="(total, label) in row.totals_by_label"
                  :key="label"
                  class="tnum inline-flex items-center gap-1 rounded-md border border-line bg-surface-2 px-2 py-0.5 text-[11.5px] text-ink"
                >
                  {{ total }}
                  <span class="text-ink-faint">{{ label || 'unlabelled' }}</span>
                </li>
              </ul>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
