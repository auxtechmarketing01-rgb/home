<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => 'The '.fake()->unique()->lastName().'s',
            'invite_code' => strtoupper(fake()->unique()->bothify('??######')),
        ];
    }

    /**
     * Groups are only visible to their members (GroupPolicy::view), so a
     * group created without its owner's membership row would be invisible to
     * its own owner — this keeps fixtures matching what CreateGroupAction
     * actually produces.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Group $group): void {
            $membership = new GroupMember;
            $membership->forceFill([
                'group_id' => $group->id,
                'user_id' => $group->owner_id,
                'role' => 'owner',
            ])->save();
        });
    }
}
