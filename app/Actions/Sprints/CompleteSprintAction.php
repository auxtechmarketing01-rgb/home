<?php

namespace App\Actions\Sprints;

use App\Jobs\RecalculateGoalStatsJob;
use App\Models\ActivityLog;
use App\Models\Sprint;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteSprintAction
{
    /**
     * FR-SPR-05. Stamps the row, then hands the rollup to the queue.
     *
     * The recalculation is dispatched rather than computed inline because it
     * touches roadmap items, goal stats, streaks and (from Phase 4) rewards
     * — none of which the member should wait on after tapping "stop"
     * (01 NFR Performance, 02 §6).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    public function __invoke(Sprint $sprint, array $attributes = []): Sprint
    {
        if (! $sprint->isActive()) {
            throw ValidationException::withMessages([
                'status' => 'Only a running or paused sprint can be completed.',
            ]);
        }

        return DB::transaction(function () use ($sprint, $attributes): Sprint {
            $now = CarbonImmutable::now();

            /**
             * A sprint completed while paused still has an open pause to
             * account for; folding it in first keeps
             * `paused_seconds_total` a complete record and keeps the
             * duration below from counting that time as focus.
             */
            $openPause = ($sprint->status === 'paused' && $sprint->paused_at !== null)
                ? max(0, (int) $now->diffInSeconds($sprint->paused_at, absolute: true))
                : 0;

            $actualDuration = $sprint->focusSecondsAt($now);

            $sprint->forceFill([
                'status' => 'completed',
                'ended_at' => $now,
                'paused_at' => null,
                'paused_seconds_total' => (int) $sprint->paused_seconds_total + $openPause,
                'actual_duration_seconds' => $actualDuration,
                'notes' => $attributes['notes'] ?? $sprint->notes,
            ])->save();

            ActivityLog::create([
                'user_id' => $sprint->user_id,
                'subject_type' => Sprint::class,
                'subject_id' => $sprint->id,
                'action' => 'sprint.completed',
                'meta' => ['actual_duration_seconds' => $actualDuration],
            ]);

            /**
             * A sprint with no goal is a general focus session (FR-SPR-01) —
             * there is nothing to roll it up into.
             */
            if ($sprint->goal !== null) {
                RecalculateGoalStatsJob::dispatch($sprint->goal);
            }

            return $sprint;
        });
    }
}
