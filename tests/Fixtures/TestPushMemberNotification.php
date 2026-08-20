<?php

namespace Tests\Fixtures;

use App\Notifications\MemberNotification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The opt-in variant: a notification that is worth reaching a member whose
 * browser is closed (the FR-SPR-10 case).
 */
class TestPushMemberNotification extends MemberNotification
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['message' => 'Your session is done.'];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Session complete')
            ->body('Your session is done.');
    }

    protected function reachesClosedBrowser(): bool
    {
        return true;
    }
}
