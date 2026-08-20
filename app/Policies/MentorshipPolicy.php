<?php

namespace App\Policies;

use App\Models\Mentorship;
use App\Models\User;

/**
 * 02 §5, FR-MENT-01/02/07.
 */
class MentorshipPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Mentorship $mentorship): bool
    {
        return $mentorship->involves($user);
    }

    /**
     * FR-MENT-01: restricted to someone the requester already shares a Group
     * with, since this app has no public user directory and may include
     * minors.
     *
     * The same check is repeated inside RequestMentorshipAction rather than
     * being trusted here alone. That is not redundancy for its own sake: this
     * is a real authorization rule, and it must hold when the Action is
     * called from a console command or a future client that never passes
     * through this policy (02 §5, `.ai/rules/actions.md`).
     */
    public function create(User $user, ?User $target = null): bool
    {
        if ($target === null) {
            return true;
        }

        return $user->sharesGroupWith($target);
    }

    /**
     * FR-MENT-02: only the party who did **not** initiate may accept or
     * decline. Without this, a requester could approve themselves into
     * someone else's goals — the one mistake in this whole flow that would
     * hand out read access nobody agreed to.
     */
    public function respond(User $user, Mentorship $mentorship): bool
    {
        return $mentorship->status === 'pending'
            && $mentorship->involves($user)
            && ! $mentorship->isInitiator($user);
    }

    /**
     * FR-MENT-07: either party, at any time. Covers withdrawing a request
     * that is still `pending` as well as ending an `accepted` relationship
     * (FR-MENT-02's "either side can withdraw").
     *
     * Ending removes access going forward and leaves already-`fulfilled`
     * rewards untouched — history is not rewritten.
     */
    public function end(User $user, Mentorship $mentorship): bool
    {
        return in_array($mentorship->status, ['pending', 'accepted'], true)
            && $mentorship->involves($user);
    }
}
