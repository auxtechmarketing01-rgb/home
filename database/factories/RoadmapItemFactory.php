<?php

namespace Database\Factories;

use App\Models\Roadmap;
use App\Models\RoadmapItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoadmapItem>
 */
class RoadmapItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'roadmap_id' => Roadmap::factory(),
            'parent_id' => null,
            'title' => 'Day '.fake()->unique()->numberBetween(1, 500),
            'description' => fake()->sentence(),
            'day_number' => null,
            'scheduled_date' => null,
            'estimated_minutes' => 60,
            'time_spent_seconds' => 0,
            'status' => 'todo',
            'position' => 0,
            'reflection_note' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'skipped',
        ]);
    }

    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }
}
