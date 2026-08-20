<?php

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\Goal;
use App\Models\GoalStats;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;

/**
 * 06 §1.3: "the core 'does the rollup actually work' test", with several
 * fixture variations. Run through handle() directly rather than dispatched,
 * so each case is arithmetic with nothing queued in between.
 */
function recalculate(Goal $goal): void
{
    /**
     * Resolved through the container rather than by hand-passing
     * dependencies: this job gained responsibilities in Phase 3 and Phase 4
     * (leaderboard invalidation, gamification, the reward flip), and a
     * hand-written argument list would have to be edited every time — which
     * is a test breaking for a reason that has nothing to do with the
     * behaviour it asserts.
     */
    app()->call([new RecalculateGoalStatsJob($goal), 'handle']);
}

/**
 * @return array{0: Goal, 1: Roadmap}
 */
function goalWithRoadmap(?User $user = null): array
{
    $user ??= User::factory()->create();
    $goal = Goal::factory()->for($user)->create();
    $roadmap = Roadmap::factory()->for($goal)->create();

    return [$goal, $roadmap];
}

it('rolls a single sprint into its item and the goal total', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    $item = RoadmapItem::factory()->for($roadmap)->create();

    Sprint::factory()->for($goal->user)->completed(1500)->create([
        'goal_id' => $goal->id,
        'roadmap_item_id' => $item->id,
    ]);

    recalculate($goal);

    expect($item->fresh()->time_spent_seconds)->toBe(1500);

    $stats = GoalStats::query()->where('goal_id', $goal->id)->sole();

    expect($stats->total_focus_seconds)->toBe(1500)
        ->and($stats->sessions_count)->toBe(1)
        ->and($stats->last_recalculated_at)->not->toBeNull();
});

it('sums multiple sprints on one item', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    $item = RoadmapItem::factory()->for($roadmap)->create();

    foreach ([1500, 900, 600] as $duration) {
        Sprint::factory()->for($goal->user)->completed($duration)->create([
            'goal_id' => $goal->id,
            'roadmap_item_id' => $item->id,
        ]);
    }

    recalculate($goal);

    expect($item->fresh()->time_spent_seconds)->toBe(3000);

    $stats = GoalStats::query()->where('goal_id', $goal->id)->sole();

    expect($stats->total_focus_seconds)->toBe(3000)
        ->and($stats->sessions_count)->toBe(3);
});

it('keeps sprints on different items apart while summing the goal', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    $first = RoadmapItem::factory()->for($roadmap)->create();
    $second = RoadmapItem::factory()->for($roadmap)->create();
    $untouched = RoadmapItem::factory()->for($roadmap)->create();

    Sprint::factory()->for($goal->user)->completed(1500)->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $first->id,
    ]);
    Sprint::factory()->for($goal->user)->completed(300)->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $first->id,
    ]);
    Sprint::factory()->for($goal->user)->completed(600)->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $second->id,
    ]);

    recalculate($goal);

    expect($first->fresh()->time_spent_seconds)->toBe(1800)
        ->and($second->fresh()->time_spent_seconds)->toBe(600)
        ->and($untouched->fresh()->time_spent_seconds)->toBe(0);

    expect(GoalStats::query()->where('goal_id', $goal->id)->sole()->total_focus_seconds)->toBe(2400);
});

/**
 * A sprint against the goal but no particular item is still the member's
 * time on that goal (FR-SPR-01), so it counts toward the goal total without
 * landing on any item.
 */
it('counts a goal level sprint in the total but on no item', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    $item = RoadmapItem::factory()->for($roadmap)->create();

    Sprint::factory()->for($goal->user)->completed(1200)->create([
        'goal_id' => $goal->id,
        'roadmap_item_id' => null,
    ]);

    recalculate($goal);

    expect($item->fresh()->time_spent_seconds)->toBe(0)
        ->and(GoalStats::query()->where('goal_id', $goal->id)->sole()->total_focus_seconds)->toBe(1200);
});

it('ignores running, paused and cancelled sprints', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    $item = RoadmapItem::factory()->for($roadmap)->create();

    Sprint::factory()->for($goal->user)->completed(600)->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $item->id,
    ]);
    Sprint::factory()->for($goal->user)->cancelled()->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $item->id,
    ]);
    Sprint::factory()->for($goal->user)->paused()->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $item->id,
    ]);

    recalculate($goal);

    expect($item->fresh()->time_spent_seconds)->toBe(600)
        ->and(GoalStats::query()->where('goal_id', $goal->id)->sole()->sessions_count)->toBe(1);
});

it('never counts another goal sprints', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    $item = RoadmapItem::factory()->for($roadmap)->create();

    Sprint::factory()->for($goal->user)->completed(600)->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $item->id,
    ]);

    [$otherGoal, $otherRoadmap] = goalWithRoadmap();
    $otherItem = RoadmapItem::factory()->for($otherRoadmap)->create();
    Sprint::factory()->for($otherGoal->user)->completed(9999)->create([
        'goal_id' => $otherGoal->id, 'roadmap_item_id' => $otherItem->id,
    ]);

    recalculate($goal);

    expect(GoalStats::query()->where('goal_id', $goal->id)->sole()->total_focus_seconds)->toBe(600);
});

