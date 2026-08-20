<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject_type' => Goal::class,
            'subject_id' => Goal::factory(),
            'action' => 'goal.created',
            'meta' => null,
        ];
    }
}
