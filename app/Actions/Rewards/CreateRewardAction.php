<?php

namespace App\Actions\Rewards;

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\Mentorship;
use App\Models\Reward;
use App\Models\RoadmapItem;
use App\Models\User;
use App\Notifications\RewardOfferedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRewardAction
{
    /**
     * FR-RWD-01: a mentor pre-commits to a reward tied to a goal or item.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function __invoke(User $mentor, Mentorship $mentorship, array $attributes): Reward
    {
        return DB::transaction(function () use ($mentor, $mentorship, $attributes): Reward {
            $goal = $this->resolveAnchor($mentorship, $attributes);

            $reward = new Reward;
            $reward->fill($attributes);
            $reward->forceFill([
                'mentorship_id' => $mentorship->id,
                'status' => 'offered',
                'requested_by' => 'mentor',
            ])->save();

            $mentorship->mentee?->notify(new RewardOfferedNotification($reward));

            ActivityLog::create([
                'user_id' => $mentor->id,
                'subject_type' => Reward::class,
                'subject_id' => $reward->id,
                'action' => 'reward.offered',
                'meta' => ['mentorship_id' => $mentorship->id, 'title' => $reward->title],
            ]);

            /**
             * A mentor may well offer a reward for work the mentee has
             * *already* finished. Rather than deciding here whether that
             * means `earned`, the recalculation job is dispatched so the flip
             * goes through MarkRewardsEarnedForItemAction — the single
             * trigger point for `offered` -> `earned` (02 §6, FR-RWD-02). No
             * competing path, no chance of the two disagreeing.
             */
            if ($goal !== null) {
                RecalculateGoalStatsJob::dispatch($goal);
            }

            return $reward;
        });
    }

    /**
     * A reward must be anchored to something the *mentee* owns. Anchoring to
     * a third party's goal would put a stranger's progress in charge of this
     * reward, so it is refused as an authorization-grade check inside the
     * Action (02 §5).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    protected function resolveAnchor(Mentorship $mentorship, array $attributes): ?Goal
    {
        $itemId = $attributes['roadmap_item_id'] ?? null;
        $goalId = $attributes['goal_id'] ?? null;

        if ($itemId !== null) {
            $item = RoadmapItem::query()->with('roadmap.goal')->find($itemId);
            $goal = $item?->roadmap?->goal;

            if ($goal === null || $goal->user_id !== $mentorship->mentee_id) {
                throw ValidationException::withMessages([
                    'roadmap_item_id' => 'That roadmap item does not belong to this mentee.',
                ]);
            }

            return $goal;
        }

        if ($goalId !== null) {
            $goal = Goal::query()->find($goalId);

            if ($goal === null || $goal->user_id !== $mentorship->mentee_id) {
                throw ValidationException::withMessages([
                    'goal_id' => 'That goal does not belong to this mentee.',
                ]);
            }

            return $goal;
        }

        return null;
    }
}
