<?php

namespace App\Jobs;

use App\Actions\Rewards\MarkRewardsEarnedForItemAction;
use App\Models\Goal;
use App\Models\GoalStats;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Services\GamificationService;
use App\Services\LeaderboardService;
use App\Services\ProjectionService;
use App\Services\StreakService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds every denormalized number for one goal (02 §6).
 *
 * Deliberately a *rebuild from source*, not an increment. Incrementing
 * `time_spent_seconds` by each finished sprint would be cheaper, but any
 * missed or double-delivered job would leave the number permanently wrong
 * with no way to notice. Recomputing from the sprint rows means the cache is
 * self-healing: whatever went wrong last time, the next run is correct.
 */
class RecalculateGoalStatsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Debounce window. Rapid successive sprint completions on one goal
     * collapse into a single recalculation (02 §6); the lock is released
     * after this many seconds even if a worker died holding it.
     */
    public int $uniqueFor = 300;

    public function __construct(public Goal $goal) {}

    public function uniqueId(): string
    {
        return (string) $this->goal->id;
    }

    public function handle(
        StreakService $streaks,
        ProjectionService $projections,
        MarkRewardsEarnedForItemAction $markRewardsEarned,
        LeaderboardService $leaderboards,
        GamificationService $gamification,
    ): void {
        /**
         * The goal may have been hard-deleted between dispatch and
         * execution. Archiving is a soft delete (FR-GOAL-03), so an archived
         * goal still resolves here and still gets accurate stats — which is
         * what makes un-archiving safe.
         */
        $goal = Goal::withTrashed()->with('user')->find($this->goal->id);

        if ($goal === null) {
            return;
        }

        DB::transaction(function () use ($goal, $streaks, $projections, $markRewardsEarned, $gamification): void {
            /**
             * Item queries go through `roadmap_id` rather than the goal's
             * hasManyThrough relation: the aggregates below use GROUP BY, and
             * a grouped query over a relation that already carries its own
             * join and select list is needlessly fragile. A goal has exactly
             * one roadmap (FR-RM-01), so this is the same set of rows.
             */
            $roadmapId = $goal->roadmap()->value('id');
            $itemIds = $roadmapId === null
                ? []
                : RoadmapItem::query()->where('roadmap_id', $roadmapId)->pluck('id')->all();

            $this->rollUpItemTime($itemIds);

            [$totalFocusSeconds, $sessionsCount] = $this->goalTotals($goal, $itemIds);

            $streak = $streaks->forGoal($goal);

            $stats = GoalStats::query()->where('goal_id', $goal->id)->first() ?? new GoalStats;

            $stats->forceFill([
                'goal_id' => $goal->id,
                'total_focus_seconds' => $totalFocusSeconds,
                'sessions_count' => $sessionsCount,
                'completion_percentage' => $this->completionPercentage($roadmapId),
                'current_streak' => $streak['current'],
                'longest_streak' => $streak['longest'],
                'projected_completion_date' => $projections->projectCompletionDate($goal)?->toDateString(),
                'last_recalculated_at' => now(),
            ])->save();

            /**
             * FR-RWD-02. The **single** trigger point for `offered` ->
             * `earned`: no observer, controller or second job competes with
             * it, so one completed item produces exactly one flip and one
             * notification (02 §6).
             */
            $markRewardsEarned($goal);

            /**
             * FR-GAM-02/03, and a no-op for a member who has switched
             * gamification off.
             */
            if ($goal->user !== null) {
                $gamification->recalculateFor($goal->user);
            }
        });

        /**
         * Invalidated *after* the transaction commits, so a concurrent
         * leaderboard read cannot repopulate the cache from rows this
         * transaction has not written yet (02 §7).
         */
        if ($goal->user !== null) {
            $leaderboards->invalidateForUser($goal->user);
        }
    }

    /**
     * `roadmap_items.time_spent_seconds` is owned by this job and by nothing
     * else (02 §3). Every item is zeroed first so an item whose only sprint
     * was later cancelled falls back to zero instead of keeping a stale
     * total.
     *
     * @param  list<int>  $itemIds
     */
    protected function rollUpItemTime(array $itemIds): void
    {
        if ($itemIds === []) {
            return;
        }

        DB::table('roadmap_items')->whereIn('id', $itemIds)->update(['time_spent_seconds' => 0]);

        $totalsPerItem = Sprint::query()
            ->completed()
            ->whereIn('roadmap_item_id', $itemIds)
            ->groupBy('roadmap_item_id')
            ->selectRaw('roadmap_item_id, SUM(actual_duration_seconds) as total_seconds')
            ->pluck('total_seconds', 'roadmap_item_id');

        foreach ($totalsPerItem as $itemId => $total) {
            DB::table('roadmap_items')
                ->where('id', $itemId)
                ->update(['time_spent_seconds' => (int) $total]);
        }
    }

    /**
     * Focus time and session count for the goal.
     *
     * The OR covers a sprint linked only to a roadmap item: StartSprintAction
     * backfills `goal_id` in that case, but a single query that accepts
     * either link keeps historical or imported rows correct too. Because it
     * is one query, a sprint carrying both links is still counted once.
     *
     * @param  list<int>  $itemIds
     * @return array{0: int, 1: int}
     */
    protected function goalTotals(Goal $goal, array $itemIds): array
    {
        $query = Sprint::query()
            ->completed()
            ->where(function ($query) use ($goal, $itemIds): void {
                $query->where('goal_id', $goal->id);

                if ($itemIds !== []) {
                    $query->orWhereIn('roadmap_item_id', $itemIds);
                }
            });

        return [
            (int) $query->clone()->sum('actual_duration_seconds'),
            (int) $query->clone()->count(),
        ];
    }

    /**
     * Done items over items that are still meant to be done.
     *
     * `skipped` items are excluded from the denominator, not counted as done.
     * Including them would cap a roadmap with any skipped item below 100%
     * forever, which would in turn mean the "all items done" completion
     * banner (FR-GOAL-04) could never appear for that goal. Nested items each
     * count once and on their own, because FR-RM-03 makes a parent's status
     * informational rather than derived from its children.
     */
    protected function completionPercentage(?int $roadmapId): float
    {
        if ($roadmapId === null) {
            return 0.0;
        }

        $countsByStatus = RoadmapItem::query()
            ->where('roadmap_id', $roadmapId)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as item_count')
            ->pluck('item_count', 'status');

        $total = (int) $countsByStatus->sum();
        $skipped = (int) ($countsByStatus['skipped'] ?? 0);
        $done = (int) ($countsByStatus['done'] ?? 0);

        $countable = $total - $skipped;

        if ($countable <= 0) {
            return 0.0;
        }

        return round($done / $countable * 100, 2);
    }
}
