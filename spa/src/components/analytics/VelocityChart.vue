<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Line } from 'vue-chartjs'
import type { ChartData, ChartOptions } from 'chart.js'
import { format, parseISO } from 'date-fns'
import EmptyState from '@/components/ui/EmptyState.vue'
import { chartTheme, ensureChartsRegistered } from './chartSetup'
import type { TrendPoint } from '@/types/analytics'

const props = withDefaults(
  defineProps<{ trend: TrendPoint[]; height?: number; label?: string }>(),
  { height: 220, label: 'Focus minutes' },
)

ensureChartsRegistered()

const theme = ref(chartTheme())

onMounted(() => {
  theme.value = chartTheme()
})

const hasData = computed(() => props.trend.some((point) => point.focus_minutes > 0))

const data = computed<ChartData<'line'>>(() => ({
  labels: props.trend.map((point) => format(parseISO(point.date), 'd MMM')),
  datasets: [
    {
      label: props.label,
      data: props.trend.map((point) => point.focus_minutes),
      borderColor: theme.value.brand,
      backgroundColor: `${theme.value.brand}22`,
      borderWidth: 2,
      tension: 0.32,
      fill: true,
      pointRadius: 0,
      /** Points appear on hover only -- a dot per day is noise at 84 days. */
      pointHoverRadius: 4,
      pointHoverBackgroundColor: theme.value.brand,
      pointHoverBorderColor: theme.value.surface,
      pointHoverBorderWidth: 2,
    },
  ],
}))

const options = computed<ChartOptions<'line'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: theme.value.surface,
      borderColor: theme.value.line,
      borderWidth: 1,
      titleColor: theme.value.ink,
      bodyColor: theme.value.ink,
      padding: 10,
      displayColors: false,
      callbacks: {
        label: (context) => `${context.parsed.y} focus minutes`,
      },
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
      title="No focus logged in this window"
      body="Run a sprint and the line starts here."
      compact
    />
    <!-- Height is reserved so arriving data never shifts the layout. -->
    <div v-else :style="{ height: `${height}px` }">
      <Line :data="data" :options="options" :aria-label="`${label} over time`" role="img" />
    </div>
  </div>
</template>
