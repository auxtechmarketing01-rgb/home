<?php

namespace App\Actions\Sprints;

use App\Models\Sprint;
use Illuminate\Validation\ValidationException;

class ResumeSprintAction
{
    /**
     * FR-SPR-04: the pause that just ended is folded into
     * `paused_seconds_total`, which is what `actual_duration_seconds`
     * subtracts on completion.
     *
     * Note that resuming does *not* move the deadline. `started_at` plus
     * `planned_duration_seconds` stays the one definition of when the plan is
     * reached, shared with the SPA (Sprint::deadlineAt(), 03 §4).
     *
     * @throws ValidationException
     */
    public function __invoke(Sprint $sprint): Sprint
    {
        if ($sprint->status !== 'paused') {
            throw ValidationException::withMessages([
                'status' => 'Only a paused sprint can be resumed.',
            ]);
        }

        $pausedFor = $sprint->paused_at === null
            ? 0
            : max(0, (int) now()->diffInSeconds($sprint->paused_at, absolute: true));

        $sprint->forceFill([
            'status' => 'running',
            'paused_at' => null,
            'paused_seconds_total' => (int) $sprint->paused_seconds_total + $pausedFor,
        ])->save();

        return $sprint;
    }
}
