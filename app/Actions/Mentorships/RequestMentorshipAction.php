<?php

namespace App\Actions\Mentorships;

use App\Models\ActivityLog;
use App\Models\Mentorship;
use App\Models\User;
use App\Notifications\MentorshipRequestedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestMentorshipAction
{
    /**
     * FR-MENT-01/02/03.
     *
     * `$targetRole` is the role the *other* person would take, which is what
     * makes a single endpoint serve both directions: either the prospective
     * mentee or the prospective mentor may initiate (02 §3
     * `requested_by_user_id`).
     *
     * @param  'mentor'|'mentee'  $targetRole
     *
     * @throws AuthorizationException|ValidationException
     */
    public function __invoke(User $actor, User $target, string $targetRole): Mentorship
    {
        /**
         * FR-MENT-01, enforced here and not only in the Form Request.
         *
         * This is a real authorization rule, not input validation: there is
         * no public user directory, so "any user" means "any user I already
         * share a Group with" — and the app may include minors, which makes
         * this the boundary that stops a stranger reaching a child. It has to
         * hold when the Action is called from anywhere, not just from behind
         * RequestMentorshipRequest.
         */
        if (! $actor->sharesGroupWith($target)) {
            throw new AuthorizationException(
                'You can only request a mentorship with someone you share a group with.'
            );
        }

        [$mentorId, $menteeId] = $targetRole === 'mentor'
            ? [$target->id, $actor->id]
            : [$actor->id, $target->id];

        return DB::transaction(function () use ($actor, $target, $mentorId, $menteeId): Mentorship {
            $existing = Mentorship::query()
                ->where('mentor_id', $mentorId)
                ->where('mentee_id', $menteeId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && in_array($existing->status, ['pending', 'accepted'], true)) {
                throw ValidationException::withMessages([
                    'user_id' => $existing->status === 'accepted'
                        ? 'This mentorship is already active.'
                        : 'A request between you two is already waiting for a response.',
                ]);
            }

            /**
             * 02 §3: a pair that re-requests after `declined` or `ended`
             * reuses its row rather than inserting a duplicate, so the whole
             * history of two people lives in one place — and so the unique
             * constraint is never fought against.
             */
            $mentorship = $existing ?? new Mentorship;

            $mentorship->forceFill([
                'mentor_id' => $mentorId,
                'mentee_id' => $menteeId,
                'requested_by_user_id' => $actor->id,
                'status' => 'pending',
                'responded_at' => null,
            ])->save();

            $target->notify(new MentorshipRequestedNotification($mentorship, $actor));

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => Mentorship::class,
                'subject_id' => $mentorship->id,
                'action' => 'mentorship.requested',
                'meta' => ['mentor_id' => $mentorId, 'mentee_id' => $menteeId],
            ]);

            return $mentorship;
        });
    }
}
