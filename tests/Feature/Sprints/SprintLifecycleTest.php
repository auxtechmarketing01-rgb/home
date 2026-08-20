<?php

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->goal = Goal::factory()->for($this->user)->create();
    $this->roadmap = Roadmap::factory()->for($this->goal)->create();
    $this->item = RoadmapItem::factory()->for($this->roadmap)->create();

    Sanctum::actingAs($this->user);
});

/**
 * FR-SPR-01, FR-SPR-02.
 */
it('starts a sprint against a roadmap item', function () {
    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
        'break_seconds' => 300,
        'roadmap_item_id' => $this->item->id,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.mode', 'pomodoro')
        ->assertJsonPath('data.is_overtime', false);

    $sprint = Sprint::query()->sole();

    expect($sprint->user_id)->toBe($this->user->id)
        ->and($sprint->started_at)->not->toBeNull()
        ->and($sprint->actual_duration_seconds)->toBeNull();
});

/**
 * A sprint on an item always belongs to that item's goal, so the link is
 * backfilled rather than left to the client to send consistently.
 */
it('backfills the goal from the roadmap item', function () {
    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'countdown',
        'planned_duration_seconds' => 600,
        'roadmap_item_id' => $this->item->id,
    ])->assertCreated();

    expect(Sprint::query()->sole()->goal_id)->toBe($this->goal->id);
});

it('starts a general focus session with no goal or item', function () {
    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'stopwatch',
    ])->assertCreated()->assertJsonPath('data.planned_duration_seconds', null);

    $sprint = Sprint::query()->sole();

    expect($sprint->goal_id)->toBeNull()
        ->and($sprint->roadmap_item_id)->toBeNull()
        ->and($sprint->deadlineAt())->toBeNull();
});

/**
 * FR-SPR-08. The gate test named in 04 Phase 2 — a deliberate 409, not an
 * unhandled 500.
 */
it('rejects starting a second sprint while one is already running', function () {
    Sprint::factory()->for($this->user)->running()->create();

    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
    ])->assertStatus(409);

    expect(Sprint::query()->count())->toBe(1);
});

it('rejects starting a second sprint while one is paused', function () {
    Sprint::factory()->for($this->user)->paused()->create();

    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
    ])->assertStatus(409);

    expect(Sprint::query()->count())->toBe(1);
});

it('allows a new sprint once the previous one has finished', function () {
    Sprint::factory()->for($this->user)->completed()->create();

    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
    ])->assertCreated();

    expect(Sprint::query()->count())->toBe(2);
});

/**
 * Logging time against another member's goal would corrupt *their* stats and
 * leaderboard standing, so it is refused as an authorization failure rather
 * than quietly accepted.
 */
it('refuses to log time against another member goal', function () {
    $foreignGoal = Goal::factory()->create();

    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
        'goal_id' => $foreignGoal->id,
    ])->assertForbidden();

    expect(Sprint::query()->count())->toBe(0);
});

it('refuses to log time against another member roadmap item', function () {
    $foreignItem = RoadmapItem::factory()->create();

    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
        'roadmap_item_id' => $foreignItem->id,
    ])->assertForbidden();
});

it('requires a planned duration for every mode except stopwatch', function () {
    $this->postJson('/api/v1/sprints/start', ['mode' => 'pomodoro'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['planned_duration_seconds']);
});

it('rejects a planned duration on a stopwatch', function () {
    $this->postJson('/api/v1/sprints/start', [
        'mode' => 'stopwatch',
        'planned_duration_seconds' => 1500,
    ])->assertUnprocessable()->assertJsonValidationErrors(['planned_duration_seconds']);
});

/**
 * FR-SPR-04: paused time is excluded from the recorded duration.
 */
it('excludes paused time from the recorded duration', function () {
    $this->freezeTime();

    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'goal_id' => $this->goal->id,
        'started_at' => now(),
    ]);

    $this->travel(10)->minutes();
    $this->postJson("/api/v1/sprints/{$sprint->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', 'paused');

    $this->travel(5)->minutes();
    $this->postJson("/api/v1/sprints/{$sprint->id}/resume")
        ->assertOk()
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.paused_seconds_total', 300);

    $this->travel(10)->minutes();
    $this->postJson("/api/v1/sprints/{$sprint->id}/complete")->assertOk();

    /** 25 minutes of wall clock, 5 of them paused. */
    expect($sprint->fresh()->actual_duration_seconds)->toBe(1200);
});

it('folds an open pause into the total when completed while paused', function () {
    $this->freezeTime();

    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'goal_id' => $this->goal->id,
        'started_at' => now(),
    ]);

    $this->travel(10)->minutes();
    $this->postJson("/api/v1/sprints/{$sprint->id}/pause")->assertOk();

    $this->travel(4)->minutes();
    $this->postJson("/api/v1/sprints/{$sprint->id}/complete")->assertOk();

    $sprint->refresh();

    expect($sprint->paused_seconds_total)->toBe(240)
        ->and($sprint->actual_duration_seconds)->toBe(600)
        ->and($sprint->paused_at)->toBeNull();
});

it('rejects pausing a sprint that is not running', function () {
    $sprint = Sprint::factory()->for($this->user)->paused()->create();

    $this->postJson("/api/v1/sprints/{$sprint->id}/pause")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('rejects resuming a sprint that is not paused', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create();

    $this->postJson("/api/v1/sprints/{$sprint->id}/resume")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

/**
 * FR-SPR-05. The feature test asserts the dispatch and stops there; the
 * arithmetic has its own unit test (06 §1.2).
 */
it('dispatches the stats recalculation when a sprint completes', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'goal_id' => $this->goal->id,
        'roadmap_item_id' => $this->item->id,
    ]);

    $this->postJson("/api/v1/sprints/{$sprint->id}/complete")->assertOk();

    Queue::assertPushed(RecalculateGoalStatsJob::class);
});

it('does not dispatch a recalculation for a sprint with no goal', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create(['goal_id' => null]);

    $this->postJson("/api/v1/sprints/{$sprint->id}/complete")->assertOk();

    Queue::assertNotPushed(RecalculateGoalStatsJob::class);
});

it('records no time and no recalculation when a sprint is cancelled', function () {
    $this->freezeTime();

    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'goal_id' => $this->goal->id,
        'started_at' => now(),
    ]);

    $this->travel(12)->minutes();

    $this->postJson("/api/v1/sprints/{$sprint->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($sprint->fresh()->actual_duration_seconds)->toBeNull();

    Queue::assertNotPushed(RecalculateGoalStatsJob::class);
});

it('rejects completing a sprint that already finished', function () {
    $sprint = Sprint::factory()->for($this->user)->completed()->create();

    $this->postJson("/api/v1/sprints/{$sprint->id}/complete")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('returns the single active sprint for the session recovery endpoint', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create();

    $this->getJson('/api/v1/sprints/active')
        ->assertOk()
        ->assertJsonPath('data.id', $sprint->id);
});

it('returns a null active sprint rather than a 404 when nothing is running', function () {
    $this->getJson('/api/v1/sprints/active')->assertOk()->assertJsonPath('data', null);
});
