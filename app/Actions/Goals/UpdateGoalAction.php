<?php

namespace App\Actions\Goals;

use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\User;

class UpdateGoalAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(User $actor, Goal $goal, array $attributes): Goal
    {
        $goal->fill($attributes);

        $changes = array_keys($goal->getDirty());

        $goal->save();

        if ($changes !== []) {
            ActivityLog::create([
                'user_id' => $actor->id,
                'subject_type' => Goal::class,
                'subject_id' => $goal->id,
                'action' => 'goal.updated',
                'meta' => ['changed' => $changes],
            ]);
        }

        return $goal;
    }
}
