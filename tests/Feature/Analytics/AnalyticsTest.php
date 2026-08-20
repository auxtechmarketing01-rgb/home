<?php

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * FR-ANL-01 (per-goal dashboard) and FR-ANL-03 (cross-goal overview).
 */
beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'UTC']);
    $this->goal = Goal::factory()->for($this->user)->create();
    $this->roadmap = Roadmap::factory()->for($this->goal)->create();

    Sanctum::actingAs($this->user);
});

it('reports goal stats from the cache row', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->done()->create();
    Sprint::factory()->for($this->user)->completed(1800)->create([
        'goal_id' => $this->goal->id,
        'roadmap_item_id' => $item->id,
    ]);

    app()->call([new RecalculateGoalStatsJob($this->goal), 'handle']);

    $this->getJson("/api/v1/goals/{$this->goal->id}/stats")
        ->assertOk()
        ->assertJsonPath('data.total_focus_seconds', 1800)
        ->assertJsonPath('data.sessions_count', 1)
        ->assertJsonPath('data.completion_percentage', 100);
});

/**
 * A goal with no logged time yet is normal, not an error.
 */
it('reports null stats before the first recalculation', function () {
    $this->getJson("/api/v1/goals/{$this->goal->id}/stats")
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('forbids another member from reading goal stats', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/goals/{$this->goal->id}/stats")->assertForbidden();
});

/**
 * FR-ANL-02: null rather than a fabricated date. The SPA renders this as "not
 * enough data yet".
 */
it('leaves the projection null while the evidence is thin', function () {
    RoadmapItem::factory()->for($this->roadmap)->create(['estimated_minutes' => 600]);
    Sprint::factory()->for($this->user)->completed(1800)->create(['goal_id' => $this->goal->id]);

    app()->call([new RecalculateGoalStatsJob($this->goal), 'handle']);

    $this->getJson("/api/v1/goals/{$this->goal->id}/stats")
        ->assertOk()
        ->assertJsonPath('data.projected_completion_date', null);
});

it('returns a personal overview with totals and a streak', function () {
    $this->freezeTime();

    Sprint::factory()->for($this->user)->completed(1800)->create(['goal_id' => $this->goal->id]);
    Sprint::factory()->for($this->user)->completed(600)->create(['goal_id' => $this->goal->id]);

    $this->getJson('/api/v1/analytics/overview')
        ->assertOk()
        ->assertJsonPath('data.totals.total_focus_seconds', 2400)
        ->assertJsonPath('data.totals.sessions_count', 2)
        ->assertJsonPath('data.totals.active_goals', 1)
        ->assertJsonPath('data.streak.current', 1)
        ->assertJsonStructure([
            'data' => ['totals', 'streak', 'by_category', 'daily_trend', 'gamification'],
        ]);
});

/**
 * FR-ANL-03: time distribution by category, with uncategorised work reported
 * rather than dropped — otherwise the chart quietly fails to add up to the
 * member's own total.
 */
it('breaks focus time down by category and keeps uncategorised time visible', function () {
    $programming = Category::factory()->global()->create(['name' => 'Programming']);
    $categorised = Goal::factory()->for($this->user)->create(['category_id' => $programming->id]);

    Sprint::factory()->for($this->user)->completed(3600)->create(['goal_id' => $categorised->id]);
    Sprint::factory()->for($this->user)->completed(1800)->create(['goal_id' => $this->goal->id]);

    $byCategory = collect($this->getJson('/api/v1/analytics/overview')->assertOk()
        ->json('data.by_category'));

    expect($byCategory->firstWhere('category.name', 'Programming')['focus_seconds'])->toBe(3600)
        ->and($byCategory->firstWhere('category', null)['focus_seconds'])->toBe(1800)
        ->and($byCategory->sum('focus_seconds'))->toBe(5400);
});

/**
 * Zero-activity days must be present. A trend built only from active days
 * would compress a fortnight of two sessions into a flattering pair.
 */
it('includes quiet days in the daily trend', function () {
    $this->freezeTime();

    Sprint::factory()->for($this->user)->completed(1800)->create([
        'goal_id' => $this->goal->id,
        'ended_at' => now(),
    ]);

    $trend = collect($this->getJson('/api/v1/analytics/overview?trend_days=7')->assertOk()
        ->json('data.daily_trend'));

    expect($trend)->toHaveCount(7)
        ->and($trend->last()['focus_minutes'])->toBe(30)
        ->and($trend->first()['focus_minutes'])->toBe(0)
        ->and($trend->pluck('date')->unique())->toHaveCount(7);
});

it('never mixes another member data into the overview', function () {
    $other = User::factory()->create();
    $otherGoal = Goal::factory()->for($other)->create();
    Sprint::factory()->for($other)->completed(99999)->create(['goal_id' => $otherGoal->id]);

    Sprint::factory()->for($this->user)->completed(600)->create(['goal_id' => $this->goal->id]);

    $this->getJson('/api/v1/analytics/overview')
        ->assertOk()
        ->assertJsonPath('data.totals.total_focus_seconds', 600);
});

/**
 * FR-GAM-02: opting out means nothing is computed and nothing is reported.
 */
it('reports gamification as disabled when the member opted out', function () {
    $this->user->update(['settings' => ['gamification_enabled' => false]]);

    $this->getJson('/api/v1/analytics/overview')
        ->assertOk()
        ->assertJsonPath('data.gamification.enabled', false)
        ->assertJsonMissingPath('data.gamification.xp');
});

it('rejects an out of range trend window', function () {
    $this->getJson('/api/v1/analytics/overview?trend_days=1000')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['trend_days']);
});

it('requires authentication', function () {
    app('auth')->forgetGuards();

    $this->getJson('/api/v1/analytics/overview')->assertUnauthorized();
});
