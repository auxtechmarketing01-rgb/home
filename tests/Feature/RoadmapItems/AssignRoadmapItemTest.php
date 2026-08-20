<?php

use App\Models\Goal;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Mentorship;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use App\Notifications\RoadmapItemAssignedNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * FR-MENT-05 and FR-MENT-06.
 *
 * 04 Phase 4 says to write the authorization test for this boundary **before**
 * the happy path, because it is "the one most likely to be gotten wrong under
 * time pressure". The named assertion from 06 §1.2 is here: a mentor who can
 * successfully call `assign` on an item must still get 403 from the
 * update-title endpoint on that same item.
 *
 * If that ever stops holding, the mentor relationship has quietly become the
 * mentor's roadmap instead of the mentee's.
 */
beforeEach(function () {
    Notification::fake();

    $this->mentee = User::factory()->create();
    $this->mentor = User::factory()->create();
    $this->stranger = User::factory()->create();

    $group = Group::factory()->create(['owner_id' => $this->mentee->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $this->mentor->id]);

    Mentorship::factory()->accepted()->between($this->mentor, $this->mentee)->create();

    $this->goal = Goal::factory()->for($this->mentee)->create();
    $roadmap = Roadmap::factory()->for($this->goal)->create();
    $this->item = RoadmapItem::factory()->for($roadmap)->create([
        'title' => 'Day 1 – Variables',
        'estimated_minutes' => 60,
        'status' => 'todo',
    ]);
});

/**
 * **The named boundary test.** One member, one item, two abilities, two
 * different answers.
 */
it('lets a mentor assign but still refuses to let them edit the same item', function () {
    Sanctum::actingAs($this->mentor);

    $this->patchJson("/api/v1/roadmap-items/{$this->item->id}/assign", [
        'assigned_minutes' => 120,
    ])->assertOk()->assertJsonPath('data.assigned_minutes', 120);

    /** Same mentor, same item, `update` instead of `assign`. */
    $this->putJson("/api/v1/roadmap-items/{$this->item->id}", ['title' => 'Rewritten by mentor'])
        ->assertForbidden();

    expect($this->item->fresh()->title)->toBe('Day 1 – Variables');
});

/**
 * FR-MENT-06 spelled out field by field: a mentor cannot mark a mentee's work
 * done on their behalf. The mentee keeps their own claim of "I did this".
 */
it('refuses to let a mentor mark the item done', function () {
    Sanctum::actingAs($this->mentor);

    $this->putJson("/api/v1/roadmap-items/{$this->item->id}", ['status' => 'done'])
        ->assertForbidden();

    expect($this->item->fresh()->status)->toBe('todo');
});

it('refuses to let a mentor delete the item', function () {
    Sanctum::actingAs($this->mentor);

    $this->deleteJson("/api/v1/roadmap-items/{$this->item->id}")->assertForbidden();

    $this->assertDatabaseHas('roadmap_items', ['id' => $this->item->id]);
});

/**
 * The two time fields are allowed to disagree, and that disagreement is
 * information rather than a conflict (02 §3). Assigning must never overwrite
 * the mentee's own estimate.
 */
it('never touches the mentee own estimate', function () {
    Sanctum::actingAs($this->mentor);

    $this->patchJson("/api/v1/roadmap-items/{$this->item->id}/assign", [
        'assigned_minutes' => 240,
        'assigned_due_at' => now()->addWeek()->toIso8601String(),
    ])->assertOk();

    $item = $this->item->fresh();

    expect($item->assigned_minutes)->toBe(240)
        ->and($item->estimated_minutes)->toBe(60)
        ->and($item->assigned_by_mentor_id)->toBe($this->mentor->id)
        ->and($item->assigned_due_at)->not->toBeNull();
});

it('records who set the expectation', function () {
    Sanctum::actingAs($this->mentor);

    $this->patchJson("/api/v1/roadmap-items/{$this->item->id}/assign", ['assigned_minutes' => 90])
        ->assertOk();

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $this->mentor->id,
        'subject_type' => RoadmapItem::class,
        'subject_id' => $this->item->id,
        'action' => 'roadmap_item.assigned',
    ]);
});

it('notifies the mentee that an expectation was set', function () {
    Sanctum::actingAs($this->mentor);

    $this->patchJson("/api/v1/roadmap-items/{$this->item->id}/assign", ['assigned_minutes' => 90])
        ->assertOk();

    Notification::assertSentTo($this->mentee, RoadmapItemAssignedNotification::class);
});

/**
 * `assign` belongs to a mentor, not to the owner. Keeping the owner out means
 * the two abilities never overlap, so neither can be mistaken for the other.
 */
it('refuses to let the owner assign to themselves', function () {
    Sanctum::actingAs($this->mentee);

    $this->patchJson("/api/v1/roadmap-items/{$this->item->id}/assign", ['assigned_minutes' => 30])
        ->assertForbidden();

    expect($this->item->fresh()->assigned_minutes)->toBeNull();
});

it('refuses a member with no mentorship at all', function () {
    Sanctum::actingAs($this->stranger);

    $this->patchJson("/api/v1/roadmap-items/{$this->item->id}/assign", ['assigned_minutes' => 30])
        ->assertForbidden();
});

/**
 * FR-MENT-07: ending the relationship removes access going forward.
 */
it('refuses a mentor whose mentorship has ended', function () {
    Mentorship::query()->update(['status' => 'ended']);

    Sanctum::actingAs($this->mentor);

    $this->patchJson("/api/v1/roadmap-items/{$this->item->id}/assign", ['assigned_minutes' => 30])
        ->assertForbidden();
});

it('refuses a mentor whose request is still pending', function () {
    Mentorship::query()->update(['status' => 'pending']);

    Sanctum::actingAs($this->mentor);

    $this->patchJson("/api/v1/roadmap-items/{$this->item->id}/assign", ['assigned_minutes' => 30])
        ->assertForbidden();
});

it('requires at least a time budget or a due date', function () {
    Sanctum::actingAs($this->mentor);

    $this->patchJson("/api/v1/roadmap-items/{$this->item->id}/assign", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['assigned_minutes']);
});

/**
 * The assign endpoint accepts only the two expectation fields. Anything else
 * in the payload must be ignored, not quietly applied.
 */
it('ignores content fields smuggled into the assign payload', function () {
    Sanctum::actingAs($this->mentor);

    $this->patchJson("/api/v1/roadmap-items/{$this->item->id}/assign", [
        'assigned_minutes' => 45,
        'title' => 'Smuggled title',
        'status' => 'done',
        'estimated_minutes' => 999,
    ])->assertOk();

    $item = $this->item->fresh();

    expect($item->title)->toBe('Day 1 – Variables')
        ->and($item->status)->toBe('todo')
        ->and($item->estimated_minutes)->toBe(60)
        ->and($item->assigned_minutes)->toBe(45);
});
