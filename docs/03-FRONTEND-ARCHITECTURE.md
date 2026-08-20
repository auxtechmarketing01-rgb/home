# Frontend Architecture — Vue 3 SPA

Companion to `01-SRS.md` and `02-BACKEND-ARCHITECTURE.md`. Pure SPA consuming the REST API (no Inertia — see backend doc §1 for the tradeoff note).

---

## 1. Directory structure

```
src/
  api/
    client.ts              # axios instance, base URL, withCredentials for Sanctum cookies
    auth.ts, goals.ts, roadmaps.ts, sprints.ts, resources.ts,
    groups.ts, analytics.ts, notifications.ts,
    mentorships.ts, rewards.ts, pushSubscriptions.ts
  components/
    ui/                     # Button, Modal, Card, Badge, ProgressRing, Tabs
    goals/                  # GoalCard, GoalForm, GoalStatusBadge, GoalVisibilityToggle
    roadmap/                # RoadmapBuilder, RoadmapItemNode, RoadmapTimelineView,
                             # RoadmapKanbanView, ResourceUploader, ResourceList,
                             # AssignmentBadge, AssignRoadmapItemForm
    focus/                  # FocusTimerWidget, FocusModeSelector, SprintHistoryList,
                             # SprintHistoryFilters, OvertimeBanner
    analytics/              # StatCard, HeatmapCalendar, VelocityChart,
                             # LeaderboardTable, ComparisonChart, ProjectionBanner
    groups/                 # GroupMemberList, InviteModal, ChallengeCard
    mentorship/             # MentorRequestForm, MentorList, MenteeList, MentorDashboard
    rewards/                # RewardCard, RewardOfferForm, RewardRequestForm,
                             # RewardClaimButton, RewardLedgerTable
    layout/                 # AppShell, Sidebar, Topbar, PersistentFocusBar,
                             # NotificationPermissionPrompt
  composables/
    useAuth.ts, useFocusTimer.ts, useRoadmapBuilder.ts, useGoalStats.ts,
    useFileUpload.ts, useCountdown.ts, useStreak.ts, useDragReorder.ts,
    usePushNotifications.ts
  stores/                   # Pinia
    auth.ts, goals.ts, roadmaps.ts, sprints.ts, groups.ts,
    analytics.ts, notifications.ts, mentorships.ts, rewards.ts
  router/
    index.ts, guards.ts
  types/
    goal.ts, roadmap.ts, sprint.ts, group.ts, analytics.ts, api.ts,
    mentorship.ts, reward.ts
  views/
    auth/LoginView.vue, RegisterView.vue
    DashboardView.vue
    goals/GoalListView.vue, GoalDetailView.vue
    roadmap/RoadmapBuilderView.vue
    focus/FocusView.vue
    analytics/AnalyticsView.vue
    groups/GroupListView.vue, GroupDetailView.vue
    mentorship/MentorshipsView.vue
    rewards/RewardsView.vue
    SettingsView.vue
  utils/
    formatDuration.ts, date.ts, colors.ts
public/
  sw.js                     # service worker — push event handler, see §4.1
  manifest.webmanifest       # PWA installability, see §9
```

---

## 2. Type mirroring (backend ↔ frontend contract)

Every Laravel API Resource has a matching TS interface. Example — flag drift between these two whenever either side changes:

```php
// app/Http/Resources/GoalResource.php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'title' => $this->title,
        'description' => $this->description,
        'status' => $this->status,
        'visibility' => $this->visibility,
        'target_start_date' => $this->target_start_date?->toDateString(),
        'target_end_date' => $this->target_end_date?->toDateString(),
        'category' => new CategoryResource($this->whenLoaded('category')),
        'stats' => new GoalStatsResource($this->whenLoaded('stats')),
        'roadmap_item_count' => $this->whenCounted('roadmapItems'),
    ];
}
```

