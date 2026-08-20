<?php

use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * 02 §5: a roadmap item is never independently more permissive than the goal
 * it hangs off. The Phase 4 `assign`-versus-`update` separation is tested
 * separately, once mentorship exists.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $goal = Goal::factory()->for($this->owner)->create();
    $roadmap = Roadmap::factory()->for($goal)->create();
    $this->item = RoadmapItem::factory()->for($roadmap)->create(['title' => 'Owned']);

    Sanctum::actingAs(User::factory()->create());
});

it('forbids a non-owner from updating an item', function () {
    $this->putJson("/api/v1/roadmap-items/{$this->item->id}", ['title' => 'Hijacked'])
        ->assertForbidden();

    expect($this->item->fresh()->title)->toBe('Owned');
});

it('forbids a non-owner from marking an item done', function () {
    $this->putJson("/api/v1/roadmap-items/{$this->item->id}", ['status' => 'done'])
        ->assertForbidden();

    expect($this->item->fresh()->status)->toBe('todo');
});

it('forbids a non-owner from deleting an item', function () {
    $this->deleteJson("/api/v1/roadmap-items/{$this->item->id}")->assertForbidden();

    $this->assertDatabaseHas('roadmap_items', ['id' => $this->item->id]);
});

it('requires authentication', function () {
    app('auth')->forgetGuards();

    $this->putJson("/api/v1/roadmap-items/{$this->item->id}", ['title' => 'x'])
        ->assertUnauthorized();
});
