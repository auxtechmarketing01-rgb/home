<?php

namespace App\Jobs;

use App\Models\Goal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * FR-ANL-02's "recompute nightly" half.
 *
 * `RecalculateGoalStatsJob` is otherwise only dispatched by an event — a
 * finished sprint, an item's status changing, a goal being completed. That
 * covers everything except the passage of time, and the projected completion
 * date is precisely a function of time: a member who logs nothing for a week
 * has a genuinely worse projection than they did on Monday, and nothing would
 * ever recompute it. The streak in `goal_stats` decays the same way.
 *
 * Fans out one debounced job per non-archived, unfinished goal. Archived goals
 * are excluded because FR-GOAL-03 takes them out of active streak logic, and
 * completed ones because there is nothing left to project.
 */
class RecalculateActiveGoalStatsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Goal::query()
            ->whereNotIn('status', ['completed', 'abandoned'])
            ->chunkById(200, function ($goals): void {
                foreach ($goals as $goal) {
                    /**
                     * Each dispatch is ShouldBeUnique by goal id, so a goal
                     * already queued from a just-finished sprint is not
                     * recalculated twice.
                     */
                    RecalculateGoalStatsJob::dispatch($goal);
                }
            });
    }
}
