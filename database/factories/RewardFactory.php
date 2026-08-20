<?php

namespace Database\Factories;

use App\Models\Mentorship;
use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentorship_id' => Mentorship::factory()->accepted(),
            'goal_id' => null,
            'roadmap_item_id' => null,
            'title' => 'Reward: '.fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'type' => 'custom',
            'monetary_amount' => null,
            'currency_label' => null,
            'status' => 'offered',
            'requested_by' => 'mentor',
            'claimed_at' => null,
            'fulfilled_at' => null,
            'fulfilled_note' => null,
        ];
    }

    /**
     * A state per node in the diagram from 02 §3, so a test can start from
     * any point in the machine without walking the whole chain.
     */
    public function requested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'requested',
            'requested_by' => 'mentee',
        ]);
    }

    public function offered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'offered',
            'requested_by' => 'mentor',
        ]);
    }

    public function earned(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'earned']);
    }

    public function claimed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'claimed',
            'claimed_at' => now(),
        ]);
    }

    public function fulfilled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'fulfilled',
            'claimed_at' => now()->subDay(),
            'fulfilled_at' => now(),
            'fulfilled_note' => 'Paid in cash.',
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'denied']);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'revoked']);
    }

    /**
     * FR-RWD-06: only monetary, fulfilled rewards reach the ledger.
     */
    public function monetary(float $amount = 500, string $label = 'BDT'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'monetary',
            'monetary_amount' => $amount,
            'currency_label' => $label,
        ]);
    }
}
