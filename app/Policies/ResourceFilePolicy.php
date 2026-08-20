<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\ResourceFile;
use App\Models\RoadmapItem;
use App\Models\User;

/**
 * Delegates to whichever parent the attachment hangs off (02 §5).
 *
 * An attachment is never more permissive than the Goal or RoadmapItem it is
 * attached to, so there is no rule of its own here to drift out of step with
 * GoalPolicy as that policy gains its group and mentorship branches.
 */
class ResourceFilePolicy
{
    public function view(User $user, ResourceFile $resource): bool
    {
        return $user->can('view', $this->parentOf($resource));
    }

    /**
     * Deleting an attachment is a mutation of the parent's content, so it
     * maps to the parent's `update` ability rather than its `delete` — a
     * mentor with read access must not be able to remove a mentee's files
     * (FR-MENT-06).
     */
    public function delete(User $user, ResourceFile $resource): bool
    {
        return $user->can('update', $this->parentOf($resource));
    }

    protected function parentOf(ResourceFile $resource): Goal|RoadmapItem
    {
        return $resource->resolveParent();
    }
}
