<?php

use App\Actions\Rewards\ClaimRewardAction;
use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\User;
use App\Notifications\RewardClaimedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * 06 §1.3 names this Action for direct testing, and an audit found it had none.
 *
 * The invariant it owns is the *source state*. RewardPolicy answers "is this
 * the mentee?"; this Action answers "is the reward earned yet?" — and the split
 * is what produces 403 for the wrong actor and 422 for the wrong moment
 * (06 §1.2). Tested here rather than only through HTTP because the state rule
 * has to hold for any caller.
 */
beforeEach(function () {
    Notification::fake();

    $this->mentor = User::factory()->create();
    $this->mentee = User::factory()->create();

    $this->mentorship = Mentorship::factory()
        ->accepted()
        ->between($this->mentor, $this->mentee)
        ->create();

    $this->action = app(ClaimRewardAction::class);
});

it('claims an earned reward and stamps the time', function () {
    $this->freezeTime();

    $reward = Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);

    $claimed = ($this->action)($this->mentee, $reward);

    expect($claimed->status)->toBe('claimed')
        ->and($claimed->claimed_at->toIso8601String())->toBe(now()->toIso8601String())
        /** Claiming is not delivery — that stays a human step (FR-RWD-05). */
        ->and($claimed->fulfilled_at)->toBeNull();

    Notification::assertSentTo($this->mentor, RewardClaimedNotification::class);
});

/**
 * Every state that is not `earned` is refused, one by one — this is the whole
 * point of the Action.
 */
it('refuses every state other than earned', function (string $state) {
    $reward = Reward::factory()->{$state}()->create(['mentorship_id' => $this->mentorship->id]);

    expect(fn () => ($this->action)($this->mentee, $reward))
        ->toThrow(ValidationException::class);

    expect($reward->fresh()->status)->toBe($reward->status)
        ->and($reward->fresh()->claimed_at?->toIso8601String())
        ->toBe($reward->claimed_at?->toIso8601String());
})->with(['requested', 'offered', 'denied', 'revoked', 'fulfilled']);

it('refuses to claim the same reward twice', function () {
    $reward = Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);

    ($this->action)($this->mentee, $reward);

    expect(fn () => ($this->action)($this->mentee, $reward->fresh()))
        ->toThrow(ValidationException::class);

    Notification::assertSentToTimes($this->mentor, RewardClaimedNotification::class, 1);
});

it('records the claim in the activity feed', function () {
    $reward = Reward::factory()->earned()->create(['mentorship_id' => $this->mentorship->id]);

    ($this->action)($this->mentee, $reward);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $this->mentee->id,
        'subject_type' => Reward::class,
        'subject_id' => $reward->id,
        'action' => 'reward.claimed',
    ]);
});

/**
 * FR-RWD-05: the app records that something happened outside it and moves no
 * money. A claim must not touch the amount.
 */
it('never alters the recorded amount', function () {
    $reward = Reward::factory()->earned()->monetary(500, 'BDT')
        ->create(['mentorship_id' => $this->mentorship->id]);

    $claimed = ($this->action)($this->mentee, $reward);

    expect((float) $claimed->monetary_amount)->toBe(500.0)
        ->and($claimed->currency_label)->toBe('BDT');
});
