<script setup lang="ts">
import AppIcon from '@/components/ui/AppIcon.vue'
import BrandMark from '@/components/layout/BrandMark.vue'
import ThemeToggle from '@/components/layout/ThemeToggle.vue'

defineProps<{ title: string; subtitle?: string }>()

const PILLARS = [
  {
    icon: 'route' as const,
    title: 'Break it into a path',
    body: 'One goal, an ordered roadmap, real time logged against each step.',
  },
  {
    icon: 'timer' as const,
    title: 'The timer lives on the server',
    body: 'Close the tab, switch device, come back in six hours - the sprint is still running.',
  },
  {
    icon: 'handshake' as const,
    title: 'Someone in your corner',
    body: 'A mentor sets time budgets and due dates, and attaches rewards worth finishing for.',
  },
]
</script>

<template>
  <div class="pf-grain relative min-h-dvh bg-canvas lg:grid lg:grid-cols-[1.05fr_1fr]">
    <!--
      The left panel repeats the product's rail-and-node motif at scale, so the
      first screen already teaches the visual language the roadmap uses.
    -->
    <aside class="relative hidden overflow-hidden border-r border-line bg-canvas-deep p-10 lg:flex lg:flex-col">
      <span
        class="pf-rail absolute bottom-10 left-[3.25rem] top-40 w-px"
        aria-hidden="true"
      />

      <BrandMark :size="30" with-wordmark />

      <div class="mt-auto max-w-md">
        <h2 class="font-display text-[30px] font-semibold leading-[1.15] tracking-[-0.02em] text-ink">
          Set the goal.
          <span class="block text-ink-muted">Forge the path.</span>
          <span class="block text-brand">Log the hours.</span>
        </h2>

        <ul class="mt-9 space-y-6">
          <li v-for="pillar in PILLARS" :key="pillar.title" class="relative flex gap-4">
            <span
              class="relative z-10 grid size-9 shrink-0 place-items-center rounded-full border border-line bg-canvas-deep text-brand"
              aria-hidden="true"
            >
              <AppIcon :name="pillar.icon" :size="16" />
            </span>
            <span class="min-w-0 pt-1">
              <span class="block text-[13.5px] font-semibold text-ink">{{ pillar.title }}</span>
              <span class="mt-1 block text-[12.5px] leading-relaxed text-ink-muted">
                {{ pillar.body }}
              </span>
            </span>
          </li>
        </ul>
      </div>

      <p class="mt-10 text-[11px] leading-relaxed text-ink-faint">
        Invite-only. There is no public directory and no cross-group search - you only ever see the
        people you share a circle with.
      </p>
    </aside>

    <main class="flex min-h-dvh flex-col px-5 py-8 sm:px-8 lg:py-10">
      <div class="flex items-center justify-between gap-3">
        <BrandMark :size="26" with-wordmark class="lg:invisible" />
        <ThemeToggle />
      </div>

      <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-10">
        <header class="mb-7">
          <h1 class="font-display text-[24px] font-semibold tracking-[-0.02em] text-ink">
            {{ title }}
          </h1>
          <p v-if="subtitle" class="mt-1.5 text-[13px] leading-relaxed text-ink-muted">
            {{ subtitle }}
          </p>
        </header>

        <slot />
      </div>

      <footer class="text-center text-[11px] text-ink-faint">
        <slot name="footer" />
      </footer>
    </main>
  </div>
</template>
