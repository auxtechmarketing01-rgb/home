# Pathforge

A private goal-tracking platform for a small closed group (a family, a friend circle). Each member
sets **Goals**, breaks one into a **Roadmap** of ordered statusable items, runs **Focus Sprints**
(Pomodoro / countdown / stopwatch) against those items so real time rolls up
Sprint → Roadmap Item → Goal → Group leaderboard, and compares progress inside an invite-only
**Group**. A **Mentorship** is a relationship between two members that lets a mentor set time
budgets and due dates on a mentee's roadmap items and attach **Rewards** to them.

Laravel 12 REST API (`/api/v1`) with Sanctum cookie SPA auth, consumed by a standalone Vue 3 + TS
SPA. MySQL 8, Redis, Horizon queues. Real-time delivery over Pusher Channels; Web Push (VAPID) for
the closed-browser case.

## Current state

**The backend is complete: all four phases of `docs/04-BACKEND-STEPS.md` are built and green.**

- Phase 1 — Auth (incl. password reset + email verification), Categories, Goals, Roadmap items with
  batch reorder.
- Phase 2 — Focus Sprints (full lifecycle, CSV export), Resources (file/link/note), `goal_stats`,
  `StreakService`/`ProjectionService`, `RecalculateGoalStatsJob`, `NotifyExpiredSprintsJob`,
  `CleanupStaleSprintsJob`, Web Push subscriptions.
- Phase 3 — Groups + invites, group goal visibility, `LeaderboardService` (cached, explicitly
  invalidated), Squad Challenges, Analytics endpoints, per-member `streaks`, gamification
  (XP/levels/badges), `DailyStreakCheckJob`, `SendSprintReminderJob`, admin routes.
- Phase 4 — Mentorships, the `assign` ability, the mentorship branch on `GoalPolicy::view`, the
  full Reward state machine, `MarkRewardsEarnedForItemAction`, `SendRewardClaimReminderJob`, the
  FR-RWD-06 ledger, mentor dashboard.
- Cross-cutting — Pusher broadcasting + the notification layer, rate limiting, structured job
  failure logging, `/api/v1` versioning.

**The SPA is built too: all four phases of `docs/05-FRONTEND-STEPS.md` live in `spa/`**, verified in a
real browser against the running API — login through the form, all nine views rendering, and a focus
sprint started and stopped end to end.

78 API routes, 5 scheduled jobs, 484 Pest tests. Run the suite with `php artisan test --compact`
and format with `vendor/bin/pint` before finalising any change.

The SPA has its own toolchain, run from `spa/`: `npm run dev` (Vite on 5173), `npx vue-tsc --noEmit`,
`npx vitest run` (85 tests), `npx vite build`. All three checks must be green before finalising.

**Running it locally takes two servers, and the frontend is not the one on 8000.** `php artisan serve`
serves JSON only and has no Blade views, so opening it in a browser correctly shows nothing — the app
is `http://localhost:5173`, and Vite proxies `/api`, `/sanctum` and `/storage` to the API so the
browser only ever sees one origin. `spa/.env`'s `VITE_DEV_API_PROXY` names the API port; change it
there rather than in `vite.config.ts`.

Three local failure modes have each cost an hour, and none of them look like application bugs.
A port that completes the TCP handshake but never answers HTTP is usually a wedged `composer` or
`php` process holding `vendor/`. MariaDB being down takes the whole API down rather than degrading
it, because `SESSION_DRIVER` and `CACHE_STORE` are both `database`, so every request needs a query.
And a stale dev server bound to `[::1]:5173` beats a healthy one on `[::]` because the address is
more specific — `localhost` silently hits the zombie while `127.0.0.1` is fine, so check
`netstat -ano | grep 5173` for two listeners before debugging anything else.

Local environment differs from the docs in three ways worth knowing before you debug something:
MariaDB 10.4 rather than MySQL 8 (avoid MySQL-8-only SQL), PHP 8.2 rather than 8.3+, and Redis is
not running — so cache/queue code must go through the `Cache`/queue facades and stay driver-agnostic.
Horizon is installed and configured for the deploy `redis` connection but cannot run locally
(needs `ext-pcntl`/`ext-posix`); local work uses the `database` queue driver.

`docs/` remains the design authority for everything unbuilt. Never assume a class, table, endpoint,
or component named in the docs exists; check the filesystem first.

## Specification

`docs/` is the source of truth and outranks inference from surrounding code. Read the rows that
cover what you are about to touch **before** planning or writing.

| Read | When |
| --- | --- |
| `docs/01-SRS.md` | Deciding what a feature must do. Holds every `FR-*` requirement id, its MoSCoW priority, and its acceptance criteria — cite the id in commits, tests, and rule notes. |
| `docs/02-BACKEND-ARCHITECTURE.md` | Touching schema, routes, policies, jobs, or caching. §3 is the column-level schema and the reward state diagram; §5 is the authorization matrix; §10 is the real-time/broadcast layer. |
| `docs/03-FRONTEND-ARCHITECTURE.md` | Touching the SPA. §2 is the Resource ↔ TS type contract; §4 is the focus-timer design; §4.1 is Web Push; §4.2 is Echo/Pusher. |
| `docs/04-BACKEND-STEPS.md` / `docs/05-FRONTEND-STEPS.md` | Choosing what to build next. Four phases, each shippable alone, backend and frontend sequenced phase-for-phase. |
| `docs/06-TESTING-STRATEGY.md` | Writing tests, or deciding whether a phase is done. Holds the per-area test matrix and the phase gates. |

