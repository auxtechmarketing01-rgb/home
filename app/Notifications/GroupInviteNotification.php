<?php

namespace App\Notifications;

use App\Models\Group;
use App\Models\User;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * FR-GRP-01. Opts into Web Push: an invitation is time-sensitive in the sense
 * that the invitee is usually being told about it in person at the same
 * moment, and finding it days later is a poor experience.
 */
class GroupInviteNotification extends MemberNotification
{
    public function __construct(
        public Group $group,
        public User $invitedBy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            /**
             * The code travels with the invitation so the invitee can join
             * without being sent it separately. It is only ever delivered to
             * the specific member being invited.
             */
            'invite_code' => $this->group->invite_code,
            'invited_by' => ['id' => $this->invitedBy->id, 'name' => $this->invitedBy->name],
            'title' => "{$this->invitedBy->name} invited you to {$this->group->name}",
            'body' => 'Join to compare progress with the group.',
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        return (new WebPushMessage)
            ->title("Invitation to {$this->group->name}")
            ->body("{$this->invitedBy->name} invited you to join.")
            ->data(['url' => '/groups']);
    }

    protected function reachesClosedBrowser(): bool
    {
        return true;
    }
}
