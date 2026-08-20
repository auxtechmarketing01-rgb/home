<?php

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\Goal;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\Streak;
use App\Models\User;
use App\Services\LeaderboardService;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

/**
 * FR-GRP-03, and the Phase 3 gate from 04: "leaderboard correctness, and
 * cache invalidation".
 *
 * 06 §1.2 additionally demands one thing explicitly: **assert a private
 * goal's data never appears in another member's leaderboard response.** For a
 * family app, that is the worst-case bug, so it is tested from several angles
 * below rather than once.
 */
beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Amina', 'timezone' => 'UTC']);
    $this->sibling = User::factory()->create(['name' => 'Bilal', 'timezone' => 'UTC']);
    $this->outsider = User::factory()->create(['name' => 'Outsider']);

    $this->group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $this->group->id, 'user_id' => $this->sibling->id]);

    $this->leaderboards = app(LeaderboardService::class);
});

/**
 * Records `$seconds` of completed focus against a goal with the given
 * visibility.
 */
function logFocusOnGoal(User $user, ?Group $group, string $visibility, int $seconds): Goal
{
    $goal = Goal::factory()->for($user)->create([
        'visibility' => $visibility,
        'group_id' => $visibility === 'group' ? $group?->id : null,
    ]);

    Sprint::factory()->for($user)->completed($seconds)->create(['goal_id' => $goal->id]);

    return $goal;
}

it('ranks members by focus minutes from group visible goals', function () {
    logFocusOnGoal($this->owner, $this->group, 'group', 3600);
    logFocusOnGoal($this->sibling, $this->group, 'group', 7200);

    Sanctum::actingAs($this->owner);

    $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.user.name', 'Bilal')
        ->assertJsonPath('data.0.focus_minutes', 120)
        ->assertJsonPath('data.1.user.name', 'Amina')
        ->assertJsonPath('data.1.focus_minutes', 60)
        ->assertJsonPath('meta.period', 'week');
});

/**
 * **The named privacy assertion.** A private goal's time must not reach
 * another member's leaderboard, not even as an anonymous number — a total
 * that moves without a visible cause still discloses hidden activity.
 */
it('never counts a private goal towards the leaderboard', function () {
    logFocusOnGoal($this->sibling, $this->group, 'group', 600);
    logFocusOnGoal($this->sibling, null, 'private', 36000);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")->assertOk();

    $bilal = collect($response->json('data'))->firstWhere('user.name', 'Bilal');

    /** 10 minutes from the shared goal, and nothing from the 10 private hours. */
    expect($bilal['focus_minutes'])->toBe(10);
});

it('never leaks a private goal title or id through the leaderboard', function () {
    $private = logFocusOnGoal($this->sibling, null, 'private', 36000);
    $private->update(['title' => 'Secret therapy journal']);

    Sanctum::actingAs($this->owner);

    $body = $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")->assertOk()->content();

    expect($body)->not->toContain('Secret therapy journal')
        ->and($body)->not->toContain('"goal_id"');
});

/**
 * A goal marked group-visible for a *different* group must not surface here
 * either — otherwise marking a goal shared would publish it to every circle
 * the owner belongs to.
 */
it('never counts a goal shared with a different group', function () {
    $otherGroup = Group::factory()->create(['owner_id' => $this->sibling->id]);

    logFocusOnGoal($this->sibling, $this->group, 'group', 600);
    logFocusOnGoal($this->sibling, $otherGroup, 'group', 36000);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")->assertOk();
    $bilal = collect($response->json('data'))->firstWhere('user.name', 'Bilal');

    expect($bilal['focus_minutes'])->toBe(10);
});

it('never includes a member of another group', function () {
    logFocusOnGoal($this->outsider, null, 'private', 36000);

    Sanctum::actingAs($this->owner);

    $names = collect($this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")
        ->assertOk()->json('data'))->pluck('user.name');

    expect($names)->not->toContain('Outsider')->toHaveCount(2);
});