```typescript
// src/types/goal.ts
export type GoalStatus = 'draft' | 'active' | 'paused' | 'completed' | 'abandoned'
export type GoalVisibility = 'private' | 'group'

export interface Goal {
  id: number
  title: string
  description: string | null
  status: GoalStatus
  visibility: GoalVisibility
  target_start_date: string | null   // ISO date string, not Date — parse at the edge
  target_end_date: string | null
  category: Category | null
  stats: GoalStats | null
  roadmap_item_count: number | null
}

export interface GoalStats {
  total_focus_seconds: number
  sessions_count: number
  completion_percentage: number
  current_streak: number
  longest_streak: number
  projected_completion_date: string | null
}
```

Apply the same pattern for `RoadmapItem`, `Sprint`, `Group`, `LeaderboardEntry`. **Rule**: nothing in a Pinia store or component should use an inline/ad-hoc object shape for API data — always the typed interface, imported from `types/`.

`RoadmapItem` (`types/roadmap.ts`) gains three mentor-assignment fields alongside the ones already there, mirroring the `02-BACKEND-ARCHITECTURE.md` §3 migration exactly:

```typescript
export interface RoadmapItem {
  // ...existing fields (id, title, status, estimated_minutes, etc.)
  assigned_by_mentor: { id: number; name: string } | null
  assigned_minutes: number | null   // a mentor's expectation — distinct from estimated_minutes, never conflate these in a component
  assigned_due_at: string | null    // ISO datetime
}
```

New type files:

```typescript
// src/types/notification.ts
// One interface for both transports: this is exactly what
// GET /notifications returns per row, and exactly what arrives over Pusher
// (see §4.2). Never model the live frame as a separate shape.
export interface AppNotification<TPayload = Record<string, unknown>> {
  id: string                 // uuid
  type: string               // short class name, e.g. 'RewardEarnedNotification'
  payload: TPayload          // the notification's own fields
  read_at: string | null     // always null on a freshly broadcast frame
  created_at: string
}
```

```typescript
// src/types/mentorship.ts
export type MentorshipStatus = 'pending' | 'accepted' | 'declined' | 'ended'

export interface Mentorship {
  id: number
  mentor: { id: number; name: string }
  mentee: { id: number; name: string }
  status: MentorshipStatus
  requested_by_user_id: number
  responded_at: string | null
}
```

```typescript
// src/types/reward.ts
export type RewardType = 'monetary' | 'privilege' | 'custom'
export type RewardStatus = 'requested' | 'offered' | 'earned' | 'claimed' | 'fulfilled' | 'denied' | 'revoked'

export interface Reward {
  id: number
  mentorship_id: number
  goal_id: number | null
  roadmap_item_id: number | null
  title: string
  description: string | null
  type: RewardType
  monetary_amount: number | null
  currency_label: string | null
  status: RewardStatus
  requested_by: 'mentor' | 'mentee'
  claimed_at: string | null
  fulfilled_at: string | null
  fulfilled_note: string | null
}
```

---

## 3. Pinia stores

| Store | Holds | Key actions |
|---|---|---|
| `auth` | current user, auth status | `login`, `logout`, `fetchUser` |
| `goals` | `Map<id, Goal>`, list/filter state | `fetchAll`, `fetchOne`, `create`, `update`, `archive` |
| `roadmaps` | items per roadmap, keyed by `roadmap_id` | `fetchItems`, `createItem`, `updateItem`, `reorder` (optimistic, rolls back on failure) |
| `sprints` | **the single active/running sprint** (if any) + history page | `start`, `pause`, `resume`, `complete`, `cancel`, `fetchHistory` |
| `groups` | groups the user belongs to, members, leaderboard cache | `fetchAll`, `invite`, `join`, `fetchLeaderboard` |
| `analytics` | per-goal stats, overview dashboard data | `fetchGoalStats`, `fetchOverview` |
| `notifications` | unread count, list | `fetchAll`, `markRead`, `receiveLive` (prepends a frame arriving over Pusher, §4.2 — must be idempotent by `id`, since a refetch and a live frame can race) |
| `mentorships` | relationships where the user is mentor or mentee, split into two computed lists | `fetchAll`, `request`, `accept`, `decline`, `end` |
| `rewards` | rewards where the user is mentor or mentee, filterable by status | `fetchAll`, `offer`, `request`, `respond`, `claim`, `fulfill`, `revoke` |

