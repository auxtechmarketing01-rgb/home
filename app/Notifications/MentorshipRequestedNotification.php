<?php

namespace App\Notifications;

use App\Models\Mentorship;
use App\Models\User;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * FR-MENT-02. Goes to the party who did *not* initiate, since they are the
 * only one who can respond.
 *
 * Opts into Web Push: nothing moves until this person acts, so a request that
 * sits unseen blocks the whole relationship.
 */
class MentorshipRequestedNotification extends MemberNotification
{
    public function __construct(
        public Mentorship $mentorship,
        public User $requestedBy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $asMentor = $this->mentorship->mentee_id === $this->requestedBy->id;

        return [
            'mentorship_id' => $this->mentorship->id,
            'requested_by' => ['id' => $this->requestedBy->id, 'name' => $this->requestedBy->name],
            /** Which side the recipient is being asked to take. */
            'requested_role' => $asMentor ? 'mentor' : 'mentee',
            'title' => $asMentor
                ? "{$this->requestedBy->name} asked you to be their mentor"
                : "{$this->requestedBy->name} offered to mentor you",
            'body' => 'Accept or decline from your mentorships.',
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('New mentorship request')
            ->body("{$this->requestedBy->name} is waiting on your response.")
            ->data(['url' => '/mentorships']);
    }

    protected function reachesClosedBrowser(): bool
    {
        return true;
    }
}
