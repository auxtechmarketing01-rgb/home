<?php

namespace App\Notifications;

use App\Models\RoadmapItem;
use App\Models\User;

/**
 * FR-MENT-05. The mentee is told what their mentor now expects.
 *
 * The copy is careful about the boundary FR-MENT-06 draws: the mentor set a
 * budget and a deadline, not the work itself. The mentee's own
 * `estimated_minutes` is untouched and still theirs.
 */
class RoadmapItemAssignedNotification extends MemberNotification
{
    public function __construct(
        public RoadmapItem $item,
        public User $mentor,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'roadmap_item_id' => $this->item->id,
            'roadmap_item_title' => $this->item->title,
            'goal_id' => $this->item->resolveGoal()->id,
            'mentor' => ['id' => $this->mentor->id, 'name' => $this->mentor->name],
            'assigned_minutes' => $this->item->assigned_minutes,
            'assigned_due_at' => $this->item->assigned_due_at?->toIso8601String(),
            'title' => "{$this->mentor->name} set an expectation on \"{$this->item->title}\"",
            'body' => 'Your own estimate is unchanged — this is what they are hoping for.',
        ];
    }
}
