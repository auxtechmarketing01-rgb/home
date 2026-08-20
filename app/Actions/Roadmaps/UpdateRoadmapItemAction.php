<?php

namespace App\Actions\Roadmaps;

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\ActivityLog;
use App\Models\RoadmapItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateRoadmapItemAction
{
    /**
     * Only the item's owner reaches here — a mentor's `assign` ability is a
     * separate Action and a separate route (FR-MENT-06).
     *
     * A status transition is recorded in the activity feed (FR-RM-02);
     * `roadmap_item.completed` is used for the transition into `done` so the
     * feed reads the way 02 §3 describes it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(User $actor, RoadmapItem $item, array $attributes): RoadmapItem
    {
        return DB::transaction(function () use ($actor, $item, $attributes): RoadmapItem {
            $previousStatus = $item->status;

            $item->fill($attributes);
            $changed = array_keys($item->getDirty());
            $item->save();

            $statusChanged = in_array('status', $changed, true);

            if ($statusChanged) {
                ActivityLog::create([
                    'user_id' => $actor->id,
                    'subject_type' => RoadmapItem::class,
                    'subject_id' => $item->id,
                    'action' => $item->status === 'done'
                        ? 'roadmap_item.completed'
                        : 'roadmap_item.status_changed',
                    'meta' => ['from' => $previousStatus, 'to' => $item->status],
                ]);
            } elseif ($changed !== []) {
                ActivityLog::create([
                    'user_id' => $actor->id,
                    'subject_type' => RoadmapItem::class,
                    'subject_id' => $item->id,
                    'action' => 'roadmap_item.updated',
                    'meta' => ['changed' => $changed],
                ]);
            }

            /**
             * A status change moves the completion percentage and, from Phase
             * 4, flips any reward tied to this item (FR-RWD-02). A changed
             * estimate moves the projected completion date. Both are
             * recalculated on the queue through the one job that owns those
             * numbers — never inline, and never by a second trigger point
             * (02 §6).
             */
            if ($statusChanged || in_array('estimated_minutes', $changed, true)) {
                RecalculateGoalStatsJob::dispatch($item->resolveGoal());
            }

            return $item;
        });
    }
}
