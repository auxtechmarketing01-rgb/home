<?php

use App\Models\Goal;
use App\Models\GoalStats;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * 06 §3, gate 2 — one of the two non-negotiable integration checks, and the
 * one that runs deliberately **without `Queue::fake()`**.
 *
 * Every other sprint test asserts that the recalculation was *dispatched*.
 * That proves the controller's half and nothing else: a job that is queued
 * but broken, or a rollup that never reaches the columns the dashboard reads,
 * passes all of them. This file drives the whole chain — HTTP request, real
 * queue worker, real job, real columns — so "I finished a session and my time
 * went up" is verified end to end at least once.
 */
beforeEach(function () {
    $this->user = User::factory()->create(['timezone' => 'UTC']);
    $this->goal = Goal::factory()->for($this->user)->create();
    $this->roadmap = Roadmap::factory()->for($this->goal)->create();
    $this->item = RoadmapItem::factory()->for($this->roadmap)->create(['estimated_minutes' => 120]);

    Sanctum::actingAs($this->user);
});

it('rolls a completed sprint all the way into the item and the goal stats', function () {
    $this->freezeTime();

    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
        'roadmap_item_id' => $this->item->id,
    ])->assertCreated();

    $sprint = Sprint::query()->sole();

    $this->travel(25)->minutes();

    $this->postJson("/api/v1/sprints/{$sprint->id}/complete")->assertOk();

    /** The item's denormalized rollup. */
    expect($this->item->fresh()->time_spent_seconds)->toBe(1500);

    /** The analytics cache the dashboard reads. */
    $stats = GoalStats::query()->where('goal_id', $this->goal->id)->sole();

    expect($stats->total_focus_seconds)->toBe(1500)
        ->and($stats->sessions_count)->toBe(1)
        ->and($stats->current_streak)->toBe(1)
        ->and($stats->last_recalculated_at)->not->toBeNull();
});

it('surfaces the rolled up stats through the goal endpoint', function () {
    $this->freezeTime();

    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'countdown',
        'planned_duration_seconds' => 1800,
        'roadmap_item_id' => $this->item->id,
    ])->assertCreated();

    $this->travel(30)->minutes();
    $this->postJson('/api/v1/sprints/'.Sprint::query()->sole()->id.'/complete')->assertOk();

    $this->getJson("/api/v1/goals/{$this->goal->id}")
        ->assertOk()
        ->assertJsonPath('data.stats.total_focus_seconds', 1800)
        ->assertJsonPath('data.stats.sessions_count', 1)
        ->assertJsonPath('data.roadmap.items.0.time_spent_seconds', 1800);
});

it('accumulates across several sessions on different items', function () {
    $this->freezeTime();

    $second = RoadmapItem::factory()->for($this->roadmap)->create(['estimated_minutes' => 60]);

    foreach ([[$this->item, 1500], [$second, 600], [$this->item, 900]] as [$target, $seconds]) {
        $this->postJson('/api/v1/sprints/start', [
            'mode' => 'countdown',
            'planned_duration_seconds' => $seconds,
            'roadmap_item_id' => $target->id,
        ])->assertCreated();

        $this->travel($seconds)->seconds();

        $this->postJson('/api/v1/sprints/'.Sprint::query()->active()->sole()->id.'/complete')->assertOk();
    }

    expect($this->item->fresh()->time_spent_seconds)->toBe(2400)
        ->and($second->fresh()->time_spent_seconds)->toBe(600);

    $stats = GoalStats::query()->where('goal_id', $this->goal->id)->sole();

    expect($stats->total_focus_seconds)->toBe(3000)
        ->and($stats->sessions_count)->toBe(3);
});

/**
 * Marking an item done goes through the same single trigger point, so
 * completion percentage stays consistent with the time rollup rather than
 * being maintained by a competing observer (02 §6).
 */
it('recalculates completion when an item is marked done', function () {
    RoadmapItem::factory()->for($this->roadmap)->create();

    $this->putJson("/api/v1/roadmap-items/{$this->item->id}", ['status' => 'done'])->assertOk();

    expect(GoalStats::query()->where('goal_id', $this->goal->id)->sole()->completion_percentage)
        ->toBe(50.0);
});

it('recalculates completion when an item is deleted', function () {
    $second = RoadmapItem::factory()->for($this->roadmap)->create();
    RoadmapItem::factory()->for($this->roadmap)->create();

    /** 1 of 3 done. */
    $this->putJson("/api/v1/roadmap-items/{$second->id}", ['status' => 'done'])->assertOk();
    expect(GoalStats::query()->where('goal_id', $this->goal->id)->sole()->completion_percentage)
        ->toBe(33.33);

    /** Removing an unfinished item lifts the percentage: 1 of 2. */
    $this->deleteJson("/api/v1/roadmap-items/{$this->item->id}")->assertNoContent();

    expect(GoalStats::query()->where('goal_id', $this->goal->id)->sole()->completion_percentage)
        ->toBe(50.0);
});

/**
 * A cancelled session must leave no trace in the numbers, even with the real
 * job running.
 */
it('adds nothing to the rollup when a session is cancelled', function () {
    $this->freezeTime();

    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
        'roadmap_item_id' => $this->item->id,
    ])->assertCreated();

    $this->travel(20)->minutes();
    $this->postJson('/api/v1/sprints/'.Sprint::query()->sole()->id.'/cancel')->assertOk();

    expect($this->item->fresh()->time_spent_seconds)->toBe(0)
        ->and(GoalStats::query()->where('goal_id', $this->goal->id)->count())->toBe(0);
});

/**
 * The projection has to survive the real pipeline too, and stay null while
 * the evidence is thin rather than producing a confident date from one
 * session (FR-ANL-02).
 */
it('leaves the projection null until there is enough history', function () {
    $this->freezeTime();

    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'countdown',
        'planned_duration_seconds' => 3600,
        'roadmap_item_id' => $this->item->id,
    ])->assertCreated();

    $this->travel(60)->minutes();
    $this->postJson('/api/v1/sprints/'.Sprint::query()->sole()->id.'/complete')->assertOk();

    $this->getJson("/api/v1/goals/{$this->goal->id}")
        ->assertOk()
        ->assertJsonPath('data.stats.projected_completion_date', null);
});
