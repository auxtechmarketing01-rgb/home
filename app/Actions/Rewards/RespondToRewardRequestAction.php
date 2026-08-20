<?php

namespace App\Actions\Rewards;

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\ActivityLog;
use App\Models\Reward;
use App\Models\User;
use App\Notifications\RewardRequestRespondedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RespondToRewardRequestAction
{
    /**
     * FR-RWD-03/07: the mentor accepts or denies a `requested` reward.
     *
     * The source-state check lives here rather than in RewardPolicy so a
     * wrong *state* returns 422 while a wrong *actor* returns 403 (06 §1.2).
     *
     * @throws ValidationException
     */
    public function __invoke(User $mentor, Reward $reward, bool $accepted, ?string $note = null): Reward
    {
        if ($reward->status !== 'requested') {
            throw ValidationException::withMessages([
                'status' => 'Only a requested reward can be responded to.',
            ]);
        }

        return DB::transaction(function () use ($mentor, $reward, $accepted, $note): Reward {
            $reward->forceFill([
                'status' => $accepted ? 'offered' : 'denied',
                'fulfilled_note' => $note ?? $reward->fulfilled_note,
            ])->save();

            $reward->mentorship?->mentee?->notify(new RewardRequestRespondedNotification($reward));

            ActivityLog::create([
                'user_id' => $mentor->id,
                'subject_type' => Reward::class,
                'subject_id' => $reward->id,
                'action' => $accepted ? 'reward.request_accepted' : 'reward.request_denied',
                'meta' => null,
            ]);

            /**
             * FR-RWD-03 says an accepted request converts to "offered/earned
             * as appropriate" — the mentee may well have finished the work
             * while waiting for an answer. That decision is left to the one
             * Action that owns the `offered` -> `earned` transition rather
             * than duplicated here.
             */
            if ($accepted) {
                $goal = $reward->goal ?? $reward->roadmapItem?->resolveGoal();

                if ($goal !== null) {
                    RecalculateGoalStatsJob::dispatch($goal);
                }
            }

            return $reward;
        });
    }
}
