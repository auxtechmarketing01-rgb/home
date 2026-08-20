<?php

namespace App\Notifications;

use App\Models\Challenge;
use App\Models\User;

/**
 * FR-GRP-04. Sent to the other participants when someone joins a challenge
 * they are in — the social pull that makes a challenge different from the
 * passive leaderboard.
 *
 * No Web Push: this is encouragement, not something to wake a phone for.
 */
class ChallengeUpdateNotification extends MemberNotification
{
    public function __construct(
        public Challenge $challenge,
        public User $actor,
        public string $event = 'joined',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'challenge_id' => $this->challenge->id,
            'challenge_title' => $this->challenge->title,
            'group_id' => $this->challenge->group_id,
            'event' => $this->event,
            'actor' => ['id' => $this->actor->id, 'name' => $this->actor->name],
            'title' => "{$this->actor->name} {$this->event} {$this->challenge->title}",
            'body' => 'Open the challenge to see where everyone stands.',
        ];
    }
}
