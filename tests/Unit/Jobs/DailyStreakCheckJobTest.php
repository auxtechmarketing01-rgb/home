<?php

use App\Jobs\DailyStreakCheckJob;
use App\Models\Badge;
use App\Models\Goal;
use App\Models\Sprint;
use App\Models\Streak;
use App\Models\User;
use App\Notifications\StreakAtRiskNotification;
use Illuminate\Support\Facades\Notification;

/**
 * FR-GAM-01, FR-GAM-03 and FR-NOT-02.
 *
 * The job runs **hourly** despite its name, because members are in different
 * timezones and there is no single UTC hour that is evening everywhere. These
 * tests pin the two consequences of that: it acts only when the member's own
 * reminder hour has arrived, and it nags at most once per *their* local day.
 */
beforeEach(function () {
    Notification::fake();

    foreach (['streak_7', 'streak_30', 'streak_100', 'first_goal_completed'] as $key) {
        Badge::factory()->create(['key' => $key]);
    }

    $this->job = new DailyStreakCheckJob;
});

function logDays(User $user, Goal $goal, array $daysAgo): void
{
    foreach ($daysAgo as $offset) {
        Sprint::factory()->for($user)->completed(1800)->create([
            'goal_id' => $goal->id,
            'ended_at' => now()->subDays($offset),
        ]);
    }
}

it('writes the per member streak row', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    $goal = Goal::factory()->for($user)->create();

    logDays($user, $goal, [0, 1, 2]);

    app()->call([$this->job, 'handle']);

    $streak = Streak::query()->where('user_id', $user->id)->sole();

    expect($streak->current_streak)->toBe(3)
        ->and($streak->longest_streak)->toBe(3)
        ->and($streak->last_active_date->toDateString())->toBe(now()->toDateString());
});

/**
 * The longest streak is a historical high-water mark. Recomputing it from a
 * window whose activity has since been archived would quietly take it away.
 */
it('never lowers a previously recorded longest streak', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    $goal = Goal::factory()->for($user)->create();

    Streak::factory()->create(['user_id' => $user->id, 'longest_streak' => 40]);

    logDays($user, $goal, [0, 1]);

    app()->call([$this->job, 'handle']);

    $streak = Streak::query()->where('user_id', $user->id)->sole();

    expect($streak->current_streak)->toBe(2)
        ->and($streak->longest_streak)->toBe(40);
});

/**
 * FR-GOAL-03: archiving a goal stops it counting toward streak logic.
 */
it('drops activity on an archived goal from the streak', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    $goal = Goal::factory()->for($user)->create();

    logDays($user, $goal, [0, 1, 2]);
    $goal->delete();

    app()->call([$this->job, 'handle']);

    expect(Streak::query()->where('user_id', $user->id)->sole()->current_streak)->toBe(0);
});

/**
 * FR-SPR-01: a session with no goal is still the member showing up.
 */
