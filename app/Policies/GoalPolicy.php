<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\Mentorship;
use App\Models\User;

/**
 * 02 §5. `view` has **three independent grants**; `update`/`delete` have one.
 *
 * The asymmetry is the entire mentorship boundary: a mentor can read
 * everything and set expectations, and still cannot touch a word of the
 * mentee's plan or mark anything done on their behalf (FR-MENT-06). The
 * mentee keeps ownership of their plan and of their claim of "I did this".
 *
 * Every branch here has a matching branch in Goal::scopeVisibleTo. Policies
 * guard single-record routes; the scope guards every list and leaderboard.
 * Changing one without the other is how a private goal leaks.
 */
class GoalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Goal $goal): bool
    {
        return $this->owns($user, $goal)
            || $this->isGroupPeer($user, $goal)
            || $this->isAcceptedMentorOfOwner($user, $goal);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Owner only, full stop. A mentor who can `view` and `assign` still gets
     * 403 here (FR-MENT-06), and a group peer who can see the goal never
     * gets to edit it (FR-GRP-02).
     */
    public function update(User $user, Goal $goal): bool
    {
        return $this->owns($user, $goal);
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $this->owns($user, $goal);
    }

    protected function owns(User $user, Goal $goal): bool
    {
        return $goal->user_id === $user->id;
    }

    /**
     * FR-GRP-02. Scoped to the goal's *own* group — being in some other group
     * with the owner grants nothing, or marking a goal `group`-visible would
     * quietly publish it to every circle the owner belongs to.
     */
    protected function isGroupPeer(User $user, Goal $goal): bool
    {
        if ($goal->visibility !== 'group' || $goal->group_id === null) {
            return false;
        }

        return $user->belongsToGroup($goal->group_id);
    }

    /**
     * FR-MENT-04, kept as its own branch on purpose.
     *
     * This grant ignores `visibility` entirely: an accepted mentor reads a
     * `private` goal too. Mentorship is an explicit, mutual grant of read
     * access, not a side effect of sharing a group — so it must not be folded
     * into the branch above, and it must not require the goal to be
     * group-visible.
     */
    protected function isAcceptedMentorOfOwner(User $user, Goal $goal): bool
    {
        return Mentorship::acceptedBetween($user, $goal->user_id);
    }
}
