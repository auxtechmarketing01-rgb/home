<?php

use App\Jobs\NotifyExpiredSprintsJob;
use App\Models\Sprint;
use App\Models\User;
use App\Notifications\SprintCompleteNotification;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * FR-SPR-10, with the dedup guarantee 06 §1.3 asks for.
 */
beforeEach(function () {
    Notification::fake();

    $this->user = User::factory()->create();
    $this->job = new NotifyExpiredSprintsJob;
});

it('sends exactly one notification for a sprint that just passed its plan', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(26),
    ]);

    $this->job->handle();

    Notification::assertSentToTimes($this->user, SprintCompleteNotification::class, 1);

    expect($sprint->fresh()->notified_expired_at)->not->toBeNull();
});

/**
 * The dedup test. Without it, a member working in overtime would be
 * interrupted every single minute.
 */
it('sends nothing on a second run for the same sprint', function () {
    Sprint::factory()->for($this->user)->running()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(26),
    ]);

    $this->job->handle();
    $this->job->handle();
    $this->job->handle();

    Notification::assertSentToTimes($this->user, SprintCompleteNotification::class, 1);
});

/**
 * The whole point of the job: it tells the member the plan was reached and
 * leaves the session running (FR-SPR-09). It must not touch `status`.
 */
it('never changes the sprint status', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(26),
    ]);

    $this->job->handle();

    $sprint->refresh();

    expect($sprint->status)->toBe('running')
        ->and($sprint->ended_at)->toBeNull()
        ->and($sprint->actual_duration_seconds)->toBeNull();
});

it('does not notify before the plan is reached', function () {
    Sprint::factory()->for($this->user)->running()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(10),
    ]);

    $this->job->handle();

    Notification::assertNothingSent();
});

it('never notifies a sprint that finished before its deadline', function () {
    Sprint::factory()->for($this->user)->completed(600)->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(40),
    ]);

    Sprint::factory()->for($this->user)->cancelled()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(40),
    ]);

    $this->job->handle();

    Notification::assertNothingSent();
});

/**
 * A stopwatch has no plan to reach (FR-SPR-02), so it can never expire —
 * otherwise every open-ended session would fire an alert immediately.
 */
it('never notifies a stopwatch', function () {
    Sprint::factory()->for($this->user)->stopwatch()->running()->create([
        'started_at' => now()->subHours(4),
    ]);

    $this->job->handle();

    Notification::assertNothingSent();
});

it('notifies each member about their own sprint only', function () {
    $other = User::factory()->create();

    Sprint::factory()->for($this->user)->running()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(26),
    ]);

    Sprint::factory()->for($other)->running()->create([
        'planned_duration_seconds' => 900,
        'started_at' => now()->subMinutes(20),
    ]);

    $this->job->handle();

    Notification::assertSentToTimes($this->user, SprintCompleteNotification::class, 1);
    Notification::assertSentToTimes($other, SprintCompleteNotification::class, 1);
});

/**
 * The copy has to match the behaviour: the session is *not* over.
 */
it('tells the member the session is still running', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create([
        'planned_duration_seconds' => 1500,
        'started_at' => now()->subMinutes(26),
    ]);

    $notification = new SprintCompleteNotification($sprint);
    $payload = $notification->toArray($this->user);

    expect($payload['title'])->toBe('Your 25-minute session is up')
        ->and($payload['body'])->toContain('Still running until you stop it')
        ->and($payload['sprint_id'])->toBe($sprint->id);
});

/**
 * This is one of the few notifications worth an OS-level interruption — it
 * exists precisely for the member who closed the tab.
 */
it('reaches a closed browser as well as the notification centre', function () {
    $sprint = Sprint::factory()->for($this->user)->running()->create();

    expect((new SprintCompleteNotification($sprint))->via($this->user))
        ->toBe(['database', 'broadcast', WebPushChannel::class]);
});
