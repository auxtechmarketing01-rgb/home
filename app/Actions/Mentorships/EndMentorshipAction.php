<?php

namespace App\Actions\Mentorships;

use App\Models\ActivityLog;
use App\Models\Mentorship;
use App\Models\User;

class EndMentorshipAction
{
    /**
     * FR-MENT-07: either party, at any time.
     *
     * Note what this deliberately does **not** do: it touches no rewards.
     * Ending removes access going forward — every mentorship-derived grant
     * tests for `accepted`, so read access, `assign` and every reward ability
     * stop at once — while already-`fulfilled` rewards stay exactly as they
     * are. The ledger is a record of things that actually happened outside
     * the app (FR-RWD-05); rewriting it because a relationship ended would be
     * falsifying history.
     */
    public function __invoke(User $actor, Mentorship $mentorship): Mentorship
    {
        $mentorship->forceFill([
            'status' => 'ended',
            'responded_at' => now(),
        ])->save();

        ActivityLog::create([
            'user_id' => $actor->id,
            'subject_type' => Mentorship::class,
            'subject_id' => $mentorship->id,
            'action' => 'mentorship.ended',
            'meta' => null,
        ]);

        return $mentorship;
    }
}
