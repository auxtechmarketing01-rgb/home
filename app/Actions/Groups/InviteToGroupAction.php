<?php

namespace App\Actions\Groups;

use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\User;
use App\Notifications\GroupInviteNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InviteToGroupAction
{
    /**
     * FR-GRP-01: invite by email, which sends a queued notification.
     *
     * This app has no public user directory (01 §8), so an invitation can
     * only *notify* somebody who already has an account. When the address is
     * unknown the action still succeeds and hands back the invite code for
     * the owner to pass along out of band — refusing would make it impossible
     * to invite a family member who has not signed up yet, which is the
     * common case for a brand-new group.
     *
     * @return array{invite_code: string, notified: bool}
     *
     * @throws ValidationException
     */
    public function __invoke(User $actor, Group $group, ?string $email = null): array
    {
        return DB::transaction(function () use ($actor, $group, $email): array {
            $invitee = $email === null
                ? null
                : User::query()->where('email', $email)->first();

            if ($invitee !== null && $group->hasMember($invitee)) {
                throw ValidationException::withMessages([
                    'email' => 'That person is already a member of this group.',
                ]);
            }

            if ($invitee !== null) {
                $invitee->notify(new GroupInviteNotification($group, $actor));
            }

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => Group::class,
                'subject_id' => $group->id,
                'action' => 'group.invited',
                'meta' => ['email' => $email, 'notified' => $invitee !== null],
            ]);

            return [
                'invite_code' => $group->invite_code,
                'notified' => $invitee !== null,
            ];
        });
    }
}