it('counts a general focus session with no goal', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    Sprint::factory()->for($user)->completed(1800)->create(['goal_id' => null]);

    app()->call([$this->job, 'handle']);

    expect(Streak::query()->where('user_id', $user->id)->sole()->current_streak)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| FR-NOT-02: the at-risk reminder
|--------------------------------------------------------------------------
*/

it('nudges a member whose local evening has arrived with nothing logged', function () {
    config(['pathforge.streaks.reminder_hour' => 20]);
    $this->travelTo('2026-03-10 21:00:00');

    $user = User::factory()->create(['timezone' => 'UTC']);
    $goal = Goal::factory()->for($user)->create();
    logDays($user, $goal, [1, 2]);

    app()->call([$this->job, 'handle']);

    Notification::assertSentToTimes($user, StreakAtRiskNotification::class, 1);
});

it('stays quiet before the member own reminder hour', function () {
    config(['pathforge.streaks.reminder_hour' => 20]);

    /** 21:00 UTC is 03:00 the next day in Dhaka — nowhere near their evening. */
    $this->travelTo('2026-03-10 21:00:00');

    $user = User::factory()->create(['timezone' => 'Asia/Dhaka']);
    $goal = Goal::factory()->for($user)->create();
    logDays($user, $goal, [2, 3]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});

it('stays quiet when the member has already been active today', function () {
    config(['pathforge.streaks.reminder_hour' => 20]);
    $this->travelTo('2026-03-10 21:00:00');

    $user = User::factory()->create(['timezone' => 'UTC']);
    $goal = Goal::factory()->for($user)->create();
    logDays($user, $goal, [0]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});

/**
 * The reason the job can safely run hourly.
 */
it('nags at most once per local day however often it runs', function () {
    config(['pathforge.streaks.reminder_hour' => 20]);
    $this->travelTo('2026-03-10 20:30:00');

    $user = User::factory()->create(['timezone' => 'UTC']);
    $goal = Goal::factory()->for($user)->create();
    logDays($user, $goal, [1]);

    app()->call([$this->job, 'handle']);
    $this->travelTo('2026-03-10 21:30:00');
    app()->call([$this->job, 'handle']);
    $this->travelTo('2026-03-10 22:30:00');
    app()->call([$this->job, 'handle']);

    Notification::assertSentToTimes($user, StreakAtRiskNotification::class, 1);
});

it('nudges again the following local day', function () {
    config(['pathforge.streaks.reminder_hour' => 20]);
    $this->travelTo('2026-03-10 21:00:00');

    $user = User::factory()->create(['timezone' => 'UTC']);
    $goal = Goal::factory()->for($user)->create();
    logDays($user, $goal, [1]);

    app()->call([$this->job, 'handle']);

    $this->travelTo('2026-03-11 21:00:00');
    app()->call([$this->job, 'handle']);

    Notification::assertSentToTimes($user, StreakAtRiskNotification::class, 2);
});

it('never nudges a disabled account', function () {
    config(['pathforge.streaks.reminder_hour' => 20]);
    $this->travelTo('2026-03-10 21:00:00');

    $user = User::factory()->create(['timezone' => 'UTC', 'disabled_at' => now()]);
    $goal = Goal::factory()->for($user)->create();
    logDays($user, $goal, [1]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});

/*
|--------------------------------------------------------------------------
| FR-GAM-03: badges
|--------------------------------------------------------------------------
*/

it('awards a streak badge once the milestone is reached', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    $goal = Goal::factory()->for($user)->create();

    logDays($user, $goal, range(0, 7));

    app()->call([$this->job, 'handle']);

    expect($user->fresh()->badges->pluck('key'))->toContain('streak_7')
        ->and($user->fresh()->badges->pluck('key'))->not->toContain('streak_30');
});

it('never awards the same badge twice', function () {
    $this->freezeTime();

    $user = User::factory()->create(['timezone' => 'UTC']);
    $goal = Goal::factory()->for($user)->create();
    logDays($user, $goal, range(0, 7));

    app()->call([$this->job, 'handle']);
    app()->call([$this->job, 'handle']);
    app()->call([$this->job, 'handle']);

    expect($user->fresh()->badges)->toHaveCount(1);
});

/**
 * FR-GAM-02/03 are opt-in, and the research behind this product specifically
 * flagged gamification fatigue — so opting out means nothing is awarded, not
 * a hidden tally waiting to reappear.
 */
it('awards nothing to a member who opted out of gamification', function () {
    $this->freezeTime();

    $user = User::factory()->withoutGamification()->create(['timezone' => 'UTC']);
    $goal = Goal::factory()->for($user)->create();
    logDays($user, $goal, range(0, 7));

    app()->call([$this->job, 'handle']);

    expect($user->fresh()->badges)->toHaveCount(0)
        ->and($user->fresh()->xp)->toBe(0)
        ->and($user->fresh()->level)->toBe(1);

    /** The streak itself is still tracked — it is not a gamification feature. */
    expect(Streak::query()->where('user_id', $user->id)->sole()->current_streak)->toBe(8);
});
