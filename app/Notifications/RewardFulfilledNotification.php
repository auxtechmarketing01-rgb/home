<?php

namespace App\Notifications;

use App\Models\Reward;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * FR-RWD-05: the mentor has recorded that they actually delivered it.
 *
 * The app records that something happened outside it — cash handed over, a
 * privilege granted — and moves no money itself (01 NFR Financial
 * integrity). Opts into Web Push because it closes the loop the mentee has
 * been waiting on.
 */
class RewardFulfilledNotification extends MemberNotification
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
            'fulfilled_at' => $this->reward->fulfilled_at?->toIso8601String(),
            'fulfilled_note' => $this->reward->fulfilled_note,
            'title' => "Reward delivered: {$this->reward->title}",
            'body' => 'Your mentor marked it as fulfilled.',
        ];
    }

    public function toWebPush(object $notifiable, mixed $notification = null): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Reward delivered')
            ->body("{$this->reward->title} was marked fulfilled.")
            ->data(['url' => '/rewards']);
    }

    protected function reachesClosedBrowser(): bool
    {
        return true;
    }
}
