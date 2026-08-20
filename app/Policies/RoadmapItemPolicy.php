<?php

namespace App\Policies;

use App\Models\Mentorship;
use App\Models\RoadmapItem;
use App\Models\User;

/**
 * 02 §5. A roadmap item is never independently more permissive than the Goal
 * it hangs off, so `view`/`update`/`delete` delegate upward.
 *
 * `assign` is the exception, and it is deliberately a **separate ability**
 * rather than a widening of `update` (FR-MENT-06). Folding it in would hand a
 * mentor the ability to rewrite the item's title and description and to mark
 * it done on the mentee's behalf — the exact boundary this design draws. A
 * mentor sets expectations; the mentee owns their plan and their claim of
 * having finished something.
 */
class RoadmapItemPolicy
{
    public function view(User $user, RoadmapItem $item): bool
    {
        return $user->can('view', $item->resolveGoal());
    }

    public function update(User $user, RoadmapItem $item): bool
    {
        return $user->can('update', $item->resolveGoal());
    }

    public function delete(User $user, RoadmapItem $item): bool
    {
        return $user->can('update', $item->resolveGoal());
    }

    /**
     * FR-MENT-05: true only when the acting member is the `mentor_id` on an
     * `accepted` mentorship whose mentee owns this item's goal.
     *
     * Note what is *not* here: the owner. A member does not "assign" to
     * themselves — they set their own `estimated_minutes` and
     * `scheduled_date` through `update`. Keeping the owner out means the two
     * abilities never overlap, so neither can be mistaken for the other.
     */
    public function assign(User $user, RoadmapItem $item): bool
    {
        $goal = $item->resolveGoal();

        if ($goal->user_id === $user->id) {
            return false;
        }

        return Mentorship::acceptedBetween($user, $goal->user_id);
    }
}
