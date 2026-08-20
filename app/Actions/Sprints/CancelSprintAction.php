<?php

namespace App\Actions\Sprints;

use App\Models\ActivityLog;
use App\Models\Sprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSprintAction
{
    /**
     * A cancelled sprint contributes no time: `actual_duration_seconds`
     * stays null and no recalculation is dispatched, so the member's stats
     * are untouched by a session they threw away.
     *
     * @throws ValidationException
     */
    public function __invoke(Sprint $sprint): Sprint
    {
        if (! $sprint->isActive()) {
            throw ValidationException::withMessages([
                'status' => 'Only a running or paused sprint can be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($sprint): Sprint {
            $sprint->forceFill([
                'status' => 'cancelled',
                'ended_at' => now(),
                'paused_at' => null,
                'actual_duration_seconds' => null,
            ])->save();

            ActivityLog::create([
                'user_id' => $sprint->user_id,
                'subject_type' => Sprint::class,
                'subject_id' => $sprint->id,
                'action' => 'sprint.cancelled',
                'meta' => null,
            ]);

            return $sprint;
        });
    }
}
