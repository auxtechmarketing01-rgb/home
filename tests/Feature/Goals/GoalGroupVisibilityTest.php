<?php

use App\Models\Goal;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Roadmap;
use App\Models\RoadmapItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * FR-GOAL-02 and FR-GRP-02, the Phase 3 half of `GoalPolicy::view`.
 *
 * 04 Phase 3's gate calls for "private vs. group visibility" policy-branch
 * tests. Both directions are asserted: what group visibility must reach, and
 * what it must still refuse — the second being the one that matters for a
 * family app.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->peer = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $this->group->id, 'user_id' => $this->peer->id]);
});

it('lets a group peer view a group visible goal', function () {
    $goal = Goal::factory()->for($this->owner)->groupVisible()->create([
        'group_id' => $this->group->id,
        'title' => 'Shared goal',
    ]);

    Sanctum::actingAs($this->peer);

    $this->getJson("/api/v1/goals/{$goal->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Shared goal')
        ->assertJsonPath('data.user.name', $this->owner->name);
});

it('includes a peer group visible goal in the goal list', function () {
    Goal::factory()->for($this->owner)->groupVisible()->create(['group_id' => $this->group->id]);
    Goal::factory()->for($this->peer)->create();

    Sanctum::actingAs($this->peer);

    $this->getJson('/api/v1/goals')->assertOk()->assertJsonCount(2, 'data');
});

/**
 * The core privacy rule (01 §5). A peer sharing a group does not get to see
 * everything — only what the owner chose to share.
 */
it('refuses a group peer on a private goal', function () {
    $goal = Goal::factory()->for($this->owner)->create(['visibility' => 'private']);

    Sanctum::actingAs($this->peer);

    $this->getJson("/api/v1/goals/{$goal->id}")->assertForbidden();
    $this->getJson('/api/v1/goals')->assertOk()->assertJsonCount(0, 'data');
});

it('refuses an outsider on a group visible goal', function () {
    $goal = Goal::factory()->for($this->owner)->groupVisible()->create(['group_id' => $this->group->id]);

    Sanctum::actingAs($this->outsider);

    $this->getJson("/api/v1/goals/{$goal->id}")->assertForbidden();
    $this->getJson('/api/v1/goals')->assertOk()->assertJsonCount(0, 'data');
});

/**
 * Scoped to the goal's *own* group. Being in some other group with the owner
 * grants nothing — otherwise marking a goal shared would publish it to every
 * circle the owner belongs to.
 */
it('refuses a member of a different group', function () {
    $otherGroup = Group::factory()->create(['owner_id' => $this->owner->id]);
    $otherMember = User::factory()->create();
    GroupMember::factory()->create(['group_id' => $otherGroup->id, 'user_id' => $otherMember->id]);

    $goal = Goal::factory()->for($this->owner)->groupVisible()->create(['group_id' => $this->group->id]);

    Sanctum::actingAs($otherMember);

    $this->getJson("/api/v1/goals/{$goal->id}")->assertForbidden();
});

/**
 * The safe direction to fail in: `visibility = 'group'` with no group is
 * treated as owner-only, because the policy's group branch requires a
 * non-null `group_id`.
 */
it('treats group visibility with no group as private', function () {
    $goal = Goal::factory()->for($this->owner)->groupVisible()->create(['group_id' => null]);

    Sanctum::actingAs($this->peer);

    $this->getJson("/api/v1/goals/{$goal->id}")->assertForbidden();
});

/**
 * Read access is not write access (FR-GRP-02: "enforced by Policy, not just
 * hidden in UI").
 */
it('never lets a group peer mutate a shared goal', function () {
    $goal = Goal::factory()->for($this->owner)->groupVisible()->create(['group_id' => $this->group->id]);
    $roadmap = Roadmap::factory()->for($goal)->create();
    $item = RoadmapItem::factory()->for($roadmap)->create(['title' => 'Owned']);

    Sanctum::actingAs($this->peer);

    $this->putJson("/api/v1/goals/{$goal->id}", ['title' => 'Hijacked'])->assertForbidden();
    $this->deleteJson("/api/v1/goals/{$goal->id}")->assertForbidden();
    $this->postJson("/api/v1/goals/{$goal->id}/complete")->assertForbidden();
    $this->postJson("/api/v1/roadmaps/{$roadmap->id}/items", ['title' => 'Injected'])->assertForbidden();
    $this->putJson("/api/v1/roadmap-items/{$item->id}", ['title' => 'Hijacked'])->assertForbidden();

    expect($goal->fresh()->title)->not->toBe('Hijacked')
        ->and($item->fresh()->title)->toBe('Owned');
});

/**
 * Child records delegate upward, so a shared goal's roadmap and attachments
 * are readable by peers — and no more than readable.
 */
it('lets a group peer read the shared goal roadmap and attachments', function () {
    $goal = Goal::factory()->for($this->owner)->groupVisible()->create(['group_id' => $this->group->id]);
    $roadmap = Roadmap::factory()->for($goal)->create();
    RoadmapItem::factory()->for($roadmap)->create();

    Sanctum::actingAs($this->peer);

    $this->getJson("/api/v1/roadmaps/{$roadmap->id}/items")->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/goals/{$goal->id}/resources")->assertOk();
});

it('refuses to let a member share a goal into a group they are not in', function () {
    $foreignGroup = Group::factory()->create(['owner_id' => $this->outsider->id]);

    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/goals', [
        'title' => 'Sneaky',
        'visibility' => 'group',
        'group_id' => $foreignGroup->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['group_id']);
});

it('lets an owner share a goal into their own group', function () {
    Sanctum::actingAs($this->owner);

    $this->postJson('/api/v1/goals', [
        'title' => 'Shared from the start',
        'visibility' => 'group',
        'group_id' => $this->group->id,
    ])->assertCreated()
        ->assertJsonPath('data.visibility', 'group')
        ->assertJsonPath('data.group_id', $this->group->id);
});

it('lets an owner switch a goal back to private', function () {
    $goal = Goal::factory()->for($this->owner)->groupVisible()->create(['group_id' => $this->group->id]);

    Sanctum::actingAs($this->owner);
    $this->putJson("/api/v1/goals/{$goal->id}", ['visibility' => 'private'])->assertOk();

    Sanctum::actingAs($this->peer);
    $this->getJson("/api/v1/goals/{$goal->id}")->assertForbidden();
});
