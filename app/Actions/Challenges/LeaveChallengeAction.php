<?php

namespace App\Actions\Challenges;

use App\Models\ActivityLog;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LeaveChallengeAction
{
    /**
     * @throws ValidationException
     */
    public function __invoke(User $actor, Challenge $challenge): void
    {
        $removed = $challenge->participants()->where('user_id', $actor->id)->delete();

        if ($removed === 0) {
            throw ValidationException::withMessages([
                'challenge' => 'You are not part of this challenge.',
            ]);
        }

        ActivityLog::create([
            'user_id' => $actor->id,
            'subject_type' => Challenge::class,
            'subject_id' => $challenge->id,
            'action' => 'challenge.left',
            'meta' => null,
        ]);
    }
}
