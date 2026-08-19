# Backend Implementation Steps — Laravel

Sequenced against `02-BACKEND-ARCHITECTURE.md`. Three phases, each shippable/demoable on its own — do not start Phase 2 with Phase 1 untested (see `06-TESTING-STRATEGY.md` for the gate).

---

## Phase 1 — Core loop: Auth, Goals, Roadmap CRUD

**Goal of this phase**: a user can register, log in, create a Goal, and build/edit a Roadmap with statusable items. No timer yet, no social features yet.

- [ ] Laravel 12 project scaffold, MySQL connection, Sanctum installed and configured for SPA (stateful domains, CORS with credentials).
- [ ] Migrations: `users` (extend default), `categories`, `goals`, `roadmaps`, `roadmap_items`.
- [ ] Models + relationships (`Goal::roadmap()`, `Roadmap::items()`, `RoadmapItem::parent()/children()`) with explicit `$fillable`.
- [ ] `GoalPolicy`, `RoadmapItemPolicy` (owner-only for this phase — group visibility comes in Phase 3).
- [ ] `AuthController` (register/login/logout/me) + `StoreGoalRequest`/`UpdateGoalRequest`.
- [ ] `Actions\Goals\CreateGoalAction` — creates the Goal **and** its empty Roadmap in one DB transaction.
- [ ] `GoalController` (index/show/store/update/destroy) — thin, delegates to Action, returns `GoalResource`/`GoalCollection`.
- [ ] `RoadmapItemController` (index/store/update/destroy) + `Actions\Roadmaps\CreateRoadmapItemAction`.
- [ ] Reorder endpoint: `Actions\Roadmaps\ReorderRoadmapItemsAction` — single transaction, bulk `position` update, validated (all IDs belong to the same roadmap, no gaps/duplicates required but no cross-roadmap leakage).
- [ ] Eager-load roadmap item counts on goal index (`withCount('roadmapItems')`) — no N+1.
- [ ] Seeders: default categories, a demo user with a sample goal+roadmap for local dev.
- [ ] **Gate**: Pest feature tests green for Auth + Goals + RoadmapItems (see testing doc §Phase 1) before moving on.

## Phase 2 — Focus Sprints, time rollup, resources

**Goal of this phase**: a user can run a timed Sprint against a Roadmap Item, see time roll up, and attach files/links/notes.

