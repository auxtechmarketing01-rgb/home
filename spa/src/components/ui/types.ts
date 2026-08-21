import type { IconName } from './icons'

/**
 * Shared prop shapes for the UI kit. They live here rather than in the SFCs
 * because `<script setup>` cannot carry ES exports -- a component that needs
 * `TabItem` imports it from this file, not from BaseTabs.vue.
 */
export interface TabItem {
  value: string
  label: string
  icon?: IconName
  count?: number | null
  hidden?: boolean
}

export interface SelectOption {
  value: string | number | null
  label: string
  disabled?: boolean
}
