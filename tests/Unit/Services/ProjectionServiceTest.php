<?php

use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use App\Services\ProjectionService;

beforeEach(function () {
    $this->projections = app(ProjectionService::class);

    $this->user = User::factory()->create(['timezone' => 'UTC']);
    $this->goal = Goal::factory()->for($this->user)->create();
    $this->roadmap = Roadmap::factory()->for($this->goal)->create();
});

/**
 * Records `$count` completed sprints on `$count` distinct, consecutive days.
 */
function logFocusDays(Goal $goal, int $count, int $secondsPerDay): void
{
    for ($daysAgo = 0; $daysAgo < $count; $daysAgo++) {
        Sprint::factory()->for($goal->user)->completed($secondsPerDay)->create([
            'goal_id' => $goal->id,
            'ended_at' => now()->subDays($daysAgo),
        ]);
    }
}

/**
 * 06 §1.3's illustrative example, and the rule that matters most here: a date
 * built from one good evening is worse than no date, because the member will
 * plan around it.
 */
it('returns null when there are fewer than the minimum data points', function () {
    $this->freezeTime();

    RoadmapItem::factory()->for($this->roadmap)->create(['estimated_minutes' => 600]);

    logFocusDays($this->goal, 2, 7200);

    expect($this->projections->projectCompletionDate($this->goal))->toBeNull();
});

it('returns null for a goal with no activity at all', function () {
    RoadmapItem::factory()->for($this->roadmap)->create(['estimated_minutes' => 600]);

    expect($this->projections->projectCompletionDate($this->goal))->toBeNull();
});

/**
 * The explicit divide-by-zero guard.
 */
it('does not divide by zero when the average daily focus is zero', function () {
    $this->freezeTime();

    RoadmapItem::factory()->for($this->roadmap)->create(['estimated_minutes' => 600]);

    logFocusDays($this->goal, 4, 0);

    expect($this->projections->projectCompletionDate($this->goal))->toBeNull();
});

it('projects a date from a known input set', function () {
    $this->freezeTime();

    /** 600 minutes of work still to do. */
    RoadmapItem::factory()->count(4)->for($this->roadmap)->create(['estimated_minutes' => 150]);

    /** Three active days at two hours each: 360 minutes inside a 14-day window. */
    logFocusDays($this->goal, 3, 7200);

    /**
     * 360 / 14 calendar days = 25.714 minutes a day.
     * 600 / 25.714 = 23.33, rounded up to 24 days.
     */
    expect($this->projections->averageDailyFocusMinutes($this->goal))->toBe(360 / 14)
        ->and($this->projections->projectCompletionDate($this->goal)->toDateString())
        ->toBe(now()->addDays(24)->toDateString());
});

/**
 * Dividing by calendar days rather than by active days is deliberate: a member
 * who studies hard twice a week finishes at their twice-a-week pace, not at
 * the pace of one of those evenings.
 */
it('averages over calendar days rather than active days', function () {
    $this->freezeTime();

    RoadmapItem::factory()->for($this->roadmap)->create(['estimated_minutes' => 1400]);

    /** 3 days x 60 minutes = 180 minutes over a 14-day window. */
    logFocusDays($this->goal, 3, 3600);

    $average = $this->projections->averageDailyFocusMinutes($this->goal);

    expect($average)->toBe(180 / 14)
        /** Not the 60/day an active-days average would have claimed. */
        ->and($average)->toBeLessThan(60.0);
});

it('excludes done and skipped items from the remaining estimate', function () {
    RoadmapItem::factory()->for($this->roadmap)->done()->create(['estimated_minutes' => 500]);
    RoadmapItem::factory()->for($this->roadmap)->skipped()->create(['estimated_minutes' => 500]);
    RoadmapItem::factory()->for($this->roadmap)->create(['estimated_minutes' => 120]);
    RoadmapItem::factory()->for($this->roadmap)->inProgress()->create(['estimated_minutes' => 80]);

    expect($this->projections->remainingEstimatedMinutes($this->goal))->toBe(200);
});

/**
 * Nothing left to estimate supports no projection: the first case needs none,
 * the second has nothing to divide.
 */
it('returns null when every item is finished', function () {
    $this->freezeTime();

    RoadmapItem::factory()->count(2)->for($this->roadmap)->done()->create(['estimated_minutes' => 300]);

    logFocusDays($this->goal, 5, 3600);

    expect($this->projections->projectCompletionDate($this->goal))->toBeNull();
});

it('returns null when the remaining items carry no estimates', function () {
    $this->freezeTime();

    RoadmapItem::factory()->count(3)->for($this->roadmap)->create(['estimated_minutes' => null]);

    logFocusDays($this->goal, 5, 3600);

    expect($this->projections->projectCompletionDate($this->goal))->toBeNull();
});

it('ignores focus older than the trailing window', function () {
    $this->freezeTime();

    RoadmapItem::factory()->for($this->roadmap)->create(['estimated_minutes' => 600]);

    /** Well outside the 14-day window. */
    for ($daysAgo = 40; $daysAgo < 45; $daysAgo++) {
        Sprint::factory()->for($this->user)->completed(7200)->create([
            'goal_id' => $this->goal->id,
            'ended_at' => now()->subDays($daysAgo),
        ]);
    }

    expect($this->projections->averageDailyFocusMinutes($this->goal))->toBeNull()
        ->and($this->projections->projectCompletionDate($this->goal))->toBeNull();
});

it('honours a configured window and threshold', function () {
    $this->freezeTime();

    config([
        'pathforge.projection.trailing_days' => 7,
        'pathforge.projection.minimum_data_points' => 5,
    ]);

    RoadmapItem::factory()->for($this->roadmap)->create(['estimated_minutes' => 600]);

    logFocusDays($this->goal, 4, 3600);
    expect($this->projections->projectCompletionDate($this->goal))->toBeNull();

    logFocusDays($this->goal, 5, 3600);
    expect($this->projections->projectCompletionDate($this->goal))->not->toBeNull();
});
