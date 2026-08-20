<?php

use App\Models\Category;
use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * FR-GOAL-01. FR-RM-01 requires the roadmap to arrive with the goal.
 */
it('creates a goal together with its empty roadmap', function () {
    $user = User::factory()->create();
    $category = Category::factory()->global()->create(['name' => 'Programming']);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/goals', [
        'title' => 'Learn C Programming',
        'description' => 'Two months, day by day.',
        'category_id' => $category->id,
        'target_start_date' => '2026-09-01',
        'target_end_date' => '2026-10-31',
    ])->assertCreated()
        ->assertJsonPath('data.title', 'Learn C Programming')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.visibility', 'private')
        ->assertJsonPath('data.category.name', 'Programming');

    $goal = Goal::query()->sole();

    expect($goal->user_id)->toBe($user->id)
        ->and($goal->roadmap)->not->toBeNull()
        ->and($goal->roadmap->title)->toBe('Roadmap')
        ->and($goal->roadmap->items)->toHaveCount(0);
});

it('sets the owner from the authenticated user and ignores a user_id in the payload', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/goals', [
        'title' => 'Mine',
        'user_id' => $other->id,
    ])->assertCreated();

    expect(Goal::query()->sole()->user_id)->toBe($user->id);
});

it('validates the goal payload', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/goals', [
        'title' => '',
        'visibility' => 'public',
        'target_start_date' => '2026-10-01',
        'target_end_date' => '2026-09-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'visibility', 'target_end_date']);
});

it('rejects a category belonging to another member', function () {
    $user = User::factory()->create();
    $foreignCategory = Category::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/goals', [
        'title' => 'Mine',
        'category_id' => $foreignCategory->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['category_id']);
});

/**
 * Phase 1 visibility: a member sees their own goals only. The group branch
 * arrives in Phase 3 and the mentorship branch in Phase 4.
 */
it('lists only the acting member goals', function () {
    $user = User::factory()->create();
    $mine = Goal::factory()->for($user)->create();
    Goal::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/goals')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

/**
 * 01 NFR Performance: the index must not N+1 the roadmap items.
 */
it('returns the roadmap item count on the index without loading the items', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create();
    $roadmap = Roadmap::factory()->for($goal)->create();
    RoadmapItem::factory()->count(3)->for($roadmap)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/goals')
        ->assertOk()
        ->assertJsonPath('data.0.roadmap_item_count', 3)
        ->assertJsonMissingPath('data.0.roadmap.items');
});

it('filters the index by status', function () {
    $user = User::factory()->create();
    Goal::factory()->for($user)->create(['status' => 'active']);
    $paused = Goal::factory()->for($user)->create(['status' => 'paused']);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/goals?status=paused')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $paused->id);
});

it('shows a goal with its ordered roadmap', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create();
    $roadmap = Roadmap::factory()->for($goal)->create();
    RoadmapItem::factory()->for($roadmap)->create(['title' => 'Second', 'position' => 2]);
    RoadmapItem::factory()->for($roadmap)->create(['title' => 'First', 'position' => 1]);

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/goals/{$goal->id}")
        ->assertOk()
        ->assertJsonPath('data.roadmap.items.0.title', 'First')
        ->assertJsonPath('data.roadmap.items.1.title', 'Second');
});

it('updates a goal', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create(['title' => 'Old']);

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/goals/{$goal->id}", ['title' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.title', 'New');
});

/**
 * FR-GOAL-02. Group visibility without a group is a goal nobody can see —
 * GoalPolicy's group branch requires a non-null `group_id` — so it is
 * rejected rather than silently accepted as a no-op.
 */
it('refuses group visibility without a group', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->putJson("/api/v1/goals/{$goal->id}", ['visibility' => 'group'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['group_id']);

    expect($goal->fresh()->visibility)->toBe('private');
});

/**
 * FR-GOAL-03: archiving is a soft delete, so the row and its history survive.
 */
it('archives a goal without hard deleting it', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/goals/{$goal->id}")->assertNoContent();

    $this->assertSoftDeleted('goals', ['id' => $goal->id]);

    expect(Goal::withTrashed()->count())->toBe(1);
});

it('drops an archived goal out of the index', function () {
    $user = User::factory()->create();
    Goal::factory()->for($user)->archived()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/goals')->assertOk()->assertJsonCount(0, 'data');
});

/**
 * FR-GOAL-04: completion is an explicit action, never automatic.
 */
it('completes a goal and stamps completed_at', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/goals/{$goal->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect($goal->fresh()->completed_at)->not->toBeNull();
});

it('does not complete a goal just because every roadmap item is done', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->for($user)->create();
    $roadmap = Roadmap::factory()->for($goal)->create();
    RoadmapItem::factory()->count(2)->for($roadmap)->done()->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/goals/{$goal->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.completed_at', null);
});

it('requires authentication for every goal endpoint', function () {
    $goal = Goal::factory()->create();

    $this->getJson('/api/v1/goals')->assertUnauthorized();
    $this->postJson('/api/v1/goals', ['title' => 'x'])->assertUnauthorized();
    $this->getJson("/api/v1/goals/{$goal->id}")->assertUnauthorized();
    $this->putJson("/api/v1/goals/{$goal->id}", ['title' => 'x'])->assertUnauthorized();
    $this->deleteJson("/api/v1/goals/{$goal->id}")->assertUnauthorized();
});
