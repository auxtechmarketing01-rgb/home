<?php

namespace App\Actions\Sprints;

use App\Exceptions\SprintAlreadyRunningException;
use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\RoadmapItem;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class StartSprintAction
{
    /**
     * FR-SPR-01, FR-SPR-08.
     *
     * The single-active-sprint rule is enforced here rather than with a
     * database constraint (02 §3): "at most one row per user whose status is
     * one of two values" is not expressible as a unique index, and the right
     * answer is a deliberate 409 rather than an integrity-violation 500.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws SprintAlreadyRunningException
     * @throws AuthorizationException
     */
    public function __invoke(User $user, array $attributes): Sprint
    {
        return DB::transaction(function () use ($user, $attributes): Sprint {
            /**
             * Locked for the duration of the transaction so two requests
             * arriving together cannot both see "no active sprint" and both
             * insert one.
             */
            $active = $user->sprints()->active()->lockForUpdate()->first();

            if ($active !== null) {
                throw new SprintAlreadyRunningException($active);
            }

            $attributes = $this->resolveTarget($user, $attributes);

            $sprint = new Sprint;
            $sprint->fill($attributes);
            $sprint->user_id = $user->id;
            $sprint->started_at = now();
            $sprint->status = 'running';
            $sprint->save();

            ActivityLog::create([
                'user_id' => $user->id,
                'subject_type' => Sprint::class,
                'subject_id' => $sprint->id,
                'action' => 'sprint.started',
                'meta' => [
                    'mode' => $sprint->mode,
                    'planned_duration_seconds' => $sprint->planned_duration_seconds,
                ],
            ]);

            return $sprint;
        });
    }

    /**
     * Verifies the member owns whatever they are logging time against, and
     * backfills `goal_id` from the roadmap item when only the item was sent.
     *
     * This is a real authorization rule, not input validation, so it lives
     * here and not only in StartSprintRequest (02 §5): logging time against
     * somebody else's goal would corrupt *their* stats and leaderboard
     * standing, which is the kind of write no amount of client-side
     * correctness should be trusted to prevent.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    protected function resolveTarget(User $user, array $attributes): array
    {
        $itemId = $attributes['roadmap_item_id'] ?? null;
        $goalId = $attributes['goal_id'] ?? null;

        if ($itemId !== null) {
            $item = RoadmapItem::query()->with('roadmap.goal')->findOrFail($itemId);
            $itemGoal = $item->roadmap->goal;

            if ($itemGoal->user_id !== $user->id) {
                throw new AuthorizationException('That roadmap item does not belong to you.');
            }

            /** A sprint on an item always belongs to that item's goal. */
            if ($goalId !== null && (int) $goalId !== $itemGoal->id) {
                throw new AuthorizationException('That roadmap item does not belong to the given goal.');
            }

            $attributes['goal_id'] = $itemGoal->id;

            return $attributes;
        }

        if ($goalId !== null) {
            $goal = Goal::query()->findOrFail($goalId);

            if ($goal->user_id !== $user->id) {
                throw new AuthorizationException('That goal does not belong to you.');
            }
        }

        return $attributes;
    }
}
