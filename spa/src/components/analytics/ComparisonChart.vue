<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Line } from 'vue-chartjs'
import type { ChartData, ChartOptions } from 'chart.js'
import { format, parseISO } from 'date-fns'
import EmptyState from '@/components/ui/EmptyState.vue'
import { chartTheme, ensureChartsRegistered } from './chartSetup'
import { seriesColor } from '@/utils/colors'
import type { GroupTrendSeries } from '@/types/analytics'

const props = withDefaults(defineProps<{ series: GroupTrendSeries[]; height?: number }>(), {
  height: 260,
})

ensureChartsRegistered()

const theme = ref(chartTheme())

onMounted(() => {
  theme.value = chartTheme()
})

const labels = computed(() =>
  (props.series[0]?.series ?? []).map((point) => format(parseISO(point.date), 'd MMM')),
)

const hasData = computed(() =>
  props.series.some((entry) => entry.series.some((point) => point.focus_minutes > 0)),
)

const data = computed<ChartData<'line'>>(() => ({
  labels: labels.value,
  datasets: props.series.map((entry, index) => ({
    label: entry.user.name,
    data: entry.series.map((point) => point.focus_minutes),
    borderColor: seriesColor(index),
    backgroundColor: seriesColor(index),
    borderWidth: 2,
    tension: 0.3,
    fill: false,
    pointRadius: 0,
    pointHoverRadius: 4,
    /**
     * Each member also gets a distinct dash pattern. Colour alone is not a
     * legend anyone can read if they cannot separate teal from green.
     */
    borderDash: index % 3 === 1 ? [5, 3] : index % 3 === 2 ? [2, 2] : undefined,
  })),
}))

const options = computed<ChartOptions<'line'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        color: theme.value.ink,
        boxWidth: 10,
        boxHeight: 10,
        usePointStyle: true,
        pointStyle: 'line',
        font: { size: 11 },
        padding: 14,
      },
    },
    tooltip: {
      backgroundColor: theme.value.surface,
      borderColor: theme.value.line,
      borderWidth: 1,
      titleColor: theme.value.ink,
      bodyColor: theme.value.ink,
      padding: 10,
      callbacks: { label: (context) => `${context.dataset.label}: ${context.parsed.y}m` },
    },
  },
  scales: {
    x: {
      grid: { display: false },
      border: { color: theme.value.line },
      ticks: {
        color: theme.value.inkFaint,
        font: { size: 10 },
        maxRotation: 0,
        autoSkipPadding: 24,
      },
    },
    y: {
      beginAtZero: true,
      grid: { color: theme.value.line },
      border: { display: false },
      ticks: { color: theme.value.inkFaint, font: { size: 10 }, precision: 0, maxTicksLimit: 5 },
    },
  },
}))
</script>

<template>
  <div>
    <EmptyState
      v-if="!hasData"
      icon="trend"
      title="Nothing to compare yet"
      body="Once members log focus against goals shared with this group, their lines appear here."
      compact
    />
    <div v-else :style="{ height: `${height}px` }">
      <Line :data="data" :options="options" role="img" aria-label="Focus minutes per member" />
    </div>
  </div>
</template>
