<?php

use App\Models\Category;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * FR-GOAL-05: the seeded global set plus the member's own, never anyone
 * else's.
 */
it('lists global categories and the acting member own', function () {
    $user = User::factory()->create();

    Category::factory()->global()->create(['name' => 'Programming']);
    Category::factory()->for($user)->create(['name' => 'Tabla practice']);
    Category::factory()->create(['name' => 'Someone else private hobby']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/categories')->assertOk()->assertJsonCount(2, 'data');

    expect(collect($response->json('data'))->pluck('name')->all())
        ->toBe(['Programming', 'Tabla practice']);
});

it('creates a category owned by the acting member', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/categories', ['name' => 'Tabla practice', 'icon' => 'music'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Tabla practice');

    expect(Category::query()->where('name', 'Tabla practice')->sole()->user_id)->toBe($user->id);
});

it('rejects a duplicate category name for the same member', function () {
    $user = User::factory()->create();
    Category::factory()->for($user)->create(['name' => 'Tabla practice']);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/categories', ['name' => 'Tabla practice'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('allows two members to use the same category name', function () {
    $user = User::factory()->create();
    Category::factory()->create(['name' => 'Tabla practice']);

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/categories', ['name' => 'Tabla practice'])->assertCreated();
});

it('requires authentication', function () {
    $this->getJson('/api/v1/categories')->assertUnauthorized();
});
