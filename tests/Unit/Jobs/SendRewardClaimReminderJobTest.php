<?php

use App\Jobs\SendRewardClaimReminderJob;
use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\User;
use App\Notifications\RewardClaimedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * 02 §6. This job exists because of the single most common real-world failure
 * mode found across every chore/reward app in the research (01 §2): the parent
 * simply forgets to actually deliver. Nothing in the app can hand over the
 * money, so the only useful thing it can do is keep asking.
 */
beforeEach(function () {
    Notification::fake();

    $this->mentor = User::factory()->create();
    $this->mentee = User::factory()->create();

    $this->mentorship = Mentorship::factory()
        ->accepted()
        ->between($this->mentor, $this->mentee)
        ->create();

    $this->job = new SendRewardClaimReminderJob;
});

it('nudges the mentor about a claim left unfulfilled past the grace period', function () {
    Reward::factory()->claimed()->create([
        'mentorship_id' => $this->mentorship->id,
        'claimed_at' => now()->subDays(5),
    ]);

    app()->call([$this->job, 'handle']);

    Notification::assertSentToTimes($this->mentor, RewardClaimedNotification::class, 1);
});

it('stays quiet while the claim is still inside the grace period', function () {
    Reward::factory()->claimed()->create([
        'mentorship_id' => $this->mentorship->id,
        'claimed_at' => now()->subDay(),
    ]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});

it('honours a configured grace period', function () {
    config(['pathforge.rewards.claim_reminder_grace_days' => 10]);

    Reward::factory()->claimed()->create([
        'mentorship_id' => $this->mentorship->id,
        'claimed_at' => now()->subDays(5),
    ]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});

it('never nudges about a reward that is not claimed', function () {
    foreach (['offered', 'earned', 'fulfilled', 'revoked', 'denied'] as $status) {
        Reward::factory()->create([
            'mentorship_id' => $this->mentorship->id,
            'status' => $status,
            'claimed_at' => now()->subDays(10),
        ]);
    }

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});

/**
 * FR-MENT-07: an ended relationship grants nothing going forward, so there is
 * nobody to usefully remind.
 */
it('never nudges through an ended mentorship', function () {
    $this->mentorship->forceFill(['status' => 'ended'])->save();

    Reward::factory()->claimed()->create([
        'mentorship_id' => $this->mentorship->id,
        'claimed_at' => now()->subDays(5),
    ]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSent();
});

it('nudges the mentor and never the mentee', function () {
    Reward::factory()->claimed()->create([
        'mentorship_id' => $this->mentorship->id,
        'claimed_at' => now()->subDays(5),
    ]);

    app()->call([$this->job, 'handle']);

    Notification::assertNothingSentTo($this->mentee);
});
