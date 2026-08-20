<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Roadmap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Roadmap>
 */
class RoadmapFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'title' => 'Roadmap',
        ];
    }
}
