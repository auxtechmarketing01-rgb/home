# Testing Strategy — Pest (backend) + Vitest/Vue Testing Library (frontend)

Testing is gated per phase in `04-BACKEND-STEPS.md` / `05-FRONTEND-STEPS.md` — don't start a new phase with the previous phase's gate still red.

---

## 1. Backend — Pest

### 1.1 Setup
- `RefreshDatabase` trait (or a dedicated in-memory SQLite connection for speed — MySQL-specific SQL (e.g. certain JSON functions) should still get at least one MySQL-backed CI run to catch dialect drift).
- Model factories for every model in `02-BACKEND-ARCHITECTURE.md` §3, including realistic states (`Sprint::factory()->completed()`, `Goal::factory()->groupVisible()`).
- Auth via `Sanctum::actingAs($user)` in every authenticated feature test — never fake auth by bypassing the guard.

### 1.2 Feature test matrix

| Area | Test cases |
|---|---|
| **Auth** | register (valid/invalid payload), login (correct/incorrect credentials, rate-limit after N attempts), logout invalidates session, `/user` returns 401 when unauthenticated |
| **Goals** | create (validated fields, owner auto-set), list (only own goals + group-visible goals from own groups), update (owner-only — 403 for non-owner), delete/archive (soft delete, doesn't hard-remove), `complete` endpoint sets `completed_at` |
| **Roadmap Items** | create under owned roadmap, reject create under someone else's roadmap (403), reorder (batch update persists correct `position` values, rejects IDs from a different roadmap), status transition logged to `activity_logs`, nested item (`parent_id`) creation |
| **Resources** | upload valid file type/size (201, correct disk/path stored), reject oversized file (422), reject disallowed MIME even with a spoofed extension (422 — test with a renamed `.exe` given a `.pdf` extension), delete by owner only |
| **Sprints** | start (201, `running` status), **start a second sprint while one is already running/paused → 409**, pause/resume adjusts `paused_seconds_total` correctly, complete computes `actual_duration_seconds` excluding paused time, complete dispatches `RecalculateGoalStatsJob` (assert job pushed, don't require the queue to actually run in this test — separate unit test for the job's math), cancel doesn't roll up time, export returns valid CSV with expected columns, **a sprint running well past its `planned_duration_seconds` is still `running`, not auto-cancelled** (FR-SPR-09 — see the dedicated `CleanupStaleSprintsJob` unit test in §1.3, this feature-level test just confirms the API still reports it as active) |
| **Groups** | create + invite-code generated, join via valid code (member added) / invalid code (422), non-member `GET /groups/{group}` → 403, owner-only invite/remove-member actions, leaderboard only includes group-visible goal data — **explicitly assert a private goal's data never appears in another member's leaderboard response** |
| **Mentorships** | request requires a shared Group (422/403 without one), request is `pending` until the target responds, **only the non-initiating party can accept/decline** (the requester accepting their own request → 403), accepted mentorship grants the mentor `GoalPolicy::view` on the mentee's private goals, `end` is available to either party, ending doesn't retroactively revoke already-`fulfilled` rewards |
| **Rewards (state machine)** | for *each* transition in the state diagram (`02-BACKEND-ARCHITECTURE.md` §3): a dedicated test that the correct actor can trigger it and every other actor gets 403 — specifically: mentor `offer`s (mentee attempting this → 403), mentee `request`s (mentor attempting this → 403), mentor `respond`s to a request (mentee attempting → 403), the linked roadmap item being marked done flips `offered`→`earned` automatically with **no HTTP request involved** (assert via the job, not an endpoint), mentee `claim`s an `earned` reward (claiming a merely-`offered` one → 422, wrong state), mentor `fulfill`s a `claimed` reward (fulfilling a non-`claimed` one → 422), mentor `revoke`s an `offered` (not yet `earned`) reward (revoking an already-`earned` one → 422, since that would let a mentor renege after the fact) |
| **Push subscriptions** | store persists the subscription tied to the authenticated user (not guessable/spoofable to another user's account), destroy removes it, a malformed subscription payload (missing `endpoint`/`keys`) → 422 |
| **Broadcast channel authorization** | **Treat this as a Policy test, not a plumbing test** — a member who can subscribe to another member's private channel receives their notifications live regardless of what any Policy says. `POST /broadcasting/auth` for the acting member's own `App.Models.User.{id}` → 200 with an `auth` signature; for *another* member's channel → 403; unauthenticated → 401. Also assert the endpoint is mounted under `api/v1` (the framework default puts it on the `web` group, which the separate-origin SPA cannot use). **Two traps**: the suite runs on the `null` broadcaster, which never invokes channel callbacks at all — a test left on it asserts nothing, so switch to `pusher` with dummy credentials (HMAC only, no network). And `Broadcast::channel()` registers against the *current broadcaster instance*, so after switching the driver you must re-require `routes/channels.php` or the new instance has zero channels and rejects everything, looking exactly like a broken rule. Add a row per channel as later phases introduce them (`groups.{group}` non-member → 403; `mentorships.{mentorship}` third party → 403, and an `ended` mentorship → 403 per FR-MENT-07) |
| **Notification centre** | index returns only the acting member's rows and an `unread_count`; `unread=1` filters; `markRead` sets `read_at`; another member's notification id → **404, not 403** (02 §4 marks these endpoints self-scoped, so the row is simply not in the query — keep this distinct from the policy-guarded routes' 403); the durable row is written even though `broadcast` is in the same `via()` list, since a member must never depend on having had a tab open |
| **Analytics** | goal stats reflect actual rolled-up sprint time (integration through the real job, not mocked, for at least one end-to-end test), projection returns `null` with insufficient data rather than a fabricated date |
| **Policies (cross-cutting)** | for every model with a Policy, a dedicated test asserting the 403 boundary for a non-owner/non-member — this is the single most important test category for a family app where privacy mistakes are the worst-case bug. For `RoadmapItemPolicy` specifically: **assert that having the `assign` ability does not also grant `update`** — a mentor calling the update-title endpoint on a mentee's item must still get 403 even though they can successfully call `assign` on the same item (FR-MENT-06) |

### 1.3 Unit tests
- `StreakService`: streak increments on consecutive qualifying days, resets after a gap, respects `users.timezone` (test with two users in different timezones hitting the boundary at different UTC instants).
- `ProjectionService`: correct math for a known input set; returns `null` below the minimum data-point threshold; doesn't divide by zero when average daily focus is 0.
- `RecalculateGoalStatsJob`: given a fixture of completed sprints, asserts the resulting `roadmap_items.time_spent_seconds` and `goal_stats.total_focus_seconds`/`completion_percentage` are exactly correct — this is the core "does the rollup actually work" test and deserves several fixture variations (single sprint, multiple sprints on one item, sprints across multiple items). Also asserts it calls `MarkRewardsEarnedForItemAction` when the changed item/goal is now done, and that an `offered` reward on that item flips to `earned`.
- `CleanupStaleSprintsJob`: **the test that most directly protects FR-SPR-09.** A sprint started 90 minutes ago with a 25-minute planned duration is *not* touched. A sprint started 25 hours ago (past the default 24h grace) *is* auto-cancelled. Get the boundary exactly right — this job being too aggressive is the single easiest way to silently reintroduce "the pomodoro stops when you close the tab," which is the bug this whole feature exists to prevent.
- `NotifyExpiredSprintsJob`: a sprint whose deadline just passed and has `notified_expired_at = null` triggers exactly one push notification and sets the timestamp; running the job again immediately after does **not** send a second notification for the same sprint (dedup test) — and a sprint that's already `completed`/`cancelled` before its deadline is never notified at all.
- `MarkRewardsEarnedForItemAction`: an `offered` reward tied to the completed item flips to `earned`; an `offered` reward tied to a *different*, still-incomplete item is untouched; a `requested` (not yet `offered`) reward is untouched even if its linked item is done, since nothing was promised yet.
- `MemberNotification` (the abstract base, 02 §10.2): `via()` is `['database', 'broadcast']` by default and gains `WebPushChannel` **only** for a subclass that opts in — assert both directions, because a notification silently losing its `broadcast` channel is invisible until someone complains the UI feels stale. The broadcast frame's `payload` equals `toArray()` exactly (a live frame and a refetched row must never disagree), the payload is nested rather than flat (Laravel merges `id`/`type` into the frame, and a flat payload lets a notification's own field shadow one of them), and `broadcastType()` is the short class name so the wire format never carries a PHP FQCN. Finally, assert the channel a notifiable resolves to is `private-App.Models.User.{id}` — if that drifts from `routes/channels.php`, notifications either stop arriving or start arriving on a channel nobody guards.
- Actions (`CreateGoalAction`, `StartSprintAction`, `ReorderRoadmapItemsAction`, `RequestMentorshipAction`, `ClaimRewardAction`) tested directly, not only through the HTTP layer, for their transactional/validation edge cases.

### 1.4 Example (illustrative shape, not exhaustive)

```php
// tests/Feature/Sprints/StartSprintTest.php
it('rejects starting a second sprint while one is already running', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    Sprint::factory()->for($user)->running()->create();

    $response = $this->postJson('/api/v1/sprints/start', [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
    ]);

    $response->assertStatus(409);
});
```

```php
// tests/Unit/Services/ProjectionServiceTest.php
it('returns null when there are fewer than the minimum data points', function () {
    $goal = Goal::factory()->create();

    $result = app(ProjectionService::class)->projectCompletionDate($goal);

    expect($result)->toBeNull();
});
```

---

## 2. Frontend — Vitest + Vue Testing Library

### 2.1 Setup
- `msw` (Mock Service Worker) to intercept API calls in component/store tests rather than hand-mocking axios — keeps tests closer to real request/response shapes and catches contract drift.
- Pinia's `createTestingPinia` for store isolation in component tests; real store instances for store-level unit tests.

### 2.2 Test matrix

| Area | Test cases |
|---|---|
| `useFocusTimer` | remaining time computed correctly from a mocked `started_at` + `planned_duration_seconds`; **simulated clock jump** (advance `Date.now()` mock by more than the planned duration in one step, e.g. simulating a laptop sleep) still resolves to `remainingSeconds === 0` / `isExpired === true` rather than a negative or NaN value; pause/resume math; **`overtimeSeconds` increases correctly once past the deadline and the composable never auto-stops** — this is the client-side half of FR-SPR-09, and the easiest place to accidentally add a "helpful" auto-stop that undoes the whole feature |
| `FocusTimerWidget` / `OvertimeBanner` | renders countdown text; start/pause/resume buttons call the right store actions; disabled state while a network request is in flight; once `isExpired` is true, `OvertimeBanner` renders instead of the countdown and exposes a "Stop" action, without the widget disappearing or the sprint being touched automatically |
| `usePushNotifications` | `subscribe()` returns `false` gracefully when `serviceWorker`/`PushManager` aren't present (feature detection, not a thrown error); a granted permission results in exactly one `POST /push-subscriptions` call with the subscription JSON; a denied permission never calls the endpoint |
| `useRealtimeNotifications` | with a mocked Echo: subscribes to `App.Models.User.{id}` for the **authenticated** user's id and no one else's; an incoming frame reaches the store's `receiveLive`; `onUnmounted` disconnects (a leaked socket per navigation is the failure mode of mounting this per-route instead of once in `AppShell`); nothing subscribes at all when there is no authenticated user. Also assert the app renders normally when the socket never connects — the store is fed by `fetchAll()`, so a dead connection must cost freshness, not data |
| `notifications` store `receiveLive` | a live frame prepends and bumps the unread count; **the same notification `id` arriving twice, or arriving both from a refetch and from the socket, does not duplicate the row** — this race happens in practice on a slow first load, and a duplicated bell count is the visible symptom |
| `GoalForm` | required-field validation surfaces inline errors; submit emits the correct payload shape matching `types/goal.ts` |
| `RoadmapItemNode` | status change emits the correct event; drag handle present only when the parent view is in reorder mode |
| `useDragReorder` | given a reordered array, emits the correct `{ id, newPosition }[]` diff |
| `LeaderboardTable` | renders rows sorted by the selected metric; handles an empty group (no members with activity yet) with an explicit empty state, not a blank table |
| `ProjectionBanner` | renders the "not enough data yet" state when `projected_completion_date` is `null`; renders a formatted date otherwise |
| `ResourceUploader` | rejects an oversized file client-side with a clear message; shows upload progress; surfaces a server-side 422 (e.g., disallowed MIME) as a visible error, not a silent failure |
| `MentorRequestForm` | the person picker only lists users returned by the API (already filtered to shared-Group members server-side) — the component doesn't need its own filtering logic, and a test asserting it does no client-side "who can I request" filtering guards against that logic creeping in and drifting from the backend rule |
| `RewardCard` | for each of the seven statuses, renders the correct label and the correct (and only the correct) action buttons — e.g. a `requested` reward shows accept/deny to a mentor and nothing to a mentee beyond "waiting," an `earned` reward shows "claim" to the mentee and nothing actionable to the mentor |
| `RewardClaimButton` | disabled with an explanatory label when `status !== 'earned'`; calls the `rewards` store's `claim` action when enabled and clicked |
| `AssignRoadmapItemForm` visibility | not rendered for the goal's owner; not rendered for a logged-in user with no mentorship to the owner; rendered for an accepted mentor |
| Pinia stores (`goals`, `sprints`, `roadmaps`, `mentorships`, `rewards`) | actions call the right API module function; optimistic `reorder` rolls back store state on a mocked API failure; `rewards` store correctly moves an item between status buckets after each action (e.g. `claim` moves it from the "earned" list to the "claimed" list without a full refetch) |

### 2.3 Example (illustrative shape)

```typescript
// src/composables/useFocusTimer.spec.ts
import { describe, it, expect, vi } from 'vitest'
import { useFocusTimer } from './useFocusTimer'

describe('useFocusTimer', () => {
  it('resolves to expired, not negative, after a simulated clock jump', () => {
    const startedAt = new Date('2026-01-01T10:00:00Z')
    vi.setSystemTime(startedAt)

    // ...mock the sprints store to return an active sprint with
    // started_at = startedAt, planned_duration_seconds = 1500

    const { remainingSeconds, isExpired, start } = useFocusTimer()
    start()

    // simulate the tab being suspended for longer than the planned duration
    vi.setSystemTime(new Date('2026-01-01T10:45:00Z'))

    expect(remainingSeconds.value).toBe(0)
    expect(isExpired.value).toBe(true)
  })
})
```

---

## 3. End-to-end (recommended addition, not in your original stack)

Your stated stack covers unit/feature/component testing but not browser-level E2E. **Suggested addition**: `Playwright` — one-line justification: a Pomodoro-timer-plus-drag-and-drop-roadmap app has enough cross-component, real-browser-timing behavior (the exact thing `useFocusTimer` is designed around) that at least a handful of true end-to-end smoke tests are worth the setup cost, beyond what component tests alone can verify.

Minimum smoke suite:
1. Register → log in → create a goal → add three roadmap items → reorder them → confirm persisted order after reload.
2. Start a Pomodoro sprint linked to a roadmap item → complete it → confirm the roadmap item's time and the goal's dashboard both reflect it (this exercises the queued job end-to-end, so run it against a real queue worker in CI, not `sync` faked away).
3. Create a group → invite a second test user → confirm the second user sees the first's group-visible goal in the leaderboard and does **not** see a private goal.
4. Start a sprint, artificially advance the test clock past its planned duration (Playwright supports clock mocking), confirm the UI shows the overtime state rather than auto-stopping, then manually complete it — end-to-end proof of FR-SPR-09 rather than just the unit-level pieces.
5. User A requests User B as a mentor (both in the same test-seeded Group) → User B accepts → User A offers a reward tied to one of User B's roadmap items → mark that item done → confirm the reward shows as `earned` for User B without either user refreshing anything that would mask a missed real-time update (this is FR-NOT-03 doing the work — the `offered` → `earned` flip is produced by a queued job, so there is nothing on User B's screen to trigger a refetch; if this step only passes after a manual reload, the Pusher path is broken even though every unit test is green) → User B claims it → User A fulfills it. This is the one flow that exercises every layer added in this revision (mentorship, the reward state machine, and the `RecalculateGoalStatsJob` → `MarkRewardsEarnedForItemAction` link) in a single pass.

If Playwright is out of scope for now, treat gate #2 above (Sprint completion → stats update, run against a real worker at least once) and gate #5 (the mentor/reward chain) as the minimum non-negotiable integration checks, done as Pest tests with `Queue::fake()` **removed** for those specific tests.

---

## 4. CI

- GitHub Actions, two parallel jobs:
  - **backend**: PHP 8.3, MySQL service container, `composer install`, run migrations, `php artisan test` (Pest), fail build on any red test or a coverage regression below an agreed threshold. `BROADCAST_CONNECTION=null` for the run, so no test reaches Pusher — the two places that genuinely need the driver configure it themselves with dummy credentials (§1.2 Broadcast channel authorization). No `PUSHER_*` secret belongs in CI for the test job.
  - **frontend**: Node 20, `npm ci`, `npm run test` (Vitest), `npm run build` (catches TS errors that only surface at build time, not just in tests).
- Both jobs must pass before merge; the phase gates above are enforced by convention (PR checklist), CI enforces the tests actually existing and passing.
