<?php

namespace App\Actions\Mentorships;

use App\Models\ActivityLog;
use App\Models\Mentorship;
use App\Models\User;
use App\Notifications\MentorshipAcceptedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RespondToMentorshipAction
{
    /**
     * FR-MENT-02. The policy has already established that the responder is
     * the non-initiating party; this checks the state.
     *
     * Accepting is consequential: it grants the mentor read access to every
     * one of the mentee's goals regardless of visibility (FR-MENT-04), which
     * is why only the other party can do it and why it is recorded in the
     * activity feed.
     *
     * @throws ValidationException
     */
    public function __invoke(User $actor, Mentorship $mentorship, bool $accepted): Mentorship
    {
        if ($mentorship->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'This request has already been answered.',
            ]);
        }

        return DB::transaction(function () use ($actor, $mentorship, $accepted): Mentorship {
            $mentorship->forceFill([
                'status' => $accepted ? 'accepted' : 'declined',
                'responded_at' => now(),
            ])->save();

            if ($accepted) {
                $mentorship->requester?->notify(
                    new MentorshipAcceptedNotification($mentorship, $actor)
                );
            }

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => Mentorship::class,
                'subject_id' => $mentorship->id,
                'action' => $accepted ? 'mentorship.accepted' : 'mentorship.declined',
                'meta' => null,
            ]);

            return $mentorship;
        });
    }
}
