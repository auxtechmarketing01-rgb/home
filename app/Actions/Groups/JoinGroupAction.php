<?php

namespace App\Actions\Groups;

use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinGroupAction
{
    /**
     * FR-GRP-01, invite-code based.
     *
     * The code is looked up here rather than validated by a Form Request
     * `exists` rule on purpose: an invalid code and an already-used
     * membership need different messages, and an `exists` rule would leak
     * whether a given code is real to anyone probing the endpoint.
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, string $inviteCode): Group
    {
        return DB::transaction(function () use ($user, $inviteCode): Group {
            $group = Group::query()->where('invite_code', $inviteCode)->first();

            if ($group === null) {
                throw ValidationException::withMessages([
                    'invite_code' => 'That invite code is not valid.',
                ]);
            }

            if ($group->hasMember($user)) {
                throw ValidationException::withMessages([
                    'invite_code' => 'You are already a member of this group.',
                ]);
            }

            $membership = new GroupMember;
            $membership->forceFill([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 'member',
            ])->save();

            ActivityLog::create([
                'user_id' => $user->id,
                'subject_type' => Group::class,
                'subject_id' => $group->id,
                'action' => 'group.joined',
                'meta' => null,
            ]);

            return $group;
        });
    }
}
