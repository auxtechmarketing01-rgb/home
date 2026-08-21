<script setup lang="ts">
import { computed } from 'vue'
import { format, getDay, parseISO, startOfWeek, subDays } from 'date-fns'
import { heatmapStep } from '@/utils/colors'
import type { TrendPoint } from '@/types/analytics'

const props = withDefaults(defineProps<{ trend: TrendPoint[]; weeks?: number }>(), { weeks: 12 })

interface Cell {
  date: string
  minutes: number
  label: string
}

/**
 * Column-per-week, row-per-weekday. Day maths comes from date-fns rather than
 * hand-rolled arithmetic, because the server already resolved each day boundary
 * in the member's own timezone and the grid must not disagree with it.
 */
const grid = computed<Cell[][]>(() => {
  const byDate = new Map(props.trend.map((point) => [point.date, point.focus_minutes]))

  const today = new Date()
  const firstColumn = startOfWeek(subDays(today, (props.weeks - 1) * 7), { weekStartsOn: 1 })

  const columns: Cell[][] = []

  for (let week = 0; week < props.weeks; week += 1) {
    const column: Cell[] = []

    for (let day = 0; day < 7; day += 1) {
      const date = new Date(firstColumn)
      date.setDate(firstColumn.getDate() + week * 7 + day)

      const key = format(date, 'yyyy-MM-dd')
      const minutes = byDate.get(key) ?? 0

      column.push({
        date: key,
        minutes,
        label: `${format(date, 'EEE d MMM')}: ${minutes} focus minutes`,
      })
    }

    columns.push(column)
  }

  return columns
})

const max = computed(() => Math.max(1, ...props.trend.map((point) => point.focus_minutes)))

const monthLabels = computed(() =>
  grid.value.map((column, index) => {
    const first = column[0]

    if (!first) {
      return ''
    }

    const date = parseISO(first.date)
    const previous = index > 0 ? parseISO(grid.value[index - 1]?.[0]?.date ?? first.date) : null

    return previous === null || previous.getMonth() !== date.getMonth() ? format(date, 'MMM') : ''
  }),
)

const WEEKDAYS = ['Mon', '', 'Wed', '', 'Fri', '', 'Sun']

function today(): string {
  return format(new Date(), 'yyyy-MM-dd')
}

const activeDays = computed(() => props.trend.filter((point) => point.focus_minutes > 0).length)
</script>

<template>
  <div class="space-y-3">
    <div class="-mx-1 overflow-x-auto px-1 pb-1">
      <div class="inline-flex flex-col gap-1.5">
        <div class="flex gap-[3px] pl-8">
          <span
            v-for="(label, index) in monthLabels"
            :key="`month-${index}`"
            class="w-[13px] text-[9.5px] font-medium tracking-wide text-ink-faint"
            aria-hidden="true"
          >
            {{ label }}
          </span>
        </div>

        <div class="flex gap-[3px]">
          <div class="flex w-8 flex-col gap-[3px] pr-1.5">
            <span
              v-for="(day, index) in WEEKDAYS"
              :key="`wd-${index}`"
              class="flex h-[13px] items-center justify-end text-[9.5px] text-ink-faint"
              aria-hidden="true"
            >
              {{ day }}
            </span>
          </div>

          <div
            v-for="(column, weekIndex) in grid"
            :key="`w-${weekIndex}`"
            class="flex flex-col gap-[3px]"
          >
            <span
              v-for="cell in column"
              :key="cell.date"
              class="size-[13px] rounded-[3px] transition-transform duration-150 hover:scale-125"
              :class="[
                heatmapStep(cell.minutes, max),
                cell.date === today() ? 'ring-1 ring-ember ring-offset-1 ring-offset-surface' : '',
                cell.date > today() ? 'opacity-30' : '',
              ]"
              :title="cell.label"
              role="img"
              :aria-label="cell.label"
            />
          </div>
        </div>
      </div>
    </div>

    <!--
      A legend, because intensity encoded only as colour is unreadable to anyone
      who cannot distinguish those four steps.
    -->
    <div class="flex flex-wrap items-center justify-between gap-3 text-[11px] text-ink-faint">
      <span class="tnum">{{ activeDays }} active days in this window</span>
      <span class="flex items-center gap-1.5">
        Less
        <span class="size-[11px] rounded-[3px] bg-surface-2" aria-hidden="true" />
        <span class="size-[11px] rounded-[3px] bg-brand/25" aria-hidden="true" />
        <span class="size-[11px] rounded-[3px] bg-brand/50" aria-hidden="true" />
        <span class="size-[11px] rounded-[3px] bg-brand/75" aria-hidden="true" />
        <span class="size-[11px] rounded-[3px] bg-brand" aria-hidden="true" />
        More
      </span>
    </div>
  </div>
</template>
