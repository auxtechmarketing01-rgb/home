<?php

use App\Models\Goal;
use App\Models\ResourceFile;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * 02 §5: an attachment is never more permissive than the Goal or RoadmapItem
 * it hangs off, so every ability here delegates upward rather than carrying a
 * rule of its own.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->goal = Goal::factory()->for($this->owner)->create();
    $roadmap = Roadmap::factory()->for($this->goal)->create();
    $this->item = RoadmapItem::factory()->for($roadmap)->create();

    $this->goalResource = ResourceFile::factory()->create([
        'resourceable_type' => Goal::class,
        'resourceable_id' => $this->goal->id,
    ]);

    $this->itemResource = ResourceFile::factory()->create([
        'resourceable_type' => RoadmapItem::class,
        'resourceable_id' => $this->item->id,
    ]);

    Sanctum::actingAs(User::factory()->create());
});

it('forbids a stranger from listing attachments on a goal', function () {
    $this->getJson("/api/v1/goals/{$this->goal->id}/resources")->assertForbidden();
});

it('forbids a stranger from listing attachments on a roadmap item', function () {
    $this->getJson("/api/v1/roadmap-items/{$this->item->id}/resources")->assertForbidden();
});

it('forbids a stranger from attaching to a goal', function () {
    $this->postJson("/api/v1/goals/{$this->goal->id}/resources", [
        'type' => 'note',
        'title' => 'Intruder',
        'body' => 'Should not land.',
    ])->assertForbidden();

    expect(ResourceFile::query()->count())->toBe(2);
});

it('forbids a stranger from attaching to a roadmap item', function () {
    $this->postJson("/api/v1/roadmap-items/{$this->item->id}/resources", [
        'type' => 'note',
        'title' => 'Intruder',
        'body' => 'Should not land.',
    ])->assertForbidden();
});

it('forbids a stranger from deleting an attachment on a goal', function () {
    $this->deleteJson("/api/v1/resources/{$this->goalResource->id}")->assertForbidden();

    $this->assertDatabaseHas('resource_files', ['id' => $this->goalResource->id]);
});

it('forbids a stranger from deleting an attachment on a roadmap item', function () {
    $this->deleteJson("/api/v1/resources/{$this->itemResource->id}")->assertForbidden();

    $this->assertDatabaseHas('resource_files', ['id' => $this->itemResource->id]);
});

it('lets the owner delete their own attachments', function () {
    Sanctum::actingAs($this->owner);

    $this->deleteJson("/api/v1/resources/{$this->goalResource->id}")->assertNoContent();
    $this->deleteJson("/api/v1/resources/{$this->itemResource->id}")->assertNoContent();

    expect(ResourceFile::query()->count())->toBe(0);
});
