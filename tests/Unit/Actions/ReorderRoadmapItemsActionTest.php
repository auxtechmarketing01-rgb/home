<?php

use App\Actions\Roadmaps\ReorderRoadmapItemsAction;
use App\Models\Goal;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $goal = Goal::factory()->for($this->owner)->create();
    $this->roadmap = Roadmap::factory()->for($goal)->create();

    $this->first = RoadmapItem::factory()->for($this->roadmap)->create(['position' => 1]);
    $this->second = RoadmapItem::factory()->for($this->roadmap)->create(['position' => 2]);
});

it('applies every new position', function () {
    $items = app(ReorderRoadmapItemsAction::class)($this->owner, $this->roadmap, [
        ['id' => $this->second->id, 'position' => 1],
        ['id' => $this->first->id, 'position' => 2],
    ]);

    expect($items->pluck('id')->all())->toBe([$this->second->id, $this->first->id])
        ->and($this->first->fresh()->position)->toBe(2)
        ->and($this->second->fresh()->position)->toBe(1);
});

/**
 * The Action re-checks roadmap ownership itself rather than trusting that a
 * Form Request ran first — it is called directly here, exactly as it would
 * be from a future importer or console command.
 */
it('rejects an id from another roadmap even with no form request in front of it', function () {
    $foreignItem = RoadmapItem::factory()->create(['position' => 9]);

    expect(fn () => app(ReorderRoadmapItemsAction::class)($this->owner, $this->roadmap, [
        ['id' => $foreignItem->id, 'position' => 1],
    ]))->toThrow(ValidationException::class);

    expect($foreignItem->fresh()->position)->toBe(9);
});

/**
 * One transaction for the whole batch: a rejected payload must leave every
 * position exactly as it was, not partially applied.
 */
it('leaves all positions untouched when one id is foreign', function () {
    $foreignItem = RoadmapItem::factory()->create(['position' => 9]);

    try {
        app(ReorderRoadmapItemsAction::class)($this->owner, $this->roadmap, [
            ['id' => $this->first->id, 'position' => 5],
            ['id' => $foreignItem->id, 'position' => 6],
        ]);
    } catch (ValidationException) {
        // asserted below
    }

    expect($this->first->fresh()->position)->toBe(1)
        ->and($this->second->fresh()->position)->toBe(2);
});

it('records the reorder in the activity feed', function () {
    app(ReorderRoadmapItemsAction::class)($this->owner, $this->roadmap, [
        ['id' => $this->first->id, 'position' => 2],
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $this->owner->id,
        'subject_type' => Roadmap::class,
        'subject_id' => $this->roadmap->id,
        'action' => 'roadmap.reordered',
    ]);
});
