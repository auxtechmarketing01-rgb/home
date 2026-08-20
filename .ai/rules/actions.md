---
paths:
  - app/Actions/**
  - app/Services/**
---

# Actions And Services

## Actions do transitions, Services do computation
Give each Action one domain verb and one state transition, named `<Verb><Noun>Action` under a domain subfolder (`Actions/Sprints/StartSprintAction`). Put reusable, mostly-read math in a Service instead — `StreakService`, `ProjectionService`, `LeaderboardService`, `FileStorageService` (02 §2).

## Plain invokable classes, no Actions package
Write Actions as plain invokable PHP classes; do not add `lorisleiva/laravel-actions` or similar — the folder convention gives the same organization without the dependency (02 §9).

## Controllers hand off, they never decide
Keep every controller method to `validate → call an Action/Service → return a Resource`. Query building and business logic belong here in `app/Actions` and `app/Services`, never in a controller body (02 §4).

## Authorization that is not input validation belongs in the Action
Enforce real authorization rules inside the Action, not only in the Form Request. Example: `RequestMentorshipAction` must itself check that requester and target share a Group — a Form Request alone is not the boundary (02 §5, 04 Phase 4, FR-MENT-01).

## One Action, one transaction, for multi-row writes
Wrap writes that must land together in a single DB transaction: `CreateGoalAction` creates the Goal **and** its empty Roadmap together; `ReorderRoadmapItemsAction` does one bulk `position` update and rejects IDs from another roadmap (04 Phase 1).

## One trigger point per state transition
Call `MarkRewardsEarnedForItemAction` only from `RecalculateGoalStatsJob`; add no competing trigger (observer, controller, second job) for the same transition (04 Phase 4, 02 §6, FR-RWD-02).

## Give each reward transition its own Action
Keep `CreateRewardAction`, `RequestRewardAction`, `RespondToRewardRequestAction`, `ClaimRewardAction`, and `FulfillRewardAction` separate, each paired with its own policy ability. Collapsing them into one "update" path is what breaks the state machine (04 Phase 4, 02 §5).

## Dispatch the rollup, never compute it inline
`CompleteSprintAction` sets `ended_at`/`actual_duration_seconds` and dispatches `RecalculateGoalStatsJob` to the queue — stats recalculation must not block the response the user is waiting on (04 Phase 2, 02 §6).

## StartSprintAction rejects a concurrent sprint with 409
Return a 409 when the user already has a `running` or `paused` sprint — a deliberate conflict response, not an unhandled 500 (04 Phase 2, FR-SPR-08).

## ProjectionService returns null rather than a guessed date
Compute remaining estimated minutes ÷ trailing-N-day average daily focus minutes, and return `null` when there are fewer than the minimum data points or the average is 0. Callers and the UI handle `null` as "not enough data yet" (04 Phase 2, 06 §1.3).

## LeaderboardService invalidates its key explicitly
Cache under `leaderboard:{group_id}:{period}` with a 5-minute TTL, and invalidate from `RecalculateGoalStatsJob` for any affected group member instead of waiting for expiry (02 §7, 04 Phase 3).

## Test Actions directly, not only through HTTP
Cover each Action's transactional and validation edge cases in its own test as well as via the endpoint — `CreateGoalAction`, `StartSprintAction`, `ReorderRoadmapItemsAction`, `RequestMentorshipAction`, `ClaimRewardAction` are named for this (06 §1.3).
