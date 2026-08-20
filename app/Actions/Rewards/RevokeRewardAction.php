<?php

namespace App\Actions\Rewards;

use App\Models\ActivityLog;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevokeRewardAction
{
    /**
     * FR-RWD-07: a mentor withdraws an offer.
     *
     * Only from `offered` — **never** from `earned`. That restriction is the
     * whole point: once the mentee has done the work, the promise is theirs,
     * and letting a mentor revoke afterwards would make every offer
     * worthless. Attempting it is a 422.
     *
     * @throws ValidationException
     */
    public function __invoke(User $mentor, Reward $reward, ?string $note = null): Reward
    {
        if ($reward->status !== 'offered') {
            throw ValidationException::withMessages([
                'status' => 'Only an offer that has not been earned yet can be revoked.',
            ]);
        }

        return DB::transaction(function () use ($mentor, $reward, $note): Reward {
            $reward->forceFill([
                'status' => 'revoked',
                'fulfilled_note' => $note ?? $reward->fulfilled_note,
            ])->save();

            ActivityLog::create([
                'user_id' => $mentor->id,
                'subject_type' => Reward::class,
                'subject_id' => $reward->id,
                'action' => 'reward.revoked',
                'meta' => ['note' => $note],
            ]);

            return $reward;
        });
    }
}
