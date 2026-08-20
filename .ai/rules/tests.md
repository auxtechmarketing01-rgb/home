---
paths:
  - tests/**
---

# Tests

## Phase gates are the definition of done
Each phase has a named gate that must be green before the next phase starts (04 Phase 1-4 Gate; 06 intro): Phase 1 = Auth + Goals + RoadmapItems; Phase 2 = Sprint lifecycle (including the second-sprint 409 and the no-auto-cancel-while-overtime case) plus `RecalculateGoalStatsJob` rollup math; Phase 3 = policy branches, leaderboard correctness, cache invalidation; Phase 4 = every Reward transition with its authorization boundary plus the `assign` vs `update` separation.
Treat a feature as unfinished until its gate test exists by name, not just until the endpoint responds.

## Authenticate through Sanctum
Use `Sanctum::actingAs($user)` in every authenticated feature test so the real guard and its middleware run; never fake auth by bypassing the guard (06 §1.1).

## FR-SPR-09 needs its two named tests
Feature level: a sprint running well past its `planned_duration_seconds` still reports `running` from the API (06 §1.2 Sprints). Unit level: `CleanupStaleSprintsJob` leaves a sprint started 90 minutes ago with a 25-minute planned duration untouched, and auto-cancels one started 25 hours ago (06 §1.3).
Get that boundary exact — an over-aggressive cleanup job silently reintroduces "the pomodoro stops when you close the tab," the bug the feature exists to prevent (FR-SPR-09).

## Assert the dispatch in features, assert the math in units
The sprint-complete feature test asserts `RecalculateGoalStatsJob` was pushed and stops there; the rollup arithmetic gets its own unit test with several fixture variations — single sprint, multiple sprints on one item, sprints across multiple items (06 §1.2 Sprints, §1.3).
Assert the `offered`→`earned` flip through the job and `MarkRewardsEarnedForItemAction`, with no HTTP request involved (06 §1.2 Rewards).

## Two integration checks run without Queue::fake
Sprint completion → goal/roadmap stats update, and the full mentor chain (request → accept → offer reward → item done → `earned` → claim → fulfill), are the minimum non-negotiable integration checks and must run against a real worker with `Queue::fake()` removed (06 §3 gates 2 and 5; §1.2 Analytics).
Everywhere else, faking the queue is fine.

## Every policy gets a 403-boundary test
For each model with a Policy, write a dedicated test asserting the 403 boundary for a non-owner/non-member — the highest-value category in a family app where privacy mistakes are the worst-case bug (06 §1.2 Policies). Pick 403-not-404 for non-member access to an existing record and stay consistent across policies (04 Phase 3 Gate).
Leaderboard tests must explicitly assert a private goal's data never appears in another member's response (06 §1.2 Groups).

## Ability separation is its own assertion
For `RoadmapItemPolicy`, one test must show a mentor successfully calling `assign` on a mentee's item and still getting 403 from the update-title endpoint on that same item (FR-MENT-06; 06 §1.2 Policies).

## One test per reward transition, per actor
For each transition in the reward state machine, assert the correct actor can trigger it and every other actor gets 403 — mentor `offer`, mentee `request`, mentor `respond`, mentee `claim`, mentor `fulfill`, mentor `revoke`. Wrong-state attempts return 422: claiming a merely-`offered` reward, fulfilling a non-`claimed` one, revoking an already-`earned` one (06 §1.2 Rewards).

## Name the exact boundary each unit test defends
`StreakService`: two users in different timezones cross the day boundary at different UTC instants (`users.timezone`). `ProjectionService`: returns `null` below the minimum data-point threshold instead of a fabricated date, and survives an average daily focus of 0. `NotifyExpiredSprintsJob`: exactly one notification per expired sprint, a second run sends nothing, and an already-`completed`/`cancelled` sprint is never notified (06 §1.3).

## Test the spoof, not just the happy path
Resource upload tests must reject a disallowed MIME even with a spoofed extension — a renamed `.exe` given a `.pdf` extension → 422 (06 §1.2 Resources). Push-subscription store tests must show the subscription binds to the authenticated user and cannot be aimed at another user's account (06 §1.2 Push subscriptions).

## Exercise Actions directly as well as over HTTP
`CreateGoalAction`, `StartSprintAction`, `ReorderRoadmapItemsAction`, `RequestMentorshipAction` and `ClaimRewardAction` get unit tests for their transactional and validation edge cases, not only coverage through a controller (06 §1.3).

## Keep one MySQL-backed run for dialect drift
In-memory SQLite is fine for speed, but any MySQL-specific SQL (certain JSON functions in particular) must also be exercised by the MySQL-service CI job so dialect drift fails in CI rather than production (06 §1.1, §4).
