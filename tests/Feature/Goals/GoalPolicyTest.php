<?php

use App\Models\Goal;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * 06 §1.2 Policies: for a family app, a privacy mistake is the worst-case
 * bug, so every Policy gets an explicit 403-boundary test.
 *
 * 403 rather than 404 is the deliberate house choice for a record that
 * exists but is not the caller's (04 Phase 3 Gate) — applied consistently
 * across every policy-guarded route.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();
    $this->goal = Goal::factory()->for($this->owner)->create();

    Sanctum::actingAs($this->stranger);
});

it('forbids a non-owner from viewing a goal', function () {
    $this->getJson("/api/v1/goals/{$this->goal->id}")->assertForbidden();
});

it('forbids a non-owner from updating a goal', function () {
    $this->putJson("/api/v1/goals/{$this->goal->id}", ['title' => 'Hijacked'])->assertForbidden();

    expect($this->goal->fresh()->title)->not->toBe('Hijacked');
});

it('forbids a non-owner from archiving a goal', function () {
    $this->deleteJson("/api/v1/goals/{$this->goal->id}")->assertForbidden();

    $this->assertNotSoftDeleted('goals', ['id' => $this->goal->id]);
});

it('forbids a non-owner from completing a goal', function () {
    $this->postJson("/api/v1/goals/{$this->goal->id}/complete")->assertForbidden();

    expect($this->goal->fresh()->completed_at)->toBeNull();
});

/**
 * Phase 1 has no group branch yet: marking a goal group-visible must not by
 * itself expose it to an unrelated member.
 */
it('does not expose a group visible goal to a member with no shared group', function () {
    $groupVisible = Goal::factory()->for($this->owner)->groupVisible()->create();

    $this->getJson("/api/v1/goals/{$groupVisible->id}")->assertForbidden();

    $this->getJson('/api/v1/goals')->assertOk()->assertJsonCount(0, 'data');
});
