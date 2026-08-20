<?php

namespace App\Jobs;

use App\Models\Reward;
use App\Notifications\RewardClaimedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Nudges a mentor about a reward that has sat `claimed` and unfulfilled for
 * longer than the configured grace period (02 §6, default 3 days).
 *
 * This job targets the single most common real-world failure mode found
 * across every chore/reward app in the research behind this product (01 §2):
 * the parent simply forgets to actually deliver. Nothing in the app can hand
 * over the money — so the only useful thing it can do is keep asking.
 *
 * It re-sends the claim notification rather than introducing a separate
 * "reminder" type: the mentor needs the same information and the same action,
 * and a second wording would just be a second thing to keep in step.
 */
class SendRewardClaimReminderJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $graceDays = max(1, (int) config('pathforge.rewards.claim_reminder_grace_days'));
        $cutoff = now()->subDays($graceDays);

        Reward::query()
            ->where('status', 'claimed')
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<=', $cutoff)
            ->with('mentorship.mentor')
            ->chunkById(200, function ($rewards): void {
                foreach ($rewards as $reward) {
                    /**
                     * An ended mentorship stops granting anything going
                     * forward (FR-MENT-07), so there is nobody to usefully
                     * remind.
                     */
                    if (! $reward->mentorship?->isAccepted()) {
                        continue;
                    }

                    $reward->mentorship->mentor?->notify(new RewardClaimedNotification($reward));
                }
            });
    }
}
