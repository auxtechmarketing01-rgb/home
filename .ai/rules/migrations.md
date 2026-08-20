---
paths:
  - database/migrations/**
  - app/Models/**
  - database/factories/**
  - database/seeders/**
---

# Schema And Eloquent Models

## Recalc-owned cache columns
`roadmap_items.time_spent_seconds` and every column of `goal_stats` are denormalized rollups written only by `RecalculateGoalStatsJob` (02 §3, §6). Controllers, Actions, and Resources read them; `goal_stats` is the per-goal analytics cache, so read the row rather than recomputing aggregates live (02 §7). `GoalStats` therefore has an empty `$fillable` — the job writes through `forceFill`, which makes "only this job writes here" enforced rather than merely documented.

## Rebuild rollups, never increment them
Recompute `time_spent_seconds` and the `goal_stats` columns from the sprint rows on every run, zeroing items first. Incrementing by each finished sprint is cheaper but leaves any missed or double-delivered job permanently wrong with nothing to notice it; a rebuild is self-healing and makes the job idempotent (02 §6).

## Keep both time-budget columns
`estimated_minutes` is the mentee's own estimate and `assigned_minutes` is the mentor's expected budget; they are allowed to disagree, so keep them as two columns (02 §3, FR-MENT-05). A null `assigned_by_mentor_id` means the mentee's own `estimated_minutes`/`scheduled_date` stand unmodified, and a mentor writes only `assigned_*` fields (FR-MENT-06).

## Store UTC, resolve day boundaries per member
`app.timezone` stays `UTC` so every timestamp lands in the database as UTC; convert to the member's own `users.timezone` wherever a calendar day matters — streaks, history date filters, projections (FR-GAM-01, FR-AUTH-04, 02 §1). A local `app.timezone` writes local time into the column, which makes the conversion a no-op for members in the server's zone and wrong for everyone else, and silently reinterprets every historical row if the server moves. The zone a *new* member gets is `pathforge.default_timezone` — a display preference, unrelated to storage.

## `paused_at` is part of the sprint, not an extra
Keep `sprints.paused_at` alongside `paused_seconds_total`: the total holds closed pauses, and the open pause is folded in on resume or completion. Without the timestamp there is no way to exclude paused time from `actual_duration_seconds` (FR-SPR-04), and `updated_at` is not a substitute — any other write to the row moves it.

## Overtime is derived, never stored
Keep `sprints.status` at `running`/`paused`/`completed`/`cancelled` and derive overtime by comparing `started_at + planned_duration_seconds` to now (FR-SPR-09, 02 §3). Passing the planned duration only sets `notified_expired_at` once, via `NotifyExpiredSprintsJob`; only an explicit complete/cancel changes `status` (02 §6).

## Explicit fillable, cache columns excluded
Give every model an explicit `$fillable` rather than `$guarded = []` (01 §5, 02 §5). Leave job-owned and gamification columns — `time_spent_seconds`, `goal_stats.*`, `users.xp`, `users.level`, `notified_expired_at` — out of `$fillable` so only their owning job or Action can set them.

## Soft deletes are the archive mechanism
Archive a goal by setting `deleted_at` through `SoftDeletes` instead of hard-deleting it (FR-GOAL-03, 02 §3). Archived goals must drop out of active-streak scopes, so build streak queries on the non-trashed default scope.

## Mentorship pairs are unique and reused
Put a unique constraint on `mentorships(mentor_id, mentee_id)` and, when a pair re-requests after `ended`, update that existing row so history stays in one place (02 §3). Model mentor/mentee purely as rows here — `users` carries no role column (01 §4.7, FR-MENT-03).

## Name the model ResourceFile
Call the Eloquent model `ResourceFile` on table `resource_files` to keep it clear of Laravel's `Http\Resources`, and reject any reintroduction of `Resource.php` (02 §2). Its `resourceable` morph targets `Goal` and `RoadmapItem` only (02 §3).

## Settle medialibrary vs resource_files first
Choose between `spatie/laravel-medialibrary` and the hand-rolled `resource_files` table before writing any file-storage migration, and confirm the choice with the user since it adds a dependency (02 §3, §9). If medialibrary wins, its `media` table replaces `disk`/`path`/`mime_type`/`size_bytes` — run one storage path, never both.

## Push subscriptions come from the package
Use the `push_subscriptions` migration shipped by `laravel-notification-channels/webpush`, keeping its `endpoint`/`public_key`/`auth_token`/`content_encoding` columns and `subscribable` morph as `WebPushChannel` expects (02 §3, §9). Add no second hand-written push table.

## Cross-column invariants live in validation
Keep `rewards.goal_id` and `rewards.roadmap_item_id` both nullable and enforce "at least one" in `StoreRewardRequest`, since a migration cannot express it cleanly (02 §3). Likewise enforce one `running`/`paused` sprint per user in `StartSprintAction`, not with a DB constraint (FR-SPR-08).

## Schema defaults and required keys
Default FKs to `cascadeOnDelete()` unless a column's note says otherwise, index `roadmap_items(roadmap_id, position)` for ordered reads, and make `roadmaps.goal_id`, `goal_stats.goal_id`, `groups.invite_code` unique plus a composite unique on `group_members(group_id, user_id)` (02 §3, FR-RM-01).

## Seed the global lookup rows
Seed default categories as rows with a null `user_id` — Programming, Fitness, Language, Reading, Other — reserving non-null `user_id` for user-defined ones (FR-GOAL-05, 02 §3). Seed badges `streak_7`, `streak_30`, `streak_100`, `first_goal_completed` (02 §3).
