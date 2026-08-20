<?php

namespace App\Actions\Roadmaps;

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\ActivityLog;
use App\Models\RoadmapItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteRoadmapItemAction
{
    /**
     * Removing an item changes the goal's completion denominator, which is
     * why this is an Action rather than a bare delete in the controller —
     * Phase 2 hangs the stats recalculation off the same transition.
     */
    public function __invoke(User $actor, RoadmapItem $item): void
    {
        /** Resolved before the delete, while the relation is still reachable. */
        $goal = $item->resolveGoal();

        DB::transaction(function () use ($actor, $item): void {
            $itemId = $item->id;
            $title = $item->title;

            $item->delete();

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => RoadmapItem::class,
                'subject_id' => $itemId,
                'action' => 'roadmap_item.deleted',
                'meta' => ['title' => $title],
            ]);
        });

        RecalculateGoalStatsJob::dispatch($goal);
    }
}