## Product invariants

Cross-cutting truths the design turns on. Breaking one is a product bug even when the code is clean.

- **A Sprint ends only when the user stops it.** The server row (`started_at` +
  `planned_duration_seconds`) *is* the running sprint. Passing the planned duration produces a push
  notification and a UI overtime state — never a status change, never an auto-stop, never an
  auto-complete (FR-SPR-03, FR-SPR-09).
- **A mentor sets expectations, never content.** Mentorship grants read access plus
  `assigned_minutes` / `assigned_due_at`; only the mentee edits their own items or marks them done
  (FR-MENT-04, FR-MENT-06).
- **Mentorship is scoped to shared Groups.** There is no public user directory and no cross-group
  mentor search (FR-MENT-01).
- **A notification is durable first and live second.** Every member notification writes a
  `notifications` row, then broadcasts. Pusher reaches an open tab, Web Push reaches a closed
  browser, and neither is allowed to be the only place a notification existed — a broadcast failure
  degrades the app to "not live," never to "lost" (FR-NOT-01, FR-NOT-03, FR-SPR-10).
- **A broadcast channel is an authorization boundary.** Subscribing to another member's private
  channel would leak their notifications whatever the Policies say, so every channel in
  `routes/channels.php` is private and mirrors the Policy it corresponds to (01 §5 Privacy).
- **Timestamps are stored in UTC; day boundaries are resolved per member.** `app.timezone` must stay
  `UTC` — the whole point of `users.timezone` is that two siblings in different countries get their
  own midnight. A local `app.timezone` writes local time to the database and makes every conversion
  a no-op for members in the server's zone and wrong for everyone else. New members default to
  `pathforge.default_timezone`, which is a display preference and unrelated (FR-GAM-01, FR-AUTH-04).
- **Denormalized numbers are rebuilt, never incremented.** `RecalculateGoalStatsJob` recomputes
  `roadmap_items.time_spent_seconds` and every `goal_stats` column from the sprint rows, so a missed
  or double-delivered job self-heals on the next run instead of corrupting the cache permanently
  (02 §3, §6).
- **Rewards are bookkeeping, never money.** Fulfilling a reward records that something happened
  outside the app. No payment rails, no spendable balance (FR-RWD-05).
- **Privacy is enforced in queries.** Every visibility rule lives in a Policy or a query scope, not
  in a hidden UI control (01 §5 Privacy).

## Working rules

- Build in the phase order of `docs/04-BACKEND-STEPS.md`; a phase's gate in
  `docs/06-TESTING-STRATEGY.md` must be green before the next phase starts.
- A frontend phase never gets ahead of the backend endpoints it consumes.
- Requirements are contested in `docs/`, not in code. When implementation reveals a doc is wrong,
  say so and get the doc changed rather than quietly diverging from it.

## Open decisions

Unresolved in the docs. Raise these rather than picking silently.

- **Product name** — "Pathforge" is a placeholder; `.env` still carries `APP_NAME=Laravel`. Nothing
  in `app/` is named after it, so a rename stays cheap — keep it that way.
- **Production serving of `spa/dist`** — the dev story is settled (two servers, Vite proxy), but
  nothing decides yet whether the built SPA is served by Laravel from `public/`, by a separate static
  host, or from a CDN. That choice sets whether production is same-origin (and can keep using Sanctum
  cookies as-is) or cross-origin (and needs CORS plus `SANCTUM_STATEFUL_DOMAINS` widened).

### Settled (do not re-litigate)

- **SPA root directory** — a standalone app in `spa/`, per `docs/03` §1. The Laravel-default
  `resources/js` + root `vite.config.js` are untouched and unused; `spa/` has its own `package.json`,
  `vite.config.ts` and `.gitignore`. (`spa/.gitignore` is not optional: the root one anchors its Node
  rules with a leading slash, so without it ~19.6k `node_modules` files get tracked.)
- **Inertia instead of a REST API + SPA** — considered and declined 2026-08-21. Inertia controllers
  return `Inertia::render()` props rather than API Resources, so adopting it means rewriting all 78
  routes and most of the JSON feature tests; it also fights the two features this product is built
  around, an installable PWA with Web Push and a long-lived client-side focus timer. The trade would
  have been reasonable before Phase 1 and is not now.
- **File attachments** — hand-rolled `resource_files` per 02 §3. `spatie/laravel-medialibrary` is
  out: it would not model the `link` and `note` resource types anyway, so the domain would end up
  split across two stores.
- **Squad Challenge storage** — dedicated `challenges` + `challenge_participants`, as 04 recommends.
- **PHP version** — staying on 8.2 (local is 8.2.12). Write 8.2-compatible code; `docs/02` §1's
  "8.3+" is aspirational, not a constraint met here.
- **Real-time transport** — Pusher Channels, not self-hosted Reverb (02 §10.5). Both speak the same
  protocol, so switching later is config, not code.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.2. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
