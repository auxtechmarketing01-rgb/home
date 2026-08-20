<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'title' => 'Learn '.fake()->unique()->word(),
            'description' => fake()->sentence(),
            'status' => 'active',
            'visibility' => 'private',
            'target_start_date' => now()->toDateString(),
            'target_end_date' => now()->addMonths(2)->toDateString(),
            'completed_at' => null,
        ];
    }

    /**
     * Visible to the members of the goal owner's group (FR-GOAL-02).
     */
    public function groupVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'group',
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Archiving is a soft delete, never a hard delete (FR-GOAL-03).
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
