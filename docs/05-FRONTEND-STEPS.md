# Frontend Implementation Steps — Vue 3

Sequenced to match `04-BACKEND-STEPS.md` phase-for-phase — a frontend phase should never get ahead of the backend endpoints it depends on.

---

## Phase 1 — Auth, Goals, Roadmap builder

- [ ] Vite + Vue 3 + TS scaffold; Tailwind v4 wired via `@theme` tokens (`03-FRONTEND-ARCHITECTURE.md` §8); Pinia; Vue Router; VueUse.
- [ ] `api/client.ts` — axios instance, `withCredentials: true`, CSRF cookie bootstrap (`/sanctum/csrf-cookie`) called before first mutating request.
- [ ] `types/goal.ts`, `types/roadmap.ts` mirroring the Phase 1 backend Resources exactly — cross-check field-by-field against `GoalResource`/`RoadmapItemResource`.
- [ ] `stores/auth.ts` + `LoginView`/`RegisterView` + router guards (`requiresAuth`/`guestOnly`).
- [ ] `stores/goals.ts` (`fetchAll`, `fetchOne`, `create`, `update`, `archive`) + `GoalListView`, `GoalCard`, `GoalForm` (typed props/emits, `defineModel()` for the form's two-way binding where it makes sense).
- [ ] `stores/roadmaps.ts` + `RoadmapBuilderView`.
- [ ] `RoadmapTimelineView` (ordered list) and `RoadmapKanbanView` (status columns) over the **same** store data — build the Timeline view first, confirm the store shape, then add Kanban as a second renderer rather than a second data source.
- [ ] `useDragReorder` composable wrapping `vuedraggable`; wire to `roadmaps` store's `reorder` action with optimistic update + rollback on API failure.
- [ ] `RoadmapItemNode` component — status badge using the centralized Tailwind status color tokens, not inline hex.
- [ ] **Gate**: Vitest component tests for `GoalForm` validation states and `RoadmapItemNode` status-change emit, before Phase 2.

## Phase 2 — Focus timer, Sprint history, Resources

- [ ] `types/sprint.ts` mirroring `SprintResource`.
- [ ] `stores/sprints.ts` — holds the single active sprint (if any) globally, plus paginated history.
- [ ] `useFocusTimer` composable exactly as specified in `03-FRONTEND-ARCHITECTURE.md` §4 — **the wall-clock-deadline approach is not optional/nice-to-have, it's the thing that makes this feature not embarrassing**. Write the drift/refresh test (see testing doc) before considering this composable done.
- [ ] `overtimeSeconds` from `useFocusTimer` + `OvertimeBanner` component — reaching the deadline swaps the countdown for an elapsed-overtime display with a "Stop" action; it must **not** disable, hide, or auto-stop anything (FR-SPR-09).
- [ ] `FocusModeSelector` (pomodoro/countdown/stopwatch) + `FocusTimerWidget`.
- [ ] `PersistentFocusBar` in `AppShell`, rendered outside `<router-view>`, reading `sprints` store — confirm it keeps counting down while navigating between `/goals/:id` and `/analytics`.
- [ ] `SprintHistoryList` + `SprintHistoryFilters` (date range, goal, status) + CSV export button (hits backend export endpoint, triggers browser download — don't try to build the CSV client-side).
- [ ] `useFileUpload` composable + `ResourceUploader` (progress bar via axios `onUploadProgress`) + `ResourceList`, used both under a Goal and under a Roadmap Item.
- [ ] Client-side file validation (extension/size) as a **UX nicety only** — the backend allow-list in `StoreResourceRequest` is the real gate; don't let the frontend check create a false sense of security.
- [ ] `public/sw.js` (push + notificationclick handlers) and `usePushNotifications` composable, exactly as specified in `03-FRONTEND-ARCHITECTURE.md` §4.1.
- [ ] `NotificationPermissionPrompt` in `AppShell` — must state the real delivery caveat (works reliably with browser running in background; delayed if fully quit; iOS needs home-screen install) rather than implying push always works instantly everywhere.
- [ ] `vite-plugin-pwa` configured + `manifest.webmanifest` — required for iOS push to work at all, and generally improves "feels persistent" on desktop/Android too.
- [ ] **Gate**: Vitest tests for `useFocusTimer` (deadline math, survives a simulated "advance system clock" scenario, and the overtime branch specifically — see testing doc) and `FocusTimerWidget` start/pause/resume interactions, before Phase 3.

## Phase 3 — Groups, comparison, analytics, notifications

- [ ] `types/group.ts`, `types/analytics.ts` mirroring backend Resources.
- [ ] `stores/groups.ts` (fetch, invite, join, leaderboard cache) + `GroupListView`, `GroupDetailView`, `InviteModal`, `GroupMemberList`.
- [ ] `GoalVisibilityToggle` on `GoalHeader` (private/group) — wire to the Phase 3 backend `visibility`/`group_id` fields.
- [ ] `stores/analytics.ts` + `AnalyticsView`: `StatCard`, `HeatmapCalendar` (date-fns for day math), `VelocityChart` (chart.js/vue-chartjs), `ProjectionBanner` (renders the backend's `projected_completion_date`, and **explicitly handles `null`** — show "not enough data yet," don't hide the component or show a misleading date).
- [ ] `LeaderboardTable` + `ComparisonChart` in `GroupDetailView`.
- [ ] `ChallengeCard` for Squad Challenges (FR-GRP-04) — depends on backend `challenges` table from Phase 3 backend step.
- [ ] `stores/notifications.ts` + a notification bell/dropdown in `Topbar`; poll or (better, if time allows) a lightweight SSE/WebSocket later — polling every 30–60s is an acceptable v1 approach, don't over-engineer this.
- [ ] Gamification UI (XP bar, badges) — only rendered if `user.settings.gamification_enabled`, per the opt-in requirement; build the toggle in `SettingsView` first so it's testable from day one of this feature.
- [ ] **Gate**: Vitest tests for `LeaderboardTable` sorting/rendering and `ProjectionBanner`'s null-state handling.

## Phase 4 — Mentorship & Rewards

- [ ] `types/mentorship.ts`, `types/reward.ts` mirroring `MentorshipResource`/`RewardResource` exactly; extend `types/roadmap.ts`'s `RoadmapItem` with `assigned_by_mentor`/`assigned_minutes`/`assigned_due_at`.
- [ ] `stores/mentorships.ts` (fetch, request, accept, decline, end) + `MentorshipsView` — two lists (mentors I have, mentees I mentor), not one merged list, since the actions available differ per side.
- [ ] `MentorRequestForm` — the person picker here **must only offer users who share a Group with the current user** (mirroring the backend's FR-MENT-01 constraint) rather than a free-text search, so the UI doesn't imply a capability ("search all users") the backend will 403 on anyway.
- [ ] `MentorList`/`MenteeList` components + a `MentorDashboard` (aggregated streak/progress view across all mentees, per §6.1 in the SRS) — build this after the basic mentor/mentee lists work, not before, since it's a rollup of data those lists already fetch.
- [ ] `AssignRoadmapItemForm` (mentor-only — sets `assigned_minutes`/`assigned_due_at`) rendered in `RoadmapBuilderView` **only** when the viewer has an accepted mentorship with the goal's owner and is not the owner themself; `AssignmentBadge` (read-only) rendered for the owner to see what their mentor set.
- [ ] `stores/rewards.ts` (fetch, offer, request, respond, claim, fulfill, revoke) + `RewardsView` (global "my rewards" across all goals) and the per-goal `RewardsTab` from `03-FRONTEND-ARCHITECTURE.md` §7.
- [ ] `RewardCard` — must render every status distinctly (`requested`/`offered`/`earned`/`claimed`/`fulfilled`/`denied`/`revoked`), not just a generic "reward" chip; the whole point of the state machine is that these mean different things and call for different actions.
- [ ] `RewardOfferForm` (mentor) and `RewardRequestForm` (mentee, the literal "demand a reward" UI) — two different forms, not one form with a role toggle, since the fields and validation differ (an offer needs a linked goal/item up front; a request doesn't have to).
- [ ] `RewardClaimButton` — only enabled when `status === 'earned'`; disabled state should say why ("not yet earned"), not just be greyed out with no explanation.
- [ ] `RewardLedgerTable` — read-only summary of fulfilled monetary rewards per mentee, per FR-RWD-06; explicitly labelled as a record, not a balance ("nothing here can be spent in the app").
- [ ] **Gate**: Vitest tests for the `RewardCard` status-to-UI mapping (every status renders the correct available actions and no others) and the `AssignRoadmapItemForm` visibility rule (owner never sees it; a non-mentor never sees it; only an accepted mentor does).

---

## Cross-cutting, do throughout

- [ ] Every new store action that hits the network has a loading + error state consumed by its view — no silent failures.
- [ ] Every list/table that can be empty has an explicit empty state (not a blank screen) — especially `RoadmapItemNode` lists for a brand-new goal and `LeaderboardTable` for a brand-new group.
- [ ] Accessibility pass per component as it's built (keyboard operability for drag-reorder — provide a non-drag "move up/move down" fallback control; `aria-live` on status changes) — retrofit accessibility is always more expensive than building it in.
- [ ] Confirm TS type drift against backend Resources at the end of each phase, not just at the start — Resources sometimes gain fields during implementation that the frontend types quietly miss.
