<?php

namespace App\Actions\Challenges;

use App\Models\ActivityLog;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\Goal;
use App\Models\User;
use App\Notifications\ChallengeUpdateNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinChallengeAction
{
    /**
     * FR-GRP-04. Joining is explicit — that is what separates a challenge
     * from the passive leaderboard.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function __invoke(User $actor, Challenge $challenge, array $attributes = []): ChallengeParticipant
    {
        return DB::transaction(function () use ($actor, $challenge, $attributes): ChallengeParticipant {
            if ($challenge->hasParticipant($actor)) {
                throw ValidationException::withMessages([
                    'challenge' => 'You have already joined this challenge.',
                ]);
            }

            $goalId = $attributes['goal_id'] ?? null;

            if ($goalId !== null) {
                $owned = Goal::query()->whereKey($goalId)->where('user_id', $actor->id)->exists();

                if (! $owned) {
                    throw ValidationException::withMessages([
                        'goal_id' => 'You can only enter a challenge with one of your own goals.',
                    ]);
                }
            }

            $participant = new ChallengeParticipant;
            $participant->forceFill([
                'challenge_id' => $challenge->id,
                'user_id' => $actor->id,
                'goal_id' => $goalId,
                'joined_at' => now(),
            ])->save();

            /**
             * The other participants are told — the social pull is the point
             * of a challenge. The joiner is excluded so nobody is notified
             * about their own action.
             */
            $others = $challenge->participants()
                ->with('user')
                ->where('user_id', '!=', $actor->id)
                ->get()
                ->pluck('user')
                ->filter();

            foreach ($others as $other) {
                $other->notify(new ChallengeUpdateNotification($challenge, $actor, 'joined'));
            }

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => Challenge::class,
                'subject_id' => $challenge->id,
                'action' => 'challenge.joined',
                'meta' => ['goal_id' => $goalId],
            ]);

            return $participant;
        });
    }
}