- [ ] Migration: `sprints`.
- [ ] `SprintPolicy` (owner-only, always).
- [ ] `Actions\Sprints\StartSprintAction` — **enforces FR-SPR-08** (reject if the user already has a `running`/`paused` sprint; return 409, not 500).
- [ ] `Actions\Sprints\CompleteSprintAction` — sets `ended_at`/`actual_duration_seconds`, dispatches `RecalculateGoalStatsJob` (queued, not inline).
- [ ] `Actions\Sprints\CancelSprintAction`.
- [ ] Pause/resume logic: track `paused_seconds_total`; `actual_duration_seconds` excludes paused time.
- [ ] `SprintController` (start/pause/resume/complete/cancel/index/export).
- [ ] `league/csv`-based export endpoint (or hand-rolled if you decide to skip the dependency — see backend architecture doc §9).
- [ ] Migration + model: `resource_files` (or wire up `spatie/laravel-medialibrary` — **decide once**, don't half-implement both).
- [ ] `ResourceFilePolicy` delegating to parent Goal/RoadmapItem policy.
- [ ] `StoreResourceRequest`: MIME allow-list, size limit, `finfo` content-type sniff (not just extension trust).
- [ ] `ResourceController` (index/store/destroy) scoped under both `/goals/{goal}/resources` and `/roadmap-items/{item}/resources`.
- [ ] Migration: `goal_stats`. `Services\StreakService` (timezone-aware day boundary using `users.timezone`). `Services\ProjectionService` (remaining estimated minutes ÷ trailing-N-day average daily focus minutes → projected date; return `null` if fewer than a minimum number of data points exist — **don't fabricate a projection from one data point**).
- [ ] `Jobs\RecalculateGoalStatsJob` — `ShouldBeUnique` keyed by `goal_id`; updates `roadmap_items.time_spent_seconds`, `goal_stats`, streak state.
- [ ] **Don't auto-cancel on reaching planned duration.** `Jobs\CleanupStaleSprintsJob` scheduled hourly, but with a long (default 24h) grace period — it's a crash-recovery net, not a way to end a session someone is intentionally still running past its plan (FR-SPR-09). Write the test for "a sprint running 3 hours past its 25-minute plan is still `running`, not auto-cancelled" before considering this job done — this is the single easiest place to accidentally reintroduce the exact bug the "closed website" requirement was asking to avoid.
- [ ] Add `sprints.notified_expired_at` column (migration) and `Jobs\NotifyExpiredSprintsJob` scheduled every minute — finds sprints whose deadline just passed and haven't been notified, dispatches a push notification, sets the timestamp so it never re-fires. This job does two things and no more: send one notification, mark it sent — it must not touch `status`.
- [ ] Install `laravel-notification-channels/webpush`, run its migration for `push_subscriptions`, generate VAPID keys (`php artisan webpush:vapid`, store in `.env`).
- [ ] `PushSubscriptionController` (store/destroy) + `Notifications\SprintCompleteNotification` implementing the `webpush` channel.
- [ ] Horizon installed and configured for the queue connection actually used in deploy (Redis).
- [ ] **Gate**: Pest tests for Sprint lifecycle (including the "can't start two sprints" 409 case, and the "no auto-cancel while merely overtime" case) and the rollup math in `RecalculateGoalStatsJob` before Phase 3.

## Phase 3 — Groups, comparison, analytics, gamification, notifications

**Goal of this phase**: multi-user value — the actual "compare with others" requirement.

- [ ] Migrations: `groups`, `group_members`.
- [ ] `GroupPolicy` (member = view, owner = manage).
- [ ] `Actions\Groups\InviteToGroupAction` / `JoinGroupAction` (invite-code based).
- [ ] Extend `goals` migration/model: add `group_id`, update `visibility` enum; **update `GoalPolicy::view`** to check group membership — this is the point where an existing policy gets a real second branch, not a rewrite.
- [ ] `Services\LeaderboardService` — Redis-cached (`leaderboard:{group_id}:{period}`, TTL 5 min), explicitly invalidated from `RecalculateGoalStatsJob` for any member of an affected group.
- [ ] `AnalyticsController@leaderboard`, `@goalStats`, `@overview`.
- [ ] Squad Challenge (FR-GRP-04): minimal viable version — a challenge is just two+ users' goals grouped under a shared comparison view; **decide before building** whether this needs its own table (`challenges`) or can be derived from existing `group_id` + matching goal titles/categories — recommend a dedicated lightweight `challenges` + `challenge_participants` pivot for clarity, since "derive from matching titles" is fragile.
- [ ] Migrations: `badges`, `user_badges` (opt-in per FR-GAM-02/03 — gate behind `users.settings->gamification_enabled`).
- [ ] `Jobs\DailyStreakCheckJob` scheduled, fans out per-user respecting `timezone`.
- [ ] Notifications: `GroupInviteNotification`, `StreakAtRiskNotification`, `ChallengeUpdateNotification` — queued, using Laravel's built-in notifications table.
- [ ] `NotificationController` (index/markRead).
- [ ] Basic admin routes (FR-ADM-01) behind a `role`/`is_admin` gate — deliberately minimal.
- [ ] **Gate**: Pest tests for policy branches (private vs. group visibility, non-member access returns 403 not 404-leaking-existence — decide and be consistent), leaderboard correctness, and cache invalidation.

---

## Phase 4 — Mentorship & Rewards

**Goal of this phase**: any user can be requested as a mentor by someone they share a Group with; mentors can offer rewards tied to roadmap progress and set time expectations; mentees can demand rewards, mentors approve/deny/fulfill them.

- [ ] Migration: `mentorships` (mentor_id, mentee_id, requested_by_user_id, status, responded_at) with a unique `(mentor_id, mentee_id)` constraint.
- [ ] `MentorshipPolicy` — `create` checked in the Action (not just validated as input) against shared-Group membership; `respond` restricted to the non-initiating party.
- [ ] `Actions\Mentorships\RequestMentorshipAction`, `RespondToMentorshipAction`, `EndMentorshipAction`.
- [ ] `MentorshipController` (index/store/accept/decline/end) + `MentorshipResource`.
- [ ] Migration: add `assigned_by_mentor_id`, `assigned_minutes`, `assigned_due_at` to `roadmap_items`.
- [ ] New `assign` ability on `RoadmapItemPolicy` — **deliberately separate from `update`** so it can never accidentally grant edit rights over the mentee's own content (FR-MENT-06). Write the authorization test for this boundary before writing the happy-path test; it's the one most likely to be gotten wrong under time pressure.
- [ ] `Actions\Roadmaps\AssignRoadmapItemAction` + `AssignRoadmapItemRequest` + `RoadmapItemController@assign`.
- [ ] **Extend `GoalPolicy::view`** with the mentorship branch (accepted mentor of the goal's owner) — a second, independent branch alongside the existing group-visibility one, not a rewrite of it.
- [ ] Migration: `rewards` (see `02-BACKEND-ARCHITECTURE.md` §3 for the full column list and the state diagram).
- [ ] `RewardPolicy` — separate abilities for `create` (mentor offers), `request` (mentee demands something new), `respond`, `claim`, `fulfill`, `revoke`. Resist the temptation to collapse these into one `update` ability — the state machine only stays correct if each transition has its own explicit authorization check.
- [ ] `Actions\Rewards\CreateRewardAction`, `RequestRewardAction`, `RespondToRewardRequestAction`, `ClaimRewardAction`, `FulfillRewardAction`.
- [ ] `Actions\Rewards\MarkRewardsEarnedForItemAction` — called from `RecalculateGoalStatsJob` (Phase 2's job gets a new responsibility here; don't create a second, competing trigger point for the same state transition).
- [ ] `RewardController` (index/store/request/respond/claim/fulfill/revoke) + `RewardResource`.
- [ ] `Jobs\SendRewardClaimReminderJob` scheduled daily — nudges a mentor about a `claimed` reward unfulfilled for more than the configured grace period.
- [ ] Notifications: `MentorshipRequestedNotification`, `MentorshipAcceptedNotification`, `RoadmapItemAssignedNotification`, `RewardOfferedNotification`, `RewardEarnedNotification`, `RewardClaimedNotification`, `RewardFulfilledNotification`.
- [ ] **Gate**: Pest tests covering every Reward state transition and its authorization boundary (who can and can't trigger each one — see `06-TESTING-STRATEGY.md`), plus the `assign` vs `update` ability separation.

---

## Cross-cutting, do throughout (not a separate phase)

- [ ] Rate limiting on `/login`, `/register`, `/sprints/start`.
- [ ] Structured logging on job failures (Horizon failed-job alerts wired to at least a log channel, ideally Slack/email in prod).
- [ ] API versioning prefix (`/api/v1/...`) from day one, even with only one version — cheap now, expensive to retrofit.
- [ ] Every new endpoint gets a Form Request and a Policy check before it gets a route — not after.
