<?php

namespace App\Notifications;

/**
 * 02 §6's SendSprintReminderJob: "notify user if no sprint started by a
 * configured time".
 *
 * A separate class from StreakAtRiskNotification because the two say different
 * things. An audit caught the reminder job reusing the streak notification,
 * which meant a member who had never had a streak was told "your 0-day streak
 * is at risk" — a message about a number they do not have, for an event that is
 * not about streaks at all.
 *
 * No Web Push: an opt-in daily nudge is not worth an OS-level interruption.
 */
class SprintReminderNotification extends MemberNotification
{
    public function __construct(public int $reminderHour) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reminder_hour' => $this->reminderHour,
            'title' => 'No focus session yet today',
            'body' => 'You asked to be reminded around now. Even one short session counts.',
        ];
    }
}
