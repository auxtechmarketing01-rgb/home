<?php

namespace Database\Factories;

use App\Models\Sprint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'goal_id' => null,
            'roadmap_item_id' => null,
            'mode' => 'pomodoro',
            'planned_duration_seconds' => 1500,
            'break_seconds' => 300,
            'started_at' => now(),
            'ended_at' => null,
            'paused_at' => null,
            'paused_seconds_total' => 0,
            'actual_duration_seconds' => null,
            'status' => 'running',
            'notes' => null,
            'notified_expired_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'ended_at' => null,
            'paused_at' => null,
            'actual_duration_seconds' => null,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
            'paused_at' => now(),
            'ended_at' => null,
            'actual_duration_seconds' => null,
        ]);
    }

    public function completed(int $actualDurationSeconds = 1500): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'ended_at' => now(),
            'paused_at' => null,
            'actual_duration_seconds' => $actualDurationSeconds,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'ended_at' => now(),
            'paused_at' => null,
            'actual_duration_seconds' => null,
        ]);
    }

    /**
     * Still `running`, but well past its planned duration — the FR-SPR-09
     * fixture. Nothing about this state is special in the database; it is
     * only a `started_at` far enough in the past.
     */
    public function overtime(int $minutesPastDeadline = 90): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'planned_duration_seconds' => 1500,
            'started_at' => now()->subMinutes(25 + $minutesPastDeadline),
            'ended_at' => null,
            'actual_duration_seconds' => null,
        ]);
    }

    public function stopwatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'stopwatch',
            'planned_duration_seconds' => null,
            'break_seconds' => 0,
        ]);
    }
}
