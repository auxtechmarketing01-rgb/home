<?php

namespace App\Policies;

use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\User;

/**
 * 02 §5, FR-RWD-01..07. **One ability per transition in the state machine**,
 * never a shared `update`.
 *
 * The state machine only stays correct while each transition carries its own
 * explicit check. Collapsing these into one ability is what lets a mentee
 * fulfil their own reward or a mentor claim on the mentee's behalf.
 *
 * ## Who versus when
 *
 * Every method here answers **who** — which side of the mentorship the actor
 * is on. The **source state** is checked in the corresponding Action, which
 * throws a ValidationException.
 *
 * That split is deliberate, and it is what produces the two different status
 * codes 06 §1.2 asks for: the wrong *actor* gets **403** (a mentor trying to
 * claim), and the wrong *state* gets **422** (a mentee claiming a merely
 * `offered` reward). Putting the state check in the policy too would turn
 * every wrong-state attempt into a 403 and lose that distinction — a client
 * could no longer tell "not your move" from "not yet".
 *
 * ## Authorization always resolves through the mentorship
 *
 * Never through a user id on the request. `rewards.mentorship_id` is the
 * authority: it must exist, be `accepted`, and place the actor on the right
 * side. An `ended` mentorship therefore grants nothing going forward, while
 * leaving already-`fulfilled` rewards intact (FR-MENT-07).
 */
class RewardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Reward $reward): bool
    {
        return $reward->mentorship?->involves($user) ?? false;
    }

    /**
     * FR-RWD-01, mentor offering. Takes the Mentorship rather than a Reward,
     * because there is no reward yet.
     */
    public function create(User $user, Mentorship $mentorship): bool
    {
        return $mentorship->isAccepted() && $mentorship->isMentor($user);
    }

    /**
     * FR-RWD-03, the literal "demand a reward from a mentor": the mentee asks
     * for something that was never offered.
     */
    public function request(User $user, Mentorship $mentorship): bool
    {
        return $mentorship->isAccepted() && $mentorship->isMentee($user);
    }

    /**
     * FR-RWD-03/07: the mentor accepts or denies a `requested` reward.
     */
    public function respond(User $user, Reward $reward): bool
    {
        return $this->isMentorOf($user, $reward);
    }

    /**
     * FR-RWD-04: the mentee demands payout of a reward they have earned.
     */
    public function claim(User $user, Reward $reward): bool
    {
        return $this->isMenteeOf($user, $reward);
    }

    /**
     * FR-RWD-05: the mentor records that they actually delivered it.
     */
    public function fulfill(User $user, Reward $reward): bool
    {
        return $this->isMentorOf($user, $reward);
    }

    /**
     * FR-RWD-07: the mentor withdraws an offer. The Action restricts this to
     * the `offered` state, so a mentor cannot renege on something already
     * earned.
     */
    public function revoke(User $user, Reward $reward): bool
    {
        return $this->isMentorOf($user, $reward);
    }

    protected function isMentorOf(User $user, Reward $reward): bool
    {
        $mentorship = $reward->mentorship;

        return $mentorship !== null
            && $mentorship->isAccepted()
            && $mentorship->isMentor($user);
    }

    protected function isMenteeOf(User $user, Reward $reward): bool
    {
        $mentorship = $reward->mentorship;

        return $mentorship !== null
            && $mentorship->isAccepted()
            && $mentorship->isMentee($user);
    }
}
