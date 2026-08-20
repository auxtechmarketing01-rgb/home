<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

/**
 * 02 §5: `view` → member, `update` (invite, rename, remove members) → owner.
 */
class GroupPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Group $group): bool
    {
        return $group->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Owner-only group settings (FR-GRP-05): renaming, inviting, regenerating
     * the invite code, and removing another member.
     */
    public function update(User $user, Group $group): bool
    {
        return $group->owner_id === $user->id;
    }

    public function delete(User $user, Group $group): bool
    {
        return $group->owner_id === $user->id;
    }

    /**
     * Leaving is not `update`: any member may leave, but the owner may not —
     * a group with no owner has nobody who can manage it. The owner deletes
     * the group or hands it over instead (FR-GRP-05).
     */
    public function leave(User $user, Group $group): bool
    {
        return $group->hasMember($user) && $group->owner_id !== $user->id;
    }
}
