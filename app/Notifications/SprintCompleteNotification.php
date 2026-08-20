<?php

namespace App\Notifications;

use App\Models\Sprint;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * FR-SPR-10: the member's session reached its planned duration.
 *
 * The wording matters. This says the planned time is up, not that the session
 * has ended — because it has not. The sprint is still `running` and still
 * accumulating time until the member stops it (FR-SPR-09), so the copy must
 * not imply otherwise.
 *
 * One of the few notifications that opts into Web Push: the entire point is
 * reaching someone who closed the tab.
 */
class SprintCompleteNotification extends MemberNotification
{
    public function __construct(public Sprint $sprint) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sprint_id' => $this->sprint->id,
            'goal_id' => $this->sprint->goal_id,
            'roadmap_item_id' => $this->sprint->roadmap_item_id,
            'planned_duration_seconds' => $this->sprint->planned_duration_seconds,
            'title' => $this->title(),
            'body' => $this->body(),
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/icons/notification-192.png')
            ->data(['url' => '/focus'])
            ->options(['TTL' => 1800]);
    }

    protected function reachesClosedBrowser(): bool
    {
        return true;
    }

    protected function title(): string
    {
        $minutes = (int) round(($this->sprint->planned_duration_seconds ?? 0) / 60);

        return $minutes > 0
            ? "Your {$minutes}-minute session is up"
            : 'Your session is up';
    }

    protected function body(): string
    {
        return 'Still running until you stop it — tap to finish or keep going.';
    }
}
