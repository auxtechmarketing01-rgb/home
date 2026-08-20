<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * 02 §5: `view` → member, `update` → owner.
 *
 * 04 Phase 3's gate asks specifically for "non-member access returns 403 not
 * 404-leaking-existence — decide and be consistent". 403 is the house choice
 * across every policy-guarded route in this codebase, and these tests pin it.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->member = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->group = Group::factory()->create(['owner_id' => $this->owner->id]);
    GroupMember::factory()->create(['group_id' => $this->group->id, 'user_id' => $this->member->id]);
});

it('returns 403 and not 404 for a non member viewing a group', function () {
    Sanctum::actingAs($this->outsider);

    $this->getJson("/api/v1/groups/{$this->group->id}")->assertForbidden();
});

it('forbids a non member from the leaderboard', function () {
    Sanctum::actingAs($this->outsider);

    $this->getJson("/api/v1/groups/{$this->group->id}/leaderboard")->assertForbidden();
});

it('forbids a non member from listing challenges', function () {
    Sanctum::actingAs($this->outsider);

    $this->getJson("/api/v1/groups/{$this->group->id}/challenges")->assertForbidden();
});

it('forbids a plain member from owner only settings', function () {
    Sanctum::actingAs($this->member);

    $this->putJson("/api/v1/groups/{$this->group->id}", ['name' => 'Hijacked'])->assertForbidden();
    $this->postJson("/api/v1/groups/{$this->group->id}/invite", ['email' => 'x@example.test'])
        ->assertForbidden();
    $this->postJson("/api/v1/groups/{$this->group->id}/invite-code")->assertForbidden();
    $this->deleteJson("/api/v1/groups/{$this->group->id}/members/{$this->owner->id}")
        ->assertForbidden();
    $this->deleteJson("/api/v1/groups/{$this->group->id}")->assertForbidden();

    expect($this->group->fresh()->name)->not->toBe('Hijacked');
});

it('forbids an outsider from every group route', function () {
    Sanctum::actingAs($this->outsider);

    $this->getJson("/api/v1/groups/{$this->group->id}")->assertForbidden();
    $this->putJson("/api/v1/groups/{$this->group->id}", ['name' => 'Hijacked'])->assertForbidden();
    $this->postJson("/api/v1/groups/{$this->group->id}/leave")->assertForbidden();
    $this->deleteJson("/api/v1/groups/{$this->group->id}")->assertForbidden();
});

it('lets a member view the group', function () {
    Sanctum::actingAs($this->member);

    $this->getJson("/api/v1/groups/{$this->group->id}")
        ->assertOk()
        ->assertJsonPath('data.is_owner', false);
});

it('requires authentication', function () {
    $this->getJson("/api/v1/groups/{$this->group->id}")->assertUnauthorized();
    $this->postJson('/api/v1/groups', ['name' => 'x'])->assertUnauthorized();
});
