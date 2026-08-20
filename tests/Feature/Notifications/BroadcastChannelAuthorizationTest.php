<?php

use App\Models\Challenge;
use App\Models\Group;
use App\Models\Mentorship;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Channel authorization is a privacy boundary in its own right: a member who
 * can subscribe to someone else's private channel receives their
 * notifications live, whatever the Policies say (01 §5 Privacy).
 *
 * The Pusher connection is configured here rather than relying on the test
 * environment default, because the null broadcaster does not run channel
 * callbacks at all — testing against it would assert nothing. The suite as a
 * whole stays on the null driver so no other test reaches the network.
 *
 * routes/channels.php is then re-required on purpose: `Broadcast::channel()`
 * registers against the *current broadcaster instance*, not against config,
 * so switching the driver after boot leaves the new instance with no
 * channels at all — and a broadcaster with no channels rejects everything,
 * which looks exactly like a failing authorization rule.
 */
beforeEach(function () {
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret',
        'broadcasting.connections.pusher.app_id' => 'test-app-id',
    ]);

    require base_path('routes/channels.php');
});

it('authorizes a member for their own private channel', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-App.Models.User.'.$user->id,
    ])->assertOk()->assertJsonStructure(['auth']);
});

it('refuses to authorize another member private channel', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-App.Models.User.'.$other->id,
    ])->assertForbidden();
});

it('requires authentication to authorize a channel', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-App.Models.User.'.$user->id,
    ])->assertUnauthorized();
});

/*
|--------------------------------------------------------------------------
| Phase 3 and 4 channels (02 §10.3)
|--------------------------------------------------------------------------
|
| Each mirrors the Policy that guards the same data over HTTP. A channel that
| drifts from its Policy is a privacy hole that no HTTP test would catch.
|
*/

it('authorizes a group channel for a member and refuses an outsider', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $outsider = User::factory()->create();

    Sanctum::actingAs($owner);
    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-groups.{$group->id}",
    ])->assertOk();

    Sanctum::actingAs($outsider);
    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-groups.{$group->id}",
    ])->assertForbidden();
});

it('authorizes a challenge channel through the parent group only', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create(['owner_id' => $owner->id]);
    $challenge = Challenge::factory()->create([
        'group_id' => $group->id,
        'created_by' => $owner->id,
    ]);

    Sanctum::actingAs($owner);
    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-challenges.{$challenge->id}",
    ])->assertOk();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-challenges.{$challenge->id}",
    ])->assertForbidden();
});

it('authorizes a mentorship channel for either party', function () {
    $mentor = User::factory()->create();
    $mentee = User::factory()->create();
    $mentorship = Mentorship::factory()->accepted()->between($mentor, $mentee)->create();

    foreach ([$mentor, $mentee] as $party) {
        Sanctum::actingAs($party);
        $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-mentorships.{$mentorship->id}",
        ])->assertOk();
    }

    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-mentorships.{$mentorship->id}",
    ])->assertForbidden();
});

/**
 * FR-MENT-07: ending removes access going forward. A channel left open after
 * the relationship ended would be a quiet exception to that, and the member
 * would keep receiving reward updates they no longer have any right to.
 */
it('closes the mentorship channel once the relationship ends', function () {
    $mentor = User::factory()->create();
    $mentee = User::factory()->create();
    $mentorship = Mentorship::factory()->accepted()->between($mentor, $mentee)->create();

    $mentorship->forceFill(['status' => 'ended'])->save();

    Sanctum::actingAs($mentor);
    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-mentorships.{$mentorship->id}",
    ])->assertForbidden();
});

it('refuses a channel for a record that does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-mentorships.999999',
    ])->assertForbidden();
});

/**
 * The endpoint lives inside the versioned, stateful API group so the SPA can
 * reach it with the same session cookie and CORS rules as every other call
 * (02 §1). The framework default mounts it on the `web` group instead, which
 * a separate-origin SPA cannot use.
 */
it('is mounted under the versioned api prefix', function () {
    $uris = collect(app('router')->getRoutes()->getRoutes())->map(fn ($route) => $route->uri())->all();

    expect($uris)->toContain('api/v1/broadcasting/auth');
});
