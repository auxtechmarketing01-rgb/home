<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { RouteLocationRaw } from 'vue-router'
import AppIcon from './AppIcon.vue'
import type { IconName } from './icons'

type Variant = 'primary' | 'ember' | 'ghost' | 'outline' | 'subtle' | 'danger'
type Size = 'sm' | 'md' | 'lg' | 'icon'

const props = withDefaults(
  defineProps<{
    variant?: Variant
    size?: Size
    to?: RouteLocationRaw
    href?: string
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
    loading?: boolean
    icon?: IconName
    trailingIcon?: IconName
    block?: boolean
    /** Required when the button renders an icon and nothing else. */
    label?: string
  }>(),
  { variant: 'subtle', size: 'md', type: 'button' },
)

/**
 * Every surface is a flat fill plus a hairline -- no shadows, no gradients. The
 * hover step is one luminance notch, which reads as responsive without the
 * bounce that makes an interface feel cheap.
 */
const VARIANTS: Record<Variant, string> = {
  primary:
    'bg-brand text-brand-ink border-transparent hover:bg-brand-hover active:brightness-95 font-semibold',
  ember:
    'bg-ember text-ember-ink border-transparent hover:bg-ember-hover active:brightness-95 font-semibold',
  outline: 'bg-transparent text-ink border-line-strong hover:bg-surface-2 hover:border-line-strong',
  subtle: 'bg-surface-2 text-ink border-line hover:bg-surface-3',
  ghost: 'bg-transparent text-ink-muted border-transparent hover:bg-surface-2 hover:text-ink',
  danger: 'bg-danger-soft text-danger border-danger/25 hover:bg-danger hover:text-white',
}

const SIZES: Record<Size, string> = {
  sm: 'h-8 px-3 text-[13px] gap-1.5 rounded-md',
  md: 'h-10 px-4 text-sm gap-2 rounded-lg',
  lg: 'h-12 px-5 text-[15px] gap-2 rounded-lg',
  /** 40px keeps an icon-only control at a comfortable pointer and touch target. */
  icon: 'h-10 w-10 justify-center rounded-lg',
}

const classes = computed(() => [
  'relative inline-flex items-center justify-center border font-medium select-none',
  'transition-[background-color,border-color,color,opacity] duration-150 ease-out',
  'disabled:opacity-45 disabled:pointer-events-none',
  VARIANTS[props.variant],
  SIZES[props.size],
  props.block ? 'w-full' : '',
])

const isDisabled = computed(() => props.disabled || props.loading)
</script>

<template>
  <RouterLink v-if="to && !isDisabled" :to="to" :class="classes" :aria-label="label">
    <AppIcon v-if="icon" :name="icon" :size="size === 'sm' ? 15 : 17" />
    <slot />
    <AppIcon v-if="trailingIcon" :name="trailingIcon" :size="size === 'sm' ? 15 : 17" />
  </RouterLink>

  <a
    v-else-if="href && !isDisabled"
    :href="href"
    :class="classes"
    :aria-label="label"
    rel="noopener"
  >
    <AppIcon v-if="icon" :name="icon" :size="size === 'sm' ? 15 : 17" />
    <slot />
    <AppIcon v-if="trailingIcon" :name="trailingIcon" :size="size === 'sm' ? 15 : 17" />
  </a>

  <button
    v-else
    :type="type"
    :class="classes"
    :disabled="isDisabled"
    :aria-label="label"
    :aria-busy="loading || undefined"
  >
    <AppIcon
      v-if="loading"
      name="loader"
      :size="size === 'sm' ? 15 : 17"
      class="animate-spin"
    />
    <AppIcon v-else-if="icon" :name="icon" :size="size === 'sm' ? 15 : 17" />
    <slot />
    <AppIcon v-if="trailingIcon && !loading" :name="trailingIcon" :size="size === 'sm' ? 15 : 17" />
  </button>
</template>
