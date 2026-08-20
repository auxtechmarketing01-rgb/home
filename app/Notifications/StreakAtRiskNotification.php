<?php

namespace App\Notifications;

/**
 * FR-NOT-02. Deliberately **not** a Web Push notification.
 *
 * A reminder that you have not studied yet today does not deserve an
 * OS-level interruption — that is precisely the "gamification fatigue" the
 * research behind this product warned about. It waits in the notification
 * centre, and arrives live if the member happens to have the app open.
 */
class StreakAtRiskNotification extends MemberNotification
{
    public function __construct(public int $currentStreak) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'current_streak' => $this->currentStreak,
            'title' => $this->currentStreak > 0
                ? "Your {$this->currentStreak}-day streak is at risk"
                : 'No focus time logged today',
            'body' => 'A single session today keeps it going.',
        ];
    }
}
