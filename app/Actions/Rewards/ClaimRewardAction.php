<?php

namespace App\Actions\Rewards;

use App\Models\ActivityLog;
use App\Models\Reward;
use App\Models\User;
use App\Notifications\RewardClaimedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimRewardAction
{
    /**
     * FR-RWD-04: the mentee demands payout of a reward they have earned.
     *
     * Only from `earned`. Claiming a merely `offered` reward is a 422, not a
     * 403 — the actor is right, the moment is not (06 §1.2). This is the
     * human approval step the research found every chore/reward app puts
     * between "task done" and "reward delivered": nothing auto-credits.
     *
     * @throws ValidationException
     */
    public function __invoke(User $mentee, Reward $reward): Reward
    {
        if ($reward->status !== 'earned') {
            throw ValidationException::withMessages([
                'status' => 'Only an earned reward can be claimed.',
            ]);
        }

        return DB::transaction(function () use ($mentee, $reward): Reward {
            $reward->forceFill([
                'status' => 'claimed',
                'claimed_at' => now(),
            ])->save();

            $reward->mentorship?->mentor?->notify(new RewardClaimedNotification($reward));

            ActivityLog::create([
                'user_id' => $mentee->id,
                'subject_type' => Reward::class,
                'subject_id' => $reward->id,
                'action' => 'reward.claimed',
                'meta' => null,
            ]);

            return $reward;
        });
    }
}
