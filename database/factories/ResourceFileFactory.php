<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\ResourceFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceFile>
 */
class ResourceFileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resourceable_type' => Goal::class,
            'resourceable_id' => Goal::factory(),
            'uploaded_by' => User::factory(),
            'type' => 'link',
            'title' => 'Beej networking guide',
            'url' => 'https://example.test/guide',
            'disk' => null,
            'path' => null,
            'mime_type' => null,
            'size_bytes' => null,
            'body' => null,
        ];
    }

    public function file(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'file',
            'url' => null,
            'disk' => 'local',
            'path' => 'resources/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 24_000,
            'body' => null,
        ]);
    }

    public function note(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'note',
            'url' => null,
            'body' => 'Remember: arrays decay to pointers when passed to a function.',
        ]);
    }
}
