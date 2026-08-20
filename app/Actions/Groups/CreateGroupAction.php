<?php

namespace App\Actions\Groups;

use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateGroupAction
{
    /**
     * FR-GRP-01. The group and the creator's owner membership are written
     * together: a group with no members would be invisible to its own owner,
     * since GroupPolicy::view is membership-based.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(User $user, array $attributes): Group
    {
        return DB::transaction(function () use ($user, $attributes): Group {
            $group = new Group;
            $group->fill($attributes);
            $group->owner_id = $user->id;
            $group->invite_code = Group::generateInviteCode();
            $group->save();

            $membership = new GroupMember;
            $membership->forceFill([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'owner',
            ])->save();

            ActivityLog::create([
                'user_id' => $user->id,
                'subject_type' => Group::class,
                'subject_id' => $group->id,
                'action' => 'group.created',
                'meta' => ['name' => $group->name],
            ]);

            return $group;
        });
    }
}
