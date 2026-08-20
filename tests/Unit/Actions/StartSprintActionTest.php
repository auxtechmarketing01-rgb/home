<?php

use App\Actions\Sprints\StartSprintAction;
use App\Exceptions\SprintAlreadyRunningException;
use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * 06 §1.3 names this Action for direct testing. Its two guarantees — one
 * active sprint per member, and never logging time against someone else's
 * goal — are authorization and concurrency rules, not input validation, so
 * they must hold when the Action is called with no Form Request in front of
 * it (a console command, an importer, a future mobile client).
 */
beforeEach(function () {
    $this->action = app(StartSprintAction::class);

    $this->user = User::factory()->create();
    $this->goal = Goal::factory()->for($this->user)->create();
    $this->roadmap = Roadmap::factory()->for($this->goal)->create();
    $this->item = RoadmapItem::factory()->for($this->roadmap)->create();
});

it('starts a running sprint stamped with the server clock', function () {
    $this->freezeTime();

    $sprint = ($this->action)($this->user, [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
    ]);

    expect($sprint->status)->toBe('running')
        ->and($sprint->user_id)->toBe($this->user->id)
        ->and($sprint->started_at->toIso8601String())->toBe(now()->toIso8601String())
        ->and($sprint->ended_at)->toBeNull();
});

/**
 * FR-SPR-08, asserted on the exception rather than an HTTP status so the
 * rule is pinned to the Action that owns it.
 */
it('throws rather than starting a second concurrent sprint', function () {
    $active = Sprint::factory()->for($this->user)->running()->create();

    expect(fn () => ($this->action)($this->user, [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
    ]))->toThrow(SprintAlreadyRunningException::class);

    expect(Sprint::query()->count())->toBe(1);

    /** The response tells the client which sprint is in the way. */
    try {
        ($this->action)($this->user, ['mode' => 'stopwatch']);
    } catch (SprintAlreadyRunningException $exception) {
        expect($exception->activeSprint->id)->toBe($active->id);
    }
});

it('treats a paused sprint as occupying the active slot', function () {
    Sprint::factory()->for($this->user)->paused()->create();

    expect(fn () => ($this->action)($this->user, ['mode' => 'stopwatch']))
        ->toThrow(SprintAlreadyRunningException::class);
});

it('does not count another member active sprint against this member', function () {
    Sprint::factory()->for(User::factory()->create())->running()->create();

    $sprint = ($this->action)($this->user, ['mode' => 'stopwatch']);

    expect($sprint->status)->toBe('running');
});

it('backfills the goal from the roadmap item', function () {
    $sprint = ($this->action)($this->user, [
        'mode' => 'countdown',
        'planned_duration_seconds' => 600,
        'roadmap_item_id' => $this->item->id,
    ]);

    expect($sprint->goal_id)->toBe($this->goal->id);
});

/**
 * Logging time against another member's goal would corrupt *their* stats and
 * leaderboard standing, which is why this is checked in the Action and not
 * only in the Form Request (02 §5).
 */
it('refuses a goal belonging to another member', function () {
    $foreignGoal = Goal::factory()->create();

    expect(fn () => ($this->action)($this->user, [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
        'goal_id' => $foreignGoal->id,
    ]))->toThrow(AuthorizationException::class);

    expect(Sprint::query()->count())->toBe(0);
});

it('refuses a roadmap item belonging to another member', function () {
    $foreignItem = RoadmapItem::factory()->create();

    expect(fn () => ($this->action)($this->user, [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
        'roadmap_item_id' => $foreignItem->id,
    ]))->toThrow(AuthorizationException::class);
});

it('refuses a roadmap item that does not belong to the given goal', function () {
    $otherGoal = Goal::factory()->for($this->user)->create();

    expect(fn () => ($this->action)($this->user, [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
        'goal_id' => $otherGoal->id,
        'roadmap_item_id' => $this->item->id,
    ]))->toThrow(AuthorizationException::class);
});

it('records the start in the activity feed', function () {
    $sprint = ($this->action)($this->user, [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $this->user->id,
        'subject_type' => Sprint::class,
        'subject_id' => $sprint->id,
        'action' => 'sprint.started',
    ]);
});

/**
 * Lifecycle columns are not mass-assignable: a client cannot pre-date a
 * sprint or hand itself a duration.
 */
it('ignores lifecycle columns supplied by the caller', function () {
    $this->freezeTime();

    $sprint = ($this->action)($this->user, [
        'mode' => 'pomodoro',
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subDays(30),
        'status' => 'completed',
        'actual_duration_seconds' => 999999,
        'paused_seconds_total' => 4242,
    ]);

    expect($sprint->started_at->toIso8601String())->toBe(now()->toIso8601String())
        ->and($sprint->status)->toBe('running')
        ->and($sprint->actual_duration_seconds)->toBeNull()
        ->and($sprint->paused_seconds_total)->toBe(0);
});
