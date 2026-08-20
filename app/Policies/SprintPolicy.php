<?php

namespace App\Policies;

use App\Models\Sprint;
use App\Models\User;

/**
 * Owner only, every ability, no exceptions (02 §5).
 *
 * Sprints are never group-visible and a mentor neither sees nor controls a
 * mentee's sessions. Mentors and groups see focus time only as aggregates,
 * through goal stats and the leaderboard — which is the difference between
 * "I can see you are putting in the hours" and "I can watch you work".
 */
class SprintPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Sprint $sprint): bool
    {
        return $this->owns($user, $sprint);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Sprint $sprint): bool
    {
        return $this->owns($user, $sprint);
    }

    public function delete(User $user, Sprint $sprint): bool
    {
        return $this->owns($user, $sprint);
    }

    protected function owns(User $user, Sprint $sprint): bool
    {
        return $sprint->user_id === $user->id;
    }
}
