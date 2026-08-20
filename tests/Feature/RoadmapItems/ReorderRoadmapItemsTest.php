<?php

use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * FR-RM-05.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $goal = Goal::factory()->for($this->owner)->create();
    $this->roadmap = Roadmap::factory()->for($goal)->create();

    $this->first = RoadmapItem::factory()->for($this->roadmap)->create(['title' => 'A', 'position' => 1]);
    $this->second = RoadmapItem::factory()->for($this->roadmap)->create(['title' => 'B', 'position' => 2]);
    $this->third = RoadmapItem::factory()->for($this->roadmap)->create(['title' => 'C', 'position' => 3]);
});

it('persists a reordered batch', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items/reorder", [
        'items' => [
            ['id' => $this->third->id, 'position' => 1],
            ['id' => $this->first->id, 'position' => 2],
            ['id' => $this->second->id, 'position' => 3],
        ],
    ])->assertOk()
        ->assertJsonPath('data.0.title', 'C')
        ->assertJsonPath('data.1.title', 'A')
        ->assertJsonPath('data.2.title', 'B');

    expect($this->third->fresh()->position)->toBe(1)
        ->and($this->first->fresh()->position)->toBe(2)
        ->and($this->second->fresh()->position)->toBe(3);
});

/**
 * The invariant that matters: a reorder payload must never be able to touch
 * a row on somebody else's roadmap.
 */
it('rejects an id belonging to a different roadmap', function () {
    $foreignItem = RoadmapItem::factory()->create(['position' => 5]);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items/reorder", [
        'items' => [
            ['id' => $this->first->id, 'position' => 2],
            ['id' => $foreignItem->id, 'position' => 1],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.1.id']);

    expect($foreignItem->fresh()->position)->toBe(5)
        ->and($this->first->fresh()->position)->toBe(1);
});

it('rejects a duplicated id', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items/reorder", [
        'items' => [
            ['id' => $this->first->id, 'position' => 1],
            ['id' => $this->first->id, 'position' => 2],
        ],
    ])->assertUnprocessable();
});

it('rejects an empty batch', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items/reorder", ['items' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

it('forbids a non-owner from reordering', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items/reorder", [
        'items' => [['id' => $this->first->id, 'position' => 3]],
    ])->assertForbidden();

    expect($this->first->fresh()->position)->toBe(1);
});
