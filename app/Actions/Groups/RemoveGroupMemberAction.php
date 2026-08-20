<?php

namespace App\Actions\Groups;

use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveGroupMemberAction
{
    /**
     * FR-GRP-05. Covers both an owner removing someone and a member leaving;
     * the two differ only in who is authorized, which the policy decides.
     *
     * @throws ValidationException
     */
    public function __invoke(User $actor, Group $group, User $member): void
    {
        if ($group->owner_id === $member->id) {
            throw ValidationException::withMessages([
                'user_id' => 'The group owner cannot be removed. Delete the group instead.',
            ]);
        }

        DB::transaction(function () use ($actor, $group, $member): void {
            $removed = $group->memberships()->where('user_id', $member->id)->delete();

            if ($removed === 0) {
                throw ValidationException::withMessages([
                    'user_id' => 'That person is not a member of this group.',
                ]);
            }

            /**
             * Their goals stay theirs, but must stop being visible to a group
             * they are no longer in. Detaching the group leaves the goal
             * `group`-visible with a null `group_id`, which GoalPolicy's group
             * branch treats as owner-only — the safe direction to fail in
             * (01 §5 Privacy).
             */
            Goal::query()
                ->where('user_id', $member->id)
                ->where('group_id', $group->id)
                ->update(['group_id' => null]);

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => Group::class,
                'subject_id' => $group->id,
                'action' => $actor->id === $member->id ? 'group.left' : 'group.member_removed',
                'meta' => ['user_id' => $member->id],
            ]);
        });
    }
}
