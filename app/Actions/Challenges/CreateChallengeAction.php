<?php

namespace App\Actions\Challenges;

use App\Models\ActivityLog;
use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\Goal;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateChallengeAction
{
    /**
     * FR-GRP-04. The creator is enrolled in the same transaction — a
     * challenge with no participants is not a challenge.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function __invoke(User $actor, Group $group, array $attributes): Challenge
    {
        return DB::transaction(function () use ($actor, $group, $attributes): Challenge {
            $goalId = $attributes['goal_id'] ?? null;

            if ($goalId !== null) {
                $this->assertGoalBelongsToActor($actor, (int) $goalId);
            }

            $challenge = new Challenge;
            $challenge->fill($attributes);
            $challenge->group_id = $group->id;
            $challenge->created_by = $actor->id;
            $challenge->save();

            $participant = new ChallengeParticipant;
            $participant->forceFill([
                'challenge_id' => $challenge->id,
                'user_id' => $actor->id,
                'goal_id' => $goalId,
                'joined_at' => now(),
            ])->save();

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => Challenge::class,
                'subject_id' => $challenge->id,
                'action' => 'challenge.created',
                'meta' => ['group_id' => $group->id, 'title' => $challenge->title],
            ]);

            return $challenge;
        });
    }

    /**
     * Racing with somebody else's goal would put their progress on your row.
     * A real authorization rule, so it lives here (02 §5).
     *
     * @throws ValidationException
     */
    protected function assertGoalBelongsToActor(User $actor, int $goalId): void
    {
        $owned = Goal::query()->whereKey($goalId)->where('user_id', $actor->id)->exists();

        if (! $owned) {
            throw ValidationException::withMessages([
                'goal_id' => 'You can only enter a challenge with one of your own goals.',
            ]);
        }
    }
}
