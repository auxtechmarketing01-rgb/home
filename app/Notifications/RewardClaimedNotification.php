<?php

namespace App\Notifications;

use App\Models\Reward;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * FR-RWD-04: the mentee is asking for something they have earned.
 *
 * Opts into Web Push. The research behind this product found the single
 * biggest failure mode in every chore/reward app reviewed was the parent
 * simply forgetting to deliver — so this is exactly the notification worth
 * interrupting someone for, and it is also why
 * SendRewardClaimReminderJob exists as a follow-up.
 */
class RewardClaimedNotification extends MemberNotification
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
            'status' => $this->reward->status,
            'claimed_at' => $this->reward->claimed_at?->toIso8601String(),
            'title' => "Reward claimed: {$this->reward->title}",
            'body' => 'Your mentee is waiting on you to deliver it.',
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('A reward was claimed')
            ->body("{$this->reward->title} is waiting on you.")
            ->data(['url' => '/rewards']);
    }

    protected function reachesClosedBrowser(): bool
    {
        return true;
    }
}
