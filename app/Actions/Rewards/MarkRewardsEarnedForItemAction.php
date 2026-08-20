<?php

namespace App\Actions\Rewards;

use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\Reward;
use App\Models\RoadmapItem;
use App\Notifications\RewardEarnedNotification;
use Illuminate\Support\Facades\DB;

class MarkRewardsEarnedForItemAction
{
    /**
     * FR-RWD-02: `offered` -> `earned` when the linked item or goal is done.
     *
     * **Called only from RecalculateGoalStatsJob** (02 §6). There is exactly
     * one trigger point for this transition and no observer, controller or
     * second job may compete with it — two triggers would mean two
     * notifications for one event, and a race over which one wins.
     *
     * Scoped to a whole goal rather than a single item because the job that
     * calls it is goal-scoped and debounced. Doing it per item would mean
     * redundant passes for no gain.
     *
     * Three things it deliberately does not do:
     *
     * - It never touches a `requested` reward. Nothing was promised, so
     *   finishing the work cannot conjure a promise (FR-RWD-03).
     * - It never touches a reward on an item that is not done, even if a
     *   sibling item is.
     * - It never pays anything out. `earned` is a status flip; delivery is a
     *   human step (FR-RWD-05).
     *
     * @return int number of rewards flipped
     */
    public function __invoke(Goal $goal): int
    {
        $doneItemIds = RoadmapItem::query()
            ->whereIn('roadmap_id', $goal->roadmap()->select('id'))
            ->where('status', 'done')
            ->pluck('id')
            ->all();

        $goalIsComplete = $goal->status === 'completed';

        if ($doneItemIds === [] && ! $goalIsComplete) {
            return 0;
        }

        $rewards = Reward::query()
            ->where('status', 'offered')
            ->where(function ($query) use ($doneItemIds, $goal, $goalIsComplete): void {
                if ($doneItemIds !== []) {
                    $query->whereIn('roadmap_item_id', $doneItemIds);
                }

                if ($goalIsComplete) {
                    /**
                     * A goal-level reward only lands when the goal itself is
                     * complete — not when any one of its items is.
                     */
                    $query->orWhere(function ($query) use ($goal): void {
                        $query->where('goal_id', $goal->id)->whereNull('roadmap_item_id');
                    });
                }
            })
            ->with('mentorship.mentee')
            ->get();

        if ($rewards->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($rewards): int {
            $flipped = 0;

            foreach ($rewards as $reward) {
                /**
                 * Re-checked inside the transaction so two overlapping job
                 * runs cannot both flip the same reward and send two
                 * notifications for one event.
                 */
                $claimed = Reward::query()
                    ->whereKey($reward->id)
                    ->where('status', 'offered')
                    ->update(['status' => 'earned', 'updated_at' => now()]);

                if ($claimed === 0) {
                    continue;
                }

                $reward->refresh();
                $flipped++;

                $reward->mentorship?->mentee?->notify(new RewardEarnedNotification($reward));

                ActivityLog::create([
                    'user_id' => $reward->mentorship?->mentee_id,
                    'subject_type' => Reward::class,
                    'subject_id' => $reward->id,
                    'action' => 'reward.earned',
                    'meta' => ['roadmap_item_id' => $reward->roadmap_item_id, 'goal_id' => $reward->goal_id],
                ]);
            }

            return $flipped;
        });
    }
}
