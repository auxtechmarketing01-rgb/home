<?php

namespace App\Policies;

use App\Models\Challenge;
use App\Models\User;

/**
 * FR-GRP-04. Everything here funnels through group membership: a challenge
 * lives inside a group, so it is exactly as visible as the group is and no
 * more.
 */
class ChallengePolicy
{
    public function view(User $user, Challenge $challenge): bool
    {
        return $user->can('view', $challenge->group);
    }

    /**
     * Any member may start one — a challenge is a peer arrangement, not an
     * owner-administered feature.
     */
    public function create(User $user, Challenge $challenge): bool
    {
        return $user->can('view', $challenge->group);
    }

    public function join(User $user, Challenge $challenge): bool
    {
        return $challenge->status === 'active'
            && $user->can('view', $challenge->group);
    }

    /**
     * Editing or closing a challenge belongs to whoever started it, or to the
     * group's owner as a backstop for an abandoned one.
     */
    public function update(User $user, Challenge $challenge): bool
    {
        return $challenge->created_by === $user->id
            || $challenge->group->owner_id === $user->id;
    }

    public function delete(User $user, Challenge $challenge): bool
    {
        return $this->update($user, $challenge);
    }
}
