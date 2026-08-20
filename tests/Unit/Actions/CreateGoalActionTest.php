<?php

use App\Actions\Goals\CreateGoalAction;
use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\User;

/**
 * 06 §1.3 asks for this Action to be exercised directly, not only through
 * the controller, because its transactional guarantee is the point.
 */
it('creates the goal and its roadmap together', function () {
    $user = User::factory()->create();

    $goal = app(CreateGoalAction::class)($user, [
        'title' => 'Learn C Programming',
        'status' => 'active',
        'visibility' => 'private',
    ]);

    expect($goal->user_id)->toBe($user->id)
        ->and($goal->roadmap)->not->toBeNull()
        ->and($goal->roadmap->goal_id)->toBe($goal->id);
});

/**
 * FR-RM-01 says every Goal has exactly one Roadmap. If the roadmap write
 * fails, the goal must not survive on its own — otherwise the app carries a
 * goal that can never have a plan attached.
 */
it('rolls the goal back when the roadmap cannot be created', function () {
    $user = User::factory()->create();

    Roadmap::creating(function (): void {
        throw new RuntimeException('roadmap write failed');
    });

    expect(fn () => app(CreateGoalAction::class)($user, ['title' => 'Doomed']))
        ->toThrow(RuntimeException::class);

    expect(Goal::query()->count())->toBe(0)
        ->and(Roadmap::query()->count())->toBe(0);

    $this->assertDatabaseCount('activity_logs', 0);
});

it('records the creation in the activity feed', function () {
    $user = User::factory()->create();

    $goal = app(CreateGoalAction::class)($user, ['title' => 'Learn C']);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $user->id,
        'subject_type' => Goal::class,
        'subject_id' => $goal->id,
        'action' => 'goal.created',
    ]);
});

it('never takes the owner from the attribute array', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $goal = app(CreateGoalAction::class)($user, [
        'title' => 'Mine',
        'user_id' => $other->id,
    ]);

    expect($goal->user_id)->toBe($user->id);
});
