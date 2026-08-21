<?php

use App\Jobs\SendSprintReminderJob;
use App\Models\Sprint;
use App\Models\User;
use App\Notifications\SprintReminderNotification;
use Illuminate\Support\Facades\Notification;

/**
 * 02 §6: "notify user if no sprint started by a configured time" — **opt-in**.
 *
 * Opt-in is the property worth testing hardest. An unrequested daily nudge is
 * the shape of notification people mute an app over, so a member with no
 * `settings.sprint_reminder_hour` must hear nothing at all.
 */
beforeEach(function () {
    Notification::fake();

    $this->job = new SendSprintReminderJob;
});

it('says nothing to a member who never opted in', function () {
    $this->travelTo('2026-03-10 09:00:00');

    User::factory()->create(['timezone' => 'UTC', 'settings' => null]);
    User::factory()->create(['timezone' => 'UTC', 'settings' => ['gamification_enabled' => true]]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});

it('nudges an opted in member at their own local hour', function () {
    $this->travelTo('2026-03-10 09:00:00');

    $user = User::factory()->create([
        'timezone' => 'UTC',
        'settings' => ['sprint_reminder_hour' => 9],
    ]);

    app()->call([$this->job, 'handle']);

    Notification::assertSentToTimes($user, SprintReminderNotification::class, 1);
});

/**
 * The reason the job runs hourly: 09:00 UTC is 15:00 in Dhaka, so a Dhaka
 * member who asked for a 09:00 reminder must not be nudged now.
 */
it('respects the member own timezone rather than the server hour', function () {
    $this->travelTo('2026-03-10 09:00:00');

    $dhaka = User::factory()->create([
        'timezone' => 'Asia/Dhaka',
        'settings' => ['sprint_reminder_hour' => 9],
    ]);

    app()->call([$this->job, 'handle']);
    Notification::assertNothingSent();

    /** 03:00 UTC is 09:00 in Dhaka. */
    $this->travelTo('2026-03-10 03:00:00');
    app()->call([$this->job, 'handle']);

    Notification::assertSentToTimes($dhaka, SprintReminderNotification::class, 1);
});

it('stays quiet once the member has already started a session today', function () {
    $this->travelTo('2026-03-10 09:00:00');

    $user = User::factory()->create([
        'timezone' => 'UTC',
        'settings' => ['sprint_reminder_hour' => 9],
    ]);

    Sprint::factory()->for($user)->running()->create(['started_at' => now()->subHour()]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});

/**
 * A session from yesterday does not count — the reminder is about today.
 */
it('still nudges when the last session was yesterday', function () {
    $this->travelTo('2026-03-10 09:00:00');

    $user = User::factory()->create([
        'timezone' => 'UTC',
        'settings' => ['sprint_reminder_hour' => 9],
    ]);

    Sprint::factory()->for($user)->completed()->create(['started_at' => now()->subDay()]);

    app()->call([$this->job, 'handle']);

    Notification::assertSentToTimes($user, SprintReminderNotification::class, 1);
});

it('never nudges a disabled account', function () {
    $this->travelTo('2026-03-10 09:00:00');

    User::factory()->create([
        'timezone' => 'UTC',
        'settings' => ['sprint_reminder_hour' => 9],
        'disabled_at' => now(),
    ]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});

it('ignores a malformed reminder hour rather than failing', function () {
    $this->travelTo('2026-03-10 09:00:00');

    User::factory()->create([
        'timezone' => 'UTC',
        'settings' => ['sprint_reminder_hour' => 'nine'],
    ]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});
