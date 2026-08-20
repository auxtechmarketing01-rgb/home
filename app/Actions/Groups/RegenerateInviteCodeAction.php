<?php

namespace App\Actions\Groups;

use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\User;

class RegenerateInviteCodeAction
{
    /**
     * FR-GRP-01 calls for codes that expire or regenerate. Regeneration is
     * the owner's way of invalidating a code that has been shared too
     * widely — anyone holding the old one can no longer join.
     */
    public function __invoke(User $actor, Group $group): Group
    {
        $group->forceFill(['invite_code' => Group::generateInviteCode()])->save();

        ActivityLog::create([
            'user_id' => $actor->id,
            'subject_type' => Group::class,
            'subject_id' => $group->id,
            'action' => 'group.invite_code_regenerated',
            'meta' => null,
        ]);

        return $group;
    }
}
