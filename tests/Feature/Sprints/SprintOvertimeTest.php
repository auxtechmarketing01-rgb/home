<?php

use App\Models\Goal;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * FR-SPR-09, feature half.
 *
 * This is the requirement the whole sprint design exists to satisfy: passing
 * the planned duration is a notification and a UI state, never a status
 * change. If any of these tests goes red, the app has quietly reacquired the
 * behaviour of a client-side countdown that dies with its tab.
 *
 * The unit-level guard on the same requirement lives in
 * tests/Unit/Jobs/CleanupStaleSprintsJobTest.php.
 */
beforeEach(function () {
    Queue::fake();

    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

it('still reports a sprint as running three hours past its 25 minute plan', function () {
    $sprint = Sprint::factory()->for($this->user)->overtime(minutesPastDeadline: 180)->create();

    $this->getJson('/api/v1/sprints/active')
        ->assertOk()
        ->assertJsonPath('data.id', $sprint->id)
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.is_overtime', true);

    expect($sprint->fresh()->status)->toBe('running')
        ->and($sprint->fresh()->ended_at)->toBeNull();
});

it('reports how far past the plan the sprint has run', function () {
    $this->freezeTime();

    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(40),
    ]);

    $this->getJson('/api/v1/sprints?status=running')
        ->assertOk()
        ->assertJsonPath('data.0.is_overtime', true)
        ->assertJsonPath('data.0.overtime_seconds', 900);

    expect($sprint->fresh()->status)->toBe('running');
});

/**
 * There is no `overtime` status, by design (02 §3): it would be a second
 * representation of a timestamp comparison that already exists, and the two
 * would drift.
 */
it('never introduces an overtime status value', function () {
    $sprint = Sprint::factory()->for($this->user)->overtime()->create();

    expect($sprint->fresh()->status)->toBe('running')
        ->and($sprint->fresh()->isOvertime())->toBeTrue();
});

/**
 * A member deliberately working past the plan must still be able to stop when
 * *they* decide, and the extra time must count.
 */
it('records the full overtime duration when the member finally stops', function () {
    $this->freezeTime();

    $goal = Goal::factory()->for($this->user)->create();

    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'goal_id' => $goal->id,
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(70),
    ]);

    $this->postJson("/api/v1/sprints/{$sprint->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect($sprint->fresh()->actual_duration_seconds)->toBe(4200);
});

/**
 * A stopwatch has no plan to exceed (FR-SPR-02), so overtime is meaningless
 * for it rather than permanently true.
 */
it('never treats a stopwatch as overtime', function () {
    $sprint = Sprint::factory()->for($this->user)->stopwatch()->running()->create([
        'started_at' => now()->subHours(3),
    ]);

    expect($sprint->deadlineAt())->toBeNull()
        ->and($sprint->isOvertime())->toBeFalse()
        ->and($sprint->overtimeSeconds())->toBe(0);
});
