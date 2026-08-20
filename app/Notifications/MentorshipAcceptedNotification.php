<?php

namespace App\Notifications;

use App\Models\Mentorship;
use App\Models\User;

/**
 * FR-MENT-02. Tells the initiator their request went through — and, because
 * an accepted mentorship grants the mentor read access to every one of the
 * mentee's goals regardless of visibility (FR-MENT-04), this notification
 * doubles as the mentee's record that they granted it.
 */
class MentorshipAcceptedNotification extends MemberNotification
{
    public function __construct(
        public Mentorship $mentorship,
        public User $acceptedBy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'mentorship_id' => $this->mentorship->id,
            'accepted_by' => ['id' => $this->acceptedBy->id, 'name' => $this->acceptedBy->name],
            'mentor_id' => $this->mentorship->mentor_id,
            'mentee_id' => $this->mentorship->mentee_id,
            'title' => "{$this->acceptedBy->name} accepted the mentorship",
            'body' => 'The mentor can now see the mentee\'s goals and set time expectations.',
        ];
    }
}
