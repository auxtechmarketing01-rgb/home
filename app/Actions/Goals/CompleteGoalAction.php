<?php

namespace App\Actions\Goals;

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompleteGoalAction
{
    /**
     * FR-GOAL-04: completion is always an explicit user action. When every
     * roadmap item is done the UI shows a banner suggesting it — it never
     * flips the goal on its own.
     */
    public function __invoke(User $actor, Goal $goal): Goal
    {
        return DB::transaction(function () use ($actor, $goal): Goal {
            $goal->forceFill([
                'status' => 'completed',
                'completed_at' => $goal->completed_at ?? now(),
            ])->save();

            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => Goal::class,
                'subject_id' => $goal->id,
                'action' => 'goal.completed',
                'meta' => null,
            ]);

            /**
             * FR-RWD-02 fires on a Goal being completed as well as on an item
             * being marked done, and RecalculateGoalStatsJob is the single
             * trigger point for that transition (02 §6). Dispatching here
             * keeps it that way rather than adding a competing hook in
             * Phase 4.
             */
            RecalculateGoalStatsJob::dispatch($goal);

            return $goal;
        });
    }
}
