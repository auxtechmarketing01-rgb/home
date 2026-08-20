<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalStats;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoalStats>
 */
class GoalStatsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'total_focus_seconds' => 0,
            'sessions_count' => 0,
            'completion_percentage' => 0,
            'current_streak' => 0,
            'longest_streak' => 0,
            'projected_completion_date' => null,
            'last_recalculated_at' => null,
        ];
    }
}
