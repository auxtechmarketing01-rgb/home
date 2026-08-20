<?php

namespace Database\Factories;

use App\Models\Streak;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Streak>
 */
class StreakFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_active_date' => null,
            'last_at_risk_notified_on' => null,
        ];
    }

    public function running(int $current, ?int $longest = null): static
    {
        return $this->state(fn (array $attributes) => [
            'current_streak' => $current,
            'longest_streak' => $longest ?? $current,
            'last_active_date' => now()->toDateString(),
        ]);
    }
}
