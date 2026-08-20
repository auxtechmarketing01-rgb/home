<?php

use App\Jobs\CleanupStaleSprintsJob;
use App\Models\Sprint;
use App\Models\User;

/**
 * 06 §1.3: "the test that most directly protects FR-SPR-09."
 *
 * If this file goes red in the direction of "more sprints got cancelled", the
 * app has silently reintroduced the exact bug the feature exists to prevent:
 * a pomodoro that stops because the member walked away from the tab. Err long
 * and a stale row waits for a manual cancel; err short and real work is
 * destroyed.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->job = new CleanupStaleSprintsJob;
});

it('leaves a sprint running 90 minutes past its 25 minute plan completely alone', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(90),
    ]);

    $this->job->handle();

    $sprint->refresh();

    expect($sprint->status)->toBe('running')
        ->and($sprint->ended_at)->toBeNull();
});

/**
 * The scenario 04 Phase 2 calls out by name.
 */
it('leaves a sprint running three hours past its plan alone', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subHours(3),
    ]);

    $this->job->handle();

    expect($sprint->fresh()->status)->toBe('running');
});

it('auto cancels a sprint started 25 hours ago', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subHours(25),
    ]);

    $this->job->handle();

    $sprint->refresh();

    expect($sprint->status)->toBe('cancelled')
        ->and($sprint->ended_at)->not->toBeNull()
        /** An abandoned session contributes no time, same as a manual cancel. */
        ->and($sprint->actual_duration_seconds)->toBeNull();
});

/**
 * The boundary itself, in both directions. This is the pair of assertions
 * that pins the grace period down.
 */
it('does not touch a sprint one minute inside the grace period', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'started_at' => now()->subHours(24)->addMinutes(1),
    ]);

    $this->job->handle();

    expect($sprint->fresh()->status)->toBe('running');
});

it('cancels a sprint one minute past the grace period', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'started_at' => now()->subHours(24)->subMinutes(1),
    ]);

    $this->job->handle();

    expect($sprint->fresh()->status)->toBe('cancelled');
});

it('also recovers a long abandoned paused sprint', function () {
    $sprint = Sprint::factory()->for($this->user)->paused()->create([
        'started_at' => now()->subHours(30),
        'paused_at' => now()->subHours(29),
    ]);

    $this->job->handle();

    $sprint->refresh();

    expect($sprint->status)->toBe('cancelled')
        ->and($sprint->paused_at)->toBeNull();
});

it('never touches an already finished sprint', function () {
    $completed = Sprint::factory()->for($this->user)->completed(1500)->create([
        'started_at' => now()->subHours(40),
    ]);

    $cancelled = Sprint::factory()->for($this->user)->cancelled()->create([
        'started_at' => now()->subHours(40),
    ]);

    $this->job->handle();

    expect($completed->fresh()->status)->toBe('completed')
        ->and($completed->fresh()->actual_duration_seconds)->toBe(1500)
        ->and($cancelled->fresh()->status)->toBe('cancelled');
});

/**
 * A stopwatch has no planned duration at all, so the only thing that could
 * ever end it early is this job — which makes its grace period the only thing
 * protecting an open-ended session.
 */
it('respects the grace period for an open ended stopwatch', function () {
    $withinGrace = Sprint::factory()->for($this->user)->stopwatch()->running()->create([
        'started_at' => now()->subHours(6),
    ]);

    $abandoned = Sprint::factory()->for($this->user)->stopwatch()->running()->create([
        'started_at' => now()->subHours(48),
    ]);

    $this->job->handle();

    expect($withinGrace->fresh()->status)->toBe('running')
        ->and($abandoned->fresh()->status)->toBe('cancelled');
});

it('honours a configured grace period', function () {
    config(['pathforge.sprints.stale_grace_hours' => 72]);

    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'started_at' => now()->subHours(48),
    ]);

    $this->job->handle();

    expect($sprint->fresh()->status)->toBe('running');
});

it('records the recovery in the activity feed', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'started_at' => now()->subHours(25),
    ]);

    $this->job->handle();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $this->user->id,
        'subject_type' => Sprint::class,
        'subject_id' => $sprint->id,
        'action' => 'sprint.abandoned',
    ]);
});
