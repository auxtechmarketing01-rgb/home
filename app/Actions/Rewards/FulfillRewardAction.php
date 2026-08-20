<?php

namespace App\Actions\Rewards;

use App\Models\ActivityLog;
use App\Models\Reward;
use App\Models\User;
use App\Notifications\RewardFulfilledNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FulfillRewardAction
{
    /**
     * FR-RWD-05: the mentor records that they actually delivered it.
     *
     * Only from `claimed`. **This moves no money.** It writes down that
     * something happened outside the app — cash handed over, a privilege
     * granted, a bank transfer the family made themselves. There is no
     * payment rail here and no balance anywhere, on purpose (01 NFR
     * Financial integrity); `fulfilled_note` is where "paid in cash, Aug 20"
     * goes.
     *
     * @throws ValidationException
     */
    public function __invoke(User $mentor, Reward $reward, ?string $note = null): Reward
    {
        if ($reward->status !== 'claimed') {
            throw ValidationException::withMessages([
                'status' => 'Only a claimed reward can be fulfilled.',
            ]);
        }

        return DB::transaction(function () use ($mentor, $reward, $note): Reward {
            $reward->forceFill([
                'status' => 'fulfilled',
                'fulfilled_at' => now(),
                'fulfilled_note' => $note ?? $reward->fulfilled_note,
            ])->save();

            $reward->mentorship?->mentee?->notify(new RewardFulfilledNotification($reward));

            ActivityLog::create([
                'user_id' => $mentor->id,
                'subject_type' => Reward::class,
                'subject_id' => $reward->id,
                'action' => 'reward.fulfilled',
                'meta' => ['note' => $reward->fulfilled_note],
            ]);

            return $reward;
        });
    }
}
