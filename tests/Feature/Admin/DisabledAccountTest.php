<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

/**
 * FR-ADM-01's enforcement surface, in the two places an audit found it
 * leaking.
 *
 * "Disable an abusive account" has to mean the account stops working, not that
 * it stops working in most places. Both holes below returned 200 for a
 * disabled member.
 */
beforeEach(function () {
    $this->disabled = User::factory()->create([
        'email' => 'abusive@example.test',
        'password' => Hash::make('correct-horse-battery'),
        'disabled_at' => now(),
    ]);
});

/**
 * Hole 1: `login` validated credentials and never asked whether the account
 * was disabled, so a disabled member got a valid session and only discovered
 * the lockout on their *next* request.
 */
it('refuses to log in a disabled account even with correct credentials', function () {
    $this->postJson('/api/v1/login', [
        'email' => 'abusive@example.test',
        'password' => 'correct-horse-battery',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    $this->assertGuest();
});

/**
 * Hole 2: the broadcast authorization endpoint was the one authenticated route
 * outside the `active` middleware, so a disabled member could still be handed
 * the credential that subscribes to their private channel — and would keep
 * receiving live notifications after being locked out.
 */
it('refuses to authorize a broadcast channel for a disabled account', function () {
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret',
        'broadcasting.connections.pusher.app_id' => 'test-app-id',
    ]);

    require base_path('routes/channels.php');

    Sanctum::actingAs($this->disabled);

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-App.Models.User.'.$this->disabled->id,
    ])->assertForbidden();
});

it('still authorizes a channel for an active account', function () {
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret',
        'broadcasting.connections.pusher.app_id' => 'test-app-id',
    ]);

    require base_path('routes/channels.php');

    $active = User::factory()->create();
    Sanctum::actingAs($active);

    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-App.Models.User.'.$active->id,
    ])->assertOk();
});

it('locks a disabled account out of every api route', function () {
    Sanctum::actingAs($this->disabled);

    $this->getJson('/api/v1/goals')->assertForbidden();
    $this->getJson('/api/v1/notifications')->assertForbidden();
    $this->getJson('/api/v1/rewards')->assertForbidden();
    $this->postJson('/api/v1/sprints/start', ['mode' => 'stopwatch'])->assertForbidden();
});