it('excludes cancelled and running sprints', function () {
    $goal = Goal::factory()->for($this->sibling)->groupVisible()->create(['group_id' => $this->group->id]);

    Sprint::factory()->for($this->sibling)->completed(600)->create(['goal_id' => $goal->id]);
    Sprint::factory()->for($this->sibling)->cancelled()->create(['goal_id' => $goal->id]);
    Sprint::factory()->for($this->sibling)->running()->create(['goal_id' => $goal->id]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")->assertOk();
    $bilal = collect($response->json('data'))->firstWhere('user.name', 'Bilal');

    expect($bilal['focus_minutes'])->toBe(10);
});

it('reports completed group visible goals and current streaks', function () {
    Goal::factory()->for($this->sibling)->groupVisible()->completed()->create([
        'group_id' => $this->group->id,
    ]);
    Goal::factory()->for($this->sibling)->completed()->create(['visibility' => 'private']);

    Streak::factory()->running(9)->create(['user_id' => $this->sibling->id]);

    Sanctum::actingAs($this->owner);

    $response = $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard?period=all")->assertOk();
    $bilal = collect($response->json('data'))->firstWhere('user.name', 'Bilal');

    expect($bilal['goals_completed'])->toBe(1)
        ->and($bilal['current_streak'])->toBe(9);
});

/**
 * Rolling windows, so members in different timezones never see the table
 * disagree with itself over when "this week" started.
 */
it('bounds the week and month periods by a rolling window', function () {
    $goal = Goal::factory()->for($this->sibling)->groupVisible()->create(['group_id' => $this->group->id]);

    Sprint::factory()->for($this->sibling)->completed(600)->create([
        'goal_id' => $goal->id, 'ended_at' => now()->subDays(2),
    ]);
    Sprint::factory()->for($this->sibling)->completed(1200)->create([
        'goal_id' => $goal->id, 'ended_at' => now()->subDays(20),
    ]);
    Sprint::factory()->for($this->sibling)->completed(1800)->create([
        'goal_id' => $goal->id, 'ended_at' => now()->subDays(200),
    ]);

    Sanctum::actingAs($this->owner);

    $minutesFor = function (string $period): int {
        $response = $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard?period={$period}");

        return collect($response->assertOk()->json('data'))
            ->firstWhere('user.name', 'Bilal')['focus_minutes'];
    };

    expect($minutesFor('week'))->toBe(10)
        ->and($minutesFor('month'))->toBe(30)
        ->and($minutesFor('all'))->toBe(60);
});

it('rejects an unknown period', function () {
    Sanctum::actingAs($this->owner);

    $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard?period=fortnight")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['period']);
});

it('handles a group with no activity yet without failing', function () {
    Sanctum::actingAs($this->owner);

    $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.focus_minutes', 0)
        ->assertJsonPath('data.0.current_streak', 0);
});

/*
|--------------------------------------------------------------------------
| Caching (02 §7)
|--------------------------------------------------------------------------
*/

it('caches the computed leaderboard', function () {
    Sanctum::actingAs($this->owner);

    $key = $this->leaderboards->cacheKey($this->group->id, 'week');

    expect(Cache::has($key))->toBeFalse();

    $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")->assertOk();

    expect(Cache::has($key))->toBeTrue();
});

/**
 * The Phase 3 gate item: invalidated **explicitly** by the recalculation job
 * rather than left to expire, so the table feels live without being
 * recomputed on every page view.
 */
it('invalidates the cache when a member stats are recalculated', function () {
    $goal = Goal::factory()->for($this->sibling)->groupVisible()->create(['group_id' => $this->group->id]);
    Roadmap::factory()->for($goal)->create();

    Sanctum::actingAs($this->owner);
    $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")->assertOk();

    $key = $this->leaderboards->cacheKey($this->group->id, 'week');
    expect(Cache::has($key))->toBeTrue();

    app()->call([new RecalculateGoalStatsJob($goal), 'handle']);

    expect(Cache::has($key))->toBeFalse();
});

/**
 * Stale numbers are the visible symptom of a missed invalidation, so this
 * asserts the value actually changes rather than only that a key vanished.
 */
it('serves fresh numbers after a new session is logged', function () {
    $goal = Goal::factory()->for($this->sibling)->groupVisible()->create(['group_id' => $this->group->id]);
    Roadmap::factory()->for($goal)->create();
    RoadmapItem::factory()->for($goal->roadmap)->create();

    Sanctum::actingAs($this->owner);

    $first = collect($this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")
        ->assertOk()->json('data'))->firstWhere('user.name', 'Bilal');

    expect($first['focus_minutes'])->toBe(0);

    Sprint::factory()->for($this->sibling)->completed(1800)->create(['goal_id' => $goal->id]);
    app()->call([new RecalculateGoalStatsJob($goal), 'handle']);

    $second = collect($this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")
        ->assertOk()->json('data'))->firstWhere('user.name', 'Bilal');

    expect($second['focus_minutes'])->toBe(30);
});

it('invalidates every period at once', function () {
    $goal = Goal::factory()->for($this->sibling)->groupVisible()->create(['group_id' => $this->group->id]);
    Roadmap::factory()->for($goal)->create();

    Sanctum::actingAs($this->owner);

    foreach (LeaderboardService::PERIODS as $period) {
        $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard?period={$period}")->assertOk();
    }

    app()->call([new RecalculateGoalStatsJob($goal), 'handle']);

    foreach (LeaderboardService::PERIODS as $period) {
        expect(Cache::has($this->leaderboards->cacheKey($this->group->id, $period)))->toBeFalse();
    }
});
