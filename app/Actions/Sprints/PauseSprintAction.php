<?php

namespace App\Actions\Sprints;

use App\Models\Sprint;
use Illuminate\Validation\ValidationException;

class PauseSprintAction
{
    /**
     * FR-SPR-04. Stamps when the pause began so ResumeSprintAction can
     * subtract it — the elapsed-time arithmetic has nowhere else to get that
     * from.
     *
     * @throws ValidationException
     */
    public function __invoke(Sprint $sprint): Sprint
    {
        if ($sprint->status !== 'running') {
            throw ValidationException::withMessages([
                'status' => 'Only a running sprint can be paused.',
            ]);
        }

        $sprint->forceFill([
            'status' => 'paused',
            'paused_at' => now(),
        ])->save();

        return $sprint;
    }
}
