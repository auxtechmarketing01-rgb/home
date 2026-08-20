<?php

namespace App\Notifications;

use App\Models\Reward;

/**
 * FR-RWD-03/07: the mentor accepted or denied a reward the mentee asked for.
 *
 * Not in 02 §2's notification list, but the flow needs it: a mentee who
 * "demands" a reward is left with no way to learn the answer otherwise, and
 * a denial is exactly the case where silence is worst.
 */
class RewardRequestRespondedNotification extends MemberNotification
{
    public function __construct(public Reward $reward) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $accepted = $this->reward->status !== 'denied';

        return [
            'reward_id' => $this->reward->id,
            'reward_title' => $this->reward->title,
            'status' => $this->reward->status,
            'accepted' => $accepted,
            'title' => $accepted
                ? "Your reward request was accepted: {$this->reward->title}"
                : "Your reward request was declined: {$this->reward->title}",
            'body' => $accepted
                ? 'Finish the linked work to earn it.'
                : 'Your mentor left it as declined.',
        ];
    }
}
