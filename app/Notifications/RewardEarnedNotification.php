<?php

namespace App\Notifications;

use App\Models\Reward;

/**
 * FR-RWD-02: the linked item or goal was completed and an `offered` reward
 * flipped to `earned`.
 *
 * This is the notification that makes real-time delivery earn its keep. The
 * flip happens inside a queued job with no HTTP request behind it, so there
 * is nothing on the mentee's screen to trigger a refetch — the `broadcast`
 * channel is what turns "claim" on without a reload (FR-NOT-03, and the
 * behaviour 06 §3 gate 5 checks).
 *
 * Still no Web Push: the member just finished the work, so they are almost
 * certainly looking at the app.
 */
class RewardEarnedNotification extends MemberNotification
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
            'title' => "You earned: {$this->reward->title}",
            'body' => 'Claim it to let your mentor know it is time to deliver.',
        ];
    }
}