/**
 * The job rebuilds rather than increments, which is what makes the cache
 * self-healing. A stale total from an earlier run must fall back to the truth,
 * not persist.
 */
it('rebuilds a stale item total rather than adding to it', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    $item = RoadmapItem::factory()->for($roadmap)->create(['time_spent_seconds' => 99999]);

    Sprint::factory()->for($goal->user)->completed(300)->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $item->id,
    ]);

    recalculate($goal);

    expect($item->fresh()->time_spent_seconds)->toBe(300);
});

it('zeroes an item whose only sprint was cancelled', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    $item = RoadmapItem::factory()->for($roadmap)->create(['time_spent_seconds' => 1500]);

    Sprint::factory()->for($goal->user)->cancelled()->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $item->id,
    ]);

    recalculate($goal);

    expect($item->fresh()->time_spent_seconds)->toBe(0);
});

it('computes completion percentage from done items', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    RoadmapItem::factory()->count(2)->for($roadmap)->done()->create();
    RoadmapItem::factory()->count(2)->for($roadmap)->create();

    recalculate($goal);

    expect(GoalStats::query()->where('goal_id', $goal->id)->sole()->completion_percentage)->toBe(50.0);
});

/**
 * Skipped items leave the denominator, rather than counting as unfinished.
 * Counting them would cap any roadmap containing a skipped item below 100%
 * forever, so the FR-GOAL-04 completion banner could never appear for it.
 */
it('excludes skipped items from the completion denominator', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    RoadmapItem::factory()->count(2)->for($roadmap)->done()->create();
    RoadmapItem::factory()->for($roadmap)->create();
    RoadmapItem::factory()->for($roadmap)->skipped()->create();

    recalculate($goal);

    expect(GoalStats::query()->where('goal_id', $goal->id)->sole()->completion_percentage)->toBe(66.67);
});

it('reaches one hundred percent when every unskipped item is done', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    RoadmapItem::factory()->count(3)->for($roadmap)->done()->create();
    RoadmapItem::factory()->for($roadmap)->skipped()->create();

    recalculate($goal);

    expect(GoalStats::query()->where('goal_id', $goal->id)->sole()->completion_percentage)->toBe(100.0);
});

it('reports zero completion for an empty roadmap without dividing by zero', function () {
    [$goal] = goalWithRoadmap();

    recalculate($goal);

    expect(GoalStats::query()->where('goal_id', $goal->id)->sole()->completion_percentage)->toBe(0.0);
});

it('is idempotent', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    $item = RoadmapItem::factory()->for($roadmap)->done()->create();

    Sprint::factory()->for($goal->user)->completed(1500)->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $item->id,
    ]);

    recalculate($goal);
    recalculate($goal);
    recalculate($goal);

    expect($item->fresh()->time_spent_seconds)->toBe(1500)
        ->and(GoalStats::query()->where('goal_id', $goal->id)->count())->toBe(1);

    $stats = GoalStats::query()->where('goal_id', $goal->id)->sole();

    expect($stats->total_focus_seconds)->toBe(1500)
        ->and($stats->sessions_count)->toBe(1);
});

it('records the streak alongside the totals', function () {
    $this->freezeTime();

    [$goal, $roadmap] = goalWithRoadmap();
    $item = RoadmapItem::factory()->for($roadmap)->create();

    foreach ([0, 1, 2] as $daysAgo) {
        Sprint::factory()->for($goal->user)->completed(1500)->create([
            'goal_id' => $goal->id,
            'roadmap_item_id' => $item->id,
            'ended_at' => now()->subDays($daysAgo),
        ]);
    }

    recalculate($goal);

    $stats = GoalStats::query()->where('goal_id', $goal->id)->sole();

    expect($stats->current_streak)->toBe(3)
        ->and($stats->longest_streak)->toBe(3);
});

it('survives a goal that was archived after the job was dispatched', function () {
    [$goal, $roadmap] = goalWithRoadmap();
    $item = RoadmapItem::factory()->for($roadmap)->create();

    Sprint::factory()->for($goal->user)->completed(600)->create([
        'goal_id' => $goal->id, 'roadmap_item_id' => $item->id,
    ]);

    $goal->delete();

    recalculate($goal);

    expect(GoalStats::query()->where('goal_id', $goal->id)->sole()->total_focus_seconds)->toBe(600);
});

it('does nothing when the goal has been hard deleted', function () {
    [$goal] = goalWithRoadmap();
    $goalId = $goal->id;

    $goal->forceDelete();

    recalculate($goal);

    expect(GoalStats::query()->where('goal_id', $goalId)->count())->toBe(0);
});
