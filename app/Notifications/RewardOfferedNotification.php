<?php

namespace App\Notifications;

use App\Models\Reward;

/**
 * FR-RWD-01: a mentor has pre-committed to a reward tied to a goal or item.
 *
 * No Web Push — nothing is required of the mentee, and the reward will be
 * waiting on the item when they get there.
 */
class RewardOfferedNotification extends MemberNotification
{
    public function __construct(public Reward $reward) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reward_id' => $this->reward->id,
            'reward_title' => $this->reward->title,
            'type' => $this->reward->type,
            'monetary_amount' => $this->reward->monetary_amount,
            'currency_label' => $this->reward->currency_label,
            'goal_id' => $this->reward->goal_id,
            'roadmap_item_id' => $this->reward->roadmap_item_id,
            'status' => $this->reward->status,
            'title' => "New reward offered: {$this->reward->title}",
            'body' => 'Finish the linked item to earn it.',
        ];
    }
}
