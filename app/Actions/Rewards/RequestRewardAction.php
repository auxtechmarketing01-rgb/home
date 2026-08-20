<?php

namespace App\Actions\Rewards;

use App\Models\ActivityLog;
use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\User;
use App\Notifications\RewardOfferedNotification;
use Illuminate\Support\Facades\DB;

class RequestRewardAction
{
    /**
     * FR-RWD-03 — the literal "demand rewards from mentors" requirement: the
     * mentee asks for something that was never offered.
     *
     * It lands in `requested`, which is a distinct entry point into the state
     * machine rather than a shortcut into `offered`. Nothing has been promised
     * yet, and MarkRewardsEarnedForItemAction deliberately leaves `requested`
     * rewards alone even when the linked work is finished — completing work
     * cannot conjure a promise nobody made.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(User $mentee, Mentorship $mentorship, array $attributes): Reward
    {
        return DB::transaction(function () use ($mentee, $mentorship, $attributes): Reward {
            $reward = new Reward;
            $reward->fill($attributes);
            $reward->forceFill([
                'mentorship_id' => $mentorship->id,
                'status' => 'requested',
                'requested_by' => 'mentee',
            ])->save();

            /**
             * The mentor is notified with the "offered" payload shape because
             * the SPA renders both from one RewardCard keyed on `status` —
             * `requested` shows accept/deny to a mentor (03 §2.2).
             */
            $mentorship->mentor?->notify(new RewardOfferedNotification($reward));

            ActivityLog::create([
                'user_id' => $mentee->id,
                'subject_type' => Reward::class,
                'subject_id' => $reward->id,
                'action' => 'reward.requested',
                'meta' => ['mentorship_id' => $mentorship->id, 'title' => $reward->title],
            ]);

            return $reward;
        });
    }
}