The `sprints` store is deliberately global (not scoped to a view) because the timer must keep running while the user navigates elsewhere — see `PersistentFocusBar` in layout, which reads from this store regardless of route.

---

## 4. Key composable: `useFocusTimer`

This is the single most failure-prone piece of a Pomodoro-style feature (confirmed by the research: multiple competing apps explicitly market "survives refresh" as a differentiator). The naive `setInterval` countdown drifts and resets on refresh — and critically, it also **stops existing the moment the tab or browser closes**, because it lives only in that page's JS memory. That's the actual bug behind "even if the website is closed, the pomodoro should still be working": a client-only timer has nothing to "still be running" once its JS context is gone.

The fix is the same one already built into the Phase 1/2 design, just stated explicitly: **the server, not the browser tab, is what's running the sprint.** The `sprints` row's `started_at` + `planned_duration_seconds` (or, for a stopwatch, just `started_at`) is the single source of truth. Closing the browser doesn't pause or stop anything server-side — there's simply nothing for the client to keep computing while it's gone. Reopening the app, on any device, refetches the active sprint and the composable below recomputes correctly, whether 30 seconds or 6 hours have passed. **Overtime** (past the planned duration but not yet stopped) is a first-class state here, not an error — see FR-SPR-09.

```typescript
// src/composables/useFocusTimer.ts
import { ref, computed, onUnmounted } from 'vue'
import { useSprintsStore } from '@/stores/sprints'

export function useFocusTimer() {
  const sprintsStore = useSprintsStore()
  const now = ref(Date.now())
  let tickHandle: number | undefined

  // The deadline is a wall-clock timestamp, not a countdown number.
  // This is what makes it immune to tab-throttling, refresh, AND the
  // browser being closed entirely — on (re)mount, we recompute from
  // (deadline - Date.now()) against the server's started_at, never from
  // an in-memory decrementing counter that would simply cease to exist
  // the moment the tab or browser closed.
  const deadline = computed(() => {
    const active = sprintsStore.activeSprint
    if (!active || !active.planned_duration_seconds) return null
    const startedAt = new Date(active.started_at).getTime()
    return startedAt + active.planned_duration_seconds * 1000
  })

  const remainingSeconds = computed(() => {
    if (!deadline.value) return null
    return Math.max(0, Math.round((deadline.value - now.value) / 1000))
  })

  const isExpired = computed(() => remainingSeconds.value === 0)

  // FR-SPR-09: reaching the deadline does NOT end the session — it enters
  // overtime, and this is how long it's been running past the plan. The
  // sprint stays `running` server-side until the user explicitly stops it;
  // this composable just renders that truth instead of hiding or fighting it.
  const overtimeSeconds = computed(() => {
    if (!deadline.value || !isExpired.value) return 0
    return Math.round((now.value - deadline.value) / 1000)
  })

  function tick() {
    now.value = Date.now()
    tickHandle = requestAnimationFrame(tick)
  }

  function start() {
    now.value = Date.now()
    tickHandle = requestAnimationFrame(tick)
  }

  function stop() {
    if (tickHandle) cancelAnimationFrame(tickHandle)
  }

  onUnmounted(stop)

  return { remainingSeconds, isExpired, overtimeSeconds, start, stop }
}
```

