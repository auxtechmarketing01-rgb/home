<?php

use App\Models\Group;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * FR-ADM-01, deliberately minimal: view users and groups, disable an abusive
 * account. Not a full admin panel.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->member = User::factory()->create();
});

it('lets an admin list users and groups', function () {
    Group::factory()->create(['owner_id' => $this->member->id]);

    Sanctum::actingAs($this->admin);

    $this->getJson('/api/v1/admin/users')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/admin/groups')->assertOk()->assertJsonCount(1, 'data');
});

it('refuses every admin route to an ordinary member', function () {
    Sanctum::actingAs($this->member);

    $this->getJson('/api/v1/admin/users')->assertForbidden();
    $this->getJson('/api/v1/admin/groups')->assertForbidden();
    $this->postJson("/api/v1/admin/users/{$this->admin->id}/disable")->assertForbidden();
});

it('disables an account', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/users/{$this->member->id}/disable")->assertOk();

    expect($this->member->fresh()->isDisabled())->toBeTrue();
});

/**
 * The important half: disabling has to bite on the member's very next
 * request, not merely at their next login — otherwise an abusive session
 * keeps working until its cookie expires.
 */
it('locks a disabled member out of the api immediately', function () {
    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/users/{$this->member->id}/disable")->assertOk();

    Sanctum::actingAs($this->member->fresh());

    $this->getJson('/api/v1/goals')->assertForbidden();
    $this->getJson('/api/v1/user')->assertForbidden();
});

it('re enables an account', function () {
    $this->member->forceFill(['disabled_at' => now()])->save();

    Sanctum::actingAs($this->admin);
    $this->postJson("/api/v1/admin/users/{$this->member->id}/enable")->assertOk();

    expect($this->member->fresh()->isDisabled())->toBeFalse();

    Sanctum::actingAs($this->member->fresh());
    $this->getJson('/api/v1/goals')->assertOk();
});

/**
 * Guards against an admin locking every administrator out of the app.
 */
it('refuses to disable another administrator', function () {
    $otherAdmin = User::factory()->create(['is_admin' => true]);

    Sanctum::actingAs($this->admin);

    $this->postJson("/api/v1/admin/users/{$otherAdmin->id}/disable")->assertUnprocessable();

    expect($otherAdmin->fresh()->isDisabled())->toBeFalse();
});

it('requires authentication', function () {
    $this->getJson('/api/v1/admin/users')->assertUnauthorized();
});
