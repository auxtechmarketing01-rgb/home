<?php

namespace Database\Factories;

use App\Models\Mentorship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mentorship>
 */
class MentorshipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mentor = User::factory();
        $mentee = User::factory();

        return [
            'mentor_id' => $mentor,
            'mentee_id' => $mentee,
            /**
             * Defaults to the mentee having asked, which is the common case
             * and makes `respond` belong to the mentor — the direction most
             * tests want (FR-MENT-02).
             */
            'requested_by_user_id' => $mentee,
            'status' => 'pending',
            'responded_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
            'responded_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'declined',
            'responded_at' => now(),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ended',
            'responded_at' => now(),
        ]);
    }

    /**
     * Pins both sides and records the mentee as the initiator.
     */
    public function between(User $mentor, User $mentee): static
    {
        return $this->state(fn (array $attributes) => [
            'mentor_id' => $mentor->id,
            'mentee_id' => $mentee->id,
            'requested_by_user_id' => $mentee->id,
        ]);
    }

    public function requestedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'requested_by_user_id' => $user->id,
        ]);
    }
}
