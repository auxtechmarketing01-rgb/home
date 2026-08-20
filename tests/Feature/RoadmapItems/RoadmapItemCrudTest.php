<?php

use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->goal = Goal::factory()->for($this->owner)->create();
    $this->roadmap = Roadmap::factory()->for($this->goal)->create();
});

/**
 * FR-RM-02.
 */
it('creates an item under an owned roadmap', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items", [
        'title' => 'Day 1 – Variables & types',
        'day_number' => 1,
        'estimated_minutes' => 90,
    ])->assertCreated()
        ->assertJsonPath('data.title', 'Day 1 – Variables & types')
        ->assertJsonPath('data.status', 'todo')
        ->assertJsonPath('data.time_spent_seconds', 0);

    expect(RoadmapItem::query()->sole()->roadmap_id)->toBe($this->roadmap->id);
});

/**
 * FR-RM-04: adding one item at a time must behave the same as adding sixty
 * up front, so an omitted position simply appends.
 */
it('appends a new item to the end of the roadmap', function () {
    RoadmapItem::factory()->for($this->roadmap)->create(['position' => 7]);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items", ['title' => 'Next'])
        ->assertCreated()
        ->assertJsonPath('data.position', 8);
});

it('rejects creating an item under another member roadmap', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items", ['title' => 'Intruder'])
        ->assertForbidden();

    expect(RoadmapItem::query()->count())->toBe(0);
});

it('rejects listing items of another member roadmap', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/roadmaps/{$this->roadmap->id}/items")->assertForbidden();
});

it('lists items in position order', function () {
    RoadmapItem::factory()->for($this->roadmap)->create(['title' => 'Third', 'position' => 3]);
    RoadmapItem::factory()->for($this->roadmap)->create(['title' => 'First', 'position' => 1]);
    RoadmapItem::factory()->for($this->roadmap)->create(['title' => 'Second', 'position' => 2]);

    Sanctum::actingAs($this->owner);

    $this->getJson("/api/v1/roadmaps/{$this->roadmap->id}/items")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.title', 'First')
        ->assertJsonPath('data.1.title', 'Second')
        ->assertJsonPath('data.2.title', 'Third');
});

/**
 * FR-RM-03: exactly one level of nesting.
 */
it('creates a nested item under a top level parent', function () {
    $parent = RoadmapItem::factory()->for($this->roadmap)->create();

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items", [
        'title' => 'Sub-topic',
        'parent_id' => $parent->id,
    ])->assertCreated()->assertJsonPath('data.parent_id', $parent->id);
});

it('refuses to nest an item more than one level deep', function () {
    $parent = RoadmapItem::factory()->for($this->roadmap)->create();
    $child = RoadmapItem::factory()->for($this->roadmap)->create(['parent_id' => $parent->id]);

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items", [
        'title' => 'Grandchild',
        'parent_id' => $child->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);
});

it('refuses a parent from a different roadmap', function () {
    $foreignItem = RoadmapItem::factory()->create();

    Sanctum::actingAs($this->owner);

    $this->postJson("/api/v1/roadmaps/{$this->roadmap->id}/items", [
        'title' => 'Cross-roadmap child',
        'parent_id' => $foreignItem->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);
});

it('updates an item', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->create(['title' => 'Old']);

    Sanctum::actingAs($this->owner);

    $this->putJson("/api/v1/roadmap-items/{$item->id}", ['title' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.title', 'New');
});

/**
 * FR-RM-02: status changes are recorded in the activity feed.
 */
it('logs a status transition to the activity feed', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->create(['status' => 'todo']);

    Sanctum::actingAs($this->owner);

    $this->putJson("/api/v1/roadmap-items/{$item->id}", ['status' => 'in_progress'])->assertOk();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $this->owner->id,
        'subject_type' => RoadmapItem::class,
        'subject_id' => $item->id,
        'action' => 'roadmap_item.status_changed',
    ]);
});

it('logs completion of an item under its own action name', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->create(['status' => 'in_progress']);

    Sanctum::actingAs($this->owner);

    $this->putJson("/api/v1/roadmap-items/{$item->id}", ['status' => 'done'])->assertOk();

    $this->assertDatabaseHas('activity_logs', [
        'subject_id' => $item->id,
        'action' => 'roadmap_item.completed',
    ]);
});

/**
 * FR-RM-07.
 */
it('accepts an optional reflection note when an item is marked done', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->create();

    Sanctum::actingAs($this->owner);

    $this->putJson("/api/v1/roadmap-items/{$item->id}", [
        'status' => 'done',
        'reflection_note' => 'Pointers finally clicked.',
    ])->assertOk()->assertJsonPath('data.reflection_note', 'Pointers finally clicked.');
});

/**
 * 02 §3: `time_spent_seconds` is a rollup owned by RecalculateGoalStatsJob.
 * A client must never be able to write it.
 */
it('ignores an attempt to write the rolled up time directly', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->create(['time_spent_seconds' => 0]);

    Sanctum::actingAs($this->owner);

    $this->putJson("/api/v1/roadmap-items/{$item->id}", [
        'title' => 'Still fine',
        'time_spent_seconds' => 999999,
    ])->assertOk();

    expect($item->fresh()->time_spent_seconds)->toBe(0);
});

it('deletes an item', function () {
    $item = RoadmapItem::factory()->for($this->roadmap)->create();

    Sanctum::actingAs($this->owner);

    $this->deleteJson("/api/v1/roadmap-items/{$item->id}")->assertNoContent();

    $this->assertDatabaseMissing('roadmap_items', ['id' => $item->id]);
});
