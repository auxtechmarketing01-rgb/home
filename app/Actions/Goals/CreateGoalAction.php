<?php

namespace App\Actions\Goals;

use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateGoalAction
{
    /**
     * Creates the Goal and its empty Roadmap together — FR-RM-01 says every
     * Goal has exactly one Roadmap, so the two writes share one transaction
     * and a Goal can never exist without one (04 Phase 1).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(User $user, array $attributes): Goal
    {
        return DB::transaction(function () use ($user, $attributes): Goal {
            $goal = $user->goals()->create($attributes);

            $goal->roadmap()->create([
                'title' => 'Roadmap',
            ]);

            ActivityLog::create([
                'user_id' => $user->id,
                'subject_type' => Goal::class,
                'subject_id' => $goal->id,
                'action' => 'goal.created',
                'meta' => ['title' => $goal->title],
            ]);

            return $goal;
        });
    }
}