Notes:
- Persisting `active.started_at` + `planned_duration_seconds` **server-side** (already the case — it's on the `sprints` row) means a full page reload, or reopening the app hours later, just needs to re-fetch the active sprint on app bootstrap; there is no need for a fragile client-only `localStorage` deadline as the primary source of truth. `localStorage` may still be used as an optimistic offline fallback, but the server row is authoritative.
- `requestAnimationFrame` (or a 1s `setInterval` recomputing from wall-clock, either is fine) is used only to trigger re-renders, never to compute elapsed time by counting ticks.
- `OvertimeBanner` (in `components/focus/`) renders `overtimeSeconds` once `isExpired` is true, with a "Stop" call to action — it replaces the countdown display, it doesn't hide the widget.

### 4.1 Getting notified when the deadline passes and the app *isn't* open

`useFocusTimer` alone solves "the session keeps running." It does **not** solve "the user finds out it's done if they closed the tab" — nothing in a closed tab can notify anyone; that requires the server pushing a notification, and the browser being willing to display it in the background. That's the Web Push composable:

```typescript
// src/composables/usePushNotifications.ts
import { apiClient } from '@/api/client'

function urlBase64ToUint8Array(base64: string): Uint8Array {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'))
  return Uint8Array.from(raw, (char) => char.charCodeAt(0))
}

export function usePushNotifications() {
  async function isSupported(): Promise<boolean> {
    return 'serviceWorker' in navigator && 'PushManager' in window
  }

  async function subscribe(vapidPublicKey: string): Promise<boolean> {
    if (!(await isSupported())) return false

    const permission = await Notification.requestPermission()
    if (permission !== 'granted') return false

    const registration = await navigator.serviceWorker.register('/sw.js')
    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    })

    // Sent as the raw PushSubscription JSON shape the webpush package
    // expects (endpoint + keys.p256dh + keys.auth) — see 02-BACKEND-
    // ARCHITECTURE.md §9 for the server side of this.
    await apiClient.post('/push-subscriptions', subscription.toJSON())
    return true
  }

  async function unsubscribe(): Promise<void> {
    const registration = await navigator.serviceWorker.getRegistration()
    const subscription = await registration?.pushManager.getSubscription()
    await subscription?.unsubscribe()
    await apiClient.delete('/push-subscriptions')
  }

  return { isSupported, subscribe, unsubscribe }
}
```

```javascript
// public/sw.js — deliberately plain JS, not TypeScript; service workers run
// outside Vite's normal module graph and are simplest kept dependency-free.
self.addEventListener('push', (event) => {
  const data = event.data?.json() ?? {}
  event.waitUntil(
    self.registration.showNotification(data.title ?? 'Pathforge', {
      body: data.body ?? '',
      icon: '/icons/notification-192.png',
      data: { url: data.url ?? '/' },
    }),
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  event.waitUntil(self.clients.openWindow(event.notification.data?.url ?? '/'))
})
```

### 4.2 Getting notified when the app *is* open: `useRealtimeNotifications`

§4.1 covers the closed-tab case. This covers the open-tab case, and the two are complements, not alternatives — Web Push cannot update a page (it can only raise an OS notification), and Pusher cannot reach a page that no longer exists. Implements FR-NOT-03; backend contract in `02-BACKEND-ARCHITECTURE.md` §10.

Three rules that keep this from becoming a second, drifting source of truth:

1. **A live frame is the same shape as a fetched row.** Both are `AppNotification` (§2). There is no separate "live event" type.
2. **The socket is a latency optimisation, never the source of truth.** The store is still populated by `fetchAll()` on mount. A dropped connection costs freshness, not data.
3. **`receiveLive` is idempotent by `id`.** A refetch and a live frame for the same notification will race; the store must not end up with two rows.

```typescript
// src/echo.ts — one Echo instance for the app, created after auth is known
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

export function createEcho() {
  return new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    // The SPA is a separate origin, so channel authorization goes through
    // the versioned API with the Sanctum session cookie attached — not
    // through Echo's default /broadcasting/auth on the web group.
    authEndpoint: `${import.meta.env.VITE_API_BASE_URL}/broadcasting/auth`,
    withCredentials: true,
  })
}
```

```typescript
// src/composables/useRealtimeNotifications.ts
import { onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import type { AppNotification } from '@/types/notification'
import { createEcho } from '@/echo'

export function useRealtimeNotifications() {
  const auth = useAuthStore()
  const notifications = useNotificationsStore()
  let echo: ReturnType<typeof createEcho> | null = null

  onMounted(() => {
    if (!auth.user) return

    echo = createEcho()

    // Laravel's own broadcast notification channel targets
    // `App.Models.User.{id}`; Echo's `.notification()` binds to the
    // framework's BroadcastNotificationCreated event for us.
    echo.private(`App.Models.User.${auth.user.id}`)
      .notification((frame: AppNotification) => {
        notifications.receiveLive(frame)
      })
  })

  onUnmounted(() => {
    echo?.disconnect()
    echo = null
  })

  return {}
}
```

Mount this once in `AppShell`, next to `NotificationPermissionPrompt` — subscribing per-route would tear down and re-establish the socket on every navigation.

**Only the Pusher *key* and *cluster* belong in the SPA.** They are public by design; the app secret is a server credential and must never appear in a `VITE_`-prefixed variable, because everything with that prefix is compiled into the shipped bundle.

**Degradation is a UI concern, not just a network one.** If the socket never connects, the app must stay fully usable on fetched data — do not gate rendering on a connection, and do not show a scary error for a member who simply has a flaky network. A subtle "reconnecting" affordance in `Topbar` is the right weight.

---

**Be honest with the user about what this can and can't guarantee** (`NotificationPermissionPrompt` in layout should say this plainly, not just ask for permission): it reaches them with the tab and window both closed, as long as their browser is still running in the background — true by default on desktop Chrome/Firefox/Edge, and reliable on Android regardless of browser state via the OS. If the browser itself has been fully quit, the notification is queued and arrives once it's reopened — delayed, not lost. On iOS, this only works at all if the app has been installed to the home screen (iOS 16.4+); a plain Safari tab cannot receive push. This is a platform limitation, not something more client code can work around — see `01-SRS.md`'s NFR on push delivery for the full caveat.

---

## 5. Other composables (brief)

| Composable | Responsibility |
|---|---|
| `useRoadmapBuilder` | Local reactive state for the drag-reorder list before committing to the store's batched `reorder` action; debounces the network call |
| `useGoalStats` | Thin wrapper resolving `analytics` store data for one goal, with loading/error state |
| `useFileUpload` | Wraps `FormData` construction + upload progress for `ResourceUploader` |
| `useStreak` | Formats streak state (current/longest, "at risk today" flag) for display in multiple places without duplicating date math |
| `useDragReorder` | Thin wrapper around `vuedraggable` emitting a normalized `{ id, newPosition }[]` diff |

---

## 6. Routing

```typescript
// src/router/index.ts (routes only, guards in guards.ts)
const routes = [
  { path: '/login', component: LoginView, meta: { guestOnly: true } },
  { path: '/register', component: RegisterView, meta: { guestOnly: true } },
  { path: '/', component: DashboardView, meta: { requiresAuth: true } },
  { path: '/goals', component: GoalListView, meta: { requiresAuth: true } },
  { path: '/goals/:id', component: GoalDetailView, meta: { requiresAuth: true } },
  { path: '/goals/:id/roadmap', component: RoadmapBuilderView, meta: { requiresAuth: true } },
  { path: '/focus', component: FocusView, meta: { requiresAuth: true } },
  { path: '/analytics', component: AnalyticsView, meta: { requiresAuth: true } },
  { path: '/groups', component: GroupListView, meta: { requiresAuth: true } },
  { path: '/groups/:id', component: GroupDetailView, meta: { requiresAuth: true } },
  { path: '/mentorships', component: MentorshipsView, meta: { requiresAuth: true } },
  { path: '/rewards', component: RewardsView, meta: { requiresAuth: true } },
  { path: '/settings', component: SettingsView, meta: { requiresAuth: true } },
]
```

`AppShell` renders `PersistentFocusBar` outside `<router-view>` so an active Sprint stays visible/controllable across route changes. It also mounts `NotificationPermissionPrompt` once, near app bootstrap — not per-route — since asking for push permission is a one-time app-level concern, not something to re-ask on every goal page.

---

## 7. Component tree (Goal detail — representative example)

```
GoalDetailView
├─ GoalHeader (title, status badge, visibility toggle, edit/archive menu)
├─ Tabs: Roadmap | Focus | Resources | Analytics | Rewards
│  ├─ RoadmapTab
│  │  ├─ ViewSwitcher (Timeline / Kanban)
│  │  ├─ RoadmapTimelineView → RoadmapItemNode[] (drag handle via useDragReorder)
│  │  │    └─ AssignmentBadge (shown only if assigned_by_mentor is set — mentor's time/due date, distinct from the mentee's own estimate)
│  │  └─ RoadmapKanbanView → columns (todo/in_progress/done/skipped) → RoadmapItemNode[]
│  ├─ FocusTab
│  │  ├─ FocusModeSelector (pomodoro/countdown/stopwatch)
│  │  ├─ FocusTimerWidget (uses useFocusTimer) + OvertimeBanner (once isExpired)
│  │  └─ SprintHistoryList + SprintHistoryFilters
│  ├─ ResourcesTab → ResourceUploader + ResourceList (per goal, and drilled into per roadmap item)
│  ├─ AnalyticsTab → StatCard[] + HeatmapCalendar + VelocityChart + ProjectionBanner
│  └─ RewardsTab → RewardCard[] scoped to this goal (offered/earned/claimed/fulfilled) + RewardRequestForm ("demand" one, FR-RWD-03)
```

A mentor viewing a mentee's goal (via `GoalPolicy::view` through an accepted mentorship, not ownership) sees the same `GoalDetailView` but with mutation controls swapped: `AssignRoadmapItemForm` instead of edit controls on `RoadmapItemNode`, and `RewardOfferForm` instead of `RewardRequestForm` on the Rewards tab. This is a permissions-driven render difference within one view, not two separate views — keeps the component tree from doubling.

No business logic lives in these `.vue` files beyond template glue — all data fetching/mutation goes through the stores, all timer math through `useFocusTimer`, all reorder math through `useDragReorder`.

---

## 8. Design tokens (Tailwind v4)

Defined via CSS variables per the Tailwind v4 approach (`@theme` block), not a `tailwind.config.js` theme object:

```css
/* src/assets/theme.css */
@theme {
  --color-brand-50: #eef4ff;
  --color-brand-500: #3b6df0;
  --color-brand-700: #223f9e;
  --color-status-todo: #94a3b8;
  --color-status-in-progress: #f59e0b;
  --color-status-done: #22c55e;
  --color-status-skipped: #94a3b8;
  --font-display: "Sora", "Inter", sans-serif;
}
```

Status colors are centralized here so `RoadmapItemNode`, `RoadmapKanbanView` columns, and `HeatmapCalendar` all reference the same tokens instead of each hardcoding hex values — a common source of visual drift the code review posture should catch.

---

## 9. Suggested packages (beyond your listed stack)

| Package | One-line justification |
|---|---|
| `vuedraggable` (Vue 3 SortableJS wrapper) | Drag-and-drop roadmap reordering (FR-RM-05) is core UX; hand-rolling pointer-based drag physics/auto-scroll is not worth it versus a mature, small wrapper. |
| `chart.js` + `vue-chartjs` | Velocity charts, heatmap-style calendars, and leaderboard bar comparisons (FR-ANL-01/03/04) — lightweight and well-documented for exactly these chart types; avoids a heavier charting framework for what's a fairly standard set of visualizations. |
| `date-fns` | Roadmap day-scheduling and streak/heatmap date math need reliable, tree-shakeable date arithmetic; lighter than Moment and avoids re-implementing timezone-aware day-boundary logic by hand on the frontend (the server remains authoritative for streak *computation*, but the UI still needs local date formatting). |
| `laravel-echo` + `pusher-js` | The client half of FR-NOT-03 (§4.2). `laravel-echo` is what knows how to bind to Laravel's `BroadcastNotificationCreated` event and how to authorize a private channel; `pusher-js` is the transport it drives. Hand-rolling a WebSocket client against the Pusher protocol would mean reimplementing channel auth, reconnection and backoff for no gain. |
| `vite-plugin-pwa` | Generates the web app manifest and handles service-worker registration/updates for "Add to Home Screen" installability (§6.1 in `01-SRS.md`) — on iOS this isn't optional flourish, it's the *only* way push notifications work at all, so treat this as bundled with the push-notification feature rather than a separate nice-to-have. Keep the custom `push`/`notificationclick` handlers in `public/sw.js` (§4.1) — this plugin manages caching/installability, not the push logic itself. |
