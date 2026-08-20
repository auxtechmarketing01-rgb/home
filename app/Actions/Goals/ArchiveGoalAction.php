<?php

namespace App\Actions\Goals;

use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\User;

class ArchiveGoalAction
{
    /**
     * FR-GOAL-03: archiving is a soft delete. The row survives so its
     * history stays intact, and the default non-trashed scope keeps it out
     * of active-streak queries.
     */
    public function __invoke(User $actor, Goal $goal): void
    {
        $goal->delete();

        ActivityLog::create([
            'user_id' => $actor->id,
            'subject_type' => Goal::class,
            'subject_id' => $goal->id,
            'action' => 'goal.archived',
            'meta' => null,
        ]);
    }
}
