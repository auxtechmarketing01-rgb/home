<?php

namespace App\Actions\Roadmaps;

use App\Models\ActivityLog;
use App\Models\RoadmapItem;
use App\Models\User;
use App\Notifications\RoadmapItemAssignedNotification;
use Illuminate\Support\Facades\DB;

class AssignRoadmapItemAction
{
    /**
     * FR-MENT-05: a mentor sets a time budget and/or a due date on a mentee's
     * item.
     *
     * Read the write list below carefully — it is the whole of FR-MENT-06.
     * Only `assigned_by_mentor_id`, `assigned_minutes` and `assigned_due_at`
     * are touched. Not `title`, not `description`, not `status`, and
     * emphatically not `estimated_minutes`: that is the mentee's own estimate
     * and the two are allowed to disagree, because the disagreement is useful
     * information rather than a conflict to resolve (02 §3).
     *
     * A mentor who could edit these fields would gradually turn the mentee's
     * roadmap into the mentor's roadmap, which is the failure this boundary
     * exists to prevent.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(User $mentor, RoadmapItem $item, array $attributes): RoadmapItem
    {
        return DB::transaction(function () use ($mentor, $item, $attributes): RoadmapItem {
            $item->forceFill([
                'assigned_by_mentor_id' => $mentor->id,
                'assigned_minutes' => $attributes['assigned_minutes'] ?? $item->assigned_minutes,
                'assigned_due_at' => $attributes['assigned_due_at'] ?? $item->assigned_due_at,
            ])->save();

            $owner = $item->resolveGoal()->user;

            $owner?->notify(new RoadmapItemAssignedNotification($item, $mentor));

            ActivityLog::create([
                'user_id' => $mentor->id,
                'subject_type' => RoadmapItem::class,
                'subject_id' => $item->id,
                'action' => 'roadmap_item.assigned',
                'meta' => [
                    'assigned_minutes' => $item->assigned_minutes,
                    'assigned_due_at' => $item->assigned_due_at?->toIso8601String(),
                ],
            ]);

            return $item;
        });
    }
}
